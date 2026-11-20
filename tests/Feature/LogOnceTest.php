<?php

use Illuminate\Support\Facades\Cache;
use Jiannius\Atom\Services\LogOnce;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::forgetDriver();
    Cache::flush();
});

it('is first once, then not, for the same id', function () {
    $seen = [];

    expect(LogOnce::first('a', $seen))->toBeTrue()
        ->and(LogOnce::first('a', $seen))->toBeFalse()
        ->and(LogOnce::first('b', $seen))->toBeTrue();
});

it('empties a full set when it adds a new id', function () {
    $seen = array_fill_keys(array_map(fn ($i) => "id{$i}", range(1, 100)), true);

    expect(LogOnce::first('new', $seen))->toBeTrue()
        ->and($seen)->toBe(['new' => true]);
});

it('leaves a full set alone for an id it already knows', function () {
    $seen = array_fill_keys(array_map(fn ($i) => "id{$i}", range(1, 100)), true);

    expect(LogOnce::first('id50', $seen))->toBeFalse()
        ->and($seen)->toHaveCount(100);
});

it('dedupes through the cache when there is a key, once per ttl, whatever the seen-set holds', function () {
    $seen = [];

    expect(LogOnce::first('a', $seen, 'k'))->toBeTrue()
        ->and(LogOnce::first('a', $seen, 'k'))->toBeFalse()
        ->and($seen)->toBe([]);

    $this->travelTo(now()->addSeconds(3601));

    expect(LogOnce::first('a', $seen, 'k'))->toBeTrue();
});

it('falls back to the set when the cache throws', function () {
    Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));

    $seen = [];

    expect(LogOnce::first('a', $seen, 'k'))->toBeTrue()
        ->and(LogOnce::first('a', $seen, 'k'))->toBeFalse()
        ->and($seen)->toBe(['a' => true]);
});

it('falls back to the set on a store that cannot remember (null)', function () {
    config(['cache.default' => 'null', 'cache.stores.null' => ['driver' => 'null']]);
    Cache::forgetDriver();

    expect(Cache::add('probe', 1, 60))->toBeFalse()->and(Cache::has('probe'))->toBeFalse();

    $seen = [];

    expect(LogOnce::first('a', $seen, 'k'))->toBeTrue()
        ->and(LogOnce::first('a', $seen, 'k'))->toBeFalse()
        ->and($seen)->toBe(['a' => true]);
});
