@php
// ?second=1 adds a second job on the page, ?modal=1 a third inside a closed modal; ?tw=floor|latest picks the consumer build
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
@endphp

<atom:html title="E2E: job progress" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <atom:page>
        <livewire:atom-e2e-job-progress :second="request()->boolean('second')" :modal="request()->boolean('modal')" />
    </atom:page>

    @livewireScripts
</atom:html>
