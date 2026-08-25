<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\Item;
use Jiannius\Atom\Tests\Fixtures\StickyTableFixture;
use Jiannius\Atom\Tests\Fixtures\TableFixture;
use Livewire\Livewire;

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/** The table root's table-filter:changed handler, on its own. */
function filterHandler(string $html): string
{
    return (string) str($html)->after('x-on:table-filter:changed.window="')->before('"');
}

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

        expect($html)->toContain('table-filter:changed.window');
        expect(filterHandler($html))->toContain('resetTableCheckboxes');
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

describe('show selected', function () {
    it('lists the selection instead of the filtered rows while on', function () {
        Item::factory()->count(3)->create(['status' => 'draft']);
        Item::factory()->count(2)->create(['status' => 'published']);
        $drafts = Item::where('status', 'draft')->pluck('id')->all();

        $test = Livewire::test(StickyTableFixture::class)
            ->set('filters.status', ['published'])       // hides every ticked row
            ->set('_table.checkboxes', $drafts)
            ->call('toggleTableShowSelected');

        expect($test->get('_table.show_selected'))->toBeTrue()
            ->and($test->instance()->tableRows()->count())->toBe(3);

        $test->call('toggleTableShowSelected');

        expect($test->instance()->tableRows()->count())->toBe(2);   // back to the filtered list
    });

    it('falls back to the filtered rows when the selection is emptied under it', function () {
        Item::factory()->count(5)->create();

        // the flag can outlive the ids: untick the last row and the bar (with its
        // toggle) disappears, so an empty table would have no way back
        $test = Livewire::test(StickyTableFixture::class)
            ->set('_table.checkboxes', [1])
            ->call('toggleTableShowSelected')
            ->set('_table.checkboxes', []);

        expect($test->instance()->tableRows()->count())->toBe(5);
    });

    it('clearing the selection also clears the flag', function () {
        $test = Livewire::test(StickyTableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->call('toggleTableShowSelected')
            ->call('resetTableCheckboxes');

        expect($test->get('_table.show_selected'))->toBeFalse();
    });

    it('lists the whole matching set when select-all is on', function () {
        Item::factory()->count(3)->create(['status' => 'draft']);
        Item::factory()->count(2)->create(['status' => 'published']);

        $test = Livewire::test(StickyTableFixture::class)
            ->set('filters.status', ['published'])
            ->call('selectAllTableMatching')
            ->call('toggleTableShowSelected');

        expect($test->instance()->tableRows()->count())->toBe(2);
    });
});

describe('sticky selection', function () {
    it('keeps the checked ids on a filter change, but still drops select_all', function () {
        $html = renderBlade('<atom:table :sticky-selection="true"><x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns></atom:table>');

        expect(filterHandler($html))
            ->toContain('clearTableSelectAll')
            ->not->toContain('resetTableCheckboxes');   // the ids survive the new result set
    });

    it('clearTableSelectAll drops the flag but keeps the ids', function () {
        $test = Livewire::test(StickyTableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->set('_table.select_all', true)
            ->call('clearTableSelectAll');

        expect($test->get('_table.select_all'))->toBeFalse()
            ->and($test->get('_table.checkboxes'))->toBe([1, 2]);
    });

    it('keeps the header on screen so the user can go on searching', function () {
        $header = '<x-slot:header><atom:table.search wire:model="q" /></x-slot:header>';

        // the checked bar normally takes the header's place — which would make the
        // tick, search, tick again flow impossible on the very table built for it
        expect(renderBlade('<atom:table :sticky-selection="true">'.$header.'</atom:table>'))
            ->toContain('<template x-if="true"');

        expect(renderBlade('<atom:table>'.$header.'</atom:table>'))
            ->toContain('<template x-if="!$wire._table.checkboxes.length"');
    });

    it('renders a persistent clear only when sticky', function () {
        $slot = '<x-slot:checked><button>Delete</button></x-slot:checked>';

        expect(renderBlade('<atom:table :sticky-selection="true">'.$slot.'</atom:table>'))
            ->toContain('data-atom-table-clear-selection');

        expect(renderBlade('<atom:table>'.$slot.'</atom:table>'))
            ->not->toContain('data-atom-table-clear-selection');
    });

    it('resolves checked ids against tableSelectionQuery, ignoring the live filters', function () {
        Item::factory()->count(3)->create(['status' => 'draft']);
        Item::factory()->count(2)->create(['status' => 'published']);
        $ids = Item::pluck('id')->all();

        $test = Livewire::test(StickyTableFixture::class)
            ->set('filters.status', ['published'])
            ->set('_table.checkboxes', $ids);

        expect($test->instance()->tableSelection()->count())->toBe(5);
    });

    it('narrows to the live filters by default, since tableSelectionQuery is tableQuery', function () {
        Item::factory()->count(3)->create(['status' => 'draft']);
        Item::factory()->count(2)->create(['status' => 'published']);
        $ids = Item::pluck('id')->all();

        $test = Livewire::test(TableFixture::class)
            ->set('filters.status', ['published'])
            ->set('_table.checkboxes', $ids);

        expect($test->instance()->tableSelectionQuery()->count())->toBe(2)
            ->and($test->instance()->tableSelection()->count())->toBe(2);
    });

    it('renders a show-selected toggle only when sticky', function () {
        $slot = '<x-slot:checked><button>Delete</button></x-slot:checked>';

        expect(renderBlade('<atom:table :sticky-selection="true">'.$slot.'</atom:table>'))
            ->toContain('data-atom-table-show-selected')
            ->toContain('toggleTableShowSelected');

        expect(renderBlade('<atom:table>'.$slot.'</atom:table>'))
            ->not->toContain('data-atom-table-show-selected');
    });

    it('still targets the whole filtered query in select-all mode', function () {
        Item::factory()->count(3)->create(['status' => 'draft']);
        Item::factory()->count(2)->create(['status' => 'published']);

        $test = Livewire::test(StickyTableFixture::class)
            ->set('filters.status', ['published'])
            ->call('selectAllTableMatching');

        expect($test->instance()->tableSelection()->count())->toBe(2);
    });
});
