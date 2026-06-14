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
