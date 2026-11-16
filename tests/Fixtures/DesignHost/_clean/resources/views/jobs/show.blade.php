{{-- A record page with sections, built to the page rules: header first, link tabs, a persisted trail the component builds, a rail in page.columns, no margins between blocks. --}}
@persist('breadcrumbs')
    <atom:breadcrumbs />
@endpersist

<atom:page width="contained">
    <atom:page.header :title="$job->number" :facts="[$job->customer]">
        <x-slot:primary>
            <atom:button variant="primary">Complete job</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:page.tabs :tabs="$sections" />

    <atom:page.columns>
        <atom:panel heading="Tasks">
            <atom:tabs variant="button" :tabs="$views" wire:model="view" />

            <div class="grid grid-cols-[1fr_auto] items-center gap-2">
                <atom:heading size="sm">Open tasks</atom:heading>
                <atom:button variant="ghost" icon="menu" aria-label="Open the task menu" />
            </div>
        </atom:panel>

        <x-slot:rail label="Job details" sticky>
            <atom:card heading="Customer">
                <atom:heading size="xl">Acme Studio</atom:heading>
                <div class="mt-2 ms-auto">Deeper in a card</div>
            </atom:card>
        </x-slot:rail>
    </atom:page.columns>

    <div class="mx-auto max-w-lg">
        <nav aria-label="Pagination">
            <a href="?page=1">1</a> <a href="?page=2">2</a>
        </nav>
    </div>
</atom:page>
