@php
// ?mode=bare puts the save bar in a plain box with no <atom:page> around it; ?mode=page puts it in a page.
// ?pb=80 sets the page's bottom room to 80px through its variable. ?shell=sidebar|header puts the page in an app shell.
// ?width=narrow|full is the page's width (the default is 1240px).
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$mode = request()->query('mode') === 'bare' ? 'bare' : 'page';
$pb = request()->query('pb') === '80';
$shell = in_array(request()->query('shell'), ['sidebar', 'header'], true) ? request()->query('shell') : null;
$width = in_array(request()->query('width'), ['narrow', 'full'], true) ? request()->query('width') : null;
$dark = request()->query('dark') === '1';
@endphp

@if ($shell)
    <x-dynamic-component :component="'atom::layouts.'.$shell" title="E2E: save bar" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="$dark">
        <x-slot:nav>
            <atom:navlist><atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item></atom:navlist>
        </x-slot>

        <atom:page :width="$width" :class="$pb ? '[--atom-page-pb:80px]' : ''" data-testid="page">
            <atom:page.header title="Save bar" />
            <atom:form x-on:submit.prevent>
                <div class="h-[1400px]">Long content</div>
                <atom:form.actions bar>
                    <x-slot:start><span data-testid="start">Total RM 1.00</span></x-slot:start>
                    <atom:button type="submit">Save</atom:button>
                </atom:form.actions>
            </atom:form>
        </atom:page>

        @livewireScripts
    </x-dynamic-component>
@else
<atom:html title="E2E: save bar context" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="$dark">
@if ($mode === 'bare')
    {{-- a box that clips: a bar that bled out of it would be cut or would widen the page --}}
    <div class="m-4 max-w-md overflow-x-hidden border" data-testid="box">
        <atom:form x-on:submit.prevent>
            <div class="h-[1400px]">Long content</div>
            <atom:form.actions bar><atom:button type="submit">Save</atom:button></atom:form.actions>
        </atom:form>
    </div>
@else
    <atom:page :width="$width" :class="$pb ? '[--atom-page-pb:80px]' : ''" data-testid="page">
        <atom:form x-on:submit.prevent>
            <div class="h-[1400px]">Long content</div>
            <atom:form.actions bar><atom:button type="submit">Save</atom:button></atom:form.actions>
        </atom:form>
    </atom:page>
@endif

@livewireScripts
</atom:html>
@endif
