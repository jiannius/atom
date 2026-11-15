<td x-on:click.stop x-on:auxclick.stop {{ $attributes->class('px-row py-row h-(--atom-row-min-h) w-10 text-right') }} data-atom-table-actions>
    {{-- the ⋯ trigger of <atom:page.header> and <atom:table.toolbar>: the shared <atom:menu._more> (a ghost `sm` button here, a row is dense, and a menu at its minimum width) --}}
    <atom:menu._more variant="ghost" size="sm" :wide="false">
        {{ $slot }}
    </atom:menu._more>
</td>
