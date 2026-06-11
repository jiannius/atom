<?php

describe('button', function () {
    it('renders a button element with its label and data hook', function () {
        $html = renderBlade('<atom:button>Save</atom:button>');

        expect($html)
            ->toContain('<button')
            ->toContain('type="button"')
            ->toContain('Save')
            ->toContain('data-atom-button');
    });

    it('does not auto-label a button that already has visible text', function () {
        // Regression: the slot text used to be copied into aria-label, which
        // is redundant — the visible text already names the control.
        expect(renderBlade('<atom:button>Save</atom:button>'))
            ->not->toContain('aria-label');
    });

    it('styles variants', function () {
        expect(renderBlade('<atom:button>Default</atom:button>'))->toContain('bg-white');
        expect(renderBlade('<atom:button variant="primary">P</atom:button>'))->toContain('bg-primary');
        expect(renderBlade('<atom:button variant="accent">A</atom:button>'))->toContain('bg-accent');
        expect(renderBlade('<atom:button variant="danger">D</atom:button>'))->toContain('bg-red-500');
        expect(renderBlade('<atom:button variant="ghost">G</atom:button>'))->toContain('bg-transparent');
    });

    it('gives the link variant a dark-mode text colour', function () {
        // Regression: link was text-zinc-800 with no dark: variant, so it was
        // near-invisible on dark backgrounds.
        expect(renderBlade('<atom:button variant="link">L</atom:button>'))
            ->toContain('underline-offset-5')
            ->toContain('dark:text-zinc-200');
    });

    it('tints ghost buttons by colour', function () {
        expect(renderBlade('<atom:button variant="ghost" color="danger">D</atom:button>'))->toContain('text-red-500');
        expect(renderBlade('<atom:button variant="ghost" color="primary">P</atom:button>'))->toContain('text-primary');
    });

    it('sizes the button', function () {
        expect(renderBlade('<atom:button size="xs">x</atom:button>'))->toContain('h-6');
        expect(renderBlade('<atom:button size="sm">s</atom:button>'))->toContain('h-8');
        expect(renderBlade('<atom:button>d</atom:button>'))->toContain('h-10');
        expect(renderBlade('<atom:button size="md">m</atom:button>'))->toContain('h-12');
        expect(renderBlade('<atom:button size="lg">l</atom:button>'))->toContain('h-14');
    });

    it('spans full width when block', function () {
        expect(renderBlade('<atom:button block>B</atom:button>'))->toContain('flex w-full');
    });

    it('renders a leading icon and a trailing icon', function () {
        $html = renderBlade('<atom:button icon="add" iconSuffix="arrow-right">Go</atom:button>');

        expect($html)
            ->toContain('data-atom-icon')
            ->toContain('-ml-0.5'); // the trailing icon spacing
    });

    it('renders a slotless button as an icon-only square', function () {
        $html = renderBlade('<atom:button icon="settings"/>');

        expect($html)
            ->toContain('rounded-lg')
            ->toContain('size-10');
    });

    it('auto-labels an icon-only button from the icon name', function () {
        expect(renderBlade('<atom:button icon="settings"/>'))->toContain('aria-label="Settings"');
        expect(renderBlade('<atom:button icon="arrow-right"/>'))->toContain('aria-label="Arrow Right"');
    });

    it('lets a consumer override the icon-only label', function () {
        $html = renderBlade('<atom:button icon="settings" aria-label="Open settings"/>');

        expect($html)
            ->toContain('aria-label="Open settings"')
            ->not->toContain('aria-label="Settings"');
    });

    it('styles a submit button as primary and wires its loading state', function () {
        $html = renderBlade('<atom:button type="submit">Submit</atom:button>');

        expect($html)
            ->toContain('type="submit"')
            ->toContain('bg-primary')
            ->toContain('wire:loading.class="is-loading"')
            ->toContain('wire:target="submit"')
            ->toContain('data-atom-icon'); // the check icon
    });

    it('auto-wires the delete confirm flow', function () {
        $html = renderBlade('<atom:button type="delete">Delete</atom:button>');

        expect($html)
            ->toContain('bg-red-100') // danger + inverted
            ->toContain('atom.confirm({')
            ->toContain("\$dispatch(&#039;confirmed&#039;)")
            ->toContain('x-on:confirmed="$wire.delete()"');
    });

    it('does not auto-wire the confirm flow when the caller sets its own click', function () {
        $html = renderBlade('<atom:button type="delete" wire:click="remove">Delete</atom:button>');

        expect($html)->not->toContain('atom.confirm({');
    });

    it('renders an anchor when given an href', function () {
        $html = renderBlade('<atom:button href="/foo">Link</atom:button>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/foo"')
            ->toContain('rel="noopener noreferrer"');
    });

    it('opens in a new tab when newtab is set', function () {
        expect(renderBlade('<atom:button href="/foo" newtab>Link</atom:button>'))
            ->toContain('target="_blank"');
    });

    it('builds a whatsapp share link', function () {
        $html = renderBlade("<atom:button :social=\"['name' => 'whatsapp', 'number' => '123', 'text' => 'hi']\">Chat</atom:button>");

        expect($html)
            ->toContain('<a')
            ->toContain('href="https://wa.me/123?text=hi"');
    });

    it('builds a telegram share link', function () {
        $html = renderBlade("<atom:button :social=\"['name' => 'telegram', 'url' => 'https://x.test', 'text' => 'hi']\">Share</atom:button>");

        expect($html)->toContain('href="https://t.me/share/url?url=https://x.test&amp;text=hi"');
    });

    it('uses the brand text colour on hover for an inverted whatsapp button', function () {
        // Regression: the inverted whatsapp variant had hover:text-sky-100
        // copied from telegram instead of hover:text-green-100.
        $html = renderBlade("<atom:button inverted :social=\"['name' => 'whatsapp', 'number' => '123', 'text' => 'hi']\">Chat</atom:button>");

        expect($html)
            ->toContain('hover:text-green-100')
            ->not->toContain('hover:text-sky-100');
    });

    it('wires the loading state for a wire:click button', function () {
        $html = renderBlade('<atom:button wire:click="save">Save</atom:button>');

        expect($html)
            ->toContain('wire:loading.class="is-loading"')
            ->toContain('wire:target="save"');
    });

    it('leaves an explicit wire:loading alone', function () {
        $html = renderBlade('<atom:button wire:click="save" wire:loading.attr="disabled">Save</atom:button>');

        expect($html)->not->toContain('wire:loading.class="is-loading"');
    });
});

describe('button.group', function () {
    it('collapses adjacent button borders', function () {
        $html = renderBlade('<atom:button.group><atom:button>A</atom:button><atom:button>B</atom:button></atom:button.group>');

        expect($html)
            ->toContain('group/group')
            ->toContain('[&amp;_[data-atom-button]:not(:first-child)]:-ml-px');
    });

    it('uses a plain gap layout when gap is set', function () {
        $html = renderBlade('<atom:button.group gap><atom:button>A</atom:button></atom:button.group>');

        expect($html)
            ->toContain('gap-3')
            ->not->toContain('[&amp;_[data-atom-button]:not(:first-child)]:-ml-px');
    });

    it('no longer emits the dead buttons group selector', function () {
        // Regression: index.blade.php carried group-[]/buttons:* classes that
        // had no matching ancestor (the group is named group/group).
        expect(renderBlade('<atom:button>A</atom:button>'))
            ->not->toContain('group-[]/buttons');
    });
});
