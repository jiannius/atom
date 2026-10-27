@php
// ?mode=bare puts the save bar in a plain box with no <atom:page> around it; ?mode=page puts it in a page.
// ?pb=80 sets the page's bottom room to 80px through its variable. ?shell=sidebar|header puts the page in an app shell.
// ?max=1000 puts a host utility (`[--atom-page-max:1000px]`) on the page. ?modal=1 adds a modal, open, whose form carries a bar.
// ?width=narrow|full is the page's width (the default is 1240px). ?wide=1 puts a 2400px block in the page: real sideways overflow.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$mode = request()->query('mode') === 'bare' ? 'bare' : 'page';
$pb = request()->query('pb') === '80';
$shell = in_array(request()->query('shell'), ['sidebar', 'header'], true) ? request()->query('shell') : null;
$width = in_array(request()->query('width'), ['narrow', 'full'], true) ? request()->query('width') : null;
$dark = request()->query('dark') === '1';
$wide = request()->query('wide') === '1';
$max = request()->query('max') === '1000' ? '[--atom-page-max:1000px]' : '';
$modal = request()->query('modal') === '1';
@endphp

@if ($shell)
    <x-dynamic-component :component="'atom::layouts.'.$shell" title="E2E: save bar" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]" :dark="$dark">
        <x-slot:nav>
            <atom:navlist><atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item></atom:navlist>
        </x-slot>

        <atom:page :width="$width" :class="trim(($pb ? '[--atom-page-pb:80px] ' : '').$max)" data-testid="page">
            <atom:page.header title="Save bar" />
            <atom:form x-on:submit.prevent>
                <div class="h-[1400px]">Long content</div>
                @if ($wide) <div class="h-5 w-[2400px] bg-border">A wide block</div> @endif
                <atom:form.actions bar>
                    <x-slot:start><span data-testid="start">Total RM 1.00</span></x-slot:start>
                    <atom:button type="submit">Save</atom:button>
                </atom:form.actions>
            </atom:form>

            @if ($modal)
                {{-- a bar inside a dialog that sits in the page: it must not inherit the page's bottom room --}}
                <atom:modal name="bar-modal" class="w-[28rem]" data-testid="bar-modal">
                    <atom:form x-on:submit.prevent>
                        <div class="h-12">A modal</div>
                        <atom:form.actions bar data-testid="modal-bar"><atom:button type="submit">Save</atom:button></atom:form.actions>
                    </atom:form>
                </atom:modal>
            @endif
        </atom:page>

        @if ($modal)
            <div x-data x-init="$nextTick(() => atom.modal('bar-modal').show())"></div>
        @endif

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
    <atom:page :width="$width" :class="trim(($pb ? '[--atom-page-pb:80px] ' : '').$max)" data-testid="page">
        <atom:form x-on:submit.prevent>
            <div class="h-[1400px]">Long content</div>
            <atom:form.actions bar><atom:button type="submit">Save</atom:button></atom:form.actions>
        </atom:form>
    </atom:page>
@endif

@livewireScripts
</atom:html>
@endif
