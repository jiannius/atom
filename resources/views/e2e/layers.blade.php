@php
// The restyled overlays and navigation (V4-3c) on the consumer's Tailwind: ?tw=floor|latest, ?density=compact|comfortable, ?dark=1
// (also emulate prefers-color-scheme: dark), ?accent=%23rrggbb (a host's brand colour; indigo here), ?section=modals|prompts|menus|
// tooltips|toasts|command|lightbox|nav to draw one section. Every overlay is declared once and opened by the spec (atom.modal(name).show(),
// atom.toast(...), a click on a trigger); nothing is open at load. Invented, generic data.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : '#4c55c9';
$section = request()->query('section');
$show = fn (string $name) => $section === null || $section === $name;
@endphp

<atom:html title="E2E: Overlays and navigation" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<style>:root { --color-accent: {{ $accent }}; }</style>

<div class="mx-auto grid max-w-4xl gap-10 p-8" data-testid="sheet">
    @if ($show('modals'))
    <section class="grid gap-4" data-testid="modals">
        <h2 class="text-lg font-semibold">Modals</h2>

        <p>Body copy on the page.</p>

        <atom:modal name="m-basic" data-testid="m-basic">
            <div class="grid gap-3">
                <div class="text-lg font-semibold" data-testid="m-basic-title">Rename the workspace</div>
                <p class="text-fg-muted" data-testid="m-basic-text">The name shows in the sidebar and on every invoice you send.</p>
                <atom:input label="Name" value="Northwind Traders" data-testid="m-basic-input"/>
                <div class="flex justify-end gap-2">
                    <atom:button x-on:click="$dispatch('atom-modal-close', { name: 'm-basic' })" data-testid="m-basic-cancel">Cancel</atom:button>
                    <atom:button variant="primary" data-testid="m-basic-save">Save</atom:button>
                </div>
            </div>
        </atom:modal>

        <atom:modal name="m-slide" data-testid="m-slide">
            <div class="grid min-w-80 gap-3">
                <div class="text-lg font-semibold">Edit customer</div>
                <p class="text-fg-muted" data-testid="m-slide-text">Changes apply to new invoices only.</p>
                <atom:input label="Name" value="Contoso Ltd"/>
            </div>
        </atom:modal>

        <atom:modal name="m-persistent" :closeable="false" :dismissible="false" data-testid="m-persistent">
            <div class="grid gap-3">
                <div class="text-lg font-semibold">Finish the setup</div>
                <p class="text-fg-muted">This one has no close button.</p>
            </div>
        </atom:modal>
    </section>
    @endif

    @if ($show('prompts'))
    <section class="grid gap-4" data-testid="prompts">
        <h2 class="text-lg font-semibold">Alert and confirm</h2>
        <atom:alert/>
        <atom:confirm/>
        <atom:toast/>
    </section>
    @endif

    @if ($show('menus'))
    <section class="grid gap-4" data-testid="menus">
        <h2 class="text-lg font-semibold">Dropdown, menu, context menu</h2>

        <div class="flex flex-wrap items-start gap-4">
            <atom:dropdown data-testid="dropdown">
                <atom:button iconSuffix="dropdown" data-testid="dropdown-trigger">Options</atom:button>

                <atom:menu popover class="w-56" data-testid="menu">
                    <atom:menu.item icon="edit" data-testid="item-edit">Edit</atom:menu.item>
                    <atom:menu.item icon="copy" iconSuffix="arrow-right" data-testid="item-icon-suffix">Duplicate</atom:menu.item>
                    <atom:menu.item badge="3" data-testid="item-badge">Team</atom:menu.item>
                    <atom:menu.item disabled data-testid="item-disabled">Archive</atom:menu.item>
                    <atom:separator class="my-1"/>
                    <atom:menu.item variant="warning" icon="warning" data-testid="item-warning">Suspend</atom:menu.item>
                    <atom:menu.item variant="danger" icon="delete" data-testid="item-danger">Delete</atom:menu.item>
                </atom:menu>
            </atom:dropdown>

            <atom:context-menu data-testid="context-menu">
                <div class="flex h-24 w-64 items-center justify-center rounded-panel border border-dashed border-control-border text-fg-muted select-none" data-testid="context-area">
                    Right-click here
                </div>

                <x-slot:menu>
                    <atom:menu.item icon="edit">Edit</atom:menu.item>
                    <atom:menu.item variant="danger" icon="delete">Delete</atom:menu.item>
                </x-slot:menu>
            </atom:context-menu>
        </div>
    </section>
    @endif

    @if ($show('tooltips'))
    <section class="grid gap-4" data-testid="tooltips">
        <h2 class="text-lg font-semibold">Tooltip</h2>

        <div class="flex flex-wrap items-center gap-4">
            <atom:tooltip content="Copy the invoice link" data-testid="tooltip-plain">
                <atom:button data-testid="tooltip-plain-trigger">Hover me</atom:button>
            </atom:tooltip>

            <atom:tooltip content="Open the command palette" kbd="⌘K" data-testid="tooltip-kbd">
                <atom:button data-testid="tooltip-kbd-trigger">With a shortcut</atom:button>
            </atom:tooltip>
        </div>
    </section>
    @endif

    @if ($show('command'))
    <section class="grid gap-4" data-testid="command">
        <h2 class="text-lg font-semibold">Command palette</h2>

        <atom:command name="cmd" shortcut="" data-testid="command-root">
            <atom:command.group heading="Go to">
                <atom:command.item icon="home" shortcut="G H" data-testid="command-item-home">Dashboard</atom:command.item>
                <atom:command.item icon="settings" data-testid="command-item-settings">Settings</atom:command.item>
            </atom:command.group>

            <atom:command.group heading="Create">
                <atom:command.item icon="add" data-testid="command-item-invoice">New invoice</atom:command.item>
            </atom:command.group>
        </atom:command>
    </section>
    @endif

    @if ($show('lightbox'))
    <section class="grid gap-4" data-testid="lightbox">
        <h2 class="text-lg font-semibold">Lightbox</h2>

        <div x-data>
            <div data-lightbox class="flex flex-wrap gap-3">
                <img src="/atom/e2e/photo.svg" alt="" data-lightbox-url="/atom/e2e/photo.svg" data-lightbox-name="Site photo" x-on:click="$dispatch('lightbox')" class="size-20 rounded-panel object-cover cursor-pointer" data-testid="lightbox-photo"/>
                <button type="button" data-lightbox-url="/atom/e2e/statement.pdf" data-lightbox-name="Statement.pdf" x-on:click="$dispatch('lightbox')" class="size-20 rounded-panel border" data-testid="lightbox-file">PDF</button>
            </div>

            <atom:lightbox/>
        </div>
    </section>
    @endif

    @if ($show('nav'))
    <section class="grid gap-6" data-testid="nav">
        <h2 class="text-lg font-semibold">Tabs, breadcrumbs, accordion</h2>

        {{-- the current tab is the item's own `current` (a tab given as an array reads its `current` key only through the slot form) --}}
        <atom:tabs data-testid="tabs-line">
            <atom:tabs.item label="Overview" current data-testid="tab-current"/>
            <atom:tabs.item label="Activity" data-testid="tab-idle"/>
            <atom:tabs.item label="Billing" icon="settings" data-testid="tab-icon"/>
            <atom:tabs.item label="Files" href="#files" data-testid="tab-link"/>
        </atom:tabs>

        <atom:tabs size="sm" data-testid="tabs-line-sm">
            <atom:tabs.item label="Overview" current/>
            <atom:tabs.item label="Activity"/>
        </atom:tabs>

        <div>
            <atom:tabs variant="button" data-testid="tabs-button">
                <atom:tabs.item label="Day" current data-testid="seg-current"/>
                <atom:tabs.item label="Week" data-testid="seg-idle"/>
                <atom:tabs.item label="Month"/>
            </atom:tabs>
        </div>

        <div>
            <atom:tabs variant="button" size="sm" data-testid="tabs-button-sm">
                <atom:tabs.item label="Day" current/>
                <atom:tabs.item label="Week"/>
            </atom:tabs>
        </div>

        <atom:breadcrumbs data-testid="crumbs" :heading="false"/>

        <atom:accordion data-testid="accordion">
            <atom:accordion.item heading="What is included?" expanded>
                <span data-testid="accordion-body">Invoices, customers and a statement for each.</span>
            </atom:accordion.item>
            <atom:accordion.item heading="Can I export it?">
                Yes, as a spreadsheet.
            </atom:accordion.item>
        </atom:accordion>
    </section>
    @endif
</div>

@livewireScripts
</atom:html>
