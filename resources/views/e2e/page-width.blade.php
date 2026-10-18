@php
// ?width=narrow|full (anything else is the default); ?tw=floor|latest opts in to the consumer build
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$width = is_string(request()->query('width')) ? request()->query('width') : null;
@endphp

<atom:html title="E2E: page width" :vite="[]" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<atom:page :width="$width" data-testid="page">
    <atom:page.header title="Page width"/>
</atom:page>

@livewireScripts
</atom:html>
