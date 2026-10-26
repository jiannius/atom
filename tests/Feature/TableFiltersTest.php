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
            // the active filters LEAD the row: the chips come before the controls that are not set
            ->and(domQuery($html, '//*[@data-atom-table-filters]/template/following-sibling::div[1][contains(@class, "contents")]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-table-filters]/div[@x-show="active.length"]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-table-filters]/button[@x-show="active.length"]'))->toHaveCount(1);
    });

    it('keeps a search that sits inside the filters first, with the chips and Clear all ordered after it', function () {
        $html = renderBlade('<atom:table.filters chips="inline"><atom:table.search data-filter-key="filters.q" placeholder="Find"/></atom:table.filters>');
        $bar = domQuery($html, '//*[@data-atom-table-filters]')[0];
        $chip = domQuery($html, '//template//*[@data-atom-table-filter-chip]')[0];

        // the controls are ordered after the chips, a search in the slot before them, and Clear all last
        expect(domClasses($bar))->toContain('[&>.contents>*]:order-2', '[&>.contents>[data-atom-table-search]]:order-0')
            ->and(domClasses($chip))->toContain('order-1')
            ->and(domClasses(domQuery($html, '//*[@data-atom-table-filters]/button[@x-show="active.length"]')[0]))->toContain('order-3')
            ->and(domQuery($html, '//*[@data-atom-table-filters]/div[contains(@class, "contents")]/*[@data-atom-table-search]'))->toHaveCount(1);
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
            // it hides while it has a value unless it draws its own chip (select.js `selfChip`, known only in the browser)
            ->and(domQuery($html, '//*[@x-show="selfChip || isEmpty"]'))->toHaveCount(1);
    });

    it('keeps a multiple add pill on screen, to add more values', function () use ($select) {
        expect(domQuery($select('add multiple'), '//*[@x-show="selfChip || isEmpty"]'))->toBeEmpty()
            ->and(domQuery($select('add multiple'), '//*[@x-show="isEmpty"]'))->toBeEmpty();
    });

    it('is also the chip, one button: the name, a muted operator, the value and a chevron, with an x beside it', function () use ($select) {
        $html = $select('add');
        $body = domQuery($html, '//button[@data-atom-filter-chip-body]');
        $x = domQuery($html, '//button[@data-atom-filter-chip-clear]');

        expect($body)->toHaveCount(1)
            // the pill and the chip are one trigger: the dropdown's own, first in the root
            ->and(domQuery($html, '//*[@data-atom-dropdown]/button[1][@data-atom-filter-chip-body]'))->toHaveCount(1)
            ->and($body[0]->getAttribute('x-bind:data-chip'))->toContain('chipState')
            ->and($body[0]->getAttribute('aria-haspopup'))->toBe('listbox')
            // the chip look is a set of data-chip overrides on the pill, so the SSR button is the pill
            ->and(domClasses($body[0]))->toContain('border-dashed', '[&[data-chip]]:bg-neutral-bg', '[&[data-chip]]:rounded-e-none')
            ->and(domQuery($html, '//button[@data-atom-filter-chip-body]//*[@x-text="chipOperator"]'))->toHaveCount(1)
            ->and(domQuery($html, '//button[@data-atom-filter-chip-body]//*[@x-text="filterDisplay"]'))->toHaveCount(1)
            // a chevron at size-3, muted
            ->and(domClasses(domQuery($html, '//button[@data-atom-filter-chip-body]//*[@data-atom-icon][last()]')[0]))->toContain('size-3', 'text-fg-muted')
            ->and($x)->toHaveCount(1)
            ->and($x[0]->getAttribute('x-bind:aria-label'))->toContain('Remove filter')
            // Enter on the x clears; the select's Enter would open the list
            ->and($x[0]->getAttribute('x-on:keydown.enter.stop'))->not->toBeNull()
            // the x sits after the body, outside the menu
            ->and(domQuery($html, '//button[@data-atom-filter-chip-body]/following-sibling::button[@data-atom-filter-chip-clear]'))->toHaveCount(1)
            // the control marks itself while its chip is up, which the bar orders on
            ->and(domQuery($html, '//*[@*[name()="x-bind:data-atom-filter-active"]]'))->toHaveCount(1);
    });

    it('names the operator for a multiple select, and gives the chip its own accessible name', function () use ($select) {
        $html = $select('add multiple');

        expect($html)->toContain('is any of')
            ->and(domQuery($html, '//button[@data-atom-filter-chip-body]')[0]->getAttribute('x-bind:aria-label'))->toContain('chipName');
    });

    it('gives the chip no x when the select is not clearable', function () use ($select) {
        expect(domQuery($select('add :clearable="false"'), '//button[@data-atom-filter-chip-clear]'))->toBeEmpty()
            ->and(domClasses(domQuery($select('add :clearable="false"'), '//button[@data-atom-filter-chip-body]')[0]))->not->toContain('[&[data-chip]]:rounded-e-none');
    });

    it('keeps a multiple chip menu open on a pick (the bar draws the chip when the select is not in an inline bar, and the menu closes there)', function () use ($select) {
        expect($select('add multiple'))->toContain('stayOpen && $event.stopPropagation()');
    });

    it('is the plain bordered filter control without add', function () use ($select) {
        $html = $select();

        expect(domClasses(domQuery($html, '//button[@data-atom-select-combobox]')[0]))->not->toContain('border-dashed')
            ->and(domQuery($html, '//*[@data-atom-select-add-label]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@x-show="isEmpty"]'))->toBeEmpty();
    });

    it('draws a date range as the same dashed button pill, with a plus, that hides once a range is set', function () {
        $html = renderBlade('<atom:date-picker variant="range" add data-filter-key="filters.issued" placeholder="Issued" />');
        $pill = domQuery($html, '//button[@data-atom-date-range-add]');

        expect($pill)->toHaveCount(1)
            ->and(domClasses($pill[0]))->toContain('border-dashed', 'rounded-pill', 'h-[26px]', 'inline-flex')
            // the name, then the operator and the value (hidden until the range is set), and an x of its own
            ->and(preg_replace('/\s+/', ' ', trim($pill[0]->textContent)))->toBe('Issued is')
            ->and(domQuery($html, '//button[@data-atom-date-range-add]/following-sibling::button[@data-atom-filter-chip-clear]'))->toHaveCount(1)
            ->and($pill[0]->getAttribute('x-bind:data-chip'))->toContain('chipState')
            ->and(domQuery($html, '//button[@data-atom-date-range-add]//*[@data-atom-icon]'))->toHaveCount(2)
            // not an input with a typed "+": there is nothing to type in it, and no field-sizing to fall back from
            ->and(domQuery($html, '//input'))->toBeEmpty()
            ->and($html)->not->toContain('field-sizing')
            ->and(domQuery($html, '//*[@data-atom-date-range][@x-show="selfChip || !dateRangeValue"]'))->toHaveCount(1)
            ->and(domQuery($html, '//button[@aria-label="Open calendar"]'))->toBeEmpty()
            // it still registers its chip, and opens the same calendar menu
            ->and($html)->toContain('table-filter:set')
            ->and(domQuery($html, '//*[@data-atom-menu]'))->not->toBeEmpty();
    });

    it('is the plain range field without add', function () {
        $html = renderBlade('<atom:date-picker variant="range" wire:model="filters.issued" placeholder="Issued" />');

        expect(domQuery($html, '//input[@type="text"]')[0]->getAttribute('placeholder'))->toBe('Issued')
            ->and(domQuery($html, '//button[@aria-label="Open calendar"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-date-range][@x-show]'))->toBeEmpty();
    });
});
