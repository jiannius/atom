<div>
    <atom:button x-on:click="atom.modal('job-slide').slide()" data-testid="open-slide">Open slide-over</atom:button>

    {{-- a form in a slide-over, headed by page.header at level 2: pure Blade and CSS, so a morph must leave its nodes alone --}}
    <atom:modal name="job-slide" class="w-[28rem]">
        <atom:page.header title="New job" level="2" size="sm" :facts="[['label' => 'Renders', 'value' => (string) $count]]" data-testid="slide-header">
            <x-slot:status>
                <atom:status tone="info" :running="$running" :label="$running ? 'In progress' : 'Done'"/>
            </x-slot:status>

            <x-slot:primary>
                <atom:button size="sm" variant="primary" wire:click="bump" data-testid="bump">Bump</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <atom:button size="sm" wire:click="finish" data-testid="finish">Finish</atom:button>
    </atom:modal>
</div>
