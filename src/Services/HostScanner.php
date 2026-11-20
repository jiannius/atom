<?php

namespace Jiannius\Atom\Services;

use Throwable;

/**
 * The reading half of a host scanner: what is read from a host application's
 * source and how. Walks the directories a scanner names (never following a
 * symlink, never reading Composer's `vendor/`, `node_modules/`, `.git/`, a file
 * over the size cap or a minified one), blanks comments at the same length so
 * offsets and line numbers hold, masks the PHP of a Blade file to leave its
 * JavaScript, and parses component tags and their attributes.
 *
 * It never writes. UpgradeCheck is the scanner built on it today; what a
 * scanner looks for, and how it reports it, is the subclass's.
 */
abstract class HostScanner
{
    /**
     * Files whose average line is longer than this many bytes look minified and are skipped
     */
    public const MINIFIED_LINE = 20000;

    /**
     * Directory names skipped at any depth
     */
    public const SKIP_DIRECTORIES = ['node_modules', '.git'];

    /**
     * Composer's directory: skipped at the root and wherever it holds an install
     * (an autoload.php or a composer/ directory). Any other directory with this
     * name is the host's own code, such as a "vendor" (supplier) screen.
     */
    public const VENDOR_DIRECTORY = 'vendor';

    /**
     * Paths, from the root, skipped
     */
    public const SKIP_PATHS = ['storage', 'public/build', 'bootstrap/cache'];

    /**
     * What is scanned: these directories (under the root) and the root's own build config
     */
    public const DIRECTORIES = ['app', 'resources', 'routes', 'modules', 'Modules', 'config', 'database', 'tests'];

    /**
     * Files beyond this are skipped
     */
    public const MAX_BYTES = 2097152;

    /**
     * What the last scan did not read, as [path, reason]
     *
     * @var array<int, array{path: string, reason: string}>
     */
    protected array $skipped = [];

    /**
     * Files read from memory instead of the disk during a scan, by path from the root
     * (the codemod scans what a run would leave behind without writing it)
     *
     * @var array<string, string>
     */
    protected array $overlay = [];

    /**
     * The directory being scanned
     */
    protected string $root = '';

    /**
     * The directories (under the root) that discover() reads. A scanner reads other ones by overriding this.
     *
     * @return array<int, string>
     */
    protected function directories(): array
    {
        return static::DIRECTORIES;
    }

    /**
     * A file's text: the overlay's when it has one, else the disk's
     */
    protected function read(string $path): string|false
    {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($path, strlen($this->root)), DIRECTORY_SEPARATOR));

        return $this->overlay[$relative] ?? @file_get_contents($path);
    }

    /**
     * The installed atom version, when Composer can say
     */
    protected function installedVersion(): ?string
    {
        try {
            return class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('jiannius/atom')
                ? \Composer\InstalledVersions::getPrettyVersion('jiannius/atom')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A file's text with its comments blanked (same length, so offsets hold), the
     * way the checks read it
     */
    public function commentFree(string $kind, string $source): string
    {
        return $this->prepare($kind, $source)['full'];
    }

    /**
     * A file's text as its JavaScript is read: comments blanked and, in Blade, every PHP region too, at the same length (so an
     * offset here is the offset in the file)
     */
    public function markupText(string $kind, string $source): string
    {
        return $this->prepare($kind, $source)['markup'];
    }

    /**
     * A Blade file's text as its component tags are read: Blade comments and
     * @verbatim blocks blanked, HTML comments not (Blade compiles a tag in one)
     */
    public function tagText(string $source): string
    {
        return $this->prepare('blade', $source)['tags'];
    }

    /**
     * The files to read: [absolute path, relative path, 'app'|'tests', kind]
     *
     * @return \Generator<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function discover(string $root): \Generator
    {
        $seen = [];
        $roots = [];
        $libraries = [];

        foreach ($this->directories() as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.$directory;

            if (is_link($path)) {
                $this->skipped[] = ['path' => $directory, 'reason' => 'symlink'];

                continue;
            }

            $stat = is_dir($path) ? @stat($path) : false;

            // modules and Modules are one directory on a case-insensitive disk: tell them apart by inode
            if ($stat !== false && ! isset($seen[$stat['dev'].':'.$stat['ino']])) {
                $seen[$stat['dev'].':'.$stat['ino']] = true;
                $roots[] = $path;
            }
        }

        foreach ($roots as $path) {
            $files = $this->walk($root, $path);
            sort($files);

            foreach ($files as $file) {
                $kind = $this->kindOf($file);

                if ($kind === null) {
                    continue;
                }

                $relative = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR));

                if (in_array($kind, ['js', 'css'], true) && ($library = $this->vendorLibraryDirectory($relative)) !== null) {
                    if (! isset($libraries[$library])) {
                        $libraries[$library] = true;
                        $this->skipped[] = ['path' => $library, 'reason' => 'third-party scripts or styles under resources/js|css/vendor'];
                    }

                    continue;
                }

                if (filesize($file) > static::MAX_BYTES) {
                    $this->skipped[] = ['path' => $relative, 'reason' => 'over '.(static::MAX_BYTES / 1048576).' MB'];

                    continue;
                }

                yield [$file, $relative, str_starts_with($relative, 'tests/') ? 'tests' : 'app', $kind];
            }
        }

        foreach (glob($root.'/vite.config.*') ?: [] as $file) {
            if (is_link($file)) {
                $this->skipped[] = ['path' => basename($file), 'reason' => 'symlink'];

                continue;
            }

            yield [$file, basename($file), 'app', 'config'];
        }
    }

    /**
     * Every regular file under a directory. A symlink (file or directory) is not
     * followed, a directory that cannot be read is recorded, and Composer's
     * `vendor/` is skipped (see isSkippedDirectory()), as is `node_modules/` and
     * `.git/` at any depth. Only atom's own published views are read from a
     * `resources/views/vendor/` (the root one or a module's).
     *
     * @return array<int, string>
     */
    protected function walk(string $root, string $directory): array
    {
        $relativeDirectory = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($directory, strlen($root)), DIRECTORY_SEPARATOR));
        $entries = @scandir($directory);

        if ($entries === false) {
            $this->skipped[] = ['path' => $relativeDirectory, 'reason' => 'unreadable'];

            return [];
        }

        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $relative = ($relativeDirectory === '' ? '' : $relativeDirectory.'/').$name;

            if (is_link($path)) {
                $this->skipped[] = ['path' => $relative, 'reason' => 'symlink'];

                continue;
            }

            if (is_dir($path)) {
                // only atom's own published views are read from a resources/views/vendor (the root one or a module's)
                $publishedViews = $this->isPublishedViewsDirectory($relative);

                if ((! $publishedViews && $this->isSkippedDirectory($relative, $name, $path)) || in_array($relative, static::SKIP_PATHS, true)) {
                    continue;
                }

                if ($this->isPublishedViewsDirectory($relativeDirectory) && $name !== 'atom') {
                    continue;
                }

                $files = array_merge($files, $this->walk($root, $path));
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Laravel's published-vendor-views folder, at the root or in a module: not app code
     */
    protected function isPublishedViewsDirectory(string $relative): bool
    {
        return $relative === 'resources/views/vendor' || str_ends_with($relative, '/resources/views/vendor');
    }

    /**
     * The `vendor` directory that holds third-party scripts or styles, when the
     * file is under one: a direct child of resources/js or resources/css, at the
     * root or in a module. Any other `vendor` directory (resources/js/pages/vendor)
     * is the host's own code, and PHP and Blade are read wherever they are.
     */
    protected function vendorLibraryDirectory(string $relative): ?string
    {
        return preg_match('#^((?:.*/)?resources/(?:js|css)/vendor)/#', $relative, $m) ? $m[1] : null;
    }

    /**
     * Whether a directory is left unread: node_modules and .git at any depth,
     * and Composer's vendor
     */
    protected function isSkippedDirectory(string $relative, string $name, string $path): bool
    {
        if (in_array($name, static::SKIP_DIRECTORIES, true)) {
            return true;
        }

        // the root vendor/ (defensive: the root is never walked today, only its app, resources... directories), or a
        // nested one that holds a Composer install (modules/X/vendor); any other is the host's
        return $name === static::VENDOR_DIRECTORY
            && ($relative === static::VENDOR_DIRECTORY || is_file($path.DIRECTORY_SEPARATOR.'autoload.php') || is_dir($path.DIRECTORY_SEPARATOR.'composer'));
    }

    /**
     * What a file is: blade, php, js, css, or null for something not read
     */
    protected function kindOf(string $file): ?string
    {
        $name = strtolower(basename($file));

        return match (true) {
            str_ends_with($name, '.blade.php') => 'blade',
            str_ends_with($name, '.php') => 'php',
            str_ends_with($name, '.min.js'), str_ends_with($name, '.min.css') => null,
            (bool) preg_match('/\.(?:js|mjs|cjs|ts|jsx|tsx|vue)$/', $name) => 'js',
            str_ends_with($name, '.css') => 'css',
            default => null,
        };
    }

    /**
     * The text a check reads. `full` is the source with comments blanked (same
     * length, so offsets and line numbers hold); `markup` is a Blade file with
     * every PHP region (`{{ }}`, `{!! !!}`, `@php`, `<?php`, a directive's
     * arguments) blanked too, which is where its JavaScript lives.
     *
     * @return array{full: string, markup: string, tags: string}
     */
    protected function prepare(string $kind, string $source): array
    {
        if ($kind === 'php') {
            $full = $this->blankPhpComments($source);

            return ['full' => $full, 'markup' => $full, 'tags' => $full];
        }

        if ($kind === 'js' || $kind === 'config') {
            $full = $this->blankJsComments($source);

            return ['full' => $full, 'markup' => $full, 'tags' => $full];
        }

        if ($kind === 'css') {
            $full = $this->blank($source, '/\/\*.*?\*\//s');

            return ['full' => $full, 'markup' => $full, 'tags' => $full];
        }

        // blade: PHP comments inside <?php regions, then Blade and HTML comments, then PHP comments in @php and {{ }}
        $php = $this->blankPhpComments($source);
        $strip = function (string $comments) use ($php): string {
            $text = $this->blank($php, $comments);

            return preg_replace_callback('/@php\b(?!\s*\()(.*?)@endphp|\{\{(?!--)(.*?)\}\}|\{!!(.*?)!!\}/s', function (array $m) {
                $inner = $m[1] ?? '';
                $inner = ($m[2] ?? '') !== '' ? $m[2] : $inner;
                $inner = ($m[3] ?? '') !== '' ? $m[3] : $inner;

                if ($inner === '' || (! str_contains($inner, '//') && ! str_contains($inner, '/*') && ! str_contains($inner, '#'))) {
                    return $m[0];
                }

                $blanked = substr($this->blankPhpComments('<?php '.$inner), 6);

                return str_replace($inner, $blanked, $m[0]);
            }, $text) ?? $text;
        };

        $full = $strip('/\{\{--.*?--\}\}|<!--.*?-->/s');

        // Blade compiles a component tag inside an HTML comment, so the tags are read with only the Blade comments
        // (and @verbatim blocks, which it leaves as text) blanked
        $tags = $strip('/\{\{--.*?--\}\}|(?<!@)@verbatim.*?@endverbatim/s');

        $markup = $this->maskBladePhp($full);

        // a <script> block is JavaScript: its comments are not code
        $markup = preg_replace_callback('/(<script\b[^>]*>)(.*?)(<\/script>)/is', fn (array $m) => $m[1].$this->blankJsComments($m[2]).$m[3], $markup) ?? $markup;

        return ['full' => $full, 'markup' => $markup, 'tags' => $tags];
    }

    /**
     * Replace every non-newline byte matching $pattern with a space
     */
    protected function blank(string $text, string $pattern): string
    {
        return preg_replace_callback($pattern, fn (array $m) => preg_replace('/[^\n]/', ' ', $m[0]), $text) ?? $text;
    }

    /**
     * PHP comments blanked, by tokens, so a `//` in a string or a URL is safe.
     * HTML outside the PHP tags is left as is.
     */
    protected function blankPhpComments(string $code): string
    {
        if (! str_contains($code, '//') && ! str_contains($code, '/*') && ! str_contains($code, '#')) {
            return $code;
        }

        try {
            $out = '';

            foreach (token_get_all($code) as $token) {
                if (is_array($token)) {
                    $out .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                        ? preg_replace('/[^\n]/', ' ', $token[1])
                        : $token[1];
                } else {
                    $out .= $token;
                }
            }

            return strlen($out) === strlen($code) ? $out : $code;
        } catch (Throwable) {
            return $code;
        }
    }

    /**
     * JavaScript comments blanked by a small scanner that knows strings and
     * template literals (a `//` after a `:` is a URL, not a comment)
     */
    protected function blankJsComments(string $js): string
    {
        $n = strlen($js);
        $out = $js;
        $i = 0;
        $last = '';

        while ($i < $n) {
            $c = $js[$i];
            $next = $js[$i + 1] ?? '';

            if ($c === '"' || $c === "'" || $c === '`') {
                $quote = $c;
                $i++;

                while ($i < $n && $js[$i] !== $quote) {
                    $i += $js[$i] === '\\' ? 2 : 1;

                    if ($quote !== '`' && ($js[$i] ?? '') === "\n") {
                        break;
                    }
                }

                $i++;
                $last = $quote;

                continue;
            }

            // a regex literal may hold a quote: skip it whole, so the quote does not open a string
            if ($c === '/' && $next !== '/' && $next !== '*' && ($last === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $last) || preg_match('/\b(?:return|typeof|case|in|of|void)\s*$/', substr($js, max(0, $i - 8), min(8, $i))))) {
                $j = $i + 1;
                $class = false;

                while ($j < $n && $js[$j] !== "\n") {
                    if ($js[$j] === '\\') {
                        $j += 2;

                        continue;
                    }

                    if ($js[$j] === '[') {
                        $class = true;
                    } elseif ($js[$j] === ']') {
                        $class = false;
                    } elseif ($js[$j] === '/' && ! $class) {
                        break;
                    }

                    $j++;
                }

                if (($js[$j] ?? '') === '/') {
                    $i = $j + 1;
                    $last = '/';

                    continue;
                }
            }

            if ($c === '/' && $next === '/' && ($js[$i - 1] ?? '') !== ':') {
                $end = strpos($js, "\n", $i);
                $end = $end === false ? $n : $end;

                for ($j = $i; $j < $end; $j++) {
                    $out[$j] = ' ';
                }

                $i = $end;

                continue;
            }

            if ($c === '/' && $next === '*') {
                $end = strpos($js, '*/', $i + 2);
                $end = $end === false ? $n : $end + 2;

                for ($j = $i; $j < $end; $j++) {
                    if ($js[$j] !== "\n") {
                        $out[$j] = ' ';
                    }
                }

                $i = $end;

                continue;
            }

            if (! ctype_space($c)) {
                $last = $c;
            }

            $i++;
        }

        return $out;
    }

    /**
     * Blank the PHP of a Blade file: `<?php ... ?>`, `@php ... @endphp`, `{{ }}`,
     * `{!! !!}` and a directive's parenthesised arguments (`@if(...)`)
     */
    protected function maskBladePhp(string $blade): string
    {
        $blade = $this->blank($blade, '/<\?(?:php|=).*?(?:\?>|\z)/s');
        $blade = $this->blank($blade, '/@php\b(?!\s*\().*?(?:@endphp|\z)|\{\{.*?\}\}|\{!!.*?!!\}/s');

        $out = $blade;
        $n = strlen($blade);
        $offset = 0;

        while (preg_match('/(?<![\w@])@\w+[ \t]*\(/', $blade, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]) - 1;
            $close = $this->matchingParenthesis($blade, $open);
            $end = $close === null ? min($n, strpos($blade, "\n", $open) ?: $n) : $close + 1;

            for ($j = $start; $j < $end; $j++) {
                if ($out[$j] !== "\n") {
                    $out[$j] = ' ';
                }
            }

            $offset = max($end, $start + 1);
        }

        return $this->maskBoundAttributes($out);
    }

    /**
     * A bound attribute on a component tag (`:items="$rows"`) is a PHP expression, not JavaScript
     */
    protected function maskBoundAttributes(string $text): string
    {
        if (! preg_match_all('/<(?:atom:|x-)[a-z][\w.\-:]*/i', $text, $tags, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        foreach ($tags[0] as [$tag, $at]) {
            $from = $at + strlen($tag);
            $attributes = $this->readAttributes($text, $from);

            if (! preg_match_all('/(?<=\s):[\w.\-]+\s*=\s*("[^"]*"|\'[^\']*\')/', $attributes, $bound, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($bound[1] as [$value, $valueAt]) {
                for ($j = $from + $valueAt, $end = $j + strlen($value); $j < $end; $j++) {
                    if ($text[$j] !== "\n") {
                        $text[$j] = ' ';
                    }
                }
            }
        }

        return $text;
    }

    /**
     * The index of the parenthesis that closes the one at $open, strings skipped
     */
    protected function matchingParenthesis(string $text, int $open): ?int
    {
        $depth = 0;
        $n = strlen($text);

        for ($i = $open; $i < $n; $i++) {
            $c = $text[$i];

            if ($c === '"' || $c === "'") {
                $i++;

                while ($i < $n && $text[$i] !== $c) {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }

                continue;
            }

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Byte offsets at which each line starts
     *
     * @return array<int, int>
     */
    protected function lineStarts(string $source): array
    {
        return LineIndex::starts($source);
    }

    /**
     * The 1-based line an offset is on
     *
     * @param  array<int, int>  $starts
     */
    protected function lineAt(array $starts, int $offset): int
    {
        return LineIndex::lineAt($starts, $offset);
    }

    /**
     * Every `<atom:name ...>` and `<x-atom::name ...>` in a Blade file, with its
     * attributes, where they start in the file and where each one sits in them
     *
     * @return array<int, array{name: string, offset: int, attributes: array<string, string|true>, attributes_at: int, spans: array<string, array{0: int, 1: int}>}>
     */
    public function parseTags(string $blade): array
    {
        if (! preg_match_all('/<(?:x-atom::|atom:)([a-z][a-z0-9\-]*(?:\.[a-z0-9\-]+)*)/i', $blade, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tags = [];

        foreach ($found[1] as $i => [$name, $at]) {
            $offset = $found[0][$i][1];
            $spans = [];

            $tags[] = [
                'name' => strtolower($name),
                'offset' => $offset,
                'attributes' => $this->parseAttributes($this->readAttributes($blade, $at + strlen($name)), $spans),
                'attributes_at' => $at + strlen($name),
                'spans' => $spans,
            ];
        }

        return $tags;
    }

    /**
     * The text between a tag name and the `>` that ends the tag, quotes and `{{ }}` respected
     */
    protected function readAttributes(string $text, int $from): string
    {
        $n = min(strlen($text), $from + 6000);
        $quote = null;
        $i = $from;

        while ($i < $n) {
            $c = $text[$i];

            if ($c === '{' && ($text[$i + 1] ?? '') === '{') {
                $end = strpos($text, '}}', $i);
                $i = $end === false ? $n : $end + 2;

                continue;
            }

            if ($quote !== null) {
                $quote = $c === $quote ? null : $quote;
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '>') {
                break;
            }

            $i++;
        }

        return substr($text, $from, $i - $from);
    }

    /**
     * Name to value (true for a bare attribute). A `{{ }}` anywhere in a value
     * leaves the value as is, so it is never mistaken for a static one.
     *
     * @param  array<string, array{0: int, 1: int}>  $spans  filled with each attribute's start and end in $text
     * @return array<string, string|true>
     */
    protected function parseAttributes(string $text, array &$spans = []): array
    {
        $attributes = [];
        $n = strlen($text);
        $i = 0;

        while ($i < $n) {
            while ($i < $n && (ctype_space($text[$i]) || $text[$i] === '/')) {
                $i++;
            }

            if ($i >= $n) {
                break;
            }

            if ($text[$i] === '{' && ($text[$i + 1] ?? '') === '{') {
                $end = strpos($text, '}}', $i);
                $i = $end === false ? $n : $end + 2;

                continue;
            }

            $start = $i;

            while ($i < $n && ! ctype_space($text[$i]) && ! in_array($text[$i], ['=', '/', '"', "'"], true)) {
                $i++;
            }

            $name = substr($text, $start, $i - $start);

            if ($name === '') {
                $i++;

                continue;
            }

            while ($i < $n && ctype_space($text[$i])) {
                $i++;
            }

            if (($text[$i] ?? '') !== '=') {
                $attributes[$name] = true;
                $spans[$name] = [$start, $start + strlen($name)];

                continue;
            }

            $i++;

            while ($i < $n && ctype_space($text[$i])) {
                $i++;
            }

            $quote = $text[$i] ?? '';

            if ($quote === '"' || $quote === "'") {
                $i++;
                $from = $i;

                while ($i < $n && $text[$i] !== $quote) {
                    if ($text[$i] === '{' && ($text[$i + 1] ?? '') === '{') {
                        $end = strpos($text, '}}', $i);
                        $i = $end === false ? $n : $end + 2;

                        continue;
                    }

                    $i++;
                }

                $attributes[$name] = substr($text, $from, $i - $from);
                $i++;
                $spans[$name] = [$start, min($i, $n)];

                continue;
            }

            $from = $i;

            while ($i < $n && ! ctype_space($text[$i])) {
                $i++;
            }

            $attributes[$name] = substr($text, $from, $i - $from);
            $spans[$name] = [$start, $i];
        }

        return $attributes;
    }

    /**
     * Whether a tag has an attribute, static or bound
     *
     * @param  array<string, string|true>  $attributes
     */
    public static function has(array $attributes, string $name): bool
    {
        return isset($attributes[$name]) || isset($attributes[':'.$name]) || isset($attributes['x-bind:'.$name]);
    }

    /**
     * The value of a static attribute (null when it is absent, bound or has Blade in it)
     *
     * @param  array<string, string|true>  $attributes
     */
    public static function value(array $attributes, string $name): ?string
    {
        $value = $attributes[$name] ?? null;

        return is_string($value) && ! str_contains($value, '{{') && ! str_contains($value, '{!!') ? strtolower(trim($value)) : null;
    }
}
