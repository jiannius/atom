{{-- action-cap: buttons the browser shows in turn are not counted. Nothing here is over its cap. --}}
<atom:page x-data="{ editing: false }">
    <atom:page.header title="Job">
        <x-slot:actions>
            <atom:button x-show="! editing" x-on:click="editing = true">Edit</atom:button>
            <atom:button x-show="editing" x-on:click="editing = false">Cancel</atom:button>
            <atom:button>Print</atom:button>
            <div wire:show="open"><atom:button>Share</atom:button></div>
            <template x-if="editing"><atom:button>Save</atom:button></template>
        </x-slot:actions>
    </atom:page.header>
</atom:page>
