<?php

describe('context-menu', function () {
    it('wires the contextMenu alpine component around a menu popover', function () {
        $html = renderBlade(<<<'BLADE'
        <atom:context-menu>
            <div>target</div>
            <x-slot:menu>
                <atom:menu.item icon="edit">Edit</atom:menu.item>
            </x-slot:menu>
        </atom:context-menu>
        BLADE);

        expect($html)
            ->toContain('data-atom-context-menu')
            ->toContain('x-data="contextMenu({')
            ->toContain('locked: false')
            ->toContain('data-atom-context-menu-trigger')
            ->toContain('data-atom-menu')
            ->toContain('popover')
            ->toContain('role="menuitem"')
            ->toContain('target')
            ->toContain('Edit');
    });

    it('forwards the locked flag into the factory', function () {
        $html = renderBlade(<<<'BLADE'
        <atom:context-menu locked>
            <div>t</div>
            <x-slot:menu><atom:menu.item>x</atom:menu.item></x-slot:menu>
        </atom:context-menu>
        BLADE);

        expect($html)->toContain('locked: true');
    });
});
