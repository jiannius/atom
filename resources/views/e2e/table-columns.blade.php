@php
// Column visibility on a Livewire table (table-column-visibility.spec.js), on the consumer's Tailwind: ?tw=floor|latest (default latest).
// ?renamed=1, ?removed=1, ?skeleton=1, ?nokey=1, ?state={"email":false}, ?transition=1, ?empty=1: see tests/Fixtures/TableColumnsFixture.php.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table columns" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    <livewire:atom-e2e-table-columns />
</div>

@livewireScripts
</atom:html>
