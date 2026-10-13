<?php

namespace Jiannius\Atom\Traits;

use Illuminate\Support\Arr;
use LogicException;

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

        foreach ($this->urlFilterDefinitions() as $key => $default) {
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

        if (str_contains($path, '.') || ! $this->isUrlFilterAcceptable($path, $value)) {
            abort(403, 'Invalid filters.');
        }
    }

    /**
     * Put the internal state back after the browser replaced the whole array.
     */
    public function updatedWithUrlFilters($name, $value): void
    {
        $property = $this->urlFiltersProperty();

        if ($name === $property && $this->urlFiltersStash !== null) {
            $this->{$property} = [...(is_array($this->{$property}) ? $this->{$property} : []), ...$this->urlFiltersStash];
        }

        $this->urlFiltersStash = null;
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
            && $this->isUrlFilterValid($key, $value);
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
     * Anything keyed, nested or an object is the wrong shape for both.
     */
    protected function urlFilterShapeMatches(mixed $default, mixed $value): bool
    {
        if (is_array($default)) {
            return is_array($value)
                && array_is_list($value)
                && collect($value)->every(fn ($item) => is_scalar($item));
        }

        return $value === null || is_scalar($value);
    }
}
