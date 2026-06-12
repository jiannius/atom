<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('date-picker', function () {
    it('renders the date variant driven by the datePicker() factory', function () {
        $html = renderBlade('<atom:date-picker wire:model="published_at" />');

        expect($html)
            ->toContain('data-atom-date-picker')
            ->toContain('datePicker(')
            ->toContain('data-atom-date-picker-calendar');
    });

    it('renders the range variant driven by the dateRange() factory', function () {
        $html = renderBlade('<atom:date-picker variant="range" />');

        expect($html)
            ->toContain('data-atom-date-range')
            ->toContain('dateRange(')
            ->toContain('Last 7 Days');   // a preset shortcut
    });

    it('wraps with a label via the field', function () {
        $html = renderBlade('<atom:date-picker label="When" wire:model="d" />');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('When');
    });
});

describe('time-picker', function () {
    it('renders the timePicker() factory with hour/minute/meridiem inputs', function () {
        $html = Blade::render('<atom:time-picker />');

        expect($html)
            ->toContain('timePicker(')
            ->toContain('x-model.lazy="hr"')
            ->toContain('x-model.lazy="min"')
            ->toContain('x-bind:value="am"');
    });
});
