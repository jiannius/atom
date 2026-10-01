<?php

use Illuminate\Support\Facades\Blade;

/*
 * Dark-mode contrast guards (humblebear#337). Measured in a real browser on
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

    it('darkens the ground behind every coloured badge instead of lightening it', function () {
        foreach (['red', 'blue', 'yellow', 'orange', 'green', 'purple', 'gray'] as $color) {
            $html = Blade::render('<atom:badge color="'.$color.'" label="x" />');

            expect($html)
                ->toMatch('/dark:bg-[a-z]+-500\/15/')
                ->not->toMatch('/dark:bg-[a-z]+-100\/30/');
        }
    });

    it('gives the stats indicator a dark variant for both directions', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        expect(Blade::render('<atom:card variant="stats" heading="Sales" :indicator="5" data="1" />'))
            ->toContain('text-green-500 dark:text-green-400');

        expect(Blade::render('<atom:card variant="stats" heading="Sales" :indicator="-5" data="1" />'))
            ->toContain('text-red-500 dark:text-red-400');
    });

    it('draws the filter trigger border above 3:1 in dark mode', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = Blade::render('<atom:select variant="filter" label="Status" :options="[]" />');

        expect($html)
            ->toContain('border border-zinc-200 dark:border-zinc-500')
            ->not->toContain('dark:border-white/10');
    });
});
