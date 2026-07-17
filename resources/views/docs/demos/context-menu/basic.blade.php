<atom:context-menu>
    <div class="flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 text-zinc-500 select-none dark:border-zinc-700 dark:text-zinc-400">
        Right-click anywhere in this box
    </div>

    <x-slot:menu>
        <atom:menu.item icon="edit">Edit</atom:menu.item>
        <atom:menu.item icon="copy">Duplicate</atom:menu.item>
        <atom:menu.item icon="archive">Archive</atom:menu.item>

        <atom:separator class="my-1"/>

        <atom:menu.item icon="delete" variant="danger">Delete</atom:menu.item>
    </x-slot:menu>
</atom:context-menu>
