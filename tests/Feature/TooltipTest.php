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

    it('keeps the content node through a Livewire morph (issue #56)', function () {
        // tooltip.js writes an id on the content and floating-ui an inline style. Livewire keys a node by its
        // id, so a fixed key stops the morph replacing the open popover; `.self` keeps the style and id.
        expect(renderBlade('<atom:tooltip content="Save"><button>Hover</button></atom:tooltip>'))
            ->toContain('wire:ignore.self')
            ->toContain('wire:key="atom-tooltip-content"');
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
});
