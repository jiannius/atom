<?php

namespace Jiannius\Atom\Services;

/**
 * The codemod behind `php artisan atom:upgrade`: reads what UpgradeCheck finds
 * and rewrites the four things text can fix safely (the Tailwind entry file,
 * `use AtomComponent`, the Date::use line, the `<atom:editor*>` tags and the
 * dead `editor` prop), then writes ATOM-UPGRADE-TODO.md with everything else.
 *
 * `UpgradeCheck::checks()` is the one list of what is automated: a check marked
 * `auto` there is rewritten here, and `REWRITES` names the check ids each
 * rewrite answers for (a test holds the two together). Text based, no PHP
 * parser: a rewrite that cannot be sure declines and says why, in the TODO.
 *
 * `plan()` computes the new text of every file and writes nothing. `after()`
 * scans that text in memory, so the TODO describes the tree the run leaves and
 * a second run, on that tree, finds nothing more to change.
 */
class Upgrade
{
    /**
     * The file the report is written to, at the scan root
     */
    public const TODO = 'ATOM-UPGRADE-TODO.md';

    /**
     * Rewrite kind to the check ids it answers for
     */
    public const REWRITES = [
        'css' => ['css-atom-source', 'css-skeleton-theme', 'css-border-color-rule'],
        'traits' => ['use-atom-component'],
        'date-use' => ['date-use-line'],
        'editor-tag' => ['editor-tag'],
        'editor-prop' => ['html-editor-prop'],
    ];

    /**
     * Where each trait AtomComponent became lives
     */
    public const TRAIT_NAMESPACES = [
        'WithTable' => 'Jiannius\\Atom\\Traits',
        'WithBreadcrumbs' => 'Jiannius\\Atom\\Traits',
        'WithEditor' => 'Jiannius\\Atom\\Traits',
        'WithRecaptcha' => 'Jiannius\\Atom\\Traits',
        'WithPagination' => 'Livewire',
        'WithFileUploads' => 'Livewire',
    ];

    /**
     * The marks the TODO's "changed" section sits between: a run that changes
     * nothing keeps what an earlier run wrote there
     */
    public const APPLIED_START = '<!-- atom-upgrade:applied -->';

    public const APPLIED_END = '<!-- /atom-upgrade:applied -->';

    protected UpgradeCheck $scanner;

    protected UpgradeCss $css;

    public function __construct(?UpgradeCheck $scanner = null, ?UpgradeCss $css = null)
    {
        $this->scanner = $scanner ?? new UpgradeCheck;
        $this->css = $css ?? new UpgradeCss;
    }

    /**
     * What a run would do: the new text of each file it changes, what it did (for
     * the TODO), what it declined, and the scan of the tree it would leave
     *
     * @return array{root: string, changes: array<string, array{before: string, after: string, kinds: array<int, string>}>, applied: array<int, string>, declined: array<int, array{file: string, line: int, reason: string}>, residue: array<int, array{file: string, line: int, text: string}>, scan: array<string, mixed>, before: array<string, mixed>}
     */
    public function plan(string $root): array
    {
        $root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
        $before = $this->scanner->scan($root);
        $first = $this->analyse($root, $before, []);
        $overlay = array_map(fn (array $change) => $change['after'], $first['changes']);

        // the tree the run leaves, scanned in memory: the TODO describes that one
        $scan = $first['changes'] ? $this->scanner->scan($root, $overlay) : $before;
        $second = $this->analyse($root, $scan, $overlay);

        return [
            'root' => $root,
            'changes' => $first['changes'],
            'applied' => $first['applied'],
            'declined' => $second['declined'],
            'residue' => $second['residue'],
            'scan' => $scan,
            'before' => $before,
        ];
    }

    /**
     * Run every rewrite over what a scan found, reading files through the overlay
     *
     * @param  array<string, mixed>  $scan
     * @param  array<string, string>  $overlay  relative path to text, for files an earlier pass already changed
     * @return array{changes: array<string, array{before: string, after: string, kinds: array<int, string>}>, applied: array<int, string>, declined: array<int, array{file: string, line: int, reason: string}>, residue: array<int, array{file: string, line: int, text: string}>}
     */
    protected function analyse(string $root, array $scan, array $overlay): array
    {
        $texts = [];
        $originals = [];
        $kinds = [];
        $applied = [];
        $declined = [];
        $residue = [];

        $read = function (string $relative) use ($root, &$texts, &$originals, $overlay): ?string {
            if (! array_key_exists($relative, $texts)) {
                $text = $overlay[$relative] ?? (is_file($root.'/'.$relative) && ! is_link($root.'/'.$relative) ? @file_get_contents($root.'/'.$relative) : false);
                $texts[$relative] = $text === false ? null : $text;
                $originals[$relative] = $text === false ? null : $text;
            }

            return $texts[$relative];
        };

        $touch = function (string $relative, string $kind, string $text) use (&$texts, &$kinds) {
            $texts[$relative] = $text;
            $kinds[$relative][$kind] = true;
        };

        // 1. the Tailwind entry files
        foreach ($scan['css_files'] ?? [] as $relative) {
            $text = $read($relative);

            if ($text === null) {
                continue;
            }

            $result = $this->keepingEol($text, fn (string $lf) => $this->css->rewrite($lf, $relative)['css']);
            $details = $this->css->rewrite($this->lf($text), $relative);

            if ($details['declined'] !== null) {
                $declined[] = ['file' => $relative, 'line' => 1, 'reason' => $details['declined']];
            }

            if ($result !== $text) {
                $touch($relative, 'css', $result);
                $applied[] = '`'.$relative.'`: '.$this->describeCss($details);
            }

            foreach ($this->css->residue($this->lf($texts[$relative])) as $note) {
                $residue[] = ['file' => $relative, 'line' => $note['line'], 'text' => $note['text']];
            }
        }

        // 2. use AtomComponent
        $byFile = [];

        foreach ($scan['components'] as $component) {
            if ($component['scope'] === 'app') {
                $byFile[$component['file']][] = $component;
            }
        }

        foreach ($byFile as $relative => $components) {
            $text = $read($relative);

            if ($text === null) {
                continue;
            }

            $notes = [];
            $result = $this->keepingEol($text, function (string $lf) use ($relative, $components, &$notes, &$declined) {
                return $this->rewriteTraits($lf, $relative, $components[0], $declined, $notes);
            });

            if ($result !== $text) {
                $touch($relative, 'traits', $result);
                array_push($applied, ...$notes);
            }
        }

        // 3. the Date::use line
        $dateUse = $scan['checks']['date-use-line']['findings'][0] ?? null;

        if ($dateUse !== null) {
            if (($dateUse['file'] ?? '') === '') {
                $declined[] = ['file' => 'app/Providers/AppServiceProvider.php', 'line' => 0, 'reason' => 'there is no AppServiceProvider.php: add Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); to the boot() of a service provider (UPGRADE.md, "PHP globals: dates and macros")'];
            } else {
                $relative = $dateUse['file'];
                $text = $read($relative);

                if ($text !== null) {
                    $reason = null;
                    $result = $this->keepingEol($text, function (string $lf) use (&$reason) {
                        return $this->addDateUse($lf, $reason);
                    });

                    if ($reason !== null) {
                        $declined[] = ['file' => $relative, 'line' => 1, 'reason' => $reason];
                    } elseif ($result !== $text) {
                        $touch($relative, 'date-use', $result);
                        $applied[] = '`'.$relative.'`: added `Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);` to boot()';
                    }
                }
            }
        }

        // 4. <atom:editor*> tags and the editor prop
        foreach (array_unique(array_column($scan['checks']['editor-tag']['findings'], 'file')) as $relative) {
            $text = $read($relative);

            if ($text === null) {
                continue;
            }

            $count = 0;
            $result = $this->keepingEol($text, function (string $lf) use (&$count) {
                return $this->renameEditorTags($lf, $count);
            });

            if ($result !== $text) {
                $touch($relative, 'editor-tag', $result);
                $applied[] = '`'.$relative.'`: '.$count.' `<atom:editor*>` tag'.($count === 1 ? '' : 's').' renamed to `<atom:tiptap*>`';
            }
        }

        foreach (array_unique(array_column($scan['checks']['html-editor-prop']['findings'], 'file')) as $relative) {
            $text = $read($relative);

            if ($text === null) {
                continue;
            }

            $count = 0;
            $result = $this->keepingEol($text, function (string $lf) use (&$count) {
                return $this->dropEditorProp($lf, $count);
            });

            if ($result !== $text) {
                $touch($relative, 'editor-prop', $result);
                $applied[] = '`'.$relative.'`: removed the dead `editor` attribute from '.$count.' tag'.($count === 1 ? '' : 's');
            }
        }

        $changes = [];

        foreach ($kinds as $relative => $set) {
            if ($texts[$relative] !== $originals[$relative]) {
                $changes[$relative] = ['before' => $originals[$relative], 'after' => $texts[$relative], 'kinds' => array_keys($set)];
            }
        }

        ksort($changes);
        usort($declined, fn ($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

        return compact('changes', 'applied', 'declined', 'residue');
    }

    /**
     * Run a rewrite on LF text and give the result back in the file's line endings
     * (a file that mixes the two is rewritten as it is)
     */
    protected function keepingEol(string $text, callable $rewrite): string
    {
        $crlf = substr_count($text, "\r\n");

        if ($crlf > 0 && $crlf === substr_count($text, "\n")) {
            return str_replace("\n", "\r\n", $rewrite(str_replace("\r\n", "\n", $text)));
        }

        return $rewrite($text);
    }

    protected function lf(string $text): string
    {
        $crlf = substr_count($text, "\r\n");

        return $crlf > 0 && $crlf === substr_count($text, "\n") ? str_replace("\r\n", "\n", $text) : $text;
    }

    /**
     * One sentence for what the CSS rewrite did
     *
     * @param  array{removed: array<int, string>, added: array<int, string>, brand: string|null}  $details
     */
    protected function describeCss(array $details): string
    {
        $sources = count(array_filter($details['removed'], fn ($name) => str_starts_with($name, '@source ')));
        $border = count(array_filter($details['removed'], fn ($name) => $name === 'border-color base rule'));
        $theme = count($details['removed']) - $sources - $border;
        $parts = [];

        if (in_array(true, array_map(fn ($added) => str_starts_with($added, '@import'), $details['added']), true)) {
            $parts[] = "added atom's import after `@import 'tailwindcss'`";
        }

        if ($sources) {
            $parts[] = 'removed '.$sources.' atom `@source` line'.($sources === 1 ? '' : 's');
        }

        if ($theme) {
            $parts[] = 'removed '.$theme.' theme declaration'.($theme === 1 ? '' : 's').' that were the skeleton\'s';
        }

        if ($border) {
            $parts[] = 'removed the copied border-color base rule';
        }

        if ($details['brand'] !== null) {
            $parts[] = 'set `--color-accent: '.$details['brand'].';` in `@theme`, read from your `--color-primary`';
        }

        return implode('; ', $parts);
    }

    // ------------------------------------------------------------------ PHP: use AtomComponent

    /**
     * `use AtomComponent;` to the traits the component needs, with the imports
     *
     * @param  array<string, mixed>  $component  an entry of the scan's `components`
     * @param  array<int, array{file: string, line: int, reason: string}>  $declined
     * @param  array<int, string>  $notes  filled with the lines for the TODO
     */
    protected function rewriteTraits(string $text, string $relative, array $component, array &$declined, array &$notes): string
    {
        $uses = UpgradeCheck::atomComponentUses($text);

        if (! $uses) {
            return $text;
        }

        $imports = $this->imports($text);
        $names = array_values(array_diff($component['traits'], $component['also_present']));
        $written = [];
        $needed = [];

        foreach ($names as $trait) {
            $fqn = static::TRAIT_NAMESPACES[$trait].'\\'.$trait;

            if (isset($imports[$trait]) && $imports[$trait] !== $fqn) {
                $written[] = '\\'.$fqn;
            } else {
                $written[] = $trait;

                if (! isset($imports[$trait])) {
                    $needed[] = $fqn;
                }
            }
        }

        $evidence = [];

        foreach ($component['traits'] as $trait) {
            $evidence[] = $trait.' ('.($component['evidence'][$trait] ?? 'needed by the class').')';
        }

        $changed = false;

        foreach (array_reverse($uses) as $use) {
            $line = substr_count(substr($text, 0, $use['offset']), "\n") + 1;

            if ($use['adapted']) {
                $declined[] = ['file' => $relative, 'line' => $line, 'reason' => 'the `use` statement has a `{ ... }` block of conflict rules that name AtomComponent\'s methods: change it by hand'];

                continue;
            }

            $elements = $use['elements'];
            array_splice($elements, $use['match'], 1, $written);
            $was = trim(substr($text, $use['offset'], $use['end'] - $use['offset']));

            if ($elements === []) {
                $text = $this->removeStatement($text, $use['offset'], $use['end']);
                $now = null;
            } else {
                $statement = 'use '.implode(', ', $elements).';';
                $text = substr($text, 0, $use['offset']).$statement.substr($text, $use['end']);
                $now = $statement;
            }

            $changed = true;
            $notes[] = '`'.$relative.':'.$line.'`: `'.preg_replace('/\s+/', ' ', $was).'` '.($now === null ? 'was removed: the component needs no trait it does not already use' : 'became `'.$now.'`').'. Check the traits: '.($evidence ? implode('; ', $evidence) : 'none needed').($component['notes'] ? '. Note: '.implode('; ', $component['notes']) : '');
        }

        if (! $changed) {
            return $text;
        }

        $text = $this->dropAtomComponentImport($text);

        foreach ($needed as $fqn) {
            $text = $this->addImport($text, $fqn);
        }

        return $text;
    }

    /**
     * The file's own `use X\Y;` imports at the top level, short name (or alias) to class
     *
     * @return array<string, string>
     */
    protected function imports(string $text): array
    {
        $imports = [];

        if (preg_match_all('/^use\s+\\\\?([\w\\\\]+?)(?:\s+as\s+(\w+))?\s*;[ \t]*$/m', $text, $found, PREG_SET_ORDER)) {
            foreach ($found as $m) {
                $imports[$m[2] ?? substr(strrchr('\\'.$m[1], '\\'), 1)] = $m[1];
            }
        }

        return $imports;
    }

    /**
     * Delete a statement; the whole line when it holds nothing else
     */
    protected function removeStatement(string $text, int $start, int $end): string
    {
        $lineStart = strrpos(substr($text, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($text, "\n", $end);
        $lineEnd = $lineEnd === false ? strlen($text) : $lineEnd + 1;

        if (trim(substr($text, $lineStart, $start - $lineStart)) !== '' || trim(substr($text, $end, $lineEnd - $end)) !== '') {
            return substr($text, 0, $start).substr($text, $end);
        }

        $before = substr($text, 0, $lineStart);
        $after = substr($text, $lineEnd);

        // the blank line that followed the statement goes too when it sat right under a brace
        if (str_ends_with(rtrim($before, " \t\n"), '{') && preg_match('/^[ \t]*\n/', $after, $blank)) {
            $after = substr($after, strlen($blank[0]));
        }

        return $before.$after;
    }

    /**
     * Remove `use Jiannius\Atom\Traits\AtomComponent;` once nothing in the file names it
     */
    protected function dropAtomComponentImport(string $text): string
    {
        if (! preg_match_all('/^use\s+\\\\?Jiannius\\\\Atom\\\\Traits\\\\AtomComponent(?:\s+as\s+(\w+))?\s*;[ \t]*\n?/m', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        foreach (array_reverse($found) as $m) {
            $alias = $m[1][0] ?? 'AtomComponent';
            $rest = substr($text, 0, $m[0][1]).substr($text, $m[0][1] + strlen($m[0][0]));

            if (! preg_match('/(?<![\w\\\\])'.preg_quote($alias, '/').'(?![\w])/', $rest)) {
                $text = $rest;
            }
        }

        return $text;
    }

    /**
     * Add `use X;` among the file's imports, in alphabetical place; or after the
     * namespace line, or after the opening tag when there are none
     */
    protected function addImport(string $text, string $fqn): string
    {
        $line = 'use '.$fqn.";\n";

        if (preg_match_all('/^use\s+\\\\?([\w\\\\]+?)(?:\s+as\s+\w+)?\s*;[ \t]*\n/m', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($found as $m) {
                if (strcasecmp($m[1][0], $fqn) > 0) {
                    return substr($text, 0, $m[0][1]).$line.substr($text, $m[0][1]);
                }
            }

            $last = end($found);
            $at = $last[0][1] + strlen($last[0][0]);

            return substr($text, 0, $at).$line.substr($text, $at);
        }

        if (preg_match('/^namespace\s+[\w\\\\]+\s*;[ \t]*\n/m', $text, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($text, 0, $at)."\n".$line.substr($text, $at);
        }

        if (preg_match('/<\?php[ \t]*\n/', $text, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($text, 0, $at)."\n".$line.substr($text, $at);
        }

        return $text;
    }

    // ------------------------------------------------------------------ PHP: Date::use

    /**
     * Put the call first in boot(); $reason is set when it cannot be done
     */
    protected function addDateUse(string $text, ?string &$reason): string
    {
        $blank = $this->scanner->commentFree('php', $text);

        if (preg_match('/\bDate\s*::\s*use\s*\(/', $blank)) {
            $reason = 'it already calls Date::use() with another class: make it Date::use(\\Jiannius\\Atom\\Services\\Carbon::class) to keep pretty(), local() and the ISO string form, or leave it if you chose another date class on purpose';

            return $text;
        }

        if (! preg_match('/function\s+boot\s*\([^)]*\)\s*(?::\s*[\w\\\\?|]+)?\s*\{/', $blank, $m, PREG_OFFSET_CAPTURE)) {
            $reason = 'it has no boot() method: add Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); to the boot() of a service provider';

            return $text;
        }

        $open = $m[0][1] + strlen($m[0][0]);
        $newline = strpos($text, "\n", $open);

        if ($newline === false || trim(substr($text, $open, $newline - $open)) !== '') {
            $reason = 'its boot() body starts on the line of the brace: add Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); to it by hand';

            return $text;
        }

        $lineStart = strrpos(substr($text, 0, $m[0][1]), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        preg_match('/^[ \t]*/', substr($text, $lineStart), $indent);

        $imports = $this->imports($text);
        $short = ! isset($imports['Date']) || $imports['Date'] === 'Illuminate\\Support\\Facades\\Date';
        $call = ($short ? 'Date' : '\\Illuminate\\Support\\Facades\\Date').'::use(\\Jiannius\\Atom\\Services\\Carbon::class);';
        $next = ltrim(substr($text, $newline + 1));
        $insert = $indent[0].'    '.$call."\n".(($next[0] ?? '}') === '}' ? '' : "\n");
        $text = substr($text, 0, $newline + 1).$insert.substr($text, $newline + 1);

        if ($short && ! isset($imports['Date'])) {
            $text = $this->addImport($text, 'Illuminate\\Support\\Facades\\Date');
        }

        return $text;
    }

    // ------------------------------------------------------------------ Blade: editor tags and prop

    /**
     * `<atom:editor>`, `<atom:editor.chat>`, `<atom:editor.content>` (and the
     * `<x-atom::...>` spelling, opening and closing) to `tiptap`
     */
    protected function renameEditorTags(string $text, int &$count): string
    {
        $blank = $this->scanner->commentFree('blade', $text);

        if (! preg_match_all('/<(\/?)(x-atom::|atom:)(editor)(?:\.chat|\.content)?(?![\w\-.])/i', $blank, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        foreach (array_reverse($found) as $m) {
            $name = $m[3];
            $text = substr($text, 0, $name[1]).'tiptap'.substr($text, $name[1] + strlen($name[0]));
            $count += $m[1][0] === '' ? 1 : 0;
        }

        return $text;
    }

    /**
     * Delete the `editor` attribute of `<atom:html>` and the shells
     */
    protected function dropEditorProp(string $text, int &$count): string
    {
        $blank = $this->scanner->commentFree('blade', $text);
        $cuts = [];

        foreach ($this->scanner->parseTags($blank) as $tag) {
            if (! in_array($tag['name'], ['html', 'layouts.sidebar', 'layouts.header'], true)) {
                continue;
            }

            $found = false;

            foreach (['editor', ':editor', 'x-bind:editor'] as $name) {
                if (! isset($tag['spans'][$name])) {
                    continue;
                }

                [$from, $to] = $tag['spans'][$name];
                $start = $tag['attributes_at'] + $from;
                $end = $tag['attributes_at'] + $to;

                // the whitespace before it goes with it
                while ($start > 0 && ctype_space($text[$start - 1])) {
                    $start--;
                }

                $cuts[] = [$start, $end];
                $found = true;
            }

            $count += $found ? 1 : 0;
        }

        usort($cuts, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($cuts as [$start, $end]) {
            $text = substr($text, 0, $start).substr($text, $end);
        }

        return $text;
    }

    // ------------------------------------------------------------------ writing

    /**
     * Write every changed file. Fails before writing anything when one cannot be written.
     *
     * @param  array<string, mixed>  $plan
     */
    public function apply(array $plan): void
    {
        foreach (array_keys($plan['changes']) as $relative) {
            $path = $plan['root'].'/'.$relative;

            if (! is_file($path) || is_link($path) || ! is_writable($path)) {
                throw new \RuntimeException('Cannot write '.$relative.' (missing, a symlink or read-only): nothing was changed.');
            }
        }

        foreach ($plan['changes'] as $relative => $change) {
            if (@file_put_contents($plan['root'].'/'.$relative, $change['after']) === false) {
                throw new \RuntimeException('Could not write '.$relative.'.');
            }
        }
    }

    // ------------------------------------------------------------------ the diff

    /**
     * A unified diff of every change, `a/` and `b/` paths relative to the root
     *
     * @param  array<string, mixed>  $plan
     */
    public function diff(array $plan): string
    {
        $out = '';

        foreach ($plan['changes'] as $relative => $change) {
            $out .= $this->unifiedDiff($relative, $change['before'], $change['after']);
        }

        return $out;
    }

    /**
     * Context lines either side of a change
     */
    protected const CONTEXT = 3;

    /**
     * A unified diff of one file (empty when the texts are equal)
     */
    public function unifiedDiff(string $relative, string $before, string $after): string
    {
        if ($before === $after) {
            return '';
        }

        $a = preg_split('/(?<=\n)/', $before, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/(?<=\n)/', $after, -1, PREG_SPLIT_NO_EMPTY);
        $script = $this->editScript($a, $b);
        $out = '--- a/'.$relative."\n+++ b/".$relative."\n";
        $count = count($script);
        $i = 0;

        while ($i < $count) {
            if ($script[$i][0] === ' ') {
                $i++;

                continue;
            }

            // a hunk: from CONTEXT lines before the first change to CONTEXT after the last, merging changes closer than that
            $start = max(0, $i - static::CONTEXT);
            $end = $i;

            for ($j = $i; $j < $count; $j++) {
                if ($script[$j][0] !== ' ') {
                    $end = $j;
                } elseif ($j - $end > static::CONTEXT * 2) {
                    break;
                }
            }

            $end = min($count - 1, $end + static::CONTEXT);
            $oldStart = 0;
            $newStart = 0;

            for ($k = 0; $k < $start; $k++) {
                $oldStart += $script[$k][0] !== '+' ? 1 : 0;
                $newStart += $script[$k][0] !== '-' ? 1 : 0;
            }

            $oldLength = 0;
            $newLength = 0;
            $body = '';

            for ($k = $start; $k <= $end; $k++) {
                [$mark, $line] = $script[$k];
                $oldLength += $mark !== '+' ? 1 : 0;
                $newLength += $mark !== '-' ? 1 : 0;
                $body .= $mark.$line.(str_ends_with($line, "\n") ? '' : "\n\\ No newline at end of file\n");
            }

            $out .= '@@ -'.($oldLength ? $oldStart + 1 : $oldStart).($oldLength === 1 ? '' : ','.$oldLength).' +'.($newLength ? $newStart + 1 : $newStart).($newLength === 1 ? '' : ','.$newLength)." @@\n".$body;
            $i = $end + 1;
        }

        return $out;
    }

    /**
     * Shortest edit script between two lists of lines (Myers), as [mark, line]
     * with mark ' ', '-' or '+'. A change too large to diff cheaply is a
     * whole-file replacement.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: string, 1: string}>
     */
    protected function editScript(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $prefix = 0;

        while ($prefix < $n && $prefix < $m && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        $suffix = 0;

        while ($suffix < $n - $prefix && $suffix < $m - $prefix && $a[$n - 1 - $suffix] === $b[$m - 1 - $suffix]) {
            $suffix++;
        }

        $head = array_map(fn ($line) => [' ', $line], array_slice($a, 0, $prefix));
        $tail = array_map(fn ($line) => [' ', $line], array_slice($a, $n - $suffix));
        $x = array_slice($a, $prefix, $n - $prefix - $suffix);
        $y = array_slice($b, $prefix, $m - $prefix - $suffix);

        return [...$head, ...$this->myers($x, $y), ...$tail];
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: string, 1: string}>
     */
    protected function myers(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0 || $m === 0) {
            return [...array_map(fn ($line) => ['-', $line], $a), ...array_map(fn ($line) => ['+', $line], $b)];
        }

        $max = min($n + $m, 3000);
        $v = [1 => 0];
        $trace = [];

        for ($d = 0; $d <= $max; $d++) {
            $trace[] = $v;

            for ($k = -$d; $k <= $d; $k += 2) {
                $x = ($k === -$d || ($k !== $d && $v[$k - 1] < $v[$k + 1])) ? $v[$k + 1] : $v[$k - 1] + 1;
                $y = $x - $k;

                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }

                $v[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    return $this->backtrack($trace, $a, $b, $d);
                }
            }
        }

        // too far apart to be worth a shortest script
        return [...array_map(fn ($line) => ['-', $line], $a), ...array_map(fn ($line) => ['+', $line], $b)];
    }

    /**
     * @param  array<int, array<int, int>>  $trace
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: string, 1: string}>
     */
    protected function backtrack(array $trace, array $a, array $b, int $d): array
    {
        $x = count($a);
        $y = count($b);
        $script = [];

        for ($step = $d; $step > 0; $step--) {
            $v = $trace[$step];
            $k = $x - $y;
            $previous = ($k === -$step || ($k !== $step && $v[$k - 1] < $v[$k + 1])) ? $k + 1 : $k - 1;
            $px = $v[$previous] ?? 0;
            $py = $px - $previous;

            // $trace[$step] holds v from before the step: the previous end point is in it
            while ($x > $px && $y > $py) {
                $x--;
                $y--;
                $script[] = [' ', $a[$x]];
            }

            if ($x === $px) {
                $y--;
                $script[] = ['+', $b[$y]];
            } else {
                $x--;
                $script[] = ['-', $a[$x]];
            }
        }

        while ($x > 0 && $y > 0) {
            $x--;
            $y--;
            $script[] = [' ', $a[$x]];
        }

        return array_reverse($script);
    }

    // ------------------------------------------------------------------ the TODO

    /**
     * The text of ATOM-UPGRADE-TODO.md for the tree a run leaves
     *
     * @param  array<string, mixed>  $plan
     */
    public function todo(array $plan, ?string $existing = null): string
    {
        $scan = $plan['scan'];
        $applied = $this->mergeApplied($this->existingApplied($existing), $plan['applied']);
        $out = [];
        $out[] = '# atom 4 upgrade: what is left';
        $out[] = '';
        $out[] = 'Written by `php artisan atom:upgrade`, and rewritten by every run. What each item means is in `'.UpgradeCheck::GUIDE.'` (the section named on each heading). A finding is a candidate from a text scan, not a proof.';
        $out[] = '';
        $out[] = '## Changed by atom:upgrade';
        $out[] = '';
        $out[] = 'Review the diff (`git diff`). Each line is a rewrite the codemod made; the trait choice in particular is read from text, which cannot see everything.';
        $out[] = '';
        $out[] = static::APPLIED_START;

        foreach ($applied ?: ['(nothing was changed)'] as $line) {
            $out[] = '- '.$line;
        }

        $out[] = static::APPLIED_END;
        $out[] = '';
        $out[] = '## Left for you by the codemod';
        $out[] = '';
        $declined = $plan['declined'];

        if (! $declined && ! $plan['residue']) {
            $out[] = '(nothing: every automated rewrite that applies was made)';
        }

        foreach ($declined as $item) {
            $out[] = '- `'.$item['file'].($item['line'] ? ':'.$item['line'] : '').'`: not changed, '.$item['reason'];
        }

        foreach ($plan['residue'] as $item) {
            $out[] = '- `'.$item['file'].':'.$item['line'].'`: '.$item['text'];
        }

        $out[] = '';
        $out[] = '## Accent';
        $out[] = '';

        foreach ($this->accentLines($plan) as $line) {
            $out[] = '- '.$line;
        }

        $out[] = '';
        $out[] = '## Content';
        $out[] = '';
        $out[] = '- Editor columns must be migrated before you require v4: `php artisan atom:tiptap-migrate --status` exits 0 when nothing is left (UPGRADE.md, "The legacy editor"). `atom:upgrade` refuses to write until it does.';
        $out[] = '';
        $out[] = '## Findings, by UPGRADE.md section';
        $out[] = '';

        $groups = [];

        foreach ($scan['checks'] as $check) {
            if ($check['count'] || $check['tests_count']) {
                $groups[$check['section']][] = $check;
            }
        }

        if (! $groups) {
            $out[] = '(no findings)';
        }

        foreach ($groups as $section => $checks) {
            $out[] = '### '.$section;
            $out[] = '';

            foreach ($checks as $check) {
                $total = $check['count'] + $check['tests_count'];
                $left = $check['codemod'] !== 'auto' ? '' : ($check['count'] ? ' (automated, but these were left: see above)' : ' (automated for the app, not for tests/: change these by hand)');
                $out[] = '- **'.$check['id'].'** ('.$check['severity'].', '.$total.'): '.$check['title'].$left;

                foreach (array_slice($check['findings'], 0, 100) as $finding) {
                    $out[] = '  - `'.($finding['line'] ? $finding['file'].':'.$finding['line'] : '(app)').'`'.($finding['scope'] === 'tests' ? ' (tests)' : '').' '.str_replace('`', "'", $finding['text']).(isset($finding['note']) ? ' <- '.$finding['note'] : '');
                }

                if ($total > 100) {
                    $out[] = '  - ... and '.($total - 100).' more (`php artisan atom:upgrade-check --all`)';
                }
            }

            $out[] = '';
        }

        $restyled = [];

        foreach ($scan['tags'] as $name => $tag) {
            if ($tag['restyled']) {
                $restyled[$tag['restyled']][] = $name.' x'.$tag['count'];
            }
        }

        if ($restyled) {
            $out[] = '## Restyled components in use';
            $out[] = '';
            $out[] = 'A class you put on these (`class="..."`, a colour, a size) may now fight the new look; read the section and look at the page.';
            $out[] = '';

            foreach ($restyled as $section => $names) {
                $out[] = '- '.$section.': '.implode(', ', $names);
            }

            $out[] = '';
        }

        if ($scan['published_views']) {
            $out[] = '## Published atom views';
            $out[] = '';
            $out[] = 'These copies of atom\'s views do not follow the new ones: diff each against v4.';
            $out[] = '';

            foreach ($scan['published_views'] as $view) {
                $out[] = '- `'.$view.'`';
            }

            $out[] = '';
        }

        if ($scan['skipped']) {
            $out[] = '## Not read';
            $out[] = '';

            foreach ($scan['skipped'] as $skipped) {
                $out[] = '- `'.$skipped['path'].'` ('.$skipped['reason'].')';
            }

            $out[] = '';
        }

        $out[] = '## Last';
        $out[] = '';
        $out[] = '- Run your tests, then look at the pages: the look changed (UPGRADE.md, every restyle section). Delete this file when you are done.';
        $out[] = '';

        return implode("\n", $out);
    }

    /**
     * What `--color-accent` is in the Tailwind entry file(s), as sentences
     *
     * @param  array<string, mixed>  $plan
     * @return array<int, string>
     */
    protected function accentLines(array $plan): array
    {
        $lines = [];
        $entries = 0;

        foreach ($plan['scan']['css_files'] ?? [] as $relative) {
            $text = $plan['changes'][$relative]['after'] ?? @file_get_contents($plan['root'].'/'.$relative);

            if (! is_string($text) || ! $this->css->hasEntryImport($text)) {
                continue;
            }

            $entries++;
            $accent = $this->css->accent($this->lf($text));
            $lines[] = $accent
                ? '`'.$relative.':'.$accent['line'].'` sets `--color-accent: '.$accent['value'].';`. Check it is your brand colour. Do not set `accent-foreground` unless you must.'
                : '`'.$relative.'` sets no `--color-accent`, so the accent is near-black (`#1c1b18`), which is barely visible as a fill in dark mode. Set your brand colour in `@theme`. v3\'s `primary` and `accent` are not copied unless `primary` was your own colour.';
        }

        if ($entries === 0) {
            $lines[] = 'No Tailwind entry file with atom\'s import was found. Import `'.UpgradeCss::ENTRY.'` after `@import \'tailwindcss\'` and set `--color-accent` in `@theme` (UPGRADE.md, "Tokens").';
        }

        return $lines;
    }

    /**
     * The lines between the markers of an earlier TODO
     *
     * @return array<int, string>
     */
    protected function existingApplied(?string $existing): array
    {
        if ($existing === null || ! preg_match('/'.preg_quote(static::APPLIED_START, '/').'\n(.*?)\n'.preg_quote(static::APPLIED_END, '/').'/s', $existing, $m)) {
            return [];
        }

        $lines = [];

        foreach (explode("\n", $m[1]) as $line) {
            if (str_starts_with($line, '- ') && $line !== '- (nothing was changed)') {
                $lines[] = substr($line, 2);
            }
        }

        return $lines;
    }

    /**
     * Earlier lines first, then this run's, none twice
     *
     * @param  array<int, string>  $old
     * @param  array<int, string>  $new
     * @return array<int, string>
     */
    protected function mergeApplied(array $old, array $new): array
    {
        return array_values(array_unique([...$old, ...$new]));
    }
}
