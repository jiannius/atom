<?php

describe('tooltip', function () {
    it('wires the tooltip alpine component around its trigger', function () {
        $html = renderBlade('<atom:tooltip content="Save"><button>Hover</button></atom:tooltip>');

        expect($html)
            ->toContain('data-atom-tooltip')
            ->toContain('x-data="tooltip({')
            ->toContain("placement: 'top-center'") // position + align defaults
            ->toContain('Hover')
            ->toContain('Save')
            ->toContain('data-atom-tooltip-content')
            ->toContain('popover="manual"');
    });

    it('gives the content a fixed key so a morph patches the open popover instead of replacing it (issue #56)', function () {
        // tooltip.js writes an id on the content, and Livewire keys a node by its id, so without a fixed key
        // the morph swaps the open popover for a closed copy. Its attributes are kept while it is open by a
        // morph.updating hook, not a static `.self`, so a closed one still takes server-driven attributes.
        expect(renderBlade('<atom:tooltip content="Save"><button>Hover</button></atom:tooltip>'))
            ->toContain('wire:key="atom-tooltip-content"')
            ->not->toContain('wire:ignore');
    });

    it('maps position and align to a placement', function () {
        expect(renderBlade('<atom:tooltip content="x" position="bottom" align="end"><button>b</button></atom:tooltip>'))
            ->toContain("placement: 'bottom-end'");
    });

    it('passes the interactive flag through', function () {
        expect(renderBlade('<atom:tooltip content="x" :interactive="true"><button>b</button></atom:tooltip>'))
            ->toContain('interactive: true');
    });

    it('renders a keyboard hint', function () {
        expect(renderBlade('<atom:tooltip content="Command palette" kbd="⌘K"><button>b</button></atom:tooltip>'))
            ->toContain('⌘K');
    });

    it('no longer renders the dead ui-dropdown toggleable branch', function () {
        // Regression: toggleable rendered a <ui-dropdown> element that had no
        // JS behind it (Atom does not ship Flux's web components).
        expect(renderBlade('<atom:tooltip content="x" toggleable><button>b</button></atom:tooltip>'))
            ->not->toContain('ui-dropdown');
    });

    it('renders nothing without content or a slot', function () {
        expect(trim(renderBlade('<atom:tooltip/>')))->toBe('');
    });

    it('ships its factory members under an _atom prefix, so a host scope around a tooltipped button is not shadowed', function () {
        $source = file_get_contents(__DIR__.'/../../resources/js/alpinejs/tooltip.js');

        expect($source)
            ->toContain('_atomShow')
            ->toContain('_atomLink')
            ->not->toMatch('/^\s+(show|hide|link|trigger|popover|placement|cleanup|interactive)\s*[(:]/m')
            ->not->toMatch('/^\s+get (trigger|popover)\s*\(/m');
    });
});
