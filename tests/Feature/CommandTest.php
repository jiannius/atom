<?php

describe('command', function () {
    it('renders the dialog wired to the command factory with a search combobox', function () {
        $html = renderBlade('<atom:command name="palette"/>');

        expect($html)
            ->toContain('data-atom-command')
            ->toContain('x-data="command({ name: \'palette\' })"')
            ->toContain('x-on:atom-command-show.window="showCommand"')
            ->toContain('x-on:atom-command-close.window="closeCommand"')
            ->toContain('data-atom-command-search')
            ->toContain('role="combobox"')
            ->toContain('data-atom-command-list')
            ->toContain('data-atom-command-empty');
    });

    it('gives the listbox an accessible name and binds the combobox expanded state', function () {
        $html = renderBlade('<atom:command name="palette"/>');

        expect($html)
            // the listbox needs an accessible name of its own
            ->toContain('role="listbox"')
            ->toContain('aria-label="Commands"')
            // expanded state reflects the palette open state, not a static true
            ->toContain('x-bind:aria-expanded="open"')
            ->not->toContain('aria-expanded="true"');
    });

    it('binds the default meta.k shortcut and disables it with false', function () {
        expect(renderBlade('<atom:command name="p"/>'))
            ->toContain('x-on:keydown.meta.k.window.prevent="toggle"');

        expect(renderBlade('<atom:command name="p" :shortcut="false"/>'))
            ->not->toContain('keydown.meta.k');
    });

    it('honours a custom shortcut', function () {
        expect(renderBlade('<atom:command name="p" shortcut="ctrl.slash"/>'))
            ->toContain('x-on:keydown.ctrl.slash.window.prevent="toggle"');
    });

    it('renders an item as an anchor when given href', function () {
        $html = renderBlade('<atom:command.item href="/dashboard" icon="search">Dashboard</atom:command.item>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/dashboard"')
            ->toContain('data-atom-command-item')
            ->toContain('data-label="Dashboard"')
            ->toContain('role="option"');
    });

    it('renders an item as a button when no href, forwarding wire:click', function () {
        $html = renderBlade('<atom:command.item wire:click="save">Save</atom:command.item>');

        expect($html)
            ->toContain('<button')
            ->toContain('type="button"')
            ->toContain('wire:click="save"')
            ->not->toContain('<a ');
    });

    it('renders a per-item shortcut badge', function () {
        expect(renderBlade('<atom:command.item shortcut="⌘K">Search</atom:command.item>'))
            ->toContain('<kbd')
            ->toContain('⌘K');
    });

    it('renders a labelled role=group when a heading is given', function () {
        $html = renderBlade('<atom:command.group heading="Pages"><atom:command.item>Home</atom:command.item></atom:command.group>');

        expect($html)
            ->toContain('data-atom-command-group')
            ->toContain('data-atom-command-heading')
            ->toContain('Pages')
            ->toContain('role="group"')
            // heading id and the wrapper's aria-labelledby share the generated id
            ->toContain('aria-labelledby="atom-command-group-')
            ->toContain('id="atom-command-group-');

        // the aria-labelledby value must match the heading's id exactly
        preg_match('/aria-labelledby="(atom-command-group-\w+)"/', $html, $labelledby);
        expect($html)->toContain('id="'.$labelledby[1].'"');
    });

    it('renders a bare role=group with no aria-labelledby when no heading', function () {
        $html = renderBlade('<atom:command.group><atom:command.item>Home</atom:command.item></atom:command.group>');

        expect($html)
            ->toContain('role="group"')
            ->not->toContain('aria-labelledby')
            ->not->toContain('data-atom-command-heading');
    });

    it('overrides the empty state via the empty slot', function () {
        expect(renderBlade('<atom:command name="p"><x-slot:empty>Nothing here</x-slot:empty></atom:command>'))
            ->toContain('Nothing here');
    });

    it('defaults the name to the current Livewire component name', function () {
        $component = new class ('my-page') {
            public function __construct(public string $name) {}
            public function getName(): string { return $this->name; }
        };

        $html = withLivewireContext($component, fn () => renderBlade('<atom:command/>'));

        expect($html)->toContain('name: \'my-page\'');
    });
});
