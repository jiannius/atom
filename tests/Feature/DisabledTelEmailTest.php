<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * The tel and email inputs under the disabled-state contract: the markup the browser rows (tests/e2e/tel-email-disabled.spec.js and the rig) start from.
 */

/**
 * The markers a render carries, in document order.
 *
 * @return list<string>
 */
function telEmailMarkers(string $html): array
{
    return collect(domQuery($html, '//*[@data-atom-disabled]'))->map(fn (DOMElement $el) => $el->tagName.'='.$el->getAttribute('data-atom-disabled'))->all();
}

it('disables the dial code in both routes, and the number box as the text-field table says', function () {
    $dial = '//select[@data-atom-input-tel-country]';
    $number = '//input[@type="tel"]';

    $enabled = renderBlade('<atom:input type="tel" wire:model="phone" />');
    expect(domQuery($enabled, $dial.'[@disabled]'))->toHaveCount(0);
    expect(domQuery($enabled, $number.'[@disabled or @readonly]'))->toHaveCount(0);
    expect(telEmailMarkers($enabled))->toBe([]);

    $own = renderBlade('<atom:input type="tel" wire:model="phone" disabled />');
    expect(domQuery($own, $dial.'[@disabled]'))->toHaveCount(1);
    expect(domQuery($own, $number.'[@disabled][not(@readonly)]'))->toHaveCount(1);
    expect(telEmailMarkers($own))->toBe(['div=self']);

    $form = renderBlade('<atom:form disabled><atom:input type="tel" wire:model="phone" /></atom:form>');
    expect(domQuery($form, $dial.'[@disabled]'))->toHaveCount(1);
    expect(domQuery($form, $number.'[@readonly][not(@disabled)]'))->toHaveCount(1);
    expect(telEmailMarkers($form))->toBe(['div=inherited']);

    $optOut = renderBlade('<atom:form disabled><atom:input type="tel" wire:model="phone" :disabled="false" /></atom:form>');
    expect(domQuery($optOut, $dial.'[@disabled]'))->toHaveCount(0);
    expect(domQuery($optOut, $number.'[@disabled or @readonly]'))->toHaveCount(0);
    expect(telEmailMarkers($optOut))->toBe([]);
});

it('hands a tel input the dial code and number handlers a user gesture runs, and no watcher that writes on load', function () {
    $html = renderBlade('<atom:input type="tel" wire:model="phone" />');

    expect($html)->toContain('x-on:input.stop="format()"')->toContain('x-on:change="keepCode($event); format()"')->toContain('x-on:input.stop="keepCode"');
    expect(file_get_contents(__DIR__.'/../../resources/js/alpinejs/tel-input.js'))
        ->not->toContain("\$watch('code'")
        ->not->toContain("\$watch('number'");
});

it('marks a multiple email input and hides what changes its chips behind plain wrappers', function () {
    $own = renderBlade('<atom:input type="email" multiple wire:model="to" disabled />');
    expect(telEmailMarkers($own))->toBe(['div=self']);
    expect(domQuery($own, '//input[@type="email"][@disabled]'))->toHaveCount(1);

    $form = renderBlade('<atom:form disabled><atom:input type="email" multiple wire:model="to" /></atom:form>');
    expect(telEmailMarkers($form))->toBe(['div=inherited']);
    expect(domQuery($form, '//input[@type="email"][@readonly][not(@disabled)]'))->toHaveCount(1);

    // the remove and the clear-all hide through a variant on a wrapper with no display class of its own: an icon or a button with `flex` would win
    foreach ([$own, $form] as $html) {
        foreach (domQuery($html, '//button[@data-atom-chip-remove]/parent::div | //button[@aria-label="Clear all"]/parent::div') as $wrapper) {
            expect(domClasses($wrapper))->toContain('[[data-atom-disabled]_&]:hidden')->not->toContain('flex', 'inline-flex');
        }
    }

    expect(domQuery($form, '//button[@aria-label="Clear all"]/parent::div'))->toHaveCount(1);

    $enabled = renderBlade('<atom:input type="email" multiple wire:model="to" />');
    expect(telEmailMarkers($enabled))->toBe([]);
});

it('keeps readonly off a file input and still marks it', function () {
    $form = renderBlade('<atom:form disabled><atom:input type="file" wire:model="file" /></atom:form>');

    expect(domQuery($form, '//input[@type="file"][@readonly]'))->toHaveCount(0);
    expect(telEmailMarkers($form))->toBe(['div=inherited', 'input=inherited'], 'the uploader root and the file input both carry it');
});

it('guards every way an email input adds, removes or clears', function () {
    $js = file_get_contents(__DIR__.'/../../resources/js/alpinejs/email-input.js');

    foreach (['select', 'remove', 'clear', 'keyEnter'] as $method) {
        expect($js)->toMatch('/\b'.$method.' \([^)]*\) \{\s+if \(this\.disabled\) return/', "{$method}() asks whether the control is disabled first");
    }
});
