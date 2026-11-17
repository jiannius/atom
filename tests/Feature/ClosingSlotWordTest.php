<?php

use Illuminate\Support\Facades\Blade;

// Laravel closes a slot by replacing `</x-slot...>` with ` @endslot`, and a word that follows the tag with no space between
// is glued onto the directive (`@endslotTotal`): Blade does not know that one, the slot never closes and the text is printed.
// Atom's precompiler separates them with an empty PHP block, which compiles to nothing, so the word stays exactly where it was written.

beforeEach(function () {
    Blade::anonymousComponentPath(__DIR__.'/../Fixtures/endslot', 'endslot');
});

it('closes a named slot that is followed by a word', function () {
    $html = renderBlade('<x-endslot::probe><x-slot:actions>Act</x-slot:actions>Total</x-endslot::probe>');

    expect($html)
        ->not->toContain('@endslot')
        ->toContain('<i class="actions">Act</i>')
        ->toContain('<b class="body">Total</b>');
});

it('closes a name="" slot, and a closing tag with spaces in it, that are followed by a word', function () {
    $html = renderBlade('<x-endslot::probe><x-slot name="actions">Act</x-slot>Total</x-endslot::probe>');

    expect($html)
        ->not->toContain('@endslot')
        ->toContain('<i class="actions">Act</i>')
        ->toContain('<b class="body">Total</b>');

    $html = renderBlade('<x-endslot::probe><x-slot:actions>Act</x-slot:actions   >Total</x-endslot::probe>');

    expect($html)->not->toContain('@endslot')->toContain('<i class="actions">Act</i>')->toContain('<b class="body">Total</b>');
});

it('closes a slot written on an <atom:> tag that is followed by a word', function () {
    $html = renderBlade('<x-endslot::probe><atom:icon.add slot="actions"/>Total</x-endslot::probe>');

    expect($html)
        ->not->toContain('@endslot')
        ->toContain('data-atom-icon')
        ->toContain('<b class="body">Total</b>');
});

it('adds no character to the output: a word renders exactly where it was written', function () {
    $glued = renderBlade('<x-endslot::probe><i>x</i><x-slot:actions>A</x-slot:actions>Total more</x-endslot::probe>');
    $spaced = renderBlade('<x-endslot::probe><i>x</i><x-slot:actions>A</x-slot:actions> Total more</x-endslot::probe>');

    // The one space before the word is the one Blade's ` @slot(...) ` opener leaves in the body, with or without this fix.
    expect($glued)->toContain('<b class="body"><i>x</i> Total more</b>')
        ->and($spaced)->toContain('<b class="body"><i>x</i>  Total more</b>');
});

it('leaves a newline or a space after the closing tag, and a tag after it, as they were', function () {
    foreach (["\n", ' ', '<span>x</span>', ''] as $after) {
        $html = renderBlade('<x-endslot::probe><x-slot:actions>A</x-slot:actions>'.$after.'</x-endslot::probe>');

        expect(Blade::compileString('<x-slot:a>A</x-slot:a>'.$after))->not->toContain('<?php ?>')
            ->and($html)->toContain('<i class="actions">A</i>');
    }
});

it('does not touch a closing slot tag inside @verbatim', function () {
    expect(renderBlade('@verbatim<x-slot:a>A</x-slot:a>Total@endverbatim'))->toBe('<x-slot:a>A</x-slot:a>Total');
});

it('keeps a parenthesis after the closing tag, glued or after spaces', function () {
    // Blade reads `@endslot (Total)` as the directive's argument list and drops it: the empty block goes straight after `@endslot`.
    $glued = renderBlade('<x-endslot::probe><i>x</i><x-slot:actions>A</x-slot:actions>(Total)</x-endslot::probe>');
    $spaced = renderBlade('<x-endslot::probe><i>x</i><x-slot:actions>A</x-slot:actions> (Total)</x-endslot::probe>');
    $tabbed = renderBlade("<x-endslot::probe><i>x</i><x-slot:actions>A</x-slot:actions>\t(Total)</x-endslot::probe>");

    expect($glued)->not->toContain('@endslot')->toContain('<b class="body"><i>x</i> (Total)</b>')
        ->and($spaced)->toContain('<b class="body"><i>x</i>  (Total)</b>')
        ->and($tabbed)->toContain("<b class=\"body\"><i>x</i> \t(Total)</b>");
});

it('still leaves @endslot alone when a newline or a tag follows it', function () {
    expect(Blade::compileString('<x-slot:a>A</x-slot:a>'."\n".'(Total)'))->not->toContain('<?php ?>');
});

it('documents a limit: literal text of a space, @endslot and a word is read as a closing slot tag', function () {
    // A precompiler cannot tell this text from the ` @endslot` Laravel emits for a closing tag, so it is compiled as the directive
    // (it printed as written before). This test pins the limit; it is not a promise.
    expect(fn () => renderBlade('<p>a @endslotx</p>'))->toThrow(Illuminate\View\ViewException::class);
});
