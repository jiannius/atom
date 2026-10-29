<?php

namespace Jiannius\Atom\Services;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * The server half of a table's column visibility (components/table/{index,column,cell}.blade.php; the browser half is
 * resources/js/helpers/table-columns.js).
 *
 * A column name becomes a token in a CSS attribute selector and in a token list, so the name pattern is the injection
 * gate: nothing that fails it is ever printed into a <style> or a token list.
 */
class TableColumns
{
    /** What a column `name` or a cell `column` may be. It is interpolated into a stylesheet, so this is the gate. */
    public const NAME = '/^[A-Za-z0-9_-]{1,64}$/';

    /** Where the browser keeps the user's choices: this prefix plus the table's persist-key. */
    public const STORAGE_PREFIX = 'atom:table-columns:';

    /**
     * The first-paint script, printed as the first child of the table's scroll box when the table has a persist-key and the host
     * gave no `columns-state`. It is constant text (no key, no name is ever spliced into it), so a strict CSP can allow it by hash.
     * It reads the key from the scope's own data attribute, treats what it finds in localStorage as untrusted (a name must match
     * NAME, a value must be a boolean) and writes the effective hidden list before the browser parses the <table> after it. The list
     * the server printed is the defaults; a stored `false` hides, a stored `true` shows.
     */
    public const SCRIPT = <<<'JS'
(function (s) { try {
    var d = JSON.parse(localStorage.getItem('atom:table-columns:' + s.dataset.atomColumnKey) || 'null'), c = d && d.v === 1 && d.columns
    if (!c || typeof c !== 'object' || Array.isArray(c)) c = {}
    var h = (s.getAttribute('data-atom-column-hide') || '').split(' ').filter(Boolean), k
    for (k in c) if (/^[A-Za-z0-9_-]{1,64}$/.test(k) && typeof c[k] === 'boolean') {
        h = h.filter(function (n) { return n !== k })
        if (!c[k]) h.push(k)
    }
    s.setAttribute('data-atom-column-hide', h.join(' '))
} catch (e) {} })(document.currentScript.parentElement)
JS;

    /**
     * Throw unless the value is a usable column name.
     */
    public static function assertName(mixed $name, string $what): string
    {
        if ((! is_string($name) && ! is_int($name)) || ! preg_match(static::NAME, (string) $name)) {
            throw new InvalidArgumentException(sprintf(
                '%s must be 1 to 64 letters, digits, underscores or hyphens (it becomes a CSS token), got %s.',
                $what,
                is_scalar($name) ? '"'.$name.'"' : gettype($name),
            ));
        }

        return (string) $name;
    }

    /**
     * The table's `persist-key` as a string, or null for none. Empty, null and false mean none; a string or an int is the key.
     * Anything else (an array, an object) is a mistake in the markup and throws, like a bad column name.
     */
    public static function key(mixed $key): ?string
    {
        if ($key === null || $key === false || $key === '') {
            return null;
        }

        if (! is_string($key) && ! is_int($key)) {
            throw new InvalidArgumentException('<atom:table persist-key> must be a string, got '.get_debug_type($key).'.');
        }

        return (string) $key;
    }

    /**
     * The host's `columns-state` as an array, or null when none was given. Only an array (or an Arrayable such as a collection) is a
     * state: a JSON string, a number or an object is not one, and is treated as not given, so a saved setting that came back as the
     * wrong type falls back to `persist-key` instead of switching the table to host mode with no choices or throwing on a page load.
     *
     * @return array<mixed>|null
     */
    public static function hostState(mixed $state): ?array
    {
        return match (true) {
            is_array($state) => $state,
            $state instanceof Arrayable => $state->toArray(),
            default => null,
        };
    }

    /**
     * The meta a named <th> prints for the table to collect (see column.blade.php): name, label, hideable, hidden.
     *
     * @return array{name: string, label: string, hideable: bool, hidden: bool}
     */
    public static function meta(string $name, string $label, bool $hideable, bool $hidden): array
    {
        $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5)) ?? '');

        return [
            'name' => $name,
            'label' => mb_substr($label !== '' ? $label : str($name)->headline()->toString(), 0, 120),
            'hideable' => $hideable,
            'hidden' => $hideable && $hidden,
        ];
    }

    /**
     * The named columns of a rendered `columns` slot, in order, first one winning a repeated name. Read off the meta attribute of
     * each <th> (the attribute is escaped, so a label cannot end it early); an entry that is malformed or fails NAME is dropped.
     *
     * @return list<array{name: string, label: string, hideable: bool, hidden: bool}>
     */
    public static function payload(string $html): array
    {
        preg_match_all('/data-atom-column-meta="([^"]*)"/', $html, $found);

        $columns = [];

        foreach ($found[1] as $encoded) {
            $meta = json_decode(htmlspecialchars_decode($encoded, ENT_QUOTES), true);

            if (! is_array($meta) || ! is_string($meta['name'] ?? null) || ! preg_match(static::NAME, $meta['name']) || isset($columns[$meta['name']])) {
                continue;
            }

            $hideable = ($meta['hideable'] ?? false) === true;

            $columns[$meta['name']] = [
                'name' => $meta['name'],
                'label' => is_string($meta['label'] ?? null) ? mb_substr($meta['label'], 0, 120) : str($meta['name'])->headline()->toString(),
                'hideable' => $hideable,
                'hidden' => $hideable && ($meta['hidden'] ?? false) === true,
            ];
        }

        return array_values($columns);
    }

    /**
     * The user's choices a host handed over (`columns-state`), cleaned: names that pass NAME with a boolean value. Anything else is
     * dropped, never thrown on, because it is a saved setting that may be older than the markup.
     *
     * @return array<string, bool>
     */
    public static function cleanState(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $clean = [];

        foreach ($state as $name => $visible) {
            if ((is_string($name) || is_int($name)) && preg_match(static::NAME, (string) $name) && is_bool($visible)) {
                $clean[(string) $name] = $visible;
            }
        }

        return $clean;
    }

    /**
     * The columns hidden when the table is first drawn: the hideable ones hidden by default, then the user's choices over them
     * (`false` hides, `true` shows). A locked column is never in it.
     *
     * @param  list<array{name: string, label: string, hideable: bool, hidden: bool}>  $payload
     * @param  array<string, bool>  $state
     * @return list<string>
     */
    public static function hiddenNames(array $payload, array $state = []): array
    {
        $hidden = [];

        foreach ($payload as $column) {
            if ($column['hideable'] && ! ($state[$column['name']] ?? ! $column['hidden'])) {
                $hidden[] = $column['name'];
            }
        }

        return $hidden;
    }

    /**
     * One rule per hideable column. It is `!important` and unlayered, like [x-cloak] in atom.css, so a utility such as
     * `lg:table-cell` (in Tailwind's layer) cannot show a cell again. The selector walks the table's own structure with child
     * combinators (scroll box, table, section, row, cell), so a table nested in a cell, with a column of the same name, is not
     * reached by the outer table's list. Every name here has passed NAME.
     *
     * @param  list<array{name: string, label: string, hideable: bool, hidden: bool}>  $payload
     */
    public static function css(array $payload): string
    {
        $rules = [];

        foreach ($payload as $column) {
            if ($column['hideable'] && preg_match(static::NAME, $column['name'])) {
                $rules[] = '[data-atom-column-hide~="'.$column['name'].'"]>table>:is(thead,tbody,tfoot)>tr>[data-atom-column="'.$column['name'].'"]{display:none!important}';
            }
        }

        return implode("\n", $rules);
    }
}
