<td x-on:click.stop x-on:auxclick.stop {{ $attributes->class('px-row py-row h-(--atom-row-min-h) w-10 text-right') }} data-atom-table-actions>
    {{-- the ⋯ trigger of <atom:page.header> and <atom:table.toolbar>: the `more` icon, named "More actions" (a ghost `sm` button here, a row is dense) --}}
    <atom:dropdown position="bottom" align="end">
        <atom:button variant="ghost" size="sm" icon="more" :aria-label="t('More actions')" />
        <atom:menu popover>
            {{ $slot }}
        </atom:menu>
    </atom:dropdown>
</td>
