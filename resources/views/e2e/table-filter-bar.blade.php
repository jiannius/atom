@php
// The filter bar, the toolbar and the pager over a real Livewire round trip, on the consumer's Tailwind: ?tw=floor|latest, ?dark=1.
// ?count=0 turns the toolbar's count off, ?plain=1 renders the table with no paginator, ?rows=N cuts the data to N rows, ?wide=1 makes
// the table wider than its box, ?sticky=0 is the table's sticky-pager=false, ?framed=1 draws the table in its own panel, ?modal=1 puts it in a dialog (opened by the button) and
// ?bar=1 puts a page save bar after it, the other thing pinned to the bottom of the screen.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$params = [
    'count' => request()->query('count') !== '0',
    'plain' => request()->query('plain') === '1',
    'wide' => request()->query('wide') === '1',
    'frameless' => request()->query('framed') !== '1',
    'stickyPager' => request()->query('sticky') !== '0',
    'limit' => is_numeric(request()->query('rows')) ? (int) request()->query('rows') : null,
];
@endphp

<atom:html title="E2E: Table Filter Bar" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    @if (request()->query('modal') === '1')
        <atom:modal.trigger name="table-in-modal"><atom:button data-testid="open-modal">Open</atom:button></atom:modal.trigger>
        <atom:modal name="table-in-modal" class="max-w-5xl" data-testid="modal">
            @include('atom::e2e.table-filter-bar-panel')
        </atom:modal>
    @elseif (request()->query('bar') === '1')
        {{-- the page's form, with the save bar last in it, as the form.actions note says --}}
        <atom:form x-on:submit.prevent>
            @include('atom::e2e.table-filter-bar-panel')

            <atom:form.actions bar>
                <x-slot:start><span data-testid="start">Unsaved changes</span></x-slot:start>
                <atom:button type="submit">Save</atom:button>
            </atom:form.actions>
        </atom:form>
    @else
        @include('atom::e2e.table-filter-bar-panel')
    @endif
</div>

@livewireScripts
</atom:html>
