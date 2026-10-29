@props([
    'for' => null,
])

@php
/* The table the picker steers: the one it sits in, or, outside a table (the list-page pattern puts the toolbar above the table), the
   table whose `id` is `for`. Printed as a data attribute, not into x-data, which stays a constant. */
$for = is_string($for) && $for !== '' ? $for : null;
@endphp

{{-- The column picker (resources/js/alpinejs/table-columns.js): an icon-only ghost button with the tooltip "Columns" and a menu of
     the table's named columns, each a checkbox item; a locked column (named, not `hideable`) is greyed with a padlock; "Reset to default"
     last. Opt-in through `<atom:table.toolbar columns>`, which draws it last in the end group after a divider; use it on its own
     anywhere a table's header or toolbar goes.

     The root is `wire:ignore`: everything in it is built from the table's payload (window.atom.tableColumns.list()), so Livewire has
     nothing to patch, and the rows, the badge and the check states are the factory's alone (a morph tells it to read the table again).
     The labels are plain text and may hold `<`: they are printed with x-text, never x-html. The badge is the number of hidden columns,
     in the accent colour at the button's corner (a sibling of the dropdown, because the button clips its own corners); it is
     aria-hidden and announced as the button's description (":count hidden"), so the button's name stays "Columns". A binding inside
     the x-for clone reads only its `row` (CLAUDE.md, Calendar): `rows` has computed everything a row shows. --}}
<div
x-data="tableColumns"
x-on:open="$nextTick(() => menuFocus(0))"
x-on:atom-table-columns-changed.window="read()"
wire:ignore
{{ $attributes->class(['relative inline-flex']) }}
@if ($for) data-atom-column-picker-for="{{ $for }}" @endif
data-hidden-template="{{ t(':count hidden', ['count' => ':count']) }}"
data-atom-column-picker>
    <atom:dropdown position="bottom" align="end" locked>
        <atom:button variant="ghost" icon="columns" tooltip="Columns" data-atom-column-picker-trigger />

        <atom:menu class="w-60" popover aria-label="{{ t('Show columns') }}" x-on:keydown="menuKey($event)" data-atom-column-menu>
            <div class="px-2.5 pb-1 pt-1.5 text-xs font-medium text-fg-subtle" aria-hidden="true">{{ t('Show columns') }}</div>

            <template x-for="row in rows" x-bind:key="row.name">
                <atom:menu.item
                role="menuitemcheckbox"
                x-bind:aria-checked="row.visible ? 'true' : 'false'"
                x-bind:aria-disabled="row.disabled ? 'true' : null"
                x-bind:class="row.disabled && 'cursor-default! hover:bg-transparent! focus-visible:bg-transparent!'"
                x-bind:data-atom-column-name="row.name"
                x-on:click="toggle(row)">
                    <span class="flex w-full items-center gap-2" x-bind:class="row.disabled && 'opacity-60'">
                        <atom:icon.check class="size-4 shrink-0 text-accent-ink" x-bind:class="row.visible ? '' : 'invisible'" />
                        <span class="min-w-0 grow truncate" x-text="row.label"></span>

                        <template x-if="row.locked">
                            <atom:icon.lock class="size-3.5 shrink-0 text-fg-subtle" role="img" aria-label="{{ t('Always shown') }}" />
                        </template>
                    </span>
                </atom:menu.item>
            </template>

            <div class="-mx-1 my-1 h-px bg-border" role="separator"></div>

            <atom:menu.item
            x-bind:aria-disabled="canReset ? null : 'true'"
            x-bind:class="! canReset && 'cursor-default! opacity-60 hover:bg-transparent! focus-visible:bg-transparent!'"
            x-on:click="reset()"
            data-atom-column-reset>
                {{ t('Reset to default') }}
            </atom:menu.item>
        </atom:menu>
    </atom:dropdown>

    <span
    x-show="hiddenCount"
    x-cloak
    x-text="hiddenCount"
    class="pointer-events-none absolute right-0 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-pill bg-accent px-1 text-[10px] font-medium leading-none tabular-nums text-accent-foreground"
    aria-hidden="true"
    data-atom-column-picker-badge></span>

    <span hidden x-bind:id="descriptionId" x-text="description" data-atom-column-picker-description></span>
</div>
