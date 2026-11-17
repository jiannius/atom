{{-- <atom:action> in the table's toolbar and bulk bar (tests/Fixtures/ActionTableFixture.php), on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?tone=accent. --}}
@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Action Table" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-4">
    <livewire:atom-e2e-action-table />
</div>

<atom:confirm/>

@livewireScripts
</atom:html>
