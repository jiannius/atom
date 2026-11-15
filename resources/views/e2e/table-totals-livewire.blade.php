@php
// Totals lines on a Livewire table (table-totals.spec.js), on the consumer's Tailwind: ?tw=floor|latest (default latest). See tests/Fixtures/TableTotalsFixture.php.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table totals, Livewire" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    <livewire:atom-e2e-table-totals />
</div>

@livewireScripts
</atom:html>
