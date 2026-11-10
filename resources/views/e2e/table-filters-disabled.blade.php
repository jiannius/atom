@php
// A filter bar whose controls are disabled, on the consumer's Tailwind: ?tw=floor|latest. ?case=off (the default) is a disabled form with one control that
// opts out, ?case=all-off a disabled form with nothing that does, ?case=on the same controls with no form; ?chips=below is the other chip layout. One bar a page:
// every bar hears every control's `table-filter:set`, so two would show each other's chips.
$case = in_array(request()->query('case'), ['all-off', 'on'], true) ? request()->query('case') : 'off';
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$options = [['value' => 'a', 'label' => 'Option A'], ['value' => 'b', 'label' => 'Option B']];
@endphp

<atom:html title="E2E: Table Filters Disabled" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div x-data="{ filters: { search: 'abc', status: 'a', issued: '2026-04-01 to 2026-04-30', tags: ['a'], free: 'a' } }" class="grid gap-8 p-6">
    @if ($case === 'off')
    {{-- a disabled form: every bar control honours it, but the one that opts out with :disabled="false" --}}
    <div data-testid="off" class="rounded-panel border bg-surface p-3">
        <atom:form disabled inset>
            <atom:table.filters chips="{{ request()->query('chips', 'inline') }}">
                <atom:table.search data-filter-key="filters.search" placeholder="Search" value="abc"/>
                <atom:select variant="filter" x-model="filters.status" data-filter-key="filters.status" label="Status" :options="$options"/>
                <atom:date-picker variant="range" x-model="filters.issued" data-filter-key="filters.issued" placeholder="Issued"/>
                <atom:select multiple table-filter x-model="filters.tags" data-filter-key="filters.tags" label="Tags" :options="$options"/>
                <atom:select variant="filter" :disabled="false" x-model="filters.free" data-filter-key="filters.free" label="Free" :options="$options"/>
            </atom:table.filters>
        </atom:form>
    </div>

    @elseif ($case === 'all-off')
    {{-- every control disabled: nothing is left to clear --}}
    <div data-testid="all-off" class="rounded-panel border bg-surface p-3">
        <atom:form disabled inset>
            <atom:table.filters chips="{{ request()->query('chips', 'inline') }}">
                <atom:select variant="filter" x-model="filters.status" data-filter-key="filters.status" label="Status" :options="$options"/>
            </atom:table.filters>
        </atom:form>
    </div>

    @else
    {{-- no form: the same controls, enabled --}}
    <div data-testid="on" class="rounded-panel border bg-surface p-3">
        <atom:table.filters chips="{{ request()->query('chips', 'inline') }}">
            <atom:table.search data-filter-key="filters.search" placeholder="Search" value="abc"/>
            <atom:select variant="filter" x-model="filters.status" data-filter-key="filters.status" label="Status" :options="$options"/>
            <atom:date-picker variant="range" x-model="filters.issued" data-filter-key="filters.issued" placeholder="Issued"/>
        </atom:table.filters>
    </div>
    @endif
</div>

@livewireScripts
</atom:html>
