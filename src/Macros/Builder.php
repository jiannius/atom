<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Facades\DB;

class Builder
{
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
            $config = app('livewire')->current()?->_table;
            $sortColumn = data_get($config, 'sort.column');
            $sortDirection = data_get($config, 'sort.direction') ?? 'asc';
            $showTrashed = data_get($config, 'show_trashed');
            $maxRows ??= data_get($config, 'max_rows') ?? 100;

            if ($sortColumn) $this->orderBy($sortColumn, $sortDirection);
            else if (!$this->query->orders) $this->latest('id');

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
            $table = $this->getModel()->getTable();
            $columns = cache()->remember('table_'.$table.'_columns', now()->addDays(7), fn() => DB::select("show columns from `$table`"));

            return collect($columns)->map(fn($val) => [
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
                $table = $this->getModel()->getTable();

                if ($key === 'search' && $this->hasNamedScope('search') && $value) {
                    $this->search($value);
                }
                else if ($key !== 'search' && ($scope = (string) str($key)->camel()) && $this->hasNamedScope($scope)) {
                    $this->$scope($value);
                }
                else {
                    $key = explode(':', $key);
                    $col = head($key);
                    $coltype = $this->tableColumnType(head(explode('.', $col)));
                    $operator = count($key) > 1 ? last($key) : null;

                    if (in_array($coltype, ['date', 'datetime', 'timestamp'])) {
                        $col = $coltype === 'date'
                            ? DB::raw("date($table.$col)")
                            : "$table.$col";

                        if (str($value)->is('* to *') || str($value)->is('* to') || str($value)->is('to *')) {
                            $split = collect(explode('to', $value))
                                ->map(fn ($val) => str($val)->replace('to', ''))
                                ->map(fn ($val) => trim($val));

                            $from = $split->first();
                            $to = $split->count() > 1 ? $split->last() : null;

                            if ($from) $this->where($col, '>=', $from);
                            if ($to) $this->where($col, '<=', $to);
                        }
                        else if ($value) {
                            if ($operator) {
                                $this->where($col, $operator, $value);
                            }
                            else {
                                $this->where($col, $value);
                            }
                        }
                    }
                    else if (($cast = data_get($this->getModel()->getCasts(), $col)) && enum_exists($cast)) {
                        $value = is_string($value) ? collect(explode(',', $value)) : collect($value);
                        $value = $value->map(fn ($val) => is_enum($val) ? $val->value : trim($val))->filter();

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

                        if ($operator === 'like') {
                            if (str($value)->is('%*', '*%')) $this->where($col, 'like', $value);
                            else $this->where($col, 'like', "%$value%");
                        }
                        else if (is_array($value) && $value) {
                            if ($coltype === 'json') $this->whereJsonContains($col, $value);
                            else $this->whereIn($col, $value);
                        }
                        else if (!is_array($value)) {
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
}
