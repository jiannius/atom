<div x-data>
    <atom:button x-on:click="atom.modal('demo-persistent').show()">Open persistent modal</atom:button>

    <atom:modal name="demo-persistent" :dismissible="false">
        <div class="space-y-4 p-6">
            <atom:heading>Persistent</atom:heading>
            <p>dismissible=false ignores ESC and backdrop clicks — close it explicitly (the X button stays unless closeable=false).</p>
            <atom:button x-on:click="atom.modal('demo-persistent').close()">Close</atom:button>
        </div>
    </atom:modal>
</div>
