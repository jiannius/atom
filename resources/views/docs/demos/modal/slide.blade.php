<div x-data>
    <atom:button x-on:click="atom.modal('demo-slide').slide()">Open slide-over</atom:button>

    <atom:modal name="demo-slide">
        <div class="space-y-4 p-6">
            <atom:heading>Slide-over</atom:heading>
            <p>Same modal component, slide variant. From PHP: atom()->modal('demo-slide')->slide().</p>
        </div>
    </atom:modal>
</div>
