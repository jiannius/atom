<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('slider', function () {
    it('renders a native range input bound through x-modelable', function () {
        $html = renderBlade('<atom:slider wire:model="volume" label="Volume"/>');

        expect($html)
            ->toContain('data-atom-slider')
            ->toContain('data-atom-slider-input')
            ->toContain('type="range"')
            ->toContain('x-data="slider({ min: 0, max: 100, step: 1, value: 0 })"')
            ->toContain('x-modelable="value"')
            ->toContain('x-model="value"')
            ->toContain('wire:model="volume"')
            ->toContain('Volume');
    });

    it('honours min, max, step and value props', function () {
        $html = renderBlade('<atom:slider :min="10" :max="20" :step="2" :value="14"/>');

        expect($html)
            ->toContain('slider({ min: 10, max: 20, step: 2, value: 14 })')
            ->toContain('min="10"')
            ->toContain('max="20"')
            ->toContain('step="2"');
    });

    it('shows the value bubble only when the bubble prop is set', function () {
        expect(renderBlade('<atom:slider bubble/>'))->toContain('data-atom-slider-bubble');
        expect(renderBlade('<atom:slider/>'))->not->toContain('data-atom-slider-bubble');
    });

    it('shows min/max labels only when the labels prop is set', function () {
        expect(renderBlade('<atom:slider labels :min="5" :max="50"/>'))
            ->toContain('data-atom-slider-labels')
            ->toContain('>5<')
            ->toContain('>50<');

        expect(renderBlade('<atom:slider/>'))->not->toContain('data-atom-slider-labels');
    });

    it('forwards the disabled state to the input', function () {
        expect(renderBlade('<atom:slider disabled/>'))->toContain('disabled');
    });

    it('renders caption and error chrome', function () {
        expect(renderBlade('<atom:slider caption="Pick a level."/>'))->toContain('Pick a level.');
        expect(renderBlade('<atom:slider error="This field is required."/>'))->toContain('This field is required.');
    });

    it('derives the same id on every render when it has no id, name or wire:model', function () {
        $id = fn (string $template) => preg_match('/<input\\s[^>]*id="([^"]+)"/s', renderBlade($template), $m) ? $m[1] : null;

        $first = $id('<atom:slider label="Volume"/>');

        expect($first)->toStartWith('atom-slider-')
            ->and($id('<atom:slider label="Volume"/>'))->toBe($first)
            ->and($id('<atom:slider label="Volume"/>'))->toBe($first)
            ->and($id('<atom:slider label="Brightness"/>'))->not->toBe($first)
            ->and($id('<atom:slider label="Volume" id="mine"/>'))->toBe('mine')
            ->and($id('<atom:slider label="Volume" wire:model="level"/>'))->toBe('atom-slider-level');
    });

    it('keys an unnamed slider by its x-model target', function () {
        $id = fn (string $model) => preg_match('/<input\\s[^>]*id="([^"]+)"/s', renderBlade('<atom:slider x-model="'.$model.'"/>'), $m) ? $m[1] : null;

        expect($id('a'))->toBe($id('a'))
            ->and($id('a'))->not->toBe($id('b'));
    });
});
