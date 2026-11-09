<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/*
 * Issue #178: in dark a disabled field was told from an enabled one by its sunken fill alone. Every text control's disabled
 * state also takes the faint hairline (`border-border`) in place of the strong one (`border-control-border`). The browser
 * proves the colours (tests/e2e/disabled-controls.spec.js); these hold that each control still says so in its markup.
 */
describe('a disabled text control', function () {
    it('mutes its border where the disabled attribute is on the field itself', function (string $template) {
        expect(renderBlade($template))->toContain('disabled:border-border disabled:bg-surface-sunken disabled:text-fg-subtle');
    })->with([
        'input' => '<atom:input disabled/>',
        'password' => '<atom:input type="password" disabled/>',
        'textarea' => '<atom:textarea disabled/>',
        'tel' => '<atom:input type="tel" disabled/>',
        'otp' => '<atom:input.otp disabled/>',
    ]);

    it('mutes the border of the box around a disabled field', function (string $template, string $needle) {
        expect(renderBlade($template))->toContain($needle);
    })->with([
        'native select' => ['<atom:select :options="[]" disabled/>', 'has-disabled:border-border has-disabled:bg-surface-sunken'],
        'email' => ['<atom:input type="email" multiple disabled/>', 'has-disabled:border-border has-disabled:bg-surface-sunken'],
    ]);

    it('draws the listbox trigger and the date picker sunken with the faint border, and an enabled one as before', function () {
        $listbox = fn (string $attrs) => renderBlade('<atom:select variant="listbox" :options="[]" '.$attrs.'/>');
        $date = fn (string $attrs) => renderBlade('<atom:date-picker '.$attrs.'/>');

        foreach ([$listbox('disabled'), $date('disabled')] as $html) {
            expect($html)->toContain('border-border')->toContain('bg-surface-sunken')->not->toContain(' border-control-border');
        }

        foreach ([$listbox(''), $date('')] as $html) {
            expect($html)->toContain('border-control-border')->not->toContain(' border-border ');
        }
    });

    it('leaves an enabled field the strong border', function () {
        expect(renderBlade('<atom:input/>'))->toContain('border-control-border');
    });
});
