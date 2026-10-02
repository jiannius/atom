<?php

use Illuminate\Support\Facades\Blade;

it('renders a search input with the enter-to-search handler', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<atom:table.search wire:model="filters.search" />');

    expect($html)->toContain('data-atom-table-search')
        ->and($html)->toContain('keyup.enter')
        ->and($html)->toContain('filters.search');
});

// A bound search sits in the filter bar beside selects that chip, so it has to take
// part: report its text as a chip (on init, on Enter and on blur) and empty itself
// when "Clear all" or its own chip asks. Without a binding there is no key to chip.
describe('filter bar', function () {
    beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

    it('reports a chip and listens for do-clear when it is bound with wire:model', function () {
        $html = renderBlade('<atom:table.search wire:model="filters.search" placeholder="Search customers" />');

        $wrapper = domQuery($html, '//*[@data-atom-table-search]')[0];
        $input = domQuery($html, '//*[@data-atom-table-search]//input')[0];

        // the wrapper owns the chip: it reports on init and on blur, and clears on request
        expect($wrapper->getAttribute('x-data'))
            ->toContain('table-filter:set')
            ->toContain('filters.search')
            ->toContain('Search customers')
            ->and($wrapper->getAttribute('x-init'))->toContain('emit()')
            ->and($wrapper->getAttribute('x-on:change'))->toBe('emit()')
            ->and($wrapper->getAttribute('x-on:table-filter:do-clear.window'))
            ->toContain('filters.search')
            ->toContain('$wire.$refresh()')
            ->toContain("new Event('input'")
            ->toContain('table-filter:changed')
            // Enter reports the chip before it refreshes
            ->and($input->getAttribute('x-on:keyup.enter.prevent'))
            ->toStartWith('emit();')
            ->toContain('$wire.$refresh()');
    });

    it('takes its key from data-filter-key when there is no wire:model', function () {
        $wrapper = domQuery(
            renderBlade('<atom:table.search data-filter-key="filters.q" />'),
            '//*[@data-atom-table-search]',
        )[0];

        expect($wrapper->getAttribute('x-data'))->toContain('filters.q')
            ->and($wrapper->hasAttribute('x-on:table-filter:do-clear.window'))->toBeTrue();
    });

    it('stays a plain enter-to-refresh box without a binding', function () {
        $html = renderBlade('<atom:table.search />');

        $wrapper = domQuery($html, '//*[@data-atom-table-search]')[0];
        $input = domQuery($html, '//*[@data-atom-table-search]//input')[0];

        expect($wrapper->hasAttribute('x-data'))->toBeFalse()
            ->and($wrapper->hasAttribute('x-init'))->toBeFalse()
            ->and($wrapper->hasAttribute('x-on:table-filter:do-clear.window'))->toBeFalse()
            ->and($input->getAttribute('x-on:keyup.enter.prevent'))
            ->not->toContain('emit()')
            ->toContain('table-filter:changed')
            ->toContain('$wire.$refresh()')
            ->and($html)->not->toContain('table-filter:set');
    });
});

it('renders a scoped loading spinner that keeps rows visible', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<atom:table.search wire:model="filters.search" />');

    expect($html)->toContain('wire:loading')
        ->and($html)->toContain('wire:target="$refresh"')
        ->and($html)->toContain('data-atom-table-search');
});
