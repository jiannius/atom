<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('textarea', function () {
    it('renders a textarea with the given rows', function () {
        $html = Blade::render('<atom:textarea rows="5" placeholder="Notes" />');

        expect($html)
            ->toContain('data-atom-textarea')
            ->toContain('rows="5"')
            ->toContain('placeholder="Notes"');
    });

    it('wires autosize when autoresize is set', function () {
        $html = Blade::render('<atom:textarea autoresize />');

        expect($html)
            ->toContain('x-autosize')
            ->toContain('$autosize()');
    });

    it('wraps with label, caption and error via the field', function () {
        $html = renderBlade('<atom:textarea label="Bio" caption="Max 200" error="Too long" />');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('Bio')
            ->toContain('data-atom-caption')
            ->toContain('data-atom-error')
            ->toContain('Too long');
    });

    it('drops the box chrome for the transparent variant', function () {
        $html = Blade::render('<atom:textarea variant="transparent" />');

        expect($html)
            ->toContain('bg-transparent')
            ->toContain('border-0');
    });
});
