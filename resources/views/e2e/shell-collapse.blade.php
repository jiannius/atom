@php
Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]));
$crumbs = request()->query('crumbs') === '1';
$actions = request()->query('actions') === '1';
$suffix = request()->getQueryString() ? '?'.request()->getQueryString() : '';
@endphp

{{-- The sidebar shell with nothing in its bar unless asked (?crumbs=1, ?actions=1), over two pages that wire:navigate to each other. --}}
<atom:layouts.sidebar :title="'E2E: collapse '.$page" :vite="[]" :styles="$styles" :dark="request()->query('dark') === '1'" :account="request()->query('account') === 'end' ? 'end' : 'brand'">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="/atom/e2e/shell-collapse/one{{ $suffix }}" wire:navigate>Page one</atom:navlist.item>
            <atom:navlist.item href="/atom/e2e/shell-collapse/two{{ $suffix }}" wire:navigate>Page two</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    {{-- ?brand=1: a host's brand slot (a link of the host's height, the caret is the shell's own button beside it) --}}
    @if (request()->query('brand') === '1')
        <x-slot:brand>
            <a href="/atom/e2e/shell-collapse/one" class="flex h-14 items-center px-2 font-semibold" data-host-brand>Host brand</a>
        </x-slot>
    @endif

    @if ($crumbs)
        <x-slot:crumbs>
            <span data-crumb>Customers</span>
        </x-slot>
    @endif

    @if ($actions)
        <x-slot:actions>
            <button type="button">Do it</button>
        </x-slot>
    @endif

    <x-slot:dropdown>
        <atom:menu.item href="?page=settings">Settings</atom:menu.item>
    </x-slot>

    <div class="p-6" data-fixture-page="{{ $page }}">
        <p>Page {{ $page }}</p>
        <input type="text" class="border" data-field>
        <div contenteditable="true" class="border" data-editor>editor</div>
        <a href="/atom/e2e/shell-collapse/{{ $page === 'one' ? 'two' : 'one' }}{{ $suffix }}" wire:navigate data-go>Go to the other page</a>
    </div>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</atom:layouts.sidebar>
