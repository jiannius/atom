@php
// ?tw=floor|latest picks the consumer's Tailwind build (default latest). ?go=<name> only marks which row opened this page, so a spec can tell a
// navigation from a click that did nothing. The fixture's own ?off=1, ?sticky=1, ?all=1 and ?ticked=1,2 pick the shape.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$go = preg_replace('/[^a-z-]/', '', (string) request()->query('go'));
@endphp

<atom:html title="E2E: Table Row Toggle" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-4">
    <div data-testid="opened">{{ $go }}</div>

    <livewire:atom-e2e-table-row-toggle />
</div>

@livewireScripts
</atom:html>
