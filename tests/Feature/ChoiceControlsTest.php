<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('checkbox', function () {
    it('renders a checkbox and applies the name from wire:model', function () {
        // Regression: the component built $merges with the name but rendered
        // {{ $attributes }} instead of {{ $attributes->merge($merges) }}.
        $html = renderBlade('<atom:checkbox wire:model="agree" label="I agree" />');

        expect($html)
            ->toContain('data-atom-checkbox')
            ->toContain('type="checkbox"')
            ->toContain('name="agree"')
            ->toContain('I agree')
            ->toContain('role="checkbox"');
    });

    it('renders a caption', function () {
        $html = renderBlade('<atom:checkbox label="x" caption="Helper" />');

        expect($html)->toContain('data-atom-caption')->toContain('Helper');
    });
});

describe('radio', function () {
    it('uses role=radio (not checkbox) and the matching error group', function () {
        $html = renderBlade('<atom:radio label="Option A" />');

        expect($html)
            ->toContain('data-atom-radio')
            ->toContain('type="radio"')
            ->toContain('role="radio"')
            ->not->toContain('role="checkbox"')
            ->toContain('group-has-[.error]/radio:outline-1')
            ->not->toContain('group-has-[.error]/checkbox');
    });
});

describe('toggle', function () {
    it('renders a checkbox-backed toggle with the name applied', function () {
        $html = renderBlade('<atom:toggle wire:model="enabled" label="Enabled" />');

        expect($html)
            ->toContain('data-atom-toggle')
            ->toContain('type="checkbox"')
            ->toContain('name="enabled"')
            ->toContain('Enabled');
    });
});
