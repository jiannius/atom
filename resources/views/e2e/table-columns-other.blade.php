@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table columns, other page" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
{{-- Where the table fixture's wire:navigate link goes, and back, to see the table drawn again by a navigation --}}
<div class="p-6">
    <a href="/atom/e2e/table-columns" wire:navigate data-go-back>Back to the table</a>
</div>

@livewireScripts
</atom:html>
