<div x-data>
    <atom:command.trigger name="command-demo">
        <atom:button>Open palette</atom:button>
    </atom:command.trigger>

    <atom:command name="command-demo">
        <atom:command.group heading="Pages">
            <atom:command.item href="/atom/docs" icon="search">Dashboard</atom:command.item>
            <atom:command.item href="/atom/docs/button">Buttons</atom:command.item>
            <atom:command.item href="/atom/docs/modal">Modals</atom:command.item>
        </atom:command.group>

        <atom:command.group heading="Actions">
            <atom:command.item icon="close" shortcut="⌘K" x-on:click="$dispatch('command-demo-picked', 'new')">New record</atom:command.item>
            <atom:command.item x-on:click="$dispatch('command-demo-picked', 'export')">Export</atom:command.item>
        </atom:command.group>
    </atom:command>

    <div class="mt-4" x-data="{ picked: '' }" x-on:command-demo-picked.window="picked = $event.detail">
        Picked: <span data-atom-command-result x-text="picked"></span>
    </div>
</div>
