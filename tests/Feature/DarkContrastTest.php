<?php

use Illuminate\Support\Facades\Blade;

/*
 * Dark-mode contrast guards (a host app's report). Measured in a real browser on
 * zinc-900 and zinc-800 grounds, text >= 4.5:1 and a control boundary >= 3:1.
 * These only pin the class strings that measurement was taken from.
 */
describe('dark-mode contrast', function () {
    it('gives the subheading and the caption a dark text colour', function () {
        expect(Blade::render('<atom:subheading>Sub</atom:subheading>'))
            ->toContain('text-zinc-500 dark:text-zinc-400');

        expect(Blade::render('<atom:caption>Cap</atom:caption>'))
            ->toContain('text-zinc-500 dark:text-zinc-400');
    });

    it('reads every badge colour from a role token pair, so no colour has a light half and a dark half to keep in step', function () {
        foreach (['red', 'blue', 'yellow', 'orange', 'green', 'purple', 'gray', 'amber', 'indigo', 'black'] as $color) {
            $html = Blade::render('<atom:badge color="'.$color.'" label="x" />');

            expect($html)
                ->toMatch('/\bbg-(?:neutral-bg|info-bg|success-bg|warning-bg|danger-bg|accent-soft|fg)\b/')
                ->not->toContain('dark:');
        }
    });

    it('reads the stats indicator from the success and danger tokens for both directions', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        expect(Blade::render('<atom:card variant="stats" heading="Sales" :indicator="5" data="1" />'))
            ->toContain('text-success-fg')
            ->not->toContain('dark:text-green');

        expect(Blade::render('<atom:card variant="stats" heading="Sales" :indicator="-5" data="1" />'))
            ->toContain('text-danger-fg')
            ->not->toContain('dark:text-red');
    });

    it('draws the filter trigger border above 3:1 in dark mode', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = Blade::render('<atom:select variant="filter" label="Status" :options="[]" />');

        expect($html)
            ->toContain('border border-control-border')
            ->not->toContain('dark:border-white/10');
    });
});
