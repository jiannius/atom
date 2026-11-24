<?php

namespace Jiannius\Atom\Services;

/**
 * Signs and verifies the "raw:" values a table column's declared sort can put
 * into "_table.sort.column" (see components/table/column.blade.php), so
 * toTable() can tell a host's own raw SQL expression apart from one a client
 * tampered with over the wire (issue #54).
 */
class TableSort
{
    /** Rows per page when `_table.max_rows` is missing or unusable. */
    public const DEFAULT_ROWS = 100;

    /** The most rows per page a client may ask for (the pager menu offers 50 to 400). */
    public const MAX_ROWS = 500;

    /** Sort columns already logged as dropped, so a page that keeps sending one logs it once per process. */
    protected static array $droppedLogged = [];

    /**
     * Sign a raw sort expression for the client-facing "_table.sort.column"
     * value. Keeps the "raw:" prefix toTable() dispatches on, followed by a
     * fixed 64-char sha256 hmac and a colon, then the expression itself — the
     * fixed length lets the token be parsed back out even if the expression
     * carries colons of its own.
     */
    public static function sign(string $expression) : string
    {
        return 'raw:'.static::mac($expression).':'.$expression;
    }

    /**
     * Whether a "_table.sort.column" value must be refused when it comes from
     * the client: any "raw:" value with no signature, or one whose signature
     * does not match. A plain column name is never refused here — orderBy()
     * already quotes it safely, which is why only the "raw:" branch needs
     * this gate.
     */
    public static function isUnsafeFromClient(string $column) : bool
    {
        return str_starts_with($column, 'raw:') && !static::isSignedAndValid($column);
    }

    /**
     * Whether a client-driven update to the component property path $name (new value
     * $value) would leave "_table.sort.column" or "_table.sort.direction" unsafe:
     * non-string, or a string "raw:" column with no valid signature. Livewire fires
     * its update event once per dotted path a $wire.set() targets, so "_table.sort.column",
     * "_table.sort" and "_table" wholesale are all checked, and so is a path deeper than
     * the leaf ("_table.sort.column.0"), which can only turn that leaf into an array.
     */
    public static function isUnsafeUpdate(string $name, mixed $value, bool $trashedAllowed = false) : bool
    {
        if (str_starts_with($name, '_table.sort.column.') || str_starts_with($name, '_table.sort.direction.')) {
            return true;
        }

        if (static::isUnsafeRowsOrTrashed($name, $value, $trashedAllowed)) {
            return true;
        }

        $column = match ($name) {
            '_table.sort.column' => $value,
            '_table.sort' => data_get($value, 'column'),
            '_table' => data_get($value, 'sort.column'),
            default => null,
        };

        if ($column !== null && (!is_string($column) || static::isUnsafeFromClient($column))) {
            return true;
        }

        $direction = match ($name) {
            '_table.sort.direction' => $value,
            '_table.sort' => data_get($value, 'direction'),
            '_table' => data_get($value, 'sort.direction'),
            default => null,
        };

        return $direction !== null && !is_string($direction);
    }

    /**
     * Whether a client update would leave `_table.max_rows` or `_table.show_trashed`
     * unusable: `max_rows` must be a whole number from 1 to MAX_ROWS (it is the
     * page size handed to paginate()), and `show_trashed` a bool, and true only
     * when the table rendered its trashed toggle (WithTable::$_tableTrashed): a
     * client must not list a model's soft-deleted rows the host never offered.
     * A path deeper than the leaf only turns it into an array, so it is refused too.
     */
    protected static function isUnsafeRowsOrTrashed(string $name, mixed $value, bool $trashedAllowed) : bool
    {
        if (str_starts_with($name, '_table.max_rows.') || str_starts_with($name, '_table.show_trashed.')) {
            return true;
        }

        // `$wire.set('_table', 'x')` would leave the property a string; the whole bucket is an array or it is refused
        if ($name === '_table' && !is_array($value)) {
            return true;
        }

        $rows = match ($name) {
            '_table.max_rows' => [$value],
            '_table' => is_array($value) && array_key_exists('max_rows', $value) ? [$value['max_rows']] : [],
            default => [],
        };

        foreach ($rows as $rowsValue) {
            // a `<select wire:model="_table.max_rows">` sends its option as a string; a whole number from 1 to MAX_ROWS either way
            if (is_string($rowsValue) && preg_match('/^\d{1,3}$/D', $rowsValue)) {
                $rowsValue = (int) $rowsValue;
            }

            if (!is_int($rowsValue) || $rowsValue < 1 || $rowsValue > static::MAX_ROWS) {
                return true;
            }
        }

        $trashed = match ($name) {
            '_table.show_trashed' => [$value],
            '_table' => is_array($value) && array_key_exists('show_trashed', $value) ? [$value['show_trashed']] : [],
            default => [],
        };

        foreach ($trashed as $trashedValue) {
            if ($trashedValue !== null && !is_bool($trashedValue)) {
                return true;
            }

            if ($trashedValue === true && !$trashedAllowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page size toTable() uses for a `_table.max_rows` value: a whole number
     * from 1 to MAX_ROWS, a numeric string included; anything else (an array,
     * text, zero, a negative, a huge number) is the default or the cap.
     */
    public static function rows(mixed $value) : int
    {
        if (is_string($value) && preg_match('/^\d{1,9}$/D', $value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 1) {
            return static::DEFAULT_ROWS;
        }

        return min($value, static::MAX_ROWS);
    }

    /**
     * Whether a model's column may be sorted on from the client's say-so. The column must be an
     * identifier (`name`, `items.name`, `data->key`: no space, quote, backtick, newline or `as`),
     * outside the model's `$hidden` in every `.` / `->` segment, and its first segment inside a
     * non-empty `$visible` (case-folded; the same rule filter() applies). The model's own table
     * prefix (`items.name` on a model whose table is `items`) is not counted as the column, so a
     * qualified column on a model with `$visible` sorts like the bare one; another table's
     * prefix is not the model's column and stays refused.
     */
    public static function isSortable(\Illuminate\Database\Eloquent\Model $model, string $column) : bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*(?:->[A-Za-z0-9_]+)*$/D', $column)) {
            return false;
        }

        $segments = array_map('strtolower', preg_split('/\.|->/', $column));
        $hidden = array_map('strtolower', $model->getHidden());
        $visible = array_map('strtolower', $model->getVisible());

        if (array_intersect($segments, $hidden)) {
            return false;
        }

        if (count($segments) > 1 && str_contains($column, '.') && $segments[0] === strtolower($model->getTable())) {
            array_shift($segments);
        }

        return !$visible || in_array($segments[0], $visible, true);
    }

    /**
     * Log, once per process and column, that a client sort column was dropped, so a sort that "does nothing" is
     * findable (a hidden `created_at` is the usual one). The column is client text: printable ASCII only, cut short.
     */
    public static function noteDropped(\Illuminate\Database\Eloquent\Model $model, string $column) : void
    {
        $shown = mb_substr(preg_replace('/[^\x20-\x7e]/', '?', $column), 0, 80);
        $key = get_class($model).'|'.$shown;

        if (isset(static::$droppedLogged[$key])) {
            return;
        }

        if (count(static::$droppedLogged) >= 100) {
            static::$droppedLogged = [];
        }

        static::$droppedLogged[$key] = true;

        \Illuminate\Support\Facades\Log::debug('[atom] a table sort column was dropped: it is hidden, not visible or not a plain column name. Declare it as <atom:table.column sort="raw:...">, or un-hide it.', [
            'model' => get_class($model),
            'column' => $shown,
        ]);
    }

    /**
     * The trusted SQL expression behind a "raw:" column value, for toTable()
     * to hand to orderByRaw(). Returns null when a signed value's signature
     * does not match. An unsigned "raw:<expr>" value is trusted as-is here —
     * it can only have been set from PHP, since isUnsafeFromClient() above
     * refuses it from the browser.
     */
    public static function expression(string $column) : ?string
    {
        $parsed = static::parse($column);

        if (!$parsed) {
            return substr($column, 4);
        }

        return hash_equals(static::mac($parsed[1]), $parsed[0]) ? $parsed[1] : null;
    }

    /**
     * Whether a "raw:" column value carries a signature and it verifies.
     */
    protected static function isSignedAndValid(string $column) : bool
    {
        $parsed = static::parse($column);

        return $parsed !== null && hash_equals(static::mac($parsed[1]), $parsed[0]);
    }

    /**
     * Split a "raw:<64-hex-hmac>:<expression>" value into [hmac, expression],
     * or null when it isn't shaped like a signed token.
     */
    protected static function parse(string $column) : ?array
    {
        return preg_match('/^raw:([0-9a-f]{64}):(.*)$/s', $column, $matches)
            ? [$matches[1], $matches[2]]
            : null;
    }

    /**
     * HMAC a raw sort expression against the app key.
     */
    protected static function mac(string $expression) : string
    {
        return hash_hmac('sha256', $expression, (string) config('app.key'));
    }
}
