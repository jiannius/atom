<?php

describe('input.otp', function () {
    it('renders the otp factory bound through x-modelable with N numeric boxes', function () {
        $html = renderBlade('<atom:input.otp wire:model="code"/>');

        expect($html)
            ->toContain('data-atom-input-otp')
            ->toContain('x-data="otp({ length: 6, submit: null })"')
            ->toContain('x-modelable="code"')
            ->toContain('wire:model="code"')
            ->toContain('inputmode="numeric"')
            ->toContain('maxlength="1"')
            // per-box wiring; input events are stopped so they do not bubble (LW4 .self)
            ->toContain('x-on:input.stop="onInput(0, $event)"')
            ->toContain('x-model="digits[0]"')
            ->toContain('x-model="digits[5]"');

        expect(substr_count($html, 'data-atom-input-otp-box'))->toBe(6);
    });

    it('honours the length prop', function () {
        $html = renderBlade('<atom:input.otp :length="4"/>');

        expect($html)->toContain('length: 4');
        expect(substr_count($html, 'data-atom-input-otp-box'))->toBe(4);
    });

    it('masks the boxes when masked', function () {
        expect(renderBlade('<atom:input.otp masked/>'))
            ->toContain('type="password"')
            ->not->toContain('type="text"');
    });

    it('inserts a separator between groups', function () {
        // length 6, groups of 3 => one spacer after the third box
        expect(substr_count(renderBlade('<atom:input.otp :groups="3"/>'), 'w-2'))->toBe(1);
        expect(substr_count(renderBlade('<atom:input.otp/>'), 'w-2'))->toBe(0);
    });

    it('forwards a submit method name into the factory', function () {
        expect(renderBlade('<atom:input.otp submit="verify"/>'))
            ->toContain("submit: 'verify'");
    });

    it('flags the invalid state', function () {
        expect(renderBlade('<atom:input.otp invalid/>'))->toContain('border-red-400');
    });
});
