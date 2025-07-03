<atom:dropdown>
    <button
    type="button"
    class="shrink-0 flex items-center justify-center rounded-lg size-10 hover:bg-zinc-100 dark:hover:bg-zinc-700"
    aria-label="{{ t('Toggle sidebar') }}"
    data-atom-sidebar-toggle>
        <atom:icon.darkmode-toggle class="size-5"/>
    </button>

    <atom:menu x-data="{
        setMode (mode) {
            atom.darkmode(mode)
        },
    }">
        <atom:menu.item icon="sun" x-on:click="setMode('light')">{{ t('Light') }}</atom:menu.item>
        <atom:menu.item icon="moon" x-on:click="setMode('dark')">{{ t('Dark') }}</atom:menu.item>
        <atom:menu.item icon="laptop" x-on:click="setMode('system')">{{ t('System') }}</atom:menu.item>
    </atom:menu>
</atom:dropdown>
