<?php

use Illuminate\Support\Facades\Blade;

describe('tabs', function () {
    it('binds wire:model through x-modelable, not bubbled input events', function () {
        // Regression (LW4): wire:model now adds `.self` to its underlying
        // x-model, so a child <button> dispatching `input` no longer reaches
        // the <div wire:model>. The tabs expose a reactive `value` via
        // x-modelable instead — the same mechanism <atom:select> uses.
        $html = Blade::render(<<<'BLADE'
            <atom:tabs wire:model="tab" :tabs="[
                ['label' => 'Overview', 'value' => 'overview'],
                ['label' => 'Activity', 'value' => 'activity'],
            ]" />
        BLADE);

        expect($html)
            ->toContain('data-atom-tabs')
            ->toContain('Overview')
            ->toContain('Activity')
            ->toContain('x-modelable="value"')
            ->toContain('x-data="{ value: $wire.tab }"')
            ->toContain('x-on:tabs-input="value = $event.detail"')
            ->toContain("\$dispatch('tabs-input', 'overview')")
            ->toContain('x-bind:data-active="value === \'overview\'"')
            // the old, .self-broken wiring must be gone
            ->not->toContain("\$dispatch('input',")
            ->not->toContain('$wire.tab ===');
    });

    it('renders the button variant as a sunken track with the current tab as the surface', function () {
        $html = Blade::render('<atom:tabs variant="button" :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)
            ->toContain('bg-surface-sunken')
            ->toContain('rounded-control')
            ->toContain('data-[active]:bg-surface')
            ->toContain('data-[active]:border-border-strong')
            // a track and a segment are drawn with a fill and a hairline: no shadow
            ->not->toMatch('/\bshadow-/');
    });

    it('rules the default variant underneath and underlines the current tab', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)
            ->toContain('border-b border-border')
            ->toContain('data-[active]:border-fg')
            ->not->toContain('bg-surface-sunken');
    });

    it('takes its heights from the control token', function (string $tag, string $height) {
        expect(Blade::render($tag))->toContain($height);
    })->with([
        'line' => ['<atom:tabs :tabs="[]" />', 'h-control-lg'],
        'line, sm' => ['<atom:tabs size="sm" :tabs="[]" />', 'h-control-md'],
        'segments' => ['<atom:tabs variant="button" :tabs="[]" />', 'h-control-md'],
        'segments, sm' => ['<atom:tabs variant="button" size="sm" :tabs="[]" />', 'h-control '],
    ]);

    it('reads the label from role tokens, so no theme has a half to keep in step', function () {
        // Regression: the label was `text-muted-foreground dark:text-muted`, the pair inverted, for 2.3:1 on the strip. A role
        // token is one name for both themes: fg-muted when idle (6:1 on the track and on the page), fg when current.
        $html = Blade::render('<atom:tabs variant="button" :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)
            ->toContain('text-fg-muted')
            ->toContain('data-[active]:text-fg')
            ->not->toContain('dark:')
            ->not->toMatch('/\btext-muted(?:-foreground)?\b/');
    });

    it('marks the current tab active', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'value\' => \'a\', \'current\' => true]]" />');

        // the attribute on the tab itself: every tab carries `not-[data-active]:truncate`, so a substring says nothing
        expect($html)->toMatch('/<button\b[^>]*\sdata-active(?=[\s>=])[^>]*>/');
    });

    it('opens a tab given as an array in a new tab, as its newtab key says', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'href\' => \'/a\', \'newtab\' => true]]" />');

        expect($html)->toMatch('/<a\b[^>]*\starget="_blank"/');
    });

    it('marks no tab active when none is current', function () {
        $html = Blade::render('<atom:tabs :tabs="[[\'label\' => \'A\', \'value\' => \'a\']]" />');

        expect($html)->not->toMatch('/<button\b[^>]*\sdata-active(?=[\s>=])[^>]*>/');
    });

    it('stays static — no alpine state — without wire:model', function () {
        // The second supported use case: slot items as plain buttons/links with
        // no Livewire binding. No x-data/x-modelable should be emitted.
        $html = Blade::render(<<<'BLADE'
            <atom:tabs>
                <atom:tabs.item label="Profile" current/>
                <atom:tabs.item label="Billing" href="/billing"/>
            </atom:tabs>
        BLADE);

        expect($html)
            ->toContain('data-atom-tabs')
            ->toMatch('/<button\b[^>]*\sdata-active(?=[\s>=])[^>]*>\s*Profile/')  // current
            ->not->toMatch('/<a\b[^>]*\sdata-active(?=[\s>=])/')
            ->toContain('href="/billing"')          // link tab renders an <a>
            ->not->toContain('x-modelable')
            ->not->toContain('x-data')
            ->not->toContain('tabs-input');
    });
});
