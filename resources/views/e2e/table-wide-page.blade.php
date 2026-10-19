@php
// ?tw=floor|latest opts in to the consumer build; ?frameless=1 draws the table with no frame of its own, inside a panel
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$frameless = request()->query('frameless') === '1';
// ?parent=grid|flex wraps the table in a parent that sizes its children by content, as a host's own layout may
$parent = in_array(request()->query('parent'), ['grid', 'flex'], true) ? request()->query('parent') : null;
@endphp

{{-- A table with many columns directly in <atom:page> (a grid), with a status column at the end: the case that scrolled the page sideways in the first host trial. --}}
<atom:html title="E2E: wide table in a page" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
<atom:page>
    <atom:page.header title="Jobs" />

    @if ($parent) <div class="{{ $parent }}"> @endif
    @if ($frameless) <div class="min-w-0 overflow-hidden rounded-panel border bg-surface"> @endif
    <atom:table :empty="false" :frameless="$frameless" data-testid="wide-table">
        <x-slot:columns>
            <atom:table.column>Number</atom:table.column>
            <atom:table.column>Customer</atom:table.column>
            <atom:table.column>Site</atom:table.column>
            <atom:table.column>Assigned</atom:table.column>
            <atom:table.column>Scheduled</atom:table.column>
            <atom:table.column numeric>Hours</atom:table.column>
            <atom:table.column numeric>Cost (RM)</atom:table.column>
            <atom:table.column>Status</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach (range(1, 4) as $i)
                <atom:table.row>
                    <atom:table.cell><span class="font-id">WO-10{{ $i }}</span></atom:table.cell>
                    <atom:table.cell>Customer {{ $i }}</atom:table.cell>
                    <atom:table.cell>Unit {{ $i }}, Harbour Park</atom:table.cell>
                    <atom:table.cell>Technician {{ $i }}</atom:table.cell>
                    <atom:table.cell>{{ $i }} Nov 2026</atom:table.cell>
                    <atom:table.cell numeric>{{ $i }}.5</atom:table.cell>
                    <atom:table.cell numeric>1,2{{ $i }}0.00</atom:table.cell>
                    <atom:table.cell><atom:status tone="info" label="In progress" /></atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
    @if ($frameless) </div> @endif
    @if ($parent) </div> @endif
</atom:page>

@livewireScripts
</atom:html>
