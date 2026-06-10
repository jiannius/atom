<?php

// Regression: wire:key on an <atom:...> tag must compile + render cleanly.
//
// Before the fix, Livewire's SupportCompiledWireKeys precompiler (registered in
// Livewire's boot()) ran before atom's TagCompiler and injected a raw PHP block
// into the <atom:...> opening tag. atom's opening-tag regex couldn't match the
// polluted tag, left it raw, and its compiled closing tag then emitted an orphan
// renderComponent()/endif, causing a "syntax error, unexpected endif".
//
// Fix: atom registers its tag precompiler in register() (before all boot()), so it
// converts <atom:...> tags to component form first and Livewire handles wire:key via
// its normal component path. This test passes only with that ordering.

it('renders an atom tag with a static wire:key', function () {
    $html = renderBlade('<atom:badge wire:key="k">x</atom:badge>');

    expect($html)->toContain('data-atom-badge')
        ->and($html)->toContain('wire:key="k"');
});

it('renders an atom tag with an interpolated wire:key', function () {
    $html = renderBlade('<atom:badge wire:key="row-{{ $id }}">x</atom:badge>', ['id' => 7]);

    expect($html)->toContain('wire:key="row-7"');
});

it('renders atom:table.row with wire:key (the originally reported case)', function () {
    $html = renderBlade(
        '<atom:table.row wire:key="r-{{ $id }}"><atom:table.cell>x</atom:table.cell></atom:table.row>',
        ['id' => 7],
    );

    expect($html)->toContain('data-atom-table-row')
        ->and($html)->toContain('wire:key="r-7"');
});
