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
