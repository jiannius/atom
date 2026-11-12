@php
// ?tw=floor|latest loads the consumer's Tailwind build (the checkbox is then drawn by atom's own utilities, as in a host). Without it the
// rig has no Tailwind and the style below gives the box a size.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Sticky Selection" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/StickySelectionFixture.php) so the
     E2E can drive a real filter change over a live checkbox selection. --}}
{{-- the rig serves no Tailwind, so the checkbox would render as a zero-size box
     that Playwright can never click. Give it a real one. --}}
@unless ($tw)
<style>
    [data-atom-table-checkbox] { width: 20px; height: 20px; border: 1px solid #999; }
</style>
@endunless

<div class="p-4">
    <livewire:atom-e2e-sticky-selection />
</div>

@livewireScripts
</atom:html>
