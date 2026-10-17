<atom:dropdown :attributes="$attributes">
    <button
    type="button"
    class="flex h-control aspect-square shrink-0 items-center justify-center rounded-control text-fg-muted hover:bg-fg/5 hover:text-fg"
    aria-label="{{ t('Toggle dark mode') }}"
    data-atom-darkmode-toggle>
        <atom:icon.darkmode-toggle class="size-4"/>
    </button>

    <atom:menu popover>
        <atom:menu.item icon="sun" x-on:click="window.darkmode('light')">{{ t('Light') }}</atom:menu.item>
        <atom:menu.item icon="moon" x-on:click="window.darkmode('dark')">{{ t('Dark') }}</atom:menu.item>
        <atom:menu.item icon="laptop" x-on:click="window.darkmode('system')">{{ t('System') }}</atom:menu.item>
    </atom:menu>
</atom:dropdown>
