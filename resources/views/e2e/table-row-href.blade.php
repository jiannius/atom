@php
// ?tw=floor|latest picks the consumer's Tailwind build (default latest): the focus ring is a utility, and the e2e rig has none of its own.
// ?go=<name> only marks which row opened this page, so a spec can tell a navigation from a click that did nothing.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$go = preg_replace('/[^a-z-]/', '', (string) request()->query('go'));
@endphp

<atom:html title="E2E: Table Row Href" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-4">
    <div data-testid="opened">{{ $go }}</div>

    <button type="button" data-testid="before">Before the table</button>

    <livewire:atom-e2e-table-row-href />

    <button type="button" data-testid="after">After the table</button>
</div>

@livewireScripts
</atom:html>
