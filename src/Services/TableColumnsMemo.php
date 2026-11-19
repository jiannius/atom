<?php

namespace Jiannius\Atom\Services;

/**
 * The `show columns` rows `tableColumns()` already read this request, by cache key.
 *
 * Scoped (`AtomServiceProvider`), not a static: a macro closure is rebound on every call, which copies its statics, and a class static would outlive
 * an Octane request and a test's application, so a cache entry changed in between would never be seen.
 * A process that changes the schema and then filters in the same scope (a looping CLI command) must call `app()->forgetScopedInstances()`, and forget the cache entry, to see the new columns.
 */
class TableColumnsMemo
{
    /** @var array<string, array<int, mixed>> */
    protected array $columns = [];

    /**
     * The rows held under a cache key, read through `$resolve` the first time.
     *
     * @param  callable(): array<int, mixed>  $resolve
     * @return array<int, mixed>
     */
    public function remember(string $key, callable $resolve): array
    {
        return $this->columns[$key] ??= $resolve();
    }
}
