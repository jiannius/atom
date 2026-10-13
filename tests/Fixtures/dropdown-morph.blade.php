<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
    </div>

    {{-- a plain dropdown: a button trigger, a menu with server-rendered content --}}
    <div data-probe="menu" style="margin: 24px 0">
        <atom:dropdown :class="$menuClass">
            <atom:button>Actions</atom:button>

            <atom:menu popover>
                <div data-menu-renders>{{ $renders }}</div>
                <atom:menu.item wire:click="bump">Bump</atom:menu.item>
            </atom:menu>
        </atom:dropdown>
    </div>

    {{-- a listbox bound .live whose response is slow, then a second field that is not --}}
    <div data-probe="slow" style="margin: 24px 0">
        <atom:select variant="listbox" label="Slow" wire:model.live="slow" :options="[
            ['value' => 'a', 'label' => 'Alpha'],
            ['value' => 'b', 'label' => 'Beta'],
        ]" />
    </div>

    <div data-probe="plain" style="margin: 24px 0">
        <atom:select variant="listbox" label="Plain" wire:model="plain" :options="[
            ['value' => 'one', 'label' => 'One'],
            ['value' => 'two', 'label' => 'Two'],
            ['value' => 'three', 'label' => 'Three'],
        ]" />
    </div>

    <div data-probe="filter" style="margin: 24px 0">
        <atom:select variant="filter" label="Filter" wire:model="filter" :options="[
            ['value' => 'x', 'label' => 'Ex'],
            ['value' => 'y', 'label' => 'Why'],
        ]" />
    </div>

    <div data-probe="tooltip" style="margin: 24px 0">
        <atom:tooltip content="Save the record">
            <atom:button wire:click="bump">Save</atom:button>
        </atom:tooltip>
    </div>

    <div data-probe="context" style="margin: 24px 0">
        <atom:context-menu>
            <div data-context-target style="width: 200px; height: 60px; border: 1px solid #ccc">Right click</div>

            <x-slot:menu>
                <atom:menu.item>Copy</atom:menu.item>
                <atom:menu.item>Paste</atom:menu.item>
            </x-slot:menu>
        </atom:context-menu>
    </div>

    <atom:command name="morph-palette" shortcut="">
        <atom:command.group heading="Pages">
            <atom:command.item wire:click="bump">Dashboard</atom:command.item>
            <atom:command.item wire:click="bump">Billing</atom:command.item>
            <atom:command.item wire:click="bump">Settings</atom:command.item>
        </atom:command.group>
    </atom:command>
</div>
