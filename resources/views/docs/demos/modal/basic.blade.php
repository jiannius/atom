<div x-data>
    <atom:button x-on:click="atom.modal('demo-basic').show()">Open modal</atom:button>

    <atom:modal name="demo-basic">
        <div class="space-y-4 p-6">
            <atom:heading>Modal heading</atom:heading>
            <p>Any content can live here. Dismiss with the close button, ESC, or a backdrop click.</p>
            <atom:button x-on:click="atom.modal('demo-basic').close()">Close</atom:button>
        </div>
    </atom:modal>
</div>
