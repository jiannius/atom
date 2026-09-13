<?php

use Illuminate\Support\ViewErrorBag;

/**
 * Every labelled control has to end up with an accessible name, and there is more than
 * one way to get one: a real form control takes <label for>, a composed widget is named
 * the other way round with aria-labelledby, and a control with no visible label needs
 * aria-label. What must never happen is a visible label that names nothing — the field
 * is then announced as an unnamed "edit text", which is what smgdms reported (#65).
 *
 * These are the server-rendered halves. tiptap's attributes are applied by ProseMirror at
 * runtime, so that one is asserted in tests/e2e/tiptap.spec.js.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/** Pull the id off the field's <label>, if it carries one. */
function labelId(string $html): ?string
{
    preg_match('/<label[^<]*\bid="([^"]+)"/', $html, $m);

    return $m[1] ?? null;
}

/** Pull the `for` off the field's <label>, if it carries one. */
function labelFor(string $html): ?string
{
    preg_match('/<label[^<]*\bfor="([^"]+)"/', $html, $m);

    return $m[1] ?? null;
}

describe('accessible names', function () {
    it('names a real form control with label[for] pointing at the control itself', function (string $template, string $tag) {
        $html = renderBlade($template);
        $for = labelFor($html);

        expect($for)->not->toBeNull('the label has no for attribute');

        // a wrapper <div> carrying the id names nothing, while still looking correct to
        // any check that only asks whether `for` resolves to some element
        expect($html)->toMatch('/<'.$tag.'[^<]*\bid="'.preg_quote($for, '/').'"/');
    })->with([
        'select native' => ['<atom:select label="Country" wire:model="c" :options="[]" />', 'select'],
        'date-picker' => ['<atom:date-picker label="Due date" wire:model="d" />', 'input'],
    ]);

    it('names a composed widget with aria-labelledby back at the label', function (string $template, string $role) {
        $html = renderBlade($template);
        $id = labelId($html);

        expect($id)->not->toBeNull('the label carries no id for the widget to point at');

        // exactly one element claims the name: two would be a duplicate announcement
        expect(preg_match_all('/aria-labelledby="'.preg_quote($id, '/').'"/', $html))
            ->toBe(1, 'expected exactly one element to point at the label');

        // and it is the element carrying the role, not an outer wrapper
        expect($html)->toMatch('/role="'.$role.'"[\s\S]{0,400}?aria-labelledby="'.preg_quote($id, '/').'"/');
    })->with([
        'select listbox' => ['<atom:select variant="listbox" label="Country" wire:model="c" :options="[]" />', 'combobox'],
        'select listbox searchable' => ['<atom:select variant="listbox" searchable label="Country" wire:model="c" :options="[]" />', 'combobox'],
        'radio.group' => ['<atom:radio.group label="Plan" wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>', 'radiogroup'],
        'time-picker' => ['<atom:time-picker label="Start time" wire:model="t" />', 'group'],
    ]);

    // A native <select> is named by `for`. A second anchor on the label would be an id
    // nothing points at, and an id is a Livewire morph key, so it is not free.
    it('does not mint a label id for a control that label[for] already names', function () {
        $html = renderBlade('<atom:select label="Country" wire:model="c" :options="[]" />');

        expect(labelFor($html))->not->toBeNull()
            ->and(labelId($html))->toBeNull('the label carries an id nothing references');
    });

    it('names the three time-picker inputs separately, since one for cannot reach them', function () {
        $html = renderBlade('<atom:time-picker label="Start time" wire:model="t" />');

        expect($html)
            ->toContain('aria-label="Hour"')
            ->toContain('aria-label="Minute"')
            ->toContain('aria-label="AM or PM"');
    });

    it('names the table search box from its placeholder, having no visible label', function () {
        $html = renderBlade('<atom:table.search />');

        expect($html)->toContain('aria-label="Search"');
    });

    it('hands tiptap the anchor through its editor config, since ProseMirror owns the contenteditable', function () {
        $html = renderBlade('<atom:tiptap label="Body" wire:model="b" />');
        $id = labelId($html);

        // @js() picks its own quoting, so match the value rather than a literal string
        expect($id)->not->toBeNull()
            ->and(html_entity_decode($html))->toMatch('/labelledby:\s*[\'"]'.preg_quote($id, '/').'[\'"]/');
    });

    // Same reason as <atom:input>: the id doubles as Livewire's morph key, so one that
    // churns per render makes the morph replace the control instead of patching it.
    it('mints the same anchors on every render', function (string $template) {
        $ids = fn ($html) => preg_match_all('/(?:id|aria-labelledby)="(atom-[^"]+)"/', $html, $m) ? $m[1] : [];

        expect($ids(renderBlade($template)))
            ->not->toBeEmpty('no anchors were minted at all')
            ->toBe($ids(renderBlade($template)), 'the anchors churned between two renders');
    })->with([
        'select native' => ['<atom:select label="Country" wire:model="c" :options="[]" />'],
        'select listbox' => ['<atom:select variant="listbox" label="Country" wire:model="c" :options="[]" />'],
        'date-picker' => ['<atom:date-picker label="Due date" wire:model="d" />'],
        'time-picker' => ['<atom:time-picker label="Start time" wire:model="t" />'],
        'radio.group' => ['<atom:radio.group label="Plan" wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>'],
        'tiptap' => ['<atom:tiptap label="Body" wire:model="b" />'],
    ]);

    // Nothing points at them, and an id is what drags an element into the morph swap.
    it('mints no anchors for a control with no label', function (string $template) {
        expect(renderBlade($template))->not->toMatch('/(?:id|aria-labelledby)="atom-(?:select|date-picker|time-picker|radio-group|tiptap)-/');
    })->with([
        'select native' => ['<atom:select wire:model="c" :options="[]" />'],
        'select listbox' => ['<atom:select variant="listbox" wire:model="c" :options="[]" />'],
        'date-picker' => ['<atom:date-picker wire:model="d" />'],
        'time-picker' => ['<atom:time-picker wire:model="t" />'],
        'radio.group' => ['<atom:radio.group wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>'],
        'tiptap' => ['<atom:tiptap wire:model="b" />'],
    ]);
});
