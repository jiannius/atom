@php
// The table's checked bar on the consumer's Tailwind: ?tw=floor|latest, ?dark=1, ?accent=%23rrggbb, and the fixture's own
// ?frameless=1, ?tone=accent, ?sticky=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<atom:html title="E2E: Table Checked" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div class="p-6">
    <livewire:atom-e2e-table-checked />
</div>

@livewireScripts
</atom:html>
