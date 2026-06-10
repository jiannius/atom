<div x-data>
    <atom:button x-on:click="atom.modal('demo-persistent').show()">Open persistent modal</atom:button>

    <atom:modal name="demo-persistent" :dismissible="false" :escapable="false">
        <div class="space-y-4 p-6">
            <atom:heading>Persistent</atom:heading>
            <p>dismissible=false blocks backdrop clicks, escapable=false blocks ESC, closeable=false would hide the X button. All three are independent.</p>
            <atom:button x-on:click="atom.modal('demo-persistent').close()">Close</atom:button>
        </div>
    </atom:modal>
</div>
