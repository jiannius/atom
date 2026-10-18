<td x-on:click.stop {{ $attributes->class('px-row py-row h-(--atom-row-min-h) w-10 text-right') }} data-atom-table-actions>
    <atom:dropdown>
        <atom:button variant="ghost" size="sm" icon="ellipsis" />
        <atom:menu popover>
            {{ $slot }}
        </atom:menu>
    </atom:dropdown>
</td>
