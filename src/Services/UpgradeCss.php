<?php

namespace Jiannius\Atom\Services;

/**
 * The CSS half of the upgrade: reads a host's Tailwind entry file for the
 * pieces UPGRADE.md says to delete (atom's own @source lines, the theme block
 * copied from skeleton-project, the copied border-color base rule) and, for the
 * ones that are exactly what the skeleton shipped, removes them and adds atom's
 * import.
 *
 * Text based, no CSS parser: a piece is removed only when its text is the
 * skeleton's (a declaration with the skeleton's value, a @source line with
 * atom's own path, a border rule of the skeleton's shape). Anything else in the
 * file is left byte for byte, and `residue()` names what is left of the
 * skeleton's names so the report can say why.
 */
class UpgradeCss
{
    /**
     * The declarations skeleton-project puts in `@theme`
     */
    public const THEME = [
        '--font-sans' => "'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
        '--color-primary' => 'var(--color-zinc-900)',
        '--color-primary-content' => 'var(--color-zinc-50)',
        '--color-primary-foreground' => 'var(--color-zinc-50)',
        '--color-zinc-50' => '#fafafa',
        '--color-zinc-100' => '#f5f5f5',
        '--color-zinc-200' => '#e5e5e5',
        '--color-zinc-300' => '#d4d4d4',
        '--color-zinc-400' => '#a3a3a3',
        '--color-zinc-500' => '#737373',
        '--color-zinc-600' => '#525252',
        '--color-zinc-700' => '#404040',
        '--color-zinc-800' => '#262626',
        '--color-zinc-900' => '#171717',
        '--color-zinc-950' => '#0a0a0a',
        '--color-muted' => 'var(--color-zinc-500)',
        '--color-muted-foreground' => 'var(--color-zinc-400)',
        '--color-accent' => 'var(--color-yellow-500)',
        '--color-accent-content' => 'var(--color-yellow-500)',
        '--color-accent-foreground' => 'var(--color-white)',
    ];

    /**
     * The declarations skeleton-project puts in `@layer theme { .dark { } }`
     */
    public const DARK = [
        '--color-primary' => 'var(--color-zinc-50)',
        '--color-primary-content' => 'var(--color-zinc-900)',
        '--color-primary-foreground' => 'var(--color-zinc-900)',
        '--color-accent' => 'var(--color-yellow-500)',
        '--color-accent-content' => 'var(--color-yellow-500)',
        '--color-accent-foreground' => 'var(--color-white)',
    ];

    /**
     * What atom's own @source lines end with (after `vendor/jiannius/atom/`)
     */
    public const ATOM_SOURCES = ['components/**/*.blade.php', 'resources/**/*.blade.php', 'src/Actions/*.php'];

    /**
     * Where atom's Tailwind entry sits from a host's root
     */
    public const ENTRY = 'vendor/jiannius/atom/resources/css/atom.tailwind.css';

    /**
     * The result of reading one file: the text after the rewrite, what was
     * removed or added (for the report), and why a file was left alone.
     *
     * @return array{css: string, changed: bool, removed: array<int, string>, added: array<int, string>, declined: string|null, brand: string|null}
     */
    public function rewrite(string $css, string $file): array
    {
        $bom = str_starts_with($css, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
        $css = substr($css, strlen($bom));
        $result = $this->rewriteText($css, $file);

        // a rewrite that leaves the braces unbalanced is not made: the file is declined whole
        if ($result['changed'] && ! $this->balanced($result['css'])) {
            $result = ['css' => $css, 'changed' => false, 'removed' => [], 'added' => [], 'declined' => 'the rewrite would leave the braces unbalanced; change the file by hand', 'brand' => null];
        }

        if ($result['changed'] && ! $this->balanced($css)) {
            $result = ['css' => $css, 'changed' => false, 'removed' => [], 'added' => [], 'declined' => 'its braces do not balance, so it is not safe to edit by tool', 'brand' => null];
        }

        $result['css'] = $bom.$result['css'];

        return $result;
    }

    /**
     * Whether the braces balance, comments and strings aside
     */
    public function balanced(string $css): bool
    {
        $depth = 0;

        foreach (str_split($this->structure($css)) as $c) {
            $depth += $c === '{' ? 1 : ($c === '}' ? -1 : 0);

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * @return array{css: string, changed: bool, removed: array<int, string>, added: array<int, string>, declined: string|null, brand: string|null}
     */
    protected function rewriteText(string $css, string $file): array
    {
        $result = ['css' => $css, 'changed' => false, 'removed' => [], 'added' => [], 'declined' => null, 'brand' => null];
        $import = $this->importLine($css);
        $sources = $this->atomSourceLines($css);
        $hasEntry = $this->hasEntryImport($css);

        // a file that is not an atom entry (no atom @source line, no atom import) is not ours to touch
        if (! $hasEntry && ! $sources) {
            return $result;
        }

        if ($import === null && ! $hasEntry) {
            $result['declined'] = "no plain @import 'tailwindcss'; atom's import goes right after it, so add it by hand";

            return $result;
        }

        $text = $css;

        // atom's own @source lines: atom.tailwind.css scans atom itself
        foreach ($sources as $line) {
            $text = $this->removeLine($text, $this->atomSourceLines($text)[0] ?? $line);
            $result['removed'][] = '@source '.$line['path'];
        }

        // the import, right after tailwind's
        if (! $hasEntry) {
            $at = $this->importLine($text);
            $quote = $at['quote'];
            $path = $this->relativeEntry($file);
            $break = $at['end'] > 0 && $text[$at['end'] - 1] !== "\n";
            $text = substr($text, 0, $at['end']).($break ? "\n" : '').'@import '.$quote.$path.$quote.($break ? ';' : ";\n").substr($text, $at['end']);
            $result['added'][] = "@import '".$path."'";
        }

        // the theme pieces are only the skeleton's when atom's tokens replace them
        $brand = null;
        $keepPrimary = false;
        $themes = $this->rules($text, '/^@theme$/');

        foreach ($themes as $theme) {
            $value = $this->declaration($theme['body'], '--color-primary');

            if ($value !== null && $this->normalise($value) !== $this->normalise(static::THEME['--color-primary'])) {
                $keepPrimary = true;
                $brand = $value;
            }
        }

        // a brand colour is moved only when the accent is still the skeleton's (or absent)
        $accentCustom = false;

        foreach ($themes as $theme) {
            $value = $this->declaration($theme['body'], '--color-accent');

            if ($value !== null && $this->normalise($value) !== $this->normalise(static::THEME['--color-accent'])) {
                $accentCustom = true;
            }
        }

        if ($brand !== null && ($accentCustom || preg_match('/var\(\s*--color-(?:accent|primary)\b/', $brand))) {
            $brand = null;
        }

        $text = $this->stripDeclarations($text, '/^@theme$/', static::THEME, $keepPrimary, $result['removed']);
        $text = $this->stripDarkDeclarations($text, $keepPrimary, $result['removed']);
        $text = $this->stripBorderRule($text, $result['removed']);

        if ($brand !== null) {
            $text = $this->addAccent($text, $brand);
            $result['added'][] = '--color-accent: '.$brand;
            $result['brand'] = $brand;
        }

        $result['css'] = $text;
        $result['changed'] = $text !== $css;

        return $result;
    }

    /**
     * What is still there of the skeleton's names in an entry file that has
     * atom's import: declarations that are not the skeleton's value, which the
     * rewrite left alone
     *
     * @return array<int, array{line: int, text: string}>
     */
    public function residue(string $css): array
    {
        $css = $this->withoutBom($css);

        if (! $this->hasEntryImport($css)) {
            return [];
        }

        $notes = [];
        $starts = $this->lineStarts($css);
        $names = array_merge(array_keys(static::THEME), array_keys(static::DARK));

        foreach ($this->rules($css, '/^@theme(?: (?:inline|static|default))*$/') as $theme) {
            $plain = $theme['header'] === '@theme';

            foreach ($this->declarations($css, $theme) as $declaration) {
                $line = $this->lineOf($starts, $declaration['offset']);

                if ($plain && $declaration['name'] === '--color-primary' && preg_match('/var\(\s*--color-(?:accent|primary)\b/', $declaration['value'])) {
                    $notes[] = ['line' => $line, 'text' => '`--color-primary` refers to the accent ('.$declaration['value'].'), so it was not copied as the accent: set `--color-accent` yourself'];
                } elseif (in_array($declaration['name'], $names, true) && $declaration['name'] !== '--color-accent') {
                    $notes[] = ['line' => $line, 'text' => $theme['header'].' sets '.$declaration['name'].': '.$declaration['value'].($plain ? ' (not the skeleton value, or on a line with a comment, so left alone; atom defines this token now)' : ' (the codemod does not rewrite `'.$theme['header'].'`; atom defines this token now)')];
                }
            }
        }

        foreach ($this->darkRules($css) as $dark) {
            foreach ($this->declarations($css, $dark) as $declaration) {
                if (in_array($declaration['name'], $names, true)) {
                    $notes[] = ['line' => $this->lineOf($starts, $declaration['offset']), 'text' => '.dark sets '.$declaration['name'].': '.$declaration['value'].' (not the skeleton value, or on a line with a comment, so left alone; atom flips this token itself)'];
                }
            }
        }

        usort($notes, fn ($a, $b) => $a['line'] <=> $b['line']);

        return $notes;
    }

    /**
     * The `--color-accent` an entry file sets in `@theme` (any variant), with its line, or null
     *
     * @return array{value: string, line: int}|null
     */
    public function accent(string $css): ?array
    {
        $css = $this->withoutBom($css);

        foreach ($this->rules($css, '/^@theme(?: (?:inline|static|default))*$/') as $theme) {
            foreach ($this->declarations($css, $theme) as $declaration) {
                if ($declaration['name'] === '--color-accent') {
                    return ['value' => $declaration['value'], 'line' => $this->lineOf($this->lineStarts($css), $declaration['offset'])];
                }
            }
        }

        return null;
    }

    /**
     * Whether a file is a Tailwind entry that has atom's import
     */
    public function hasEntryImport(string $css): bool
    {
        $css = $this->withoutBom($css);

        return (bool) preg_match('/^[ \t]*@import\s+[\'"][^\'"\n]*vendor\/jiannius\/atom\/resources\/css\/atom\.tailwind\.css[\'"]\s*;/m', $this->blankComments($css));
    }

    /**
     * The `@theme` / `.dark` declarations in a file that are exactly the skeleton's, as [offset, name]
     *
     * @return array<int, array{0: int, 1: string}>
     */
    public function skeletonDeclarations(string $css): array
    {
        $css = $this->withoutBom($css);

        if (! preg_match('/@theme|\.dark/', $css)) {
            return [];
        }

        $found = [];

        foreach ($this->rules($css, '/^@theme$/') as $theme) {
            foreach ($this->declarations($css, $theme) as $declaration) {
                if (isset(static::THEME[$declaration['name']]) && $this->normalise($declaration['value']) === $this->normalise(static::THEME[$declaration['name']])) {
                    $found[] = [$declaration['offset'], $declaration['name']];
                }
            }
        }

        foreach ($this->darkRules($css) as $dark) {
            foreach ($this->declarations($css, $dark) as $declaration) {
                if (isset(static::DARK[$declaration['name']]) && $this->normalise($declaration['value']) === $this->normalise(static::DARK[$declaration['name']])) {
                    $found[] = [$declaration['offset'], $declaration['name']];
                }
            }
        }

        return $found;
    }

    /**
     * Offsets of the copied border-color base rule the rewrite removes
     *
     * @return array<int, int>
     */
    public function borderRules(string $css): array
    {
        return array_column($this->borderRuleSites($css), 'start');
    }

    /**
     * The path from a CSS file to atom's entry, given the file's path from the root
     */
    public function relativeEntry(string $file): string
    {
        $depth = max(0, substr_count(str_replace('\\', '/', $file), '/'));

        return str_repeat('../', $depth).static::ENTRY;
    }

    protected function withoutBom(string $css): string
    {
        return str_starts_with($css, "\xEF\xBB\xBF") ? substr($css, 3) : $css;
    }

    /**
     * Text with every comment turned into spaces (newlines kept), so offsets hold
     */
    public function blankComments(string $css): string
    {
        // a string comes first in the pattern so a glob such as `components/**/*.php` is not read as a comment
        return preg_replace_callback('/([\'"])(?:\\\\.|(?!\1)[^\\\\\n])*\1|\/\*.*?\*\//s', fn (array $m) => isset($m[1]) && $m[1] !== '' ? $m[0] : preg_replace('/[^\n]/', ' ', $m[0]), $css) ?? $css;
    }

    /**
     * Comments and the inside of strings blanked: what braces are counted in
     */
    protected function structure(string $css): string
    {
        $css = $this->blankComments($css);

        return preg_replace_callback('/([\'"])(?:\\\\.|(?!\1)[^\\\\\n])*\1/s', fn (array $m) => $m[1].preg_replace('/[^\n]/', ' ', substr($m[0], 1, -1)).$m[1], $css) ?? $css;
    }

    /**
     * Whitespace folded, quotes unified: how two values are compared
     */
    protected function normalise(string $value): string
    {
        return str_replace('"', "'", preg_replace('/\s+/', ' ', trim($value)));
    }

    /**
     * The `@import 'tailwindcss';` line: its quote and where it ends (after its newline)
     *
     * @return array{quote: string, end: int}|null
     */
    protected function importLine(string $css): ?array
    {
        if (! preg_match('/^[ \t]*@import\s+([\'"])tailwindcss\1\s*;[ \t]*(?:\r?\n|$)/m', $this->blankComments($css), $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $end = $m[0][1] + strlen($m[0][0]);

        return ['quote' => $m[1][0], 'end' => $end];
    }

    /**
     * The @source lines whose path is one of atom's own
     *
     * @return array<int, array{start: int, end: int, path: string}>
     */
    protected function atomSourceLines(string $css): array
    {
        $lines = [];

        if (! preg_match_all('/^[ \t]*@source\s+([\'"])([^\'"\n]*)\1\s*;[ \t]*(?:\r?\n|$)/m', $this->blankComments($css), $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($found as $m) {
            $path = $m[2][0];

            foreach (static::ATOM_SOURCES as $source) {
                if (preg_match('/^(?:\.\.\/)+vendor\/jiannius\/atom\/'.preg_quote($source, '/').'$/', $path)) {
                    $lines[] = ['start' => $m[0][1], 'end' => $m[0][1] + strlen($m[0][0]), 'path' => $path];

                    break;
                }
            }
        }

        return $lines;
    }

    /**
     * Take a line-aligned range out, leaving one blank line (not two) where it was
     *
     * @param  array{start: int, end: int}  $range
     */
    protected function removeLine(string $text, array $range): string
    {
        return $this->join(substr($text, 0, $range['start']), substr($text, $range['end']));
    }

    /**
     * Join two halves of a file around a removal: one blank line between them if
     * there was one on either side (none at either end of the file, none against
     * a brace), the rest of each half untouched
     */
    protected function join(string $left, string $right): string
    {
        $leftTrim = rtrim($left, " \t\r\n");
        $leftTail = substr($left, strlen($leftTrim));
        preg_match('/^(?:[ \t]*\r?\n)*/', $right, $blank);
        $rest = substr($right, strlen($blank[0]));

        if ($leftTrim === '') {
            return $rest;
        }

        if (trim($rest) === '') {
            return $leftTrim."\n";
        }

        $hadBlank = substr_count($leftTail, "\n") >= 2 || $blank[0] !== '';
        $againstBrace = str_ends_with($leftTrim, '{') || str_starts_with(ltrim($rest), '}');

        return $leftTrim."\n".($hadBlank && ! $againstBrace ? "\n" : '').$rest;
    }

    /**
     * The rules at the top level of $css (or of a body) whose prelude matches: their offsets and text
     *
     * @return array<int, array{start: int, open: int, close: int, header: string, body: string}>
     */
    protected function rules(string $css, string $header, int $from = 0, ?int $to = null): array
    {
        $structure = $this->structure($css);
        $to ??= strlen($css);
        $rules = [];
        $depth = 0;
        $prelude = $from;
        $open = null;

        for ($i = $from; $i < $to; $i++) {
            $c = $structure[$i];

            if ($c === '{') {
                if ($depth === 0) {
                    $open = $i;
                }

                $depth++;
            } elseif ($c === '}') {
                $depth--;

                if ($depth === 0 && $open !== null) {
                    $start = $prelude;
                    $text = substr($structure, $prelude, $open - $prelude);
                    $lead = strlen($text) - strlen(ltrim($text));
                    $name = trim(preg_replace('/\s+/', ' ', $text));

                    if (preg_match($header, $name)) {
                        $rules[] = [
                            'start' => $start + $lead,
                            'open' => $open,
                            'close' => $i,
                            'header' => $name,
                            'body' => substr($css, $open + 1, $i - $open - 1),
                        ];
                    }

                    $prelude = $i + 1;
                    $open = null;
                }
            } elseif ($c === ';' && $depth === 0) {
                $prelude = $i + 1;
            }
        }

        return $rules;
    }

    /**
     * The `.dark` rules inside `@layer theme { }`
     *
     * @return array<int, array{start: int, open: int, close: int, header: string, body: string}>
     */
    protected function darkRules(string $css): array
    {
        $found = [];

        foreach ($this->rules($css, '/^@layer theme$/') as $layer) {
            foreach ($this->rules($css, '/^\.dark$/', $layer['open'] + 1, $layer['close']) as $dark) {
                $found[] = $dark;
            }
        }

        return $found;
    }

    /**
     * The `--name: value;` declarations of a rule's body (at its own level, not in a nested rule), each with its own
     * span; `clean` is false when its line carries a comment, which the rewrite then leaves alone
     *
     * @param  array{open: int, close: int}  $rule
     * @return array<int, array{name: string, value: string, offset: int, start: int, end: int, clean: bool}>
     */
    protected function declarations(string $css, array $rule): array
    {
        $blank = $this->blankComments($css);
        $structure = $this->structure($css);
        $from = $rule['open'] + 1;
        $length = $rule['close'] - $from;
        $body = substr($blank, $from, $length);
        $found = [];

        // a value may hold a string, and a string may hold a `;` or something that looks like a declaration
        if (! preg_match_all('/(?<![\w-])(--[A-Za-z0-9_-]+)[ \t]*:[ \t]*((?:[^;{}"\']|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')+?)[ \t]*;/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($matches as $m) {
            if ($this->insideString(substr($blank, 0, $from + $m[0][1]))) {
                continue;
            }

            $at = $from + $m[0][1];
            $end = $at + strlen($m[0][0]);
            $prefix = substr($structure, $from, $at - $from);

            if (substr_count($prefix, '{') !== substr_count($prefix, '}')) {
                continue;
            }

            $lineStart = strrpos(substr($css, 0, $at), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $lineEnd = strpos($css, "\n", $end);
            $lineEnd = $lineEnd === false ? strlen($css) : $lineEnd;

            $found[] = [
                'name' => $m[1][0],
                'value' => $m[2][0],
                'offset' => $at,
                'start' => $at,
                'end' => $end,
                'clean' => substr($css, $lineStart, $lineEnd - $lineStart) === substr($blank, $lineStart, $lineEnd - $lineStart),
            ];
        }

        return $found;
    }

    /**
     * Whether the end of a text is inside a string literal (comments already blanked)
     */
    protected function insideString(string $before): bool
    {
        $quote = null;

        for ($i = 0, $n = strlen($before); $i < $n; $i++) {
            $c = $before[$i];

            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote || $c === "\n") {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            }
        }

        return $quote !== null;
    }

    /**
     * The value a rule's body declares a name as, or null
     */
    protected function declaration(string $body, string $name): ?string
    {
        if (preg_match('/(?<![\w-])'.preg_quote($name, '/').'[ \t]*:[ \t]*([^;{}]+?)[ \t]*;/', $this->blankComments($body), $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Remove, from every rule of a kind, the declarations that are the skeleton's
     *
     * @param  array<string, string>  $skeleton
     * @param  array<int, string>  $removed
     */
    protected function stripDeclarations(string $css, string $header, array $skeleton, bool $keepPrimary, array &$removed): string
    {
        $guard = 0;

        while ($guard++ < 50) {
            $changed = false;

            foreach (array_reverse($this->rules($css, $header)) as $rule) {
                $next = $this->stripFromRule($css, $rule, $skeleton, $keepPrimary, $removed);

                if ($next !== $css) {
                    $css = $next;
                    $changed = true;

                    break;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $css;
    }

    /**
     * The same for `.dark` inside `@layer theme`, and the layer once it is empty
     *
     * @param  array<int, string>  $removed
     */
    protected function stripDarkDeclarations(string $css, bool $keepPrimary, array &$removed): string
    {
        $guard = 0;

        while ($guard++ < 50) {
            $next = null;

            foreach (array_reverse($this->darkRules($css)) as $dark) {
                $candidate = $this->stripFromRule($css, $dark, static::DARK, $keepPrimary, $removed);

                if ($candidate !== $css) {
                    $next = $candidate;

                    break;
                }
            }

            if ($next === null) {
                break;
            }

            $css = $next;
        }

        // a layer with nothing left in it goes too
        foreach (array_reverse($this->rules($css, '/^@layer theme$/')) as $layer) {
            if ($this->isBlank(substr($css, $layer['open'] + 1, $layer['close'] - $layer['open'] - 1))) {
                $css = $this->removeRule($css, $layer);
            }
        }

        return $css;
    }

    /**
     * Take the skeleton's declarations out of one rule; drop the rule when nothing else is left
     *
     * @param  array{start: int, open: int, close: int}  $rule
     * @param  array<string, string>  $skeleton
     * @param  array<int, string>  $removed
     */
    protected function stripFromRule(string $css, array $rule, array $skeleton, bool $keepPrimary, array &$removed): string
    {
        $drop = [];

        foreach ($this->declarations($css, $rule) as $declaration) {
            if ($keepPrimary && str_starts_with($declaration['name'], '--color-primary')) {
                continue;
            }

            if ($declaration['clean'] && isset($skeleton[$declaration['name']]) && $this->normalise($declaration['value']) === $this->normalise($skeleton[$declaration['name']])) {
                $drop[] = $declaration;
            }
        }

        if (! $drop) {
            return $css;
        }

        foreach (array_reverse($drop) as $declaration) {
            $css = $this->cut($css, $declaration['start'], $declaration['end']);
            $removed[] = $declaration['name'];
        }

        // re-find the rule: the text shifted
        $header = $rule['header'];
        $again = array_values(array_filter($this->allRules($css), fn ($candidate) => $candidate['start'] === $rule['start'] && $candidate['header'] === $header));

        if (! $again) {
            return $css;
        }

        $rule = $again[0];
        $body = substr($css, $rule['open'] + 1, $rule['close'] - $rule['open'] - 1);

        if ($this->isBlank($body)) {
            return $this->removeRule($css, $rule);
        }

        return substr($css, 0, $rule['open'] + 1).$this->tidyBody($body).substr($css, $rule['close']);
    }

    /**
     * Every rule at any depth we look at: top-level ones and the `.dark` inside `@layer theme`
     *
     * @return array<int, array{start: int, open: int, close: int, header: string, body: string}>
     */
    protected function allRules(string $css): array
    {
        return [...$this->rules($css, '/./'), ...$this->darkRules($css)];
    }

    /**
     * A body once its declarations are gone: no blank line at either end, no run of blank lines inside
     */
    protected function tidyBody(string $body): string
    {
        $lines = explode("\n", $body);

        if (count($lines) < 3) {
            return $body;
        }

        $first = array_shift($lines);
        $last = array_pop($lines);
        $middle = [];

        foreach ($lines as $line) {
            if (trim($line) === '' && ($middle === [] || trim(end($middle)) === '')) {
                continue;
            }

            $middle[] = $line;
        }

        while ($middle !== [] && trim(end($middle)) === '') {
            array_pop($middle);
        }

        return $first."\n".implode("\n", $middle)."\n".$last;
    }

    /**
     * Whether a body holds nothing (whitespace only; a comment counts as content)
     */
    protected function isBlank(string $body): bool
    {
        return trim($body) === '';
    }

    /**
     * Take a rule out: its whole line(s) when nothing else is on them, else exactly its own text
     *
     * @param  array{start: int, close: int}  $rule
     */
    protected function removeRule(string $css, array $rule): string
    {
        return $this->cut($css, $rule['start'], $rule['close'] + 1, true);
    }

    /**
     * Cut [start, end) out of a stylesheet. When nothing but whitespace shares the line(s), the whole line goes (and
     * the blank line beside it, per join()); when the cut is the end of a line, the whitespace before it goes too;
     * and when only the indent before it is left, the indent goes so a closing brace after it sits flush.
     */
    protected function cut(string $css, int $start, int $end, bool $rule = false): string
    {
        $lineStart = strrpos(substr($css, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($css, "\n", $end);
        $lineEnd = $lineEnd === false ? strlen($css) : $lineEnd;
        $before = trim(substr($css, $lineStart, $start - $lineStart)) === '';
        $after = trim(substr($css, $end, $lineEnd - $end)) === '';

        if ($before && $after) {
            if ($rule) {
                return $this->join(substr($css, 0, $lineStart), substr($css, min(strlen($css), $lineEnd + 1)));
            }

            return substr($css, 0, $lineStart).substr($css, min(strlen($css), $lineEnd + 1));
        }

        if ($after) {
            return rtrim(substr($css, 0, $start), " \t").substr($css, $end);
        }

        $stop = $end;

        while (isset($css[$stop]) && ($css[$stop] === ' ' || $css[$stop] === "\t")) {
            $stop++;
        }

        // the indent before it stays for what is left on the line, unless that is a closing brace (which keeps its own column)
        $from = $before && str_starts_with(substr($css, $stop, 1), '}') ? $lineStart : $start;

        return substr($css, 0, $from).substr($css, $stop);
    }

    /**
     * The copied border-color base rule: `*` (and any of ::after, ::before,
     * ::backdrop, ::file-selector-button) with one `border-color` declaration
     * naming a grey and `currentColor`, alone or in `@layer base`
     *
     * @return array<int, array{start: int, open: int, close: int, header: string, body: string}>
     */
    protected function borderRuleSites(string $css): array
    {
        $sites = [];
        $candidates = $this->rules($css, '/./');

        foreach ($this->rules($css, '/^@layer base$/') as $layer) {
            foreach ($this->rules($css, '/./', $layer['open'] + 1, $layer['close']) as $rule) {
                $candidates[] = $rule;
            }
        }

        foreach ($candidates as $rule) {
            $selectors = array_map('trim', explode(',', $rule['header']));

            if (! in_array('*', $selectors, true) || array_diff($selectors, ['*', '::after', '::before', '::backdrop', '::file-selector-button'])) {
                continue;
            }

            $body = trim($this->blankComments($rule['body']));

            if (preg_match('/^border-color\s*:\s*var\(\s*--color-(?:gray|zinc|neutral|slate|stone)-\d{2,3}\s*,\s*currentColor\s*\)\s*;?$/', $body)) {
                $sites[] = $rule;
            }
        }

        return $sites;
    }

    /**
     * @param  array<int, string>  $removed
     */
    protected function stripBorderRule(string $css, array &$removed): string
    {
        $guard = 0;

        while ($guard++ < 20 && ($sites = $this->borderRuleSites($css))) {
            $rule = $sites[0];
            $css = $this->removeRule($css, $rule);
            $removed[] = 'border-color base rule';

            foreach (array_reverse($this->rules($css, '/^@layer base$/')) as $layer) {
                if ($this->isBlank(substr($css, $layer['open'] + 1, $layer['close'] - $layer['open'] - 1))) {
                    $css = $this->removeRule($css, $layer);
                }
            }
        }

        return $css;
    }

    /**
     * Put `--color-accent: value;` at the top of the first `@theme` block
     */
    protected function addAccent(string $css, string $value): string
    {
        $theme = $this->rules($css, '/^@theme$/')[0] ?? null;

        if ($theme === null) {
            return $css;
        }

        $declarations = $this->declarations($css, $theme);
        $lineStart = $declarations ? strrpos(substr($css, 0, $declarations[0]['start']), "\n") : false;
        $indent = $lineStart !== false ? substr($css, $lineStart + 1, $this->indentWidth($css, $lineStart + 1)) : '    ';
        $newline = strpos($css, "\n", $theme['open']);

        // a block on one line: the declaration goes right after its brace
        if ($newline === false || $newline > $theme['close']) {
            return substr($css, 0, $theme['open'] + 1).' --color-accent: '.$value.';'.substr($css, $theme['open'] + 1);
        }

        return substr($css, 0, $newline + 1).$indent.'--color-accent: '.$value.";\n".substr($css, $newline + 1);
    }

    protected function indentWidth(string $css, int $lineStart): int
    {
        preg_match('/^[ \t]*/', substr($css, $lineStart, 80), $m);

        return strlen($m[0]);
    }

    /**
     * @param  array<int, int>  $starts
     */
    protected function lineOf(array $starts, int $offset): int
    {
        $line = 1;

        foreach ($starts as $index => $start) {
            if ($start > $offset) {
                break;
            }

            $line = $index + 1;
        }

        return $line;
    }

    /**
     * @return array<int, int>
     */
    protected function lineStarts(string $css): array
    {
        $starts = [0];

        for ($i = 0, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === "\n") {
                $starts[] = $i + 1;
            }
        }

        return $starts;
    }
}
