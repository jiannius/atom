{{-- own-overflow: a hand-built ⋯ dropdown. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:page.header title="Jobs">
        <x-slot:actions>
            <atom:dropdown> {{-- expect: own-overflow --}}
                <atom:button variant="ghost" icon="more" aria-label="More actions" />
                <atom:menu popover>
                    <atom:menu.item>Duplicate</atom:menu.item>
                </atom:menu>
            </atom:dropdown>
        </x-slot:actions>

        <x-slot:menu>
            <atom:menu.item>Duplicate</atom:menu.item>
        </x-slot:menu>
    </atom:page.header>

    <atom:panel heading="Jobs">
        <atom:table.toolbar>
            <x-slot:actions>
                <atom:dropdown> {{-- expect: own-overflow --}}
                    <atom:button icon="ellipsis" aria-label="More" />
                    <atom:menu popover><atom:menu.item>Import</atom:menu.item></atom:menu>
                </atom:dropdown>
            </x-slot:actions>
        </atom:table.toolbar>

        <atom:table>
            <x-slot:checked>
                <atom:dropdown> {{-- expect: own-overflow --}}
                    <atom:button><atom:icon.more /></atom:button>
                    <atom:menu popover><atom:menu.item>Export selected</atom:menu.item></atom:menu>
                </atom:dropdown>
            </x-slot:checked>

            <x-slot:rows>
                <atom:table.row>
                    <atom:table.cell>
                        <atom:dropdown> {{-- expect: own-overflow --}}
                            <atom:button variant="ghost" size="sm" icon="more" />
                            <atom:menu popover><atom:menu.item>Open</atom:menu.item></atom:menu>
                        </atom:dropdown>
                    </atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>
    </atom:panel>

    <atom:card heading="Customer">
        <x-slot:actions>
            <atom:dropdown> {{-- expect: own-overflow --}}
                <atom:button variant="ghost"><atom:icon.ellipsis /></atom:button>
                <atom:menu popover><atom:menu.item>Edit</atom:menu.item></atom:menu>
            </atom:dropdown>
        </x-slot:actions>
    </atom:card>

    <div class="flex justify-end">
        {{-- expect: own-overflow --}}
        <atom:dropdown
            position="bottom"
            align="end"
        >
            <atom:button icon="more" />
            <atom:menu popover><atom:menu.item>Elsewhere</atom:menu.item></atom:menu>
        </atom:dropdown>
    </div>

    {{-- the look-alikes: none of these is flagged --}}
    <atom:dropdown>
        <atom:button icon="down">Export</atom:button>
        <atom:menu popover><atom:menu.item>CSV</atom:menu.item></atom:menu>
    </atom:dropdown>

    <atom:dropdown>
        <atom:button><atom:icon.more /> Actions</atom:button>
        <atom:menu popover><atom:menu.item>Archive</atom:menu.item></atom:menu>
    </atom:dropdown>

    <atom:dropdown>
        <div>Anything that is not a button</div>
        <atom:menu popover><atom:button icon="more" /></atom:menu>
    </atom:dropdown>

    <atom:button variant="ghost" icon="more" tooltip="More actions" />

    <!-- <atom:dropdown><atom:button icon="more" /></atom:dropdown> -->
    {{-- <atom:dropdown><atom:button icon="more" /></atom:dropdown> --}}
</atom:page>
