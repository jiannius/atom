<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Facades\DB;
use Jiannius\Atom\Services\DateRange;
use Jiannius\Atom\Services\TableSort;

class Builder
{
    /**
     * Where date between
     *
     * A `Y-m-d` end is a whole calendar day in the viewer's timezone. On an Eloquent builder, against a DATETIME or TIMESTAMP column of the
     * model's own table, that is `>=` the viewer's midnight of the first day and `<` the viewer's midnight after the last; anywhere else (a
     * query builder, a DATE column, another table's column, a database that cannot say what the column is) it is a date comparison, and an
     * end that is not a bare date is compared as it is.
     */
    public function whereDateBetween()
    {
        return function ($column, $range) {
            [$from, $to] = DateRange::split($range);

            $wholeDays = false;

            if (
                (DateRange::isDate($from) || DateRange::isDate($to))
                && $this instanceof \Illuminate\Database\Eloquent\Builder
                // `show columns` is MySQL's: on another driver no statement is sent (a failed one aborts a Postgres transaction)
                && in_array($this->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
                && is_string($column)
                && preg_match('/^(?:([A-Za-z_][A-Za-z0-9_]*)\.)?([A-Za-z_][A-Za-z0-9_]*)$/D', $column, $parts)
                && ($parts[1] === '' || $parts[1] === $this->getModel()->getTable())
            ) {
                try {
                    // asks the builder's own connection, as filter() does
                    $wholeDays = preg_match('/^(datetime|timestamp)(\(\d+\))?$/', strtolower((string) $this->tableColumnType($parts[2]))) === 1;
                }
                catch (\Throwable) {
                    // the column cannot be described: compare dates
                }
            }

            /* each end: its comparison, the whole-day comparison and the instant a whole-day end stands for */
            $ends = [
                [$from, '>=', '>=', fn ($day) => DateRange::dayStart($day)],
                [$to, '<=', '<', fn ($day) => DateRange::nextDayStart($day)],
            ];

            foreach ($ends as [$end, $operator, $wholeDayOperator, $instant]) {
                if (! $end) continue;

                if ($wholeDays && DateRange::isDate($end)) $this->where($column, $wholeDayOperator, $instant($end));
                else if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $this->whereDate($column, $operator, $end); // date in YYYY-MM-DD
                else $this->where($column, $operator, $end);
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
            // Only a component that uses WithTable has its _table trusted: one with a
            // public $_table of its own has no signed-sort gate (the update listener), so a
            // client could set an unsigned raw: column that would read as PHP-set here.
            $component = app('livewire')->current() ?: null;
            // inline, not is_using_trait(): that helper is guarded by function_exists(), so a host's own definition would decide this gate
            $config = $component && in_array(\Jiannius\Atom\Traits\WithTable::class, class_uses_recursive($component), true)
                ? $component->_table
                : null;

            // Defence in depth: the raw: sort gate (AtomServiceProvider's update listener) refuses a client
            // update that would leave these non-string, but $_table is a
            // plain public property PHP code can still set directly — a
            // non-string column means "no sort" rather than a str() crash,
            // and a non-string direction just falls back to 'asc'.
            $sortColumn = data_get($config, 'sort.column');
            $sortColumn = is_string($sortColumn) ? $sortColumn : null;

            $sortDirection = data_get($config, 'sort.direction');
            $sortDirection = is_string($sortDirection) ? $sortDirection : 'asc';

            // `_table.show_trashed` and `_table.max_rows` are as the client sent them unless the
            // update gate (AtomServiceProvider) refused it; a model without SoftDeletes has no
            // onlyTrashed() to call (a 500), and the page size is clamped to a sane whole number.
            $showTrashed = (bool) data_get($config, 'show_trashed')
                && $this instanceof \Illuminate\Database\Eloquent\Builder
                && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($this->getModel()), true);
            $maxRows ??= TableSort::rows(data_get($config, 'max_rows'));

            // a client-set plain sort column never reaches a hidden or non-visible column: `orderBy` on
            // a password or token column is a row-order oracle for its value (a `raw:` sort is signed
            // by the host's own column declaration and is not this)
            if (
                $sortColumn
                && !str_starts_with($sortColumn, 'raw:')
                && $this instanceof \Illuminate\Database\Eloquent\Builder
                && !TableSort::isSortable($this->getModel(), $sortColumn)
            ) {
                TableSort::noteDropped($this->getModel(), $sortColumn);
                $sortColumn = null;
            }

            if ($sortColumn) {
                // a user-chosen sort overrides any default ordering on the query —
                // appended after existing orderBys it would never win
                $this->reorder();

                if (str($sortColumn)->startsWith('raw:')) {
                    // A signed value came from the client — verify() strips the
                    // signature; an unsigned one can only have come from PHP
                    // (the raw: sort gate (AtomServiceProvider's update listener) refuses the
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
            // Cached per connection, database and prefixed table: the select goes
            // to the builder's own connection, not the default one, and a host may
            // point one connection name at a different database per tenant.
            $connection = $this->getQuery()->getConnection();
            $table = $connection->getTablePrefix().$this->getModel()->getTable();
            $key = 'table_'.$connection->getName().'_'.$connection->getDatabaseName().'_'.$table.'_columns';

            $columns = app(\Jiannius\Atom\Services\TableColumnsMemo::class)->remember($key, fn () => cache()->remember($key, now()->addDays(7), function () use ($connection, $table) {
                // `show columns` is MySQL's: another driver has nothing to ask, and a
                // failed statement aborts an open Postgres transaction
                if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                    return [];
                }

                $quoted = str_replace('`', '``', $table);

                return collect($connection->select("show columns from `$quoted`"))
                    ->map(fn ($row) => (array) $row)
                    ->all();
            }));

            return collect($columns)->map(fn($val) => [
                'name' => data_get($val, 'Field'),
                'type' => data_get($val, 'Type'),
            ])->values();
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
                // a Stringable, a date, an enum or an array, cannot mean anything: no-op.
                if (
                    (! is_string($key) && ! is_int($key))
                    || (! is_scalar($value) && ! is_array($value) && ! $value instanceof \BackedEnum && ! $value instanceof \DateTimeInterface && ! $value instanceof \Stringable)
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
                    // `like` or `>=` against it would leak the value through the row set.
                    // A model with $visible set exposes only those, so anything else is out.
                    $name = strtolower(head(explode('.', $col)));
                    $visible = array_map('strtolower', $this->getModel()->getVisible());

                    if (
                        in_array($name, array_map('strtolower', $this->getModel()->getHidden()), true)
                        || ($visible && ! in_array($name, $visible, true))
                    ) {
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

                        // a date object stays an object: whereDate() formats it, where() binds it
                        if (! $value instanceof \DateTimeInterface) {
                            $value = (string) $value;
                        }

                        if (is_string($value) && (str($value)->is('* to *') || str($value)->is('* to') || str($value)->is('to *'))) {
                            $split = collect(explode('to', $value))
                                ->map(fn ($val) => str($val)->replace('to', ''))
                                ->map(fn ($val) => trim($val));

                            $from = $split->first();
                            $to = $split->count() > 1 ? $split->last() : null;

                            // a bare date end is a whole day in the viewer's timezone; on a datetime column that is the half-open interval of
                            // instants from its start to the start of the next day. A date column and an instant end compare as they always did.
                            if ($from) {
                                if ($coltype !== 'date' && DateRange::isDate($from)) $this->where($column, '>=', DateRange::dayStart($from));
                                else $compare('>=', $from);
                            }

                            if ($to) {
                                if ($coltype !== 'date' && DateRange::isDate($to)) $this->where($column, '<', DateRange::nextDayStart($to));
                                else $compare('<=', $to);
                            }
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
                            if (is_array($value) || $value instanceof \BackedEnum || $value instanceof \DateTimeInterface) {
                                return $this;
                            }

                            $value = (string) $value;

                            if (str($value)->is('%*', '*%')) $this->where($col, $operator, $value);
                            else $this->where($col, $operator, "%$value%");
                        }
                        else if (is_array($value) && $value) {
                            // a list means "one of" (= or no operator) or "none of" (!= / <>);
                            // an ordering comparison against a list has no meaning
                            $negate = in_array($operator, ['!=', '<>'], true);

                            if ($operator && ! $negate && $operator !== '=') {
                                return $this;
                            }

                            if ($coltype === 'json') {
                                if ($negate) $this->whereJsonDoesntContain($col, $value);
                                else $this->whereJsonContains($col, $value);
                            }
                            // whereIn takes a flat list of scalars (or dates)
                            else if (! collect($value)->contains(fn ($val) => ! is_scalar($val) && ! $val instanceof \DateTimeInterface)) {
                                if ($negate) $this->whereNotIn($col, array_values($value));
                                else $this->whereIn($col, array_values($value));
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
                            else if ($value instanceof \Stringable && ! $value instanceof \DateTimeInterface) {
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
                    $carbon = carbon($start)->local()->addYears($n);
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
                    $carbon = carbon($start)->local()->addMonths($n);
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
                    $carbon = carbon($start)->local()->addDays($n);
                    $label = $carbon->day;
                    $value = $grouped->where('month', $carbon->month)->where('day', $carbon->day)->first();
                    $breakdown->put($label, data_get($value, 'total', 0));
                }
            }

            return $breakdown;
        };
    }
}
