<?php

namespace Jiannius\Atom\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportQueryString\BaseUrl;
use Livewire\WithPagination;
use LogicException;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Keep a listing's filters in the URL (`?status=APPROVED&search=foo`), so a record
 * opened from the listing can come back to it through the breadcrumb with the
 * filters it left. Opt-in: a component that does not use this trait declares no
 * query string and is unchanged.
 *
 * The host declares what may be filtered, once:
 *
 *   protected function urlFilters(): array
 *   {
 *       return ['search' => '', 'status' => [], 'region' => 'MY'];   // key => default
 *   }
 *
 * That declaration is also an allow-list, enforced twice — see mountWithUrlFilters()
 * for what arrives from the URL and updatingWithUrlFilters() for what arrives over
 * the wire. It does not replace `filter(Arr::only(...))` for a component that does
 * not use the trait.
 *
 * A filter's query key (with its prefix) must not be one something else writes to the
 * URL: `page` on a component that uses WithPagination, the host's own `queryString`,
 * other traits' entries and `#[Url]` properties are refused at mount. A custom
 * paginator page name (`->paginate(10, pageName: 'p')`) cannot be seen at mount,
 * because Livewire registers it lazily while rendering, so dehydrateWithUrlFilters()
 * refuses it at the end of the initial render, the only request whose `$paginators`
 * only the host can have filled.
 */
trait WithUrlFilters
{
    /**
     * The internal values a wholesale `$wire.set('filters', {...})` must not be able
     * to wipe, held between Livewire's updating and updated hooks.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $urlFiltersStash = null;

    /**
     * Whether this instance was mounted, which only the initial render does. Not
     * persisted: every Livewire update request hydrates a fresh instance, so it is
     * false there. Unlike the `X-Livewire` header it cannot be left off by a client.
     */
    protected bool $urlFiltersMounted = false;

    /**
     * The host-check failures already logged by this process ("component|key|exception"),
     * the fallback when the cache cannot be used to dedupe across processes.
     *
     * @var array<string, true>
     */
    protected static array $urlFilterReported = [];

    /**
     * The filters that live in the URL, as [key => default]. A key is a plain
     * identifier (it becomes the query key, so no dot, space or bracket), a default
     * is a scalar, null, or `[]` for a multi-select (Livewire merges lists by index,
     * so a non-empty list default cannot be cleared). Called before mount(), so it
     * must not depend on mount state.
     *
     * @return array<string, mixed>
     */
    abstract protected function urlFilters(): array;

    /**
     * Keys that stay in the filters property but are internal state (a tenant or
     * scope the host sets in mount()): never written to the URL, and never accepted
     * from the browser.
     *
     * @return list<string>
     */
    protected function urlFiltersExcept(): array
    {
        return [];
    }

    /**
     * The public property that holds the filters.
     */
    protected function urlFiltersProperty(): string
    {
        return 'filters';
    }

    /**
     * A prefix for the query keys, for a page with two listings that would otherwise
     * fight over `?status=`.
     */
    protected function urlFiltersPrefix(): string
    {
        return '';
    }

    /**
     * App-specific check on a value whose key and shape already passed (a date that
     * parses, a status that exists). Return false to refuse it.
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        return true;
    }

    /**
     * The longest string a filter value may be, in bytes. A URL can carry far more
     * than any filter needs, and the value is bound into a query.
     */
    protected function urlFilterMaxLength(): int
    {
        return 1000;
    }

    /**
     * The most items a list filter may hold: a `whereIn()` with thousands of
     * placeholders is an error on some databases.
     */
    protected function urlFilterMaxItems(): int
    {
        return 200;
    }

    /**
     * One query-string entry per declared key, in Livewire's `queryString{Trait}`
     * convention so it merges with the host's own (`tab`, pagination). `history`
     * is false (replace, do not push a history entry per keystroke) and `except`
     * is the default, so a filter that returns to its default leaves the URL.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function queryStringWithUrlFilters(): array
    {
        $entries = [];
        $taken = $this->urlFilterTakenQueryKeys();

        foreach ($this->urlFilterDefinitions() as $key => $default) {
            if (in_array($this->urlFiltersPrefix().$key, $taken, true)) {
                throw new LogicException(static::class.': the query key ['.$this->urlFiltersPrefix().$key.'] is already used (pagination or another query-string entry): give the filter another key or a urlFiltersPrefix().');
            }

            $entries[$this->urlFiltersProperty().'.'.$key] = [
                'as' => $this->urlFiltersPrefix().$key,
                'history' => false,
                'except' => $default,
            ];
        }

        return $entries;
    }

    /**
     * Clean the filters once the URL has been read into them and the host's own
     * mount() has run. Livewire writes a URL value with data_set(), which never
     * fires an updating hook, so this is the only gate a value planted in a shared
     * link passes through. It drops silently, never throws: a stale bookmark or a
     * link someone crafted should open the listing unfiltered, not break it.
     *
     * Keys that are neither declared nor excepted are dropped, a value whose shape
     * does not match its default (scalar versus list of scalars) or that the host
     * refuses falls back to the default, and a declared key that is missing is
     * filled with its default.
     *
     * Not a replacement for the filter() macro's own checks, which is what guards
     * the rest. Future: if sort is ever persisted in the URL, re-check
     * `_table.sort.column` here with TableSort::isUnsafeFromClient() — AtomComponent
     * only guards a browser update, and a URL value is not one.
     */
    public function mountWithUrlFilters(): void
    {
        $this->urlFiltersMounted = true;

        $property = $this->urlFiltersProperty();
        $declared = $this->urlFilterDefinitions();
        $internal = $this->urlFilterInternalKeys();
        $clean = [];

        foreach (is_array($this->{$property}) ? $this->{$property} : [] as $key => $value) {
            $key = (string) $key;

            if (in_array($key, $internal, true)) {
                $clean[$key] = $value;
            }
            else if (array_key_exists($key, $declared)) {
                $clean[$key] = $this->isUrlFilterAcceptable($key, $value) ? $value : $declared[$key];
            }
        }

        foreach ($declared as $key => $default) {
            if (! array_key_exists($key, $clean)) {
                $clean[$key] = $default;
            }
        }

        $this->{$property} = $clean;
    }

    /**
     * Refuse (403) a browser update that would leave an undeclared key, a value of
     * the wrong shape, or one the host refuses in the filters. Livewire fires this
     * once per dotted path a `$wire.set()` targets, so every shape is checked:
     * `filters` (the whole array, whose keys and values are all checked),
     * `filters.status` (one key) and `filters.status.0` or deeper (never needed, so
     * never allowed: an array is only ever replaced whole, and a deeper path is how
     * a string turns into an array).
     *
     * Refused rather than dropped, as the signed `raw:` sort is: a URL value is
     * somebody else's link and the page should still open, but a `$wire.set()` of a
     * key nobody declared is a deliberate request for something the host never
     * offered. It cannot be `#[Locked]`: the table controls bind
     * `wire:model="filters.*"`, and Livewire throws on any client update to a
     * locked property.
     */
    public function updatingWithUrlFilters($name, $value): void
    {
        $property = $this->urlFiltersProperty();

        if ($name === $property) {
            if (! $this->isUrlFilterSetAcceptable($value)) {
                abort(403, 'Invalid filters.');
            }

            // the browser replaces the array; what the host keeps in it must survive
            $this->urlFiltersStash = Arr::only(is_array($this->{$property}) ? $this->{$property} : [], $this->urlFilterInternalKeys());

            return;
        }

        if (! str_starts_with((string) $name, $property.'.')) {
            return;
        }

        $path = substr((string) $name, strlen($property) + 1);

        // The dot test is defence in depth: a declared key never holds a dot, so the
        // undeclared-key check refuses every deeper path on its own today, and no
        // test can reach this clause alone. It stays so that a future key that may
        // hold a dot cannot quietly open `filters.x.y`.
        if (str_contains($path, '.') || ! $this->isUrlFilterAcceptable($path, $value)) {
            abort(403, 'Invalid filters.');
        }
    }

    /**
     * After the browser replaced the whole array: put the internal state back, and
     * a declared key the replacement left out gets its default (`set('filters', [])`
     * must not leave a listing without a `search` or `status` key).
     */
    public function updatedWithUrlFilters($name, $value): void
    {
        $property = $this->urlFiltersProperty();

        if ($name === $property) {
            $this->{$property} = [
                ...$this->urlFilterDefinitions(),
                ...(is_array($this->{$property}) ? $this->{$property} : []),
                ...($this->urlFiltersStash ?? []),
            ];
        }

        $this->urlFiltersStash = null;
    }

    /**
     * Refuse a filter whose query key is also a paginator's page name. Livewire
     * registers a page name in `$paginators` lazily, when the paginator is built
     * (`->paginate(10, pageName: 'p')`, during render), so the mount check cannot see
     * it. At the end of the initial render it is there, so the check is made here: a
     * loud LogicException, like the mount one, rather than two entries fighting over
     * one query key (a filter value read as a page number, a `TypeError` on
     * `nextPage()`, the last writer winning the URL).
     *
     * Initial render only. `$paginators` is a public property, so on an update request
     * a client can put any name in it (`$wire.set('paginators.search', 3)`,
     * `$wire.gotoPage(2, 'region')`) and this would be a 500, reported on every
     * request, for something that was harmless before. A name a host registers only on
     * a later request is therefore not caught. A component that uses
     * WithoutUrlPagination is checked too: it writes no paginator to the URL, but
     * Livewire still reads the page from the query (`?search=ap` becomes page "ap").
     */
    public function dehydrateWithUrlFilters(): void
    {
        if (! $this->urlFiltersMounted) {
            return;
        }

        $paginators = property_exists($this, 'paginators') && is_array($this->paginators) ? array_keys($this->paginators) : [];

        if ($paginators === []) {
            return;
        }

        $prefix = $this->urlFiltersPrefix();

        foreach ($this->urlFilterDefinitions() as $key => $default) {
            if (in_array($prefix.$key, array_map('strval', $paginators), true)) {
                throw new LogicException(static::class.': the filter query key ['.$prefix.$key.'] is also a paginator page name: give the filter another key or a urlFiltersPrefix(), or the paginator another pageName.');
            }
        }
    }

    /**
     * The declared [key => default], with a key that cannot be a query key refused
     * loudly: it is a typo in the host, not input.
     *
     * @return array<string, mixed>
     */
    protected function urlFilterDefinitions(): array
    {
        $declared = [];

        foreach ($this->urlFilters() as $key => $default) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key)) {
                throw new LogicException(static::class.'::urlFilters() key ['.$key.'] must be a plain identifier: it becomes a query key.');
            }

            if (is_array($default) && $default !== []) {
                throw new LogicException(static::class.'::urlFilters() default for ['.$key.'] must be [] for a list: Livewire merges lists by index.');
            }

            if (! is_array($default) && $default !== null && ! is_scalar($default)) {
                throw new LogicException(static::class.'::urlFilters() default for ['.$key.'] must be a scalar, null or [].');
            }

            $declared[$key] = $default;
        }

        return $declared;
    }

    /**
     * The internal keys, as strings.
     *
     * @return list<string>
     */
    protected function urlFilterInternalKeys(): array
    {
        $internal = array_map('strval', $this->urlFiltersExcept());
        $declared = $this->urlFilterDefinitions();

        foreach ($internal as $key) {
            if ($key === '' || str_contains($key, '.') || array_key_exists($key, $declared)) {
                throw new LogicException(static::class.'::urlFiltersExcept() key ['.$key.'] must not be empty, hold a dot or also be declared in urlFilters().');
            }
        }

        return $internal;
    }

    /**
     * Whether a value is acceptable for a key: declared, the right shape for its
     * default, and approved by the host. An internal key is never acceptable here:
     * only the host sets it.
     */
    protected function isUrlFilterAcceptable(string $key, mixed $value): bool
    {
        $declared = $this->urlFilterDefinitions();

        return array_key_exists($key, $declared)
            && $this->urlFilterShapeMatches($declared[$key], $value)
            && $this->urlFilterHostAccepts($key, $value);
    }

    /**
     * The host's own check, which a crafted link can make throw (a date that does not
     * parse): a throw is a refusal, never a 500. It is also logged (never its message
     * or the value, both of which a crafted link controls), once an hour per component,
     * key and exception class, so a host bug (a TypeError) shows up in the logs.
     */
    protected function urlFilterHostAccepts(string $key, mixed $value): bool
    {
        try {
            return $this->isUrlFilterValid($key, $value);
        }
        catch (\Throwable $e) {
            $this->reportUrlFilterFailure($key, $e);

            return false;
        }
    }

    /**
     * Log a failure of isUrlFilterValid() with where it happened, not what it said:
     * the component, the key, the exception class and its file and line. The message
     * is left out because it usually quotes the value (Carbon's does), which is up to
     * 1000 bytes the visitor chose. Once an hour per component, key and class across
     * processes through the cache; if the cache throws, or cannot remember (the null
     * store: add() is false and has() is false too), once per process from a seen-set
     * capped at 100 and emptied when full, like the editor's refusal log.
     */
    protected function reportUrlFilterFailure(string $key, \Throwable $e): void
    {
        $id = static::class.'|'.$key.'|'.$e::class;

        $cacheKey = 'atom:url-filter-failure:'.md5($id);

        try {
            $first = Cache::add($cacheKey, 1, 3600);
            $remembers = $first || Cache::has($cacheKey);
        }
        catch (\Throwable) {
            $first = $remembers = false;
        }

        if (! $remembers) {
            $first = ! isset(static::$urlFilterReported[$id]);

            if (count(static::$urlFilterReported) >= 100) {
                static::$urlFilterReported = [];
            }

            static::$urlFilterReported[$id] = true;
        }

        if (! $first) {
            return;
        }

        Log::warning('isUrlFilterValid() threw and its value was refused.', [
            'component' => static::class,
            'key' => $key,
            'exception' => $e::class,
            'at' => $e->getFile().':'.$e->getLine(),
        ]);
    }

    /**
     * Whether a whole replacement array holds only declared keys with acceptable values.
     */
    protected function isUrlFilterSetAcceptable(mixed $filters): bool
    {
        if (! is_array($filters)) {
            return false;
        }

        foreach ($filters as $key => $value) {
            if (! $this->isUrlFilterAcceptable((string) $key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scalar (or null) for a scalar default; a list of scalars for a list default.
     * Anything keyed, nested or an object is the wrong shape for both. A string must
     * be valid UTF-8 (Livewire cannot serialise the snapshot otherwise: a 500) and
     * within urlFilterMaxLength(); a list within urlFilterMaxItems().
     */
    protected function urlFilterShapeMatches(mixed $default, mixed $value): bool
    {
        if (is_array($default)) {
            return is_array($value)
                && array_is_list($value)
                && count($value) <= $this->urlFilterMaxItems()
                && collect($value)->every(fn ($item) => $this->urlFilterScalarMatches($item));
        }

        return $this->urlFilterScalarMatches($value);
    }

    /**
     * Null, or a scalar that, when it is a string, is valid UTF-8 and not too long.
     */
    protected function urlFilterScalarMatches(mixed $value): bool
    {
        if (is_string($value)) {
            return strlen($value) <= $this->urlFilterMaxLength() && preg_match('//u', $value) === 1;
        }

        return $value === null || is_scalar($value);
    }

    /**
     * Every query key something else on the component already writes to: `page` when
     * the component uses WithPagination, WithoutUrlPagination or not (Livewire registers
     * it lazily, so it is not in any query-string entry yet, and still reads it from the
     * query), the host's own `queryString`, other traits' entries,
     * and `#[Url]` properties (a `tab` or any other key is one of these, so it is
     * found there, not reserved by name). A filter that took one would fight it over
     * the URL.
     *
     * @return list<string>
     */
    protected function urlFilterTakenQueryKeys(): array
    {
        $entries = [];

        if (method_exists($this, 'queryString')) {
            $entries[] = $this->queryString();
        }
        else if (property_exists($this, 'queryString')) {
            $entries[] = $this->queryString;
        }

        $traits = class_uses_recursive(static::class);

        foreach ($traits as $trait) {
            $member = 'queryString'.class_basename($trait);

            if ($trait !== __TRAIT__ && method_exists($this, $member)) {
                $entries[] = $this->{$member}();
            }
        }

        $taken = in_array(WithPagination::class, $traits, true) ? ['page'] : [];

        foreach ($entries as $entry) {
            foreach ((array) $entry as $key => $value) {
                $key = is_string($key) ? $key : $value;
                $taken[] = (string) (is_array($value) ? ($value['as'] ?? $key) : $key);
            }
        }

        foreach ((new \ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(BaseUrl::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $taken[] = (string) ($attribute->newInstance()->as ?? $property->getName());
            }
        }

        return $taken;
    }
}
