<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Facades\DB;
use Jiannius\Atom\Services\TableSort;

class Builder
{
    /**
     * Where date between
     */
    public function whereDateBetween()
    {
        return function ($column, $range) {
            $range = is_string($range)
                ? collect(explode('to', $range))->map(fn ($val) => str($val)->replace('to', ''))->map(fn ($val) => trim($val))->toArray()
                : $range;

            $from = data_get($range, '0', '');
            $to = data_get($range, '1', '');

            if ($from) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $this->whereDate($column, '>=', $from); // date in YYYY-MM-DD
                else $this->where($column, '>=', $from);
            }

            if ($to) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $this->whereDate($column, '<=', $to); // date in YYYY-MM-DD
                else $this->where($column, '<=', $to);
            }

            return $this;
        };
    }

    /**
     * Go to specific page number in paginator
     */
    public function toPage()
    {
        return function ($page = 1, $rows = 50) {
            return $this->paginate($rows, ['*'], 'page', $page);
        };
    }

    /**
     * Build paginator for data table
     */
    public function toTable()
    {
        return function ($filters = null, $maxRows = null) {
            // current() returns false (not null) when no component is on the stack,
            // e.g. toTable() called from a job/console/test — guard so ?-> works.
            $config = (app('livewire')->current() ?: null)?->_table;

            // Defence in depth: updatingAtomComponent() refuses a client
            // update that would leave these non-string, but $_table is a
            // plain public property PHP code can still set directly — a
            // non-string column means "no sort" rather than a str() crash,
            // and a non-string direction just falls back to 'asc'.
            $sortColumn = data_get($config, 'sort.column');
            $sortColumn = is_string($sortColumn) ? $sortColumn : null;

            $sortDirection = data_get($config, 'sort.direction');
            $sortDirection = is_string($sortDirection) ? $sortDirection : 'asc';

            $showTrashed = data_get($config, 'show_trashed');
            $maxRows ??= data_get($config, 'max_rows') ?? 100;

            if ($sortColumn) {
                // a user-chosen sort overrides any default ordering on the query —
                // appended after existing orderBys it would never win
                $this->reorder();

                if (str($sortColumn)->startsWith('raw:')) {
                    // A signed value came from the client — verify() strips the
                    // signature; an unsigned one can only have come from PHP
                    // (AtomComponent::updatingAtomComponent() refuses the
                    // client any other way), so it stays trusted as-is.
                    $expression = TableSort::expression($sortColumn);

                    // The direction used to be spliced into the raw SQL
                    // unvalidated too — clamp it here regardless of source.
                    $direction = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

                    if ($expression !== null) {
                        $this->orderByRaw($expression.' '.$direction);
                    }
                }
                else {
                    $this->orderBy($sortColumn, $sortDirection);
                }
            }
            else if (
                ($this instanceof \Illuminate\Database\Query\Builder && !$this->orders)
                || ($this instanceof \Illuminate\Database\Eloquent\Builder && !$this->query->orders)
            ) {
                $this->latest('id');
            }

            if ($showTrashed) $this->onlyTrashed();

            if ($filters) $this->filter($filters);

            return $this->paginate($maxRows);
        };
    }

    /**
     * Get table columns
     */
    public function tableColumns()
    {
        return function () {
            // Memoize per table for the lifetime of the request, so filtering
            // multiple raw columns on the same table costs at most one cache
            // read (which is itself a DB query when cache.driver=database).
            static $memo = [];

            $table = $this->getModel()->getTable();

            if (isset($memo[$table])) {
                return $memo[$table];
            }

            $columns = cache()->remember('table_'.$table.'_columns', now()->addDays(7), function () use ($table) {
                return collect(DB::select("show columns from `$table`"))
                    ->map(fn ($row) => (array) $row)
                    ->all();
            });

            return $memo[$table] = collect($columns)->map(fn($val) => [
                'name' => data_get($val, 'Field'),
                'type' => data_get($val, 'Type'),
            ])->values();
        };
    }

    /**
     * Check if table has column
     */
    public function tableHasColumn()
    {
        return function ($column) {
            $columns = $this->getModel()->tableColumns();
            return $columns->where('name', $column)->count() > 0;
        };
    }

    /**
     * Get table column type
     */
    public function tableColumnType()
    {
        return function ($column, $checker = null) {
            $columns = $this->tableColumns();
            $column = $columns->firstWhere('name', $column);
            $type = data_get($column, 'type');
    
            return $checker ? in_array($type, (array) $checker) : $type;
        };
    }

    /**
     * Filter query
     */
    public function filter()
    {
        return function (...$filters) {
            if (count($filters) === 1 && is_array(head($filters))) {
                foreach (head($filters) as $key => $value) {
                    $this->filter($key, $value);
                }
            }
            else {
                $key = head($filters);
                $value = last($filters);

                // A blank value (null / '' / []) means "no constraint" — skip it
                // entirely. This avoids the column-type introspection below for
                // unset filters, and prevents whereIn([]) returning zero rows.
                if (blank($value)) {
                    return $this;
                }

                // Keys and values are untrusted (a public $filters can be replaced from the
                // browser). A key that is not a string or int, or a value that is not a scalar,
                // a Stringable, an enum or an array, cannot mean anything: no-op.
                if (
                    (! is_string($key) && ! is_int($key))
                    || (! is_scalar($value) && ! is_array($value) && ! $value instanceof \BackedEnum && ! $value instanceof \Stringable)
                ) {
                    return $this;
                }

                $key = (string) $key;
                $table = $this->getModel()->getTable();

                if ($key === 'search' && $this->hasNamedScope('search') && $value) {
                    // a search term is text; an array has no meaning to the scope
                    if (! is_array($value)) {
                        $this->search($value);
                    }
                }
                else if ($key !== 'search' && ($scope = (string) str($key)->camel()) && $this->hasNamedScope($scope)) {
                    $this->$scope($value);
                }
                else {
                    $key = explode(':', $key);
                    $col = head($key);
                    $operator = count($key) > 1 ? strtolower(trim(last($key))) : null;

                    // The key is client-controllable, so it must be a plain column reference
                    // before it gets anywhere near introspection or a query: `col`,
                    // `col:operator`, or `col.path` for a JSON column. Anything else is a no-op.
                    if (
                        count($key) > 2
                        || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)*$/D', $col)
                    ) {
                        return $this;
                    }

                    // only these comparisons may come from a key; the rest of what the
                    // grammar accepts (regexp, rlike, bitwise, is...) is refused
                    if ($operator && ! in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'], true)) {
                        return $this;
                    }

                    // a hidden column (password, token...) must not be searchable: a
                    // `like` or `>=` against it would leak the value through the row set
                    $name = strtolower(head(explode('.', $col)));
                    if (in_array($name, array_map('strtolower', $this->getModel()->getHidden()), true)) {
                        return $this;
                    }

                    $coltype = $this->tableColumnType(head(explode('.', $col)));

                    // a sub-path only means something on a JSON column
                    if (str_contains($col, '.') && $coltype !== 'json') {
                        return $this;
                    }

                    if (in_array($coltype, ['date', 'datetime', 'timestamp'])) {
                        // an array or an enum has no meaning against a date column
                        if (is_array($value) || $value instanceof \BackedEnum) {
                            return $this;
                        }

                        $column = "$table.$col";

                        // whereDate() wraps the column as an identifier; never interpolate it
                        $compare = fn ($operator, $value) => $coltype === 'date'
                            ? $this->whereDate($column, $operator, $value)
                            : $this->where($column, $operator, $value);

                        $value = (string) $value;

                        if (str($value)->is('* to *') || str($value)->is('* to') || str($value)->is('to *')) {
                            $split = collect(explode('to', $value))
                                ->map(fn ($val) => str($val)->replace('to', ''))
                                ->map(fn ($val) => trim($val));

                            $from = $split->first();
                            $to = $split->count() > 1 ? $split->last() : null;

                            if ($from) $compare('>=', $from);
                            if ($to) $compare('<=', $to);
                        }
                        else if ($value) {
                            $compare($operator ?: '=', $value);
                        }
                    }
                    else if (($cast = data_get($this->getModel()->getCasts(), $col)) && enum_exists($cast)) {
                        $value = is_string($value) ? explode(',', $value) : \Illuminate\Support\Arr::wrap($value);

                        // every element must be a scalar or an enum; a nested array is no filter
                        if (collect($value)->contains(fn ($val) => ! is_scalar($val) && ! $val instanceof \BackedEnum)) {
                            return $this;
                        }

                        $value = collect($value)->map(fn ($val) => is_enum($val) ? $val->value : trim((string) $val))->filter();

                        if ($value->count()) {
                            if (in_array($operator, ['!=', '<>'])) $this->whereNotIn($col, $value->values()->all());
                            else $this->whereIn($col, $value->values()->all());
                        }
                    }
                    // if got column type, means the column exists
                    else if ($coltype) {
                        $col = $coltype === 'json'
                            ? $table.'.'.((string) str($col)->replace('.', '->'))
                            : "$table.$col";

                        if (in_array($operator, ['like', 'not like'], true)) {
                            // a pattern is a single string
                            if (is_array($value) || $value instanceof \BackedEnum) {
                                return $this;
                            }

                            $value = (string) $value;

                            if (str($value)->is('%*', '*%')) $this->where($col, $operator, $value);
                            else $this->where($col, $operator, "%$value%");
                        }
                        else if (is_array($value) && $value) {
                            if ($coltype === 'json') {
                                $this->whereJsonContains($col, $value);
                            }
                            // whereIn takes a flat list of scalars
                            else if (! collect($value)->contains(fn ($val) => ! is_scalar($val))) {
                                $this->whereIn($col, array_values($value));
                            }
                        }
                        else if (!is_array($value)) {
                            // The blank() guard above already dropped null / '' / [],
                            // so a scalar here is a real constraint even when it's
                            // PHP-falsy ('0' / 0 / false — e.g. an is_active toggle
                            // filtered to "inactive"). Re-testing `&& $value` here
                            // wrongly skipped those, contradicting the guard.
                            if ($value instanceof \BackedEnum) {
                                $value = $value->value;
                            }
                            else if ($value instanceof \Stringable) {
                                $value = (string) $value;
                            }

                            if ($operator) {
                                $this->where($col, $operator, $value);
                            }
                            else {
                                $this->where($col, $value);
                            }
                        }
                    }
                }
            }

            return $this;
        };
    }

    /**
     * Generate random code
     */
    public function randomCode()
    {
        return function ($length = 6, $column = 'code') {
            $code = null;
            $dup = true;
    
            while ($dup) {
                $code = str()->upper(str()->random($length));
                $dup = $this->where($column, $code)->exists();
            }
    
            return $code;
        };
    }

    /**
     * Breakdown the query by date
     * this function will automatically detect to breakdown the query by year, month or days
     */
    public function breakdown()
    {
        return function ($diff, $start, $dateColumn = 'date', $totalColumn = 'total') {
            $breakdown = collect();
        
            if ($diff['y'] > 1) {
                $grouped = DB::query()->fromSub($this, 'data')
                    ->selectRaw("
                        year(`$dateColumn`) AS `year`,
                        sum(`$totalColumn`) AS `total`
                    ")
                    ->groupBy('year')
                    ->get();

                foreach (range(0, $diff['y']) as $n) {
                    $carbon = $start->copy()->local()->addYears($n);
                    $label = $carbon->year;
                    $value = $grouped->where('year', $carbon->year)->first();
                    $breakdown->put($label, data_get($value, 'total', 0));
                }
            }
            else if ($diff['m'] > 1) {
                $grouped = DB::query()->fromSub($this, 'data')
                    ->selectRaw("
                        date_format(`$dateColumn`, \"%c\") AS `month`,
                        year(`$dateColumn`) AS `year`,
                        sum(`$totalColumn`) AS `total`
                    ")
                    ->groupBy(['month', 'year'])
                    ->get();

                foreach (range(0, $diff['m']) as $n) {
                    $carbon = $start->copy()->local()->addMonths($n);
                    $label = $carbon->shortEnglishMonth.' '.$carbon->format('y');
                    $value = $grouped->where('month', $carbon->month)->where('year', $carbon->year)->first();
                    $breakdown->put($label, data_get($value, 'total', 0));
                }
            }
            else {
                $grouped = DB::query()->fromSub($this, 'data')
                    ->selectRaw("
                        day(`$dateColumn`) as `day`,
                        date_format(`$dateColumn`, \"%c\") AS `month`,
                        sum(`$totalColumn`) as `total`
                    ")
                    ->groupBy(['day', 'month'])
                    ->get();

                foreach (range(0, $diff['d']) as $n) {
                    $carbon = $start->copy()->local()->addDays($n);
                    $label = $carbon->day;
                    $value = $grouped->where('month', $carbon->month)->where('day', $carbon->day)->first();
                    $breakdown->put($label, data_get($value, 'total', 0));
                }
            }

            return $breakdown;
        };
    }
}
