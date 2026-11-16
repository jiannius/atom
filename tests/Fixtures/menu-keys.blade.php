<div class="flex flex-col items-start gap-6">
    <output data-testid="log">{{ json_encode($log) }}</output>
    <output data-testid="renders">{{ $renders }}</output>

    <button type="button" data-testid="before">Before</button>

    {{-- a header's ⋯ menu --}}
    <div class="w-full" data-testid="header">
        <atom:page.header title="Invoice INV-0042">
            <x-slot:menu>
                <atom:menu.item icon="copy" wire:click="record('duplicate')" data-testid="h-duplicate">Duplicate</atom:menu.item>
                <atom:menu.item disabled data-testid="h-archive">Archive</atom:menu.item>
                <atom:menu.item wire:click="record('print')" data-testid="h-print">Print</atom:menu.item>
                <atom:menu.separator />
                <atom:menu.item variant="delete" wire:click="record('header-delete')" data-testid="h-delete">Delete invoice</atom:menu.item>
            </x-slot:menu>
        </atom:page.header>
    </div>

    {{-- a table toolbar's ⋯ menu --}}
    <div class="w-full rounded-panel border bg-surface" data-testid="toolbar">
        <atom:table.toolbar strip>
            <atom:table.search placeholder="Search"/>
            <x-slot:menu>
                <atom:menu.item wire:click="record('archive-all')" data-testid="t-archive">Archive all</atom:menu.item>
                <atom:menu.item wire:click="record('import')" data-testid="t-import">Import</atom:menu.item>
                <atom:menu.item href="/atom/e2e/menu-keys/landing" data-testid="t-link">Open the report</atom:menu.item>
            </x-slot:menu>
        </atom:table.toolbar>
    </div>

    {{-- a table row's ⋯ menu --}}
    <table data-testid="rows">
        <tbody>
            <tr>
                <td>Row one</td>
                <atom:table.actions>
                    <atom:menu.item wire:click="record('row-edit')" data-testid="r-edit">Edit</atom:menu.item>
                    <atom:menu.item wire:click="record('row-void')" data-testid="r-void">Void</atom:menu.item>
                </atom:table.actions>
            </tr>
        </tbody>
    </table>

    {{-- a plain dropdown: a disabled first item, a separator, a link, an item that asks first, an item hidden by Alpine --}}
    <div data-testid="plain">
        <atom:dropdown>
            <atom:button>Options</atom:button>

            <atom:menu popover>
                <atom:menu.item disabled data-testid="p-first-disabled">Disabled first</atom:menu.item>
                <atom:menu.item wire:click="record('edit')" data-testid="p-edit">Edit</atom:menu.item>
                <atom:menu.item x-show="false" data-testid="p-hidden">Hidden</atom:menu.item>
                <atom:menu.item href="/atom/e2e/menu-keys/landing" data-testid="p-link">Open page</atom:menu.item>
                <atom:menu.separator />
                <atom:menu.item variant="warning" wire:click="record('cancel-job')" confirm="Cancel this job?" data-testid="p-confirm">Cancel job</atom:menu.item>
                <atom:menu.item disabled data-testid="p-last-disabled">Disabled last</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    {{-- a menu with a search field above its items: the field keeps its own keys --}}
    <div data-testid="search">
        <atom:dropdown>
            <atom:button>With search</atom:button>

            <atom:menu popover>
                <input type="text" value="hello world" data-testid="s-input" aria-label="Filter">
                <atom:menu.item wire:click="record('s-one')" data-testid="s-one">One</atom:menu.item>
                <atom:menu.item wire:click="record('s-two')" data-testid="s-two">Two</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    {{-- keyed items a morph can move --}}
    <div data-testid="keyed">
        <atom:dropdown>
            <atom:button>Keyed</atom:button>

            <atom:menu popover>
                @foreach ($order as $id)
                    <atom:menu.item wire:key="k-{{ $id }}-{{ $generation }}" wire:click="record('k-{{ $id }}')" data-testid="k-{{ $id }}">Item {{ $id }}</atom:menu.item>
                @endforeach
            </atom:menu>
        </atom:dropdown>
    </div>

    {{-- a menu with no reachable item --}}
    <div data-testid="empty">
        <atom:dropdown>
            <atom:button>Empty</atom:button>

            <atom:menu popover>
                <atom:menu.item disabled>Nothing</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    <button type="button" wire:click="bump" data-testid="bump">Bump</button>
    <button type="button" wire:click="reorder" data-testid="reorder">Reorder</button>
    <button type="button" wire:click="rekey" data-testid="rekey">Rekey</button>
    <button type="button" data-testid="after">After</button>
</div>
