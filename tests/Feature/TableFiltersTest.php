<?php

use Illuminate\Support\Facades\Blade;

it('renders the filter bar with the chips listener hook', function () {
    $html = Blade::render('<atom:table.filters><div>control</div></atom:table.filters>');

    expect($html)->toContain('data-atom-table-filters')
        ->and($html)->toContain('table-filter:set')
        ->and($html)->toContain('Clear all');
});

it('renders the overflow popover by default', function () {
    $html = renderBlade('<atom:table.filters>main<x-slot:more><div data-field>x</div></x-slot></atom:table.filters>');

    expect($html)->toContain('More filters')
        // the slot rides in the popover menu
        ->and((string) str($html)->after('data-atom-menu'))->toContain('data-field');
});

it('renders an expandable card when overflow=card', function () {
    $html = renderBlade('<atom:table.filters overflow="card">main<x-slot:more><div data-field>x</div></x-slot></atom:table.filters>');

    expect($html)->toContain('expanded = !expanded')
        ->and((string) str($html)->after('x-show="expanded"'))->toContain('data-field');
});

it('renders a form-shaped modal when overflow=modal', function () {
    // the slot has to come last and close with </x-slot>: Blade::render leaves a
    // </x-slot:more> close, or one followed by more content, uncompiled — the
    // slot's content then leaks into the parent's default slot instead
    $html = renderBlade('<atom:table.filters overflow="modal">CONTROL<x-slot:more><div data-field>x</div></x-slot></atom:table.filters>');

    expect($html)->toContain('data-atom-modal-trigger')
        ->and($html)->toContain('<dialog');

    // the slot belongs to the modal, laid out one field per row
    $dialog = (string) str($html)->after('<dialog');

    expect($dialog)->toContain('grid-cols-1')
        ->and($dialog)->toContain('data-field')
        ->and($dialog)->toContain('More filters');
});

it('names the overflow modal apart from the component its own modals default to', function () {
    $component = new class
    {
        /** The Livewire component name a bare <atom:modal> would take. */
        public function getName(): string
        {
            return 'invoice-table';
        }
    };

    $html = withLivewireContext($component, fn () => renderBlade(
        '<atom:table.filters overflow="modal">CONTROL<x-slot:more><div>x</div></x-slot></atom:table.filters>'
    ));

    expect($html)->toContain('invoice-table-table-filters')
        // a bare name would open the host's own modal along with this one
        ->and($html)->not->toContain('"invoice-table"');
});

/**
 * The markup that immediately follows the last filter control in the bar. The
 * overflow button is the row's own last item, so what follows a control is the
 * button's opening tag: a wrapper around the controls (the `grow` div this
 * replaced) closes there instead, which puts the button at the far end of the
 * bar, among the table's actions.
 */
function markupAfterLastControl(string $overflow = ''): string
{
    $html = renderBlade('<atom:table.filters '.$overflow.'>CONTROL<x-slot:more><div>x</div></x-slot></atom:table.filters>');

    return ltrim((string) str($html)->after('CONTROL'));
}

it('puts the overflow button in the filter row, right after the controls', function (string $overflow) {
    expect(markupAfterLastControl($overflow))->toStartWith('<')
        ->and(markupAfterLastControl($overflow))->not->toStartWith('</');
})->with([
    'popover' => '',
    'card' => 'overflow="card"',
    'modal' => 'overflow="modal"',
]);

it('does not register a select that has no wire:model', function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<atom:select variant="filter" label="Status" :options="[]" />');
    expect($html)->not->toContain('table-filter:set');
});

// The inline chips and the add-filter pill.
describe('inline chips', function () {
    beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

    it('keeps the chips on a row of their own by default, and says so', function () {
        $html = renderBlade('<atom:table.filters>CONTROL</atom:table.filters>');
        $bar = domQuery($html, '//*[@data-atom-table-filters]')[0];

        expect($bar->getAttribute('data-chips'))->toBe('below')
            ->and(domClasses($bar))->toContain('space-y-3')->not->toContain('flex')
            ->and(domQuery($html, '//*[@data-atom-table-filters]/div[@x-show="active.length"]//template'))->toHaveCount(1);
    });

    it('puts the controls and the chips in one wrapping row with chips="inline"', function () {
        $html = renderBlade('<atom:table.filters chips="inline">CONTROL</atom:table.filters>');
        $bar = domQuery($html, '//*[@data-atom-table-filters]')[0];

        expect($bar->getAttribute('data-chips'))->toBe('inline')
            ->and(domClasses($bar))->toContain('flex', 'flex-wrap', 'items-center')->not->toContain('space-y-3')
            // the controls are not boxed: they take part in the bar's own row
            ->and(domClasses(domQuery($html, '//*[@data-atom-table-filters]/div[1]')[0]))->toContain('contents')
            // the chips are the bar's direct children, with no row of their own, and Clear all follows them
            ->and(domQuery($html, '//*[@data-atom-table-filters]/template'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-table-filters]/div[@x-show="active.length"]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-table-filters]/button[@x-show="active.length"]'))->toHaveCount(1);
    });

    it('prints a chip as a name, a muted operator and the value, with a named x', function () {
        $html = renderBlade('<atom:table.filters chips="inline">CONTROL</atom:table.filters>');
        $chip = domQuery($html, '//template//*[@data-atom-table-filter-chip]')[0];

        expect($chip->ownerDocument->saveHTML($chip))->toContain('x-text="chip.label"')->toContain('x-text="chip.operator"')->toContain('x-text="chip.display"')
            ->and($html)->toContain('Remove filter')
            // an event that names no operator reads "is"
            ->and($html)->toContain("operator ?? 'is'");
    });

    it('lets the search name its chip as "Search: foo"', function () {
        $html = renderBlade('<atom:table.search wire:model="filters.search" placeholder="Search number" />');

        expect($html)->toContain("operator: ':'");
    });

    it('lays the overflow card out as a row of its own in the inline bar', function () {
        $html = renderBlade('<atom:table.filters chips="inline" overflow="card">CONTROL<x-slot:more><div>x</div></x-slot></atom:table.filters>');

        expect(domClasses(domQuery($html, '//*[@x-show="expanded"]')[0]))->toContain('w-full');
    });
});

describe('the add-filter pill', function () {
    beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

    $select = fn (string $extra = '') => renderBlade('<atom:select variant="filter" data-filter-key="filters.customer" label="Customer" '.$extra.' :options="[[\'value\' => \'a\', \'label\' => \'A\']]" />');

    it('draws a select filter as a dashed "+ Label" pill that hides while it has a value', function () use ($select) {
        $html = $select('add');
        $trigger = domQuery($html, '//button[@data-atom-select-combobox]')[0];

        expect(domClasses($trigger))->toContain('border-dashed', 'rounded-pill', 'h-[26px]')
            ->and(trim(domQuery($html, '//*[@data-atom-select-add-label]')[0]->textContent))->toBe('Customer')
            ->and(domQuery($html, '//button[@data-atom-select-combobox]//*[@data-atom-icon]'))->not->toBeEmpty()
            // no chevron and no chosen-value chip inside the pill: the table filter's chip carries the value
            ->and(domQuery($html, '//button[@data-atom-select-combobox]//template'))->toBeEmpty()
            ->and(domQuery($html, '//*[@x-show="isEmpty"]'))->toHaveCount(1);
    });

    it('keeps a multiple add pill on screen, to add more values', function () use ($select) {
        expect(domQuery($select('add multiple'), '//*[@x-show="isEmpty"]'))->toBeEmpty();
    });

    it('is the plain bordered filter control without add', function () use ($select) {
        $html = $select();

        expect(domClasses(domQuery($html, '//button[@data-atom-select-combobox]')[0]))->not->toContain('border-dashed')
            ->and(domQuery($html, '//*[@data-atom-select-add-label]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@x-show="isEmpty"]'))->toBeEmpty();
    });

    it('draws a date range as a dashed pill reading "+ Label" that hides once a range is set', function () {
        $html = renderBlade('<atom:date-picker variant="range" add data-filter-key="filters.issued" placeholder="Issued" />');
        $input = domQuery($html, '//input[@type="text"]')[0];

        expect($input->getAttribute('placeholder'))->toBe('+ Issued')
            ->and(domClasses($input))->toContain('border-dashed', 'rounded-pill', 'h-[26px]')
            ->and(domQuery($html, '//*[@data-atom-date-range][@x-show="!dateRangeValue"]'))->toHaveCount(1)
            // the clear and calendar icons are not drawn on the pill
            ->and(domQuery($html, '//button[@aria-label="Open calendar"]'))->toBeEmpty()
            // and it registers its chip like any filter
            ->and($html)->toContain('table-filter:set');
    });

    it('is the plain range field without add', function () {
        $html = renderBlade('<atom:date-picker variant="range" wire:model="filters.issued" placeholder="Issued" />');

        expect(domQuery($html, '//input[@type="text"]')[0]->getAttribute('placeholder'))->toBe('Issued')
            ->and(domQuery($html, '//button[@aria-label="Open calendar"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-date-range][@x-show]'))->toBeEmpty();
    });
});
