<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Livewire\Livewire;

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

describe('selection state', function () {
    it('selectAllTableMatching sets the flag; reset clears both modes', function () {
        $test = Livewire::test(TableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->call('selectAllTableMatching');

        expect($test->get('_table.select_all'))->toBeTrue();

        $test->call('resetTableCheckboxes');

        expect($test->get('_table.select_all'))->toBeFalse()
            ->and($test->get('_table.checkboxes'))->toBe([]);
    });

    it('tableSelection targets the whole filtered query when select_all is on', function () {
        Item::factory()->count(5)->create();

        $test = Livewire::test(TableFixture::class)->call('selectAllTableMatching');

        expect($test->instance()->tableSelection()->count())->toBe(5);
    });

    it('tableSelection targets only the checked ids otherwise', function () {
        $ids = Item::factory()->count(5)->create()->take(2)->pluck('id')->all();

        $test = Livewire::test(TableFixture::class)->set('_table.checkboxes', $ids);

        expect($test->instance()->tableSelection()->count())->toBe(2);
    });
});

describe('checkbox markup', function () {
    it('tags each row checkbox with its id and honours select_all', function () {
        $html = renderBlade('<atom:table.cell checkbox="7" />');

        expect($html)
            ->toContain('data-checkbox-id="7"')
            ->toContain('select_all');
    });

    it('derives the header indicator from page ids, not the raw count', function () {
        $html = renderBlade('<atom:table.column checkbox>Select</atom:table.column>');

        expect($html)
            ->toContain('pageIds')
            ->toContain('allChecked')
            ->not->toContain('=== checkboxes.length'); // the old across-page-lying compare
    });

    it('shows a cross-page select-all button only when opted in', function () {
        $paginate = new LengthAwarePaginator([], 120, 50, 1);

        $with = renderBlade(
            '<atom:table :select-all="true" :paginate="$p"><x-slot:checked><button>Delete</button></x-slot:checked></atom:table>',
            ['p' => $paginate],
        );

        expect($with)
            ->toContain('data-atom-table-select-all')
            ->toContain('selectAllTableMatching')
            ->toContain('120')
            ->toContain('select_all ? 120');   // count reflects the matching total

        $without = renderBlade(
            '<atom:table :paginate="$p"><x-slot:checked><button>Delete</button></x-slot:checked></atom:table>',
            ['p' => $paginate],
        );

        expect($without)->not->toContain('selectAllTableMatching');
    });
});

describe('clear on trashed toggle', function () {
    it('clears both selection modes when the trashed view is toggled', function () {
        $test = Livewire::test(TableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->set('_table.select_all', true)
            ->set('_table.show_trashed', true);

        expect($test->get('_table.checkboxes'))->toBe([])
            ->and($test->get('_table.select_all'))->toBeFalse();
    });

    it('leaves the selection alone on an unrelated property update', function () {
        $test = Livewire::test(TableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->set('_table.sort.column', 'name');

        expect($test->get('_table.checkboxes'))->toBe([1, 2]);
    });
});

describe('clear on filter change (wiring)', function () {
    it('table root listens for table-filter:changed and clears when a selection exists', function () {
        $html = renderBlade('<atom:table><x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns></atom:table>');

        expect($html)
            ->toContain('table-filter:changed.window')
            ->toContain('resetTableCheckboxes');
    });

    it('select filter dispatches table-filter:changed on value change', function () {
        $html = renderBlade('<atom:select.filter wire:model.live="status" label="Status" :options="[]" />');

        expect($html)->toContain('table-filter:changed');
    });

    it('date range filter dispatches table-filter:changed on value change', function () {
        $html = renderBlade('<atom:date-picker.range wire:model.live="range" />');

        expect($html)->toContain('table-filter:changed');
    });

    it('search dispatches table-filter:changed on submit', function () {
        $html = renderBlade('<atom:table.search wire:model="q" />');

        expect($html)->toContain('table-filter:changed');
    });
});
