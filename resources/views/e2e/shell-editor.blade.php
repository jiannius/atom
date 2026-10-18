@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$layout = request()->query('shell') === 'header' ? 'atom::layouts.header' : 'atom::layouts.sidebar';
@endphp

{{-- A tall editor in each shell: its toolbar sticks to the top of the page, and has to stick below the shell's bar. --}}
<x-dynamic-component :component="$layout" title="E2E: shell editor" :vite="[]" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    {{-- ?bare=1 leaves the top bar with nothing to hold, which the sidebar does not draw from lg up --}}
    @unless (request()->has('bare'))
        <x-slot:crumbs>
            <span>Editor</span>
        </x-slot>
    @endunless

    <div class="px-7 pb-24 pt-6">
        <atom:tiptap label="Article body" class="min-h-[2400px]"/>
    </div>

    {{-- ?slide=1: the same kind of editor in a slide-over (a <dialog> in the top layer). The shell's bar is not above it, so
         its toolbar has to stick to the top of the slide-over's own scroll box. --}}
    @if (request()->has('slide'))
        <atom:modal name="editor-slide" class="w-[40rem]">
            <atom:tiptap label="Slide-over body" class="min-h-[2400px]"/>
        </atom:modal>
    @endif

    @livewireScripts
</x-dynamic-component>
