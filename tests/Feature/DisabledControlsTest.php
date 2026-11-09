<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/*
 * Issue #178: in dark a disabled field was told from an enabled one by its sunken fill alone. Every text control's disabled
 * state also takes the faint hairline (`border-border`) in place of the strong one (`border-control-border`). The browser
 * proves the colours (tests/e2e/disabled-controls.spec.js); these hold, on each control's own element and by class token,
 * that each one still says so in its markup. A trigger holds `border-b border-border` in its menu, so a loose string match would pass.
 */

const FIELD_ERROR = 'group-has-[[data-atom-error]]/field:border-danger-fg';

/** The element that draws the field's box, for one control: [template, xpath of that element]. */
dataset('field boxes', [
    'input' => ['<atom:input %s/>', '//input'],
    'password' => ['<atom:input type="password" %s/>', '//input[@data-atom-input or @type="password"]'],
    'textarea' => ['<atom:textarea %s/>', '//textarea'],
    'tel' => ['<atom:input type="tel" %s/>', '//input[@type="tel"]'],
    'otp' => ['<atom:input.otp %s/>', '//input[@data-atom-input-otp-box]'],
    'native select' => ['<atom:select :options="[]" %s/>', '//*[@data-atom-select-native]/div[1]'],
    'email' => ['<atom:input type="email" multiple %s/>', '//*[@data-atom-input-email]//*[@data-atom-dropdown-trigger]'],
    'listbox' => ['<atom:select variant="listbox" :options="[]" %s/>', '//button[@data-atom-select-combobox]'],
    'date picker' => ['<atom:date-picker %s/>', '//*[@data-atom-date-picker]//input[@type="text"]'],
]);

/** The class tokens of the box a template draws. */
function boxClasses(string $template, string $xpath, string $attributes): array
{
    $boxes = domQuery(renderBlade(sprintf($template, $attributes)), $xpath);

    expect($boxes)->not->toBeEmpty('no element at '.$xpath);

    return domClasses($boxes[0]);
}

describe('a disabled text control', function () {
    it('takes the faint border and the sunken fill on its own element', function (string $template, string $xpath) {
        $classes = boxClasses($template, $xpath, 'disabled');

        // the variant forms (`disabled:`, `has-[select:disabled]:`) say it for the controls that are `:disabled` themselves or wrap
        // a `<select>` or `<input>` that is; the listbox trigger and the date picker are not, so they say it plain
        $says = fn (string $utility) => collect($classes)->contains(fn (string $c) => $c === $utility || str_ends_with($c, ':'.$utility));

        expect($says('border-border'))->toBeTrue('border-border')
            ->and($says('bg-surface-sunken'))->toBeTrue('bg-surface-sunken')
            ->and($says('text-fg-subtle'))->toBeTrue('text-fg-subtle');
    })->with('field boxes');

    it('leaves an enabled control the strong border and no sunken fill', function (string $template, string $xpath) {
        $classes = boxClasses($template, $xpath, '');

        expect($classes)->toContain('border-control-border')
            ->not->toContain('border-border')
            ->not->toContain('bg-surface-sunken');
    })->with('field boxes');

    it('is not drawn disabled by a disabled <option> or a disabled button inside it', function () {
        // `has-disabled:` is `:has(:disabled)` and matches an <option disabled>; the variants name the element that is the control
        foreach ([['<atom:select :options="[]" disabled/>', '//*[@data-atom-select-native]/div[1]'], ['<atom:input type="email" multiple disabled/>', '//*[@data-atom-input-email]//*[@data-atom-dropdown-trigger]']] as [$template, $xpath]) {
            $classes = domClasses(domQuery(renderBlade($template), $xpath)[0]);

            expect(collect($classes)->filter(fn ($c) => str_starts_with($c, 'has-disabled:')))->toBeEmpty()
                ->and(collect($classes)->filter(fn ($c) => str_starts_with($c, 'has-[select:disabled]:') || str_starts_with($c, 'has-[input:disabled]:')))->not->toBeEmpty();
        }
    });

    it('does not carry the field-error border, which would out-rank the faint one, on the listbox trigger or the date picker', function () {
        foreach ([['<atom:select variant="listbox" :options="[]" %s/>', '//button[@data-atom-select-combobox]'], ['<atom:date-picker %s/>', '//*[@data-atom-date-picker]//input[@type="text"]']] as [$template, $xpath]) {
            expect(boxClasses($template, $xpath, 'disabled'))->not->toContain(FIELD_ERROR)->not->toContain('border-danger-fg')
                ->and(boxClasses($template, $xpath, 'invalid disabled'))->not->toContain('border-danger-fg')
                ->and(boxClasses($template, $xpath, ''))->toContain(FIELD_ERROR);
        }
    });
});
