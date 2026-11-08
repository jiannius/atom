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

    it('hands the timezone prop to the factory, and leaves it null when unset', function () {
        expect(renderBlade('<atom:date-picker variant="range" timezone="Asia/Kuala_Lumpur" />'))
            ->toContain('dateRange({ time: false, add: false, timezone: \'Asia\\/Kuala_Lumpur\' })')
            ->not->toContain('timezone="');

        expect(renderBlade('<atom:date-picker timezone="America/New_York" />'))
            ->toContain('datePicker({ time: false, timezone: \'America\\/New_York\' })');

        expect(renderBlade('<atom:date-picker variant="range" />'))->toContain('timezone: null');
        expect(renderBlade('<atom:date-picker />'))->toContain('timezone: null');
    });

    it('forwards the timezone to the time picker inside a date-time picker, and only then', function () {
        expect(renderBlade('<atom:date-picker time timezone="Asia/Kuala_Lumpur" />'))
            ->toContain('timePicker({ timezone: &#039;Asia\\/Kuala_Lumpur&#039; })');

        expect(renderBlade('<atom:date-picker variant="range" time timezone="Asia/Kuala_Lumpur" />'))
            ->toContain('timePicker({ timezone: ');

        expect(renderBlade('<atom:date-picker time />'))->toContain('x-data="timePicker()"');
        expect(renderBlade('<atom:time-picker />'))->toContain('x-data="timePicker()"');
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

    it('selects the hour and minute on click instead of stepping them', function () {
        $html = Blade::render('<atom:time-picker />');

        // a host app's report: a click used to step the value, and a number input ignores maxlength.
        expect($html)
            ->toContain('x-on:click.stop="$el.select()"')
            ->toContain('x-on:focus="$el.select()"')
            ->toContain('inputmode="numeric"')
            ->not->toContain('type="number"')
            ->not->toContain('x-on:click.stop="up(\'hr\')"')
            ->not->toContain('x-on:click.stop="up(\'min\')"')
            // AM/PM is a toggle, so it keeps stepping on click.
            ->toContain('x-on:click.stop="up(\'am\')"');
    });
});
