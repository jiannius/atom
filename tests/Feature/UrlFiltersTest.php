<?php

use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Jiannius\Atom\Tests\Fixtures\UrlFiltersFixture;
use Jiannius\Atom\Tests\Fixtures\UrlFiltersMountDefaultFixture;
use Jiannius\Atom\Tests\Fixtures\UrlFiltersItemFixture;
use Jiannius\Atom\Tests\Fixtures\UrlFiltersStrayFixture;
use Jiannius\Atom\Traits\AtomComponent;
use Jiannius\Atom\Traits\WithUrlFilters;
use Livewire\Livewire;

/**
 * Issue #86 — the opt-in WithUrlFilters trait.
 *
 * The declared keys are an allow-list, enforced where a value arrives: from the URL
 * (silently, in mountWithUrlFilters(), the only gate a link someone crafted passes)
 * and over the wire (a 403 from updatingWithUrlFilters(), the way the signed raw:
 * sort is refused). filter() hardening (v3.35.4) is what guards a component that
 * does not use the trait; the two layers are independent.
 */

/** Mount the fixture with a query string, as a visit to /listing?… does. */
function urlFiltersMounted(array $query, string $class = UrlFiltersFixture::class)
{
    return Livewire::withQueryParams($query)->test($class);
}

describe('seeding from the URL', function () {
    it('reads each declared key into the filters, flat', function () {
        $test = urlFiltersMounted(['search' => 'ap', 'status' => ['fresh', 'dried'], 'region' => 'SG']);

        expect($test->get('filters'))->toBe(['search' => 'ap', 'status' => ['fresh', 'dried'], 'region' => 'SG', 'tenant' => 7]);
    });

    it('keeps "All" (an empty value) rather than falling back to the default', function () {
        expect(urlFiltersMounted(['region' => ''])->get('filters.region'))->toBe('');
    });

    it('never reads a key that is not declared', function () {
        $test = urlFiltersMounted(['evil' => 'x', 'tenant' => '9', 'filters' => ['tenant' => '9', 'evil' => 'x']]);

        expect($test->get('filters'))->toBe(['search' => '', 'status' => [], 'region' => 'MY', 'tenant' => 7]);
    });

    it('drops a key left in the filters that is neither declared nor internal, at mount', function () {
        $filters = urlFiltersMounted([], UrlFiltersStrayFixture::class)->get('filters');

        expect($filters)->not->toHaveKey('stray');
        expect($filters['tenant'])->toBe(7);   // internal state is kept
    });

    it('falls back to the default for a value the wrong shape for it', function (array $query, string $key, mixed $default) {
        expect(urlFiltersMounted($query)->get('filters.'.$key))->toBe($default);
    })->with([
        'scalar where a list is expected' => [['status' => 'fresh'], 'status', []],
        'a keyed array where a list is expected' => [['status' => ['a' => 'fresh']], 'status', []],
        'a nested array in the list' => [['status' => [['fresh']]], 'status', []],
        'a list where a scalar is expected' => [['search' => ['a', 'b']], 'search', ''],
        'a keyed array where a scalar is expected' => [['search' => ['a' => 'b']], 'search', ''],
        'a json object where a scalar is expected' => [['region' => '{"a":1}'], 'region', 'MY'],
    ]);

    it('falls back to the default for a value the host refuses', function () {
        expect(urlFiltersMounted(['region' => 'XX'])->get('filters.region'))->toBe('MY');
        expect(urlFiltersMounted([], UrlFiltersStrayFixture::class)->get('filters.region'))->toBe('MY');
    });

    it('still renders the listing for a hostile link, with the tenant scope intact', function () {
        $test = urlFiltersMounted(['tenant' => '9', 'status' => 'x', 'search' => ['a'], 'evil' => '1']);

        $test->assertOk();
        expect(collect($test->instance()->rows)->pluck('name')->all())->not->toContain('Secret');
    });
});

describe('a default computed in mount()', function () {
    it('is read after the URL, so ??= fills it only when the URL chose nothing', function () {
        expect(urlFiltersMounted([], UrlFiltersMountDefaultFixture::class)->get('filters.search'))->toBe('this-month');
        expect(urlFiltersMounted(['search' => 'x'], UrlFiltersMountDefaultFixture::class)->get('filters.search'))->toBe('x');
    });

    it('does not mistake an explicit "All" for no choice', function () {
        expect(urlFiltersMounted(['search' => ''], UrlFiltersMountDefaultFixture::class)->get('filters.search'))->toBe('');
    });
});

describe('what goes in the URL', function () {
    it('declares one flat, replace-not-push entry per key, with the default as `except`', function () {
        $url = urlFiltersMounted([])->effects['url'];

        expect(array_keys($url))->toBe(['filters.search', 'filters.status', 'filters.region']);
        expect($url['filters.search'])->toBe(['as' => 'search', 'use' => 'replace', 'alwaysShow' => false, 'except' => '']);
        expect($url['filters.status'])->toMatchArray(['as' => 'status', 'use' => 'replace', 'except' => []]);
        expect($url['filters.region'])->toMatchArray(['as' => 'region', 'use' => 'replace', 'except' => 'MY']);
    });

    it('never puts internal state in the URL', function () {
        expect(urlFiltersMounted([])->effects['url'])->not->toHaveKey('filters.tenant');
    });
});

describe('updates from the browser', function () {
    it('lets a declared key through, one at a time or all together', function () {
        $test = urlFiltersMounted([])
            ->set('filters.search', 'ap')
            ->set('filters.status', ['fresh'])
            ->set('filters.region', '')
            ->assertOk();

        expect($test->get('filters'))->toBe(['search' => 'ap', 'status' => ['fresh'], 'region' => '', 'tenant' => 7]);

        $test->set('filters', ['search' => 'b', 'status' => [], 'region' => 'SG'])->assertOk();

        expect($test->get('filters'))->toBe(['search' => 'b', 'status' => [], 'region' => 'SG', 'tenant' => 7]);   // tenant survived
    });

    it('refuses a key nobody declared, at every path shape', function (string $path, mixed $value) {
        urlFiltersMounted([])->set($path, $value)->assertForbidden();
    })->with([
        'a new key' => ['filters.evil', 'x'],
        'a new key holding an array' => ['filters.evil', ['a' => 'b']],
        'a column with an operator' => ['filters.amount:>=', 1],
        'a deeper path under a new key' => ['filters.evil.deeper', 'x'],
        'the whole array with a new key' => ['filters', ['evil' => 'x']],
        'the whole array, declared keys beside a new one' => ['filters', ['search' => 'a', 'evil' => 'x']],
        'the whole array with a numeric key' => ['filters', ['a', 'b']],
    ]);

    it('refuses internal state', function (string $path, mixed $value) {
        urlFiltersMounted([])->set($path, $value)->assertForbidden();
    })->with([
        'the key' => ['filters.tenant', 9],
        'inside the whole array' => ['filters', ['tenant' => 9]],
        'inside the whole array, beside declared keys' => ['filters', ['search' => 'a', 'tenant' => 9]],
    ]);

    it('refuses a value of the wrong shape, at every path shape', function (string $path, mixed $value) {
        urlFiltersMounted([])->set($path, $value)->assertForbidden();
    })->with([
        'scalar for a list' => ['filters.status', 'fresh'],
        'keyed array for a list' => ['filters.status', ['a' => 'fresh']],
        'nested array in a list' => ['filters.status', [['fresh']]],
        'list for a scalar' => ['filters.search', ['a']],
        'an array at the parent path, for a scalar' => ['filters', ['search' => ['a']]],
        'an array at the parent path, nested in a list' => ['filters', ['status' => [['a']]]],
        'a deeper path into a list' => ['filters.status.0', 'fresh'],
        'a deeper path into a scalar, turning it into an array' => ['filters.search.0', 'a'],
        'a deeper path that is itself an array' => ['filters.search.a', ['b']],
        'something that is not an array, over the whole property' => ['filters', 'x'],
    ]);

    it('refuses a value the host refuses', function () {
        urlFiltersMounted([])->set('filters.region', 'XX')->assertForbidden();
        urlFiltersMounted([])->set('filters', ['region' => 'XX'])->assertForbidden();
    });
});

describe('the reach of filter() behind the allow-list', function () {
    beforeEach(function () {
        Item::factory()->create(['name' => 'apple', 'status' => 'draft', 'amount' => 5]);
        Item::factory()->create(['name' => 'pear', 'status' => 'published', 'amount' => 50]);
    });

    it('does not let a named scope that was not declared be driven', function () {
        // `status` is a scope on Item, so filter() would call it with the client's value
        urlFiltersMounted([], UrlFiltersItemFixture::class)->set('filters.status', 'published')->assertForbidden();
        urlFiltersMounted([], UrlFiltersItemFixture::class)->set('filters', ['status' => 'published'])->assertForbidden();
    });

    it('does not let a plain column that was not declared be probed', function () {
        foreach (['filters.amount:>=', 'filters.amount', 'filters.name:like'] as $path) {
            urlFiltersMounted([], UrlFiltersItemFixture::class)->set($path, '1')->assertForbidden();
        }
    });

    it('leaves the query as the declared filters make it', function () {
        $test = urlFiltersMounted(['status' => 'published', 'amount:>=' => '10', 'search' => 'pea'], UrlFiltersItemFixture::class);

        expect($test->get('filters'))->toBe(['search' => 'pea']);
        expect(withLivewireContext($test->instance(), fn ($c) => $c->tableQuery()->pluck('name')->all()))->toBe(['pear']);
    });
});

describe('a component that does not use the trait', function () {
    it('is unchanged: no query string, no gate', function () {
        $test = Livewire::withQueryParams(['search' => 'x', 'status' => ['a']])->test(TableFixture::class);

        // pagination's `page` is the only thing in the query string
        expect(array_keys($test->effects['url'] ?? []))->not->toContain('filters.search', 'filters.status');
        expect($test->get('filters'))->toBe(['search' => null, 'status' => []]);

        // blank values, so filter() never looks the key up (sqlite has no `show columns`)
        $test->set('filters.anything', '')->assertOk();
        $test->set('filters', ['whatever' => '', 'status' => []])->assertOk();

        expect($test->get('filters'))->toBe(['whatever' => '', 'status' => []]);
    });

    it('does not carry the trait', function () {
        expect(class_uses_recursive(TableFixture::class))->not->toHaveKey('Jiannius\Atom\Traits\WithUrlFilters');
    });
});

describe('the names the trait occupies', function () {
    it('exposes only its three lifecycle hooks; every declaration hook is protected', function () {
        $reflection = new ReflectionClass(WithUrlFilters::class);

        $public = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))->map->getName()->sort()->values()->all();

        expect($public)->toBe(['mountWithUrlFilters', 'updatedWithUrlFilters', 'updatingWithUrlFilters']);

        // a host component sees one namespace: these are what it must not shadow by accident
        foreach (['urlFilters', 'urlFiltersExcept', 'urlFiltersProperty', 'urlFiltersPrefix', 'isUrlFilterValid'] as $hook) {
            expect($reflection->getMethod($hook)->isProtected())->toBeTrue("{$hook}() must be protected");
        }
    });

    it('claims no name a host is likely to want, and keeps AtomComponent off the opt-in', function () {
        expect(class_uses_recursive(AtomComponent::class))->not->toHaveKey(WithUrlFilters::class);

        foreach (['items', 'rows', 'filters', 'query'] as $name) {
            expect((new ReflectionClass(WithUrlFilters::class))->hasProperty($name))->toBeFalse();
        }
    });

    it('refuses a declaration that cannot be a query key or cannot be cleared', function (array $declared, string $message) {
        $component = new class($declared) extends UrlFiltersFixture {
            public function __construct(public array $declared = []) {}

            protected function urlFilters(): array
            {
                return $this->declared;
            }

            public function definitions(): array
            {
                return $this->urlFilterDefinitions();
            }
        };

        expect(fn () => $component->definitions())->toThrow(LogicException::class, $message);
    })->with([
        'a dotted key' => [['a.b' => ''], 'plain identifier'],
        'a key with a bracket' => [['a[]' => ''], 'plain identifier'],
        'a non-empty list default' => [['status' => ['A']], 'must be []'],
        'an object default' => [['status' => new stdClass], 'scalar, null or []'],
    ]);
});
