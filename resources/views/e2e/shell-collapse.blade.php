@php
Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]));
$crumbs = request()->query('crumbs') === '1';
$actions = request()->query('actions') === '1';
$noCurrent = request()->query('nocurrent') === '1';
$morph = request()->query('morph') === '1';
$suffix = request()->getQueryString() ? '?'.request()->getQueryString() : '';
@endphp

{{-- The sidebar shell with nothing in its bar unless asked (?crumbs=1, ?actions=1), over two pages that wire:navigate to each other. --}}
<atom:layouts.sidebar :title="'E2E: collapse '.$page" :vite="[]" :styles="$styles" :dark="request()->query('dark') === '1'" :account="request()->query('account') === 'end' ? 'end' : 'brand'">
    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item :href="'/atom/e2e/shell-collapse/one'.$suffix" :current="$noCurrent ? false : null" wire:navigate>Page one</atom:navlist.item>
            <atom:navlist.item :href="'/atom/e2e/shell-collapse/two'.$suffix" :current="$noCurrent ? false : null" wire:navigate>Page two</atom:navlist.item>
            {{-- ?tall=1: a nav longer than the window, so the sidebar scrolls --}}
            @if (request()->query('tall') === '1')
                @foreach (range(1, 40) as $n)
                    <atom:navlist.item href="#item-{{ $n }}">Filler {{ $n }}</atom:navlist.item>
                @endforeach
            @endif
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

    @if ($actions || $morph)
        <x-slot:actions>
            <button type="button" data-host-action>Do it</button>
            {{-- ?morph=1: a Livewire component in the actions slot and one in the page, to re-render under an open drawer --}}
            @if ($morph)
                <div data-morph="actions"><livewire:atom-e2e-shell-actions /></div>
            @endif
        </x-slot>
    @endif

    {{-- ?footer=1: a footer slot with a link in it --}}
    @if (request()->query('footer') === '1')
        <x-slot:footer>
            <a href="#footer" data-footer-link>Footer link</a>
        </x-slot>
    @endif

    <x-slot:dropdown>
        <atom:menu.item href="?page=settings">Settings</atom:menu.item>
    </x-slot>

    <div class="p-6" data-fixture-page="{{ $page }}">
        <p>Page {{ $page }}</p>
        <input type="text" class="border" data-field>
        <div contenteditable="true" class="border" data-editor>editor</div>
        @if ($morph)
            <div data-morph="page"><livewire:atom-e2e-shell-actions /></div>
        @endif
        {{-- ?layers=1: a modal and a dropdown to hold Escape for themselves --}}
        @if (request()->query('layers') === '1')
            <button type="button" x-data x-on:click="$dispatch('atom-modal-show', { name: 'collapse-modal' })" data-open-modal>Open modal</button>

            <atom:dropdown>
                <button type="button" data-open-dropdown>Open dropdown</button>

                <atom:menu popover>
                    <atom:menu.item href="#one">Item one</atom:menu.item>
                </atom:menu>
            </atom:dropdown>

            <div data-select-wrap><atom:select variant="listbox" :options="[['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent']]"/></div>

            <atom:modal name="collapse-modal" data-modal>
                <p>A modal</p>
                <input type="text" class="border" data-modal-field>
            </atom:modal>
        @endif
        <a href="/atom/e2e/shell-collapse/{{ $page === 'one' ? 'two' : 'one' }}{{ $suffix }}" wire:navigate data-go>Go to the other page</a>
    </div>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</atom:layouts.sidebar>
