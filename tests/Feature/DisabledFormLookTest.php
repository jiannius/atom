<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * What a disabled <atom:form> draws and holds, apart from the fields' own behaviour (tests/Feature/DisabledGuardTest.php walks those): the form dims nothing, a
 * submit button in it is disabled, and the textarea, the input and the prefix read the marker. The computed looks are in tests/e2e/form-disabled-look.spec.js.
 */

it('does not dim a disabled form', function () {
    $form = domQuery(renderBlade('<atom:form disabled><p>Content</p></atom:form>'), '//form')[0];

    expect(domClasses($form))->not->toContain('opacity-70')
        ->and(array_filter(domClasses($form), fn ($class) => str_starts_with($class, 'opacity-')))->toBe([]);
});

it('disables a submit button in a disabled form, and leaves every other button live', function () {
    $html = renderBlade('<atom:form disabled><atom:button type="submit">Save</atom:button><atom:button>Download</atom:button><atom:button type="submit" href="/x">Link</atom:button></atom:form>');

    expect(domQuery($html, '//button[@type="submit" and @disabled]'))->toHaveCount(1)
        ->and(domQuery($html, '//button[@type="button" and @disabled]'))->toHaveCount(0)
        ->and(domQuery($html, '//a[@disabled]'))->toHaveCount(0);
});

it('lets a submit button\'s own :disabled="false" beat a disabled form, and its own disabled beat an enabled one', function () {
    expect(domQuery(renderBlade('<atom:form disabled><atom:button type="submit" :disabled="false">Save</atom:button></atom:form>'), '//button[@disabled]'))->toHaveCount(0);
    expect(domQuery(renderBlade('<atom:form :disabled="false"><atom:button type="submit" disabled>Save</atom:button></atom:form>'), '//button[@disabled]'))->toHaveCount(1);
    expect(domQuery(renderBlade('<atom:button type="submit">Save</atom:button>'), '//button[@disabled]'))->toHaveCount(0);
});

it('does not leave a submit button after a disabled form disabled', function () {
    $html = renderBlade('<atom:form disabled><atom:input name="a"/></atom:form><atom:button type="submit">Save</atom:button>');

    expect(domQuery($html, '//button[@type="submit" and @disabled]'))->toHaveCount(0);
});

it('keeps a submit button with an action of its own live in a disabled form: it is not the form\'s Save', function (string $attribute) {
    $html = renderBlade('<atom:form disabled><atom:button type="submit" '.$attribute.'>Approve</atom:button><atom:button type="submit">Save</atom:button></atom:form>');

    expect(domQuery($html, '//button[@type="submit" and @disabled]'))->toHaveCount(1)
        ->and(domQuery($html, '//button[@type="submit" and not(@disabled)]'))->toHaveCount(1);
})->with(['wire:click="approve"', 'x-on:click="approve()"', '@click="approve()"']);

it('disables the default Save of form.actions in a disabled form', function () {
    $html = renderBlade('<atom:form disabled><atom:form.actions/></atom:form>');

    expect(domQuery($html, '//button[@type="submit"]'))->not->toBeEmpty()
        ->and(domQuery($html, '//button[@type="submit" and not(@disabled)]'))->toHaveCount(0);
});

it('makes <atom:form.modal disabled> a disabled form: no wire:submit, a disabled Save, marked fields', function () {
    $html = renderBlade('<atom:form.modal name="m" disabled><atom:input label="Name" value="x"/><atom:checkbox label="Opt"/></atom:form.modal>');

    $form = domQuery($html, '//form')[0];
    expect($form->getAttribute('onsubmit'))->toBe('return false')
        ->and($form->hasAttribute('wire:submit'))->toBeFalse()
        ->and(domQuery($html, '//button[@type="submit"]'))->not->toBeEmpty()
        ->and(domQuery($html, '//button[@type="submit" and not(@disabled)]'))->toHaveCount(0)
        ->and(domQuery($html, '//input[@data-atom-disabled="inherited"]'))->toHaveCount(2);

    $enabled = renderBlade('<atom:form.modal name="m"><atom:input label="Name"/></atom:form.modal>');
    expect(domQuery($enabled, '//form')[0]->hasAttribute('wire:submit'))->toBeTrue()
        ->and(domQuery($enabled, '//button[@type="submit" and @disabled]'))->toHaveCount(0)
        ->and(domQuery($enabled, '//*[@data-atom-disabled]'))->toHaveCount(0);
});

it('prints the marker on a checkbox and a radio, inherited from a form or group, own, and not at all when opted out', function (string $tag, string $type) {
    $markers = fn (string $html) => collect(domQuery($html, "//input[@type=\"{$type}\"]"))->map(fn (DOMElement $el) => [$el->getAttribute('data-atom-disabled'), $el->hasAttribute('disabled')])->all();

    expect($markers(renderBlade(str_replace('%s', 'disabled', $tag))))->toBe([['self', true]])
        ->and($markers(renderBlade('<atom:form disabled>'.str_replace('%s', '', $tag).'</atom:form>')))->toBe([['inherited', true]])
        ->and($markers(renderBlade('<atom:form disabled>'.str_replace('%s', ':disabled="false"', $tag).'</atom:form>')))->toBe([['', false]])
        ->and($markers(renderBlade('<atom:form :disabled="false">'.str_replace('%s', 'disabled', $tag).'</atom:form>')))->toBe([['self', true]])
        ->and($markers(renderBlade('<atom:form disabled>'.str_replace('%s', '', $tag).'</atom:form>'.str_replace('%s', '', $tag))))->toBe([['inherited', true], ['', false]]);
})->with([
    'checkbox' => ['<atom:checkbox label="A" %s/>', 'checkbox'],
    'radio' => ['<atom:radio label="A" value="a" %s/>', 'radio'],
]);

it('disables every checkbox and radio of a disabled group', function () {
    $html = renderBlade('<atom:checkbox.group disabled><atom:checkbox label="A"/><atom:checkbox label="B"/></atom:checkbox.group><atom:radio.group disabled><atom:radio label="A" value="a"/><atom:radio label="B" value="b"/></atom:radio.group>');

    expect(domQuery($html, '//input[@data-atom-disabled="inherited" and @disabled]'))->toHaveCount(4);
});

it('draws no clear button on a disabled clearable input, in either route, and one on an enabled field', function () {
    $clear = '//button[@aria-label="Clear"]';

    expect(domQuery(renderBlade('<atom:input clearable/>'), $clear))->toHaveCount(1)
        ->and(domQuery(renderBlade('<atom:input clearable disabled/>'), $clear))->toHaveCount(0)
        ->and(domQuery(renderBlade('<atom:form disabled><atom:input clearable/></atom:form>'), $clear))->toHaveCount(0)
        ->and(domQuery(renderBlade('<atom:form disabled><atom:input clearable :disabled="false"/></atom:form>'), $clear))->toHaveCount(1);
});

it('draws a form\'s input and its own disabled one the same, from the marker', function () {
    $classes = fn (string $html) => domClasses(domQuery($html, '//input')[0]);

    $own = $classes(renderBlade('<atom:input disabled/>'));
    $inherited = $classes(renderBlade('<atom:form disabled><atom:input/></atom:form>'));
    $enabled = $classes(renderBlade('<atom:input/>'));

    expect($inherited)->toEqual($own)
        ->and($own)->toContain('bg-surface-sunken', 'text-fg-subtle', 'cursor-not-allowed', 'border-border')
        ->and($enabled)->toContain('bg-surface', 'text-fg', 'border-control-border')
        ->and($enabled)->not->toContain('bg-surface-sunken')
        // the faint border is a plain utility, so the focus variant still beats it
        ->and($inherited)->toContain('focus-visible:border-focus');

    // a host's own readonly is not a disabled state
    expect($classes(renderBlade('<atom:input readonly/>')))->toEqual($enabled);
});

it('prints a textarea\'s state as a marker, readonly only when inherited, and draws its look from the marker', function () {
    $textarea = fn (string $html) => domQuery($html, '//textarea')[0];

    $own = $textarea(renderBlade('<atom:textarea disabled/>'));
    expect($own->getAttribute('data-atom-disabled'))->toBe('self')->and($own->hasAttribute('readonly'))->toBeFalse()->and($own->hasAttribute('disabled'))->toBeTrue();

    foreach (['<atom:form disabled><atom:textarea/></atom:form>', '<atom:form disabled><atom:textarea label="Notes"/></atom:form>', '<atom:form disabled><atom:textarea autoresize/></atom:form>'] as $tag) {
        $inherited = $textarea(renderBlade($tag));
        expect($inherited->getAttribute('data-atom-disabled'))->toBe('inherited', $tag)->and($inherited->hasAttribute('readonly'))->toBeTrue($tag)->and($inherited->hasAttribute('disabled'))->toBeFalse($tag);
    }

    $outOut = $textarea(renderBlade('<atom:form disabled><atom:textarea :disabled="false"/></atom:form>'));
    expect($outOut->hasAttribute('data-atom-disabled'))->toBeFalse()->and($outOut->hasAttribute('readonly'))->toBeFalse();

    // a host's own readonly stays what it is: no marker, no disabled state
    $host = $textarea(renderBlade('<atom:textarea readonly/>'));
    expect($host->hasAttribute('data-atom-disabled'))->toBeFalse();

    // the look is the marker's, on the element, so the carry of a wire:ignore'd autoresize textarea (the marker only, never a class) keeps it right
    foreach ([$own, $textarea(renderBlade('<atom:textarea/>'))] as $el) {
        expect(domClasses($el))->toContain('[&[data-atom-disabled]]:bg-surface-sunken', '[&[data-atom-disabled]]:text-fg-subtle', '[&[data-atom-disabled]]:border-border', '[&[data-atom-disabled]]:focus-visible:border-focus');
    }

    // #187: the transparent variant has no box, so its cue is the text
    $transparent = domClasses($textarea(renderBlade('<atom:textarea variant="transparent" disabled/>')));
    expect($transparent)->toContain('[&[data-atom-disabled]]:text-fg-subtle', '[&[data-atom-disabled]]:cursor-not-allowed')
        ->and($transparent)->not->toContain('[&[data-atom-disabled]]:bg-surface-sunken');
});

it('gives the prefix and suffix boxes the faint border beside a disabled field, and the focus border when it is focused', function () {
    $box = domClasses(domQuery(renderBlade('<atom:input prefix="https://" disabled/>'), '//div[contains(@class, "rounded-l-control")]')[0]);

    expect($box)->toContain('group-has-[[data-atom-disabled]]/prefix:border-border', 'group-has-[:focus-visible]/prefix:border-focus', 'group-has-[:focus-visible]/prefix:group-has-[[data-atom-disabled]]/prefix:border-focus');

    // the group is the prefix's own: an unnamed one would be a host's `.group` around the field
    $root = domClasses(domQuery(renderBlade('<atom:input prefix="https://"/>'), '//div[contains(@class, "group/prefix")]')[0]);
    expect($root)->toContain('group/prefix')->and($root)->not->toContain('group');
});

it('does not draw a label or heading dimmer in a disabled form', function () {
    $html = renderBlade('<atom:form disabled><atom:input label="Name"/></atom:form>');

    expect($html)->not->toContain('opacity-');
});
