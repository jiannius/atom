@php
// ?mode=bare puts the save bar in a plain box with no <atom:page> around it; ?mode=page puts it in a page, ?pb=80 sets the page's bottom room to 80px through its variable.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$mode = request()->query('mode') === 'bare' ? 'bare' : 'page';
$pb = request()->query('pb') === '80';
@endphp

<atom:html title="E2E: save bar context" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
@if ($mode === 'bare')
    {{-- a box that clips: a bar that bled out of it would be cut or would widen the page --}}
    <div class="m-4 max-w-md overflow-x-hidden border" data-testid="box">
        <atom:form x-on:submit.prevent>
            <div class="h-[1400px]">Long content</div>
            <atom:form.actions bar><atom:button type="submit">Save</atom:button></atom:form.actions>
        </atom:form>
    </div>
@else
    <atom:page :class="$pb ? '[--atom-page-pb:80px]' : ''" data-testid="page">
        <atom:form x-on:submit.prevent>
            <div class="h-[1400px]">Long content</div>
            <atom:form.actions bar><atom:button type="submit">Save</atom:button></atom:form.actions>
        </atom:form>
    </atom:page>
@endif

@livewireScripts
</atom:html>
