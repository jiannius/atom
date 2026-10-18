<div x-data>
    <atom:button size="sm" x-on:click="atom.modal('demo-page-header').slide()">Open slide-over</atom:button>

    <atom:modal name="demo-page-header" class="w-[28rem]">
        <atom:page.header title="New job" level="2" size="sm" :facts="['Customer A']">
            <x-slot:status>
                <atom:status label="Draft"/>
            </x-slot:status>
        </atom:page.header>
    </atom:modal>
</div>
