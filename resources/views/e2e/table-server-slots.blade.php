@php
// A table whose header and checked slots hold server-rendered Blade that changes (?sticky=1: the sticky-selection table, whose header
// stays while rows are ticked). ?tw=floor|latest
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: Table Server Slots" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="p-6">
    <livewire:atom-e2e-table-server-slots />
</div>

@livewireScripts
</atom:html>
