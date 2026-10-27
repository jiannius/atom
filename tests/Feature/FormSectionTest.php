<?php

// V4-2c: form.section and the save bar (form.actions).

describe('form.section', function () {
    it('prints a heading, a line of help and the fields', function () {
        $html = renderBlade('<atom:form.section heading="Customer" subheading="Who you are billing."><input id="a"><input id="b"></atom:form.section>');

        expect(trim(domQuery($html, '//*[@data-atom-form-section-heading]')[0]->textContent))->toBe('Customer')
            ->and(domQuery($html, '//*[@data-atom-form-section-heading]')[0]->nodeName)->toBe('h2')
            ->and(trim(domQuery($html, '//*[@data-atom-form-section-subheading]')[0]->textContent))->toBe('Who you are billing.')
            ->and(domQuery($html, '//*[@data-atom-form-section-fields]/input'))->toHaveCount(2);
    });

    it('is the aside layout by default: a 220px label column from 768px, stacked below', function () {
        $html = renderBlade('<atom:form.section heading="Details">x</atom:form.section>');
        $section = domQuery($html, '//section[@data-atom-form-section]')[0];
        $grid = domQuery($html, '//section/div')[0];

        expect($section->getAttribute('data-layout'))->toBe('aside')
            ->and(domClasses($section))->toContain('@container')
            ->and(domClasses($grid))->toContain('@3xl:grid-cols-[220px_minmax(0,1fr)]');
    });

    it('stacks the heading above the fields whatever the width, with `stacked`', function () {
        $html = renderBlade('<atom:form.section layout="stacked" heading="Details">x</atom:form.section>');

        expect(domQuery($html, '//section')[0]->getAttribute('data-layout'))->toBe('stacked')
            ->and(implode(' ', domClasses(domQuery($html, '//section/div')[0])))->not->toContain('220px');
    });

    it('takes any other layout for aside', function () {
        expect(domQuery(renderBlade('<atom:form.section layout="wild" heading="x">x</atom:form.section>'), '//section')[0]->getAttribute('data-layout'))->toBe('aside');
    });

    it('lays the fields out two across once their own box is wide enough', function () {
        $fields = domQuery(renderBlade('<atom:form.section heading="x">x</atom:form.section>'), '//*[@data-atom-form-section-fields]')[0];

        expect(domClasses($fields))->toContain('grid', 'grid-cols-1', '@md:grid-cols-2')
            ->and(domClasses(domQuery(renderBlade('<atom:form.section>x</atom:form.section>'), '//*[@data-atom-form-section-body]')[0]))->toContain('@container');
    });

    it('draws no label column for a section with no heading or help', function () {
        $html = renderBlade('<atom:form.section>x</atom:form.section>');

        expect(domQuery($html, '//*[@data-atom-form-section-label]'))->toBeEmpty()
            ->and(implode(' ', domClasses(domQuery($html, '//section/div')[0])))->not->toContain('220px');
    });

    it('is separated from the one before by a hairline, and spans its parent grid', function () {
        $section = domQuery(renderBlade('<atom:form.section heading="x">x</atom:form.section>'), '//section')[0];

        expect(domClasses($section))->toContain('border-t', 'pt-6', 'first:border-t-0', 'first:pt-0', 'col-span-full');
    });

    it('prints a footer under the fields, right-aligned, for a per-section save', function () {
        $html = renderBlade('<atom:form.section heading="x">f<x-slot:footer><button id="s">Save</button></x-slot:footer></atom:form.section>');
        $footer = domQuery($html, '//*[@data-atom-form-section-footer]');

        expect($footer)->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-form-section-footer]/button[@id="s"]'))->toHaveCount(1)
            ->and(domClasses($footer[0]))->toContain('justify-end')
            // after the fields, inside the same column
            ->and(domQuery($html, '//*[@data-atom-form-section-body]/*[@data-atom-form-section-fields]/following-sibling::*[@data-atom-form-section-footer]'))->toHaveCount(1);
    });

    it('has no footer without one', function () {
        expect(domQuery(renderBlade('<atom:form.section heading="x">x</atom:form.section>'), '//*[@data-atom-form-section-footer]'))->toBeEmpty();
    });

    it('takes a heading level, and nothing outside 2 to 4', function (mixed $level, string $tag) {
        $html = renderBlade('<atom:form.section heading="x" :level="$l">x</atom:form.section>', ['l' => $level]);

        expect(domQuery($html, '//*[@data-atom-form-section-heading]')[0]->nodeName)->toBe($tag);
    })->with([[2, 'h2'], [3, 'h3'], [4, 'h4'], [1, 'h2'], [9, 'h2'], ['3', 'h3']]);

    it('prints the heading and the help as text, and the caller class beside its own', function () {
        $evil = '<img src=x onerror=alert(1)>';
        $html = renderBlade('<atom:form.section :heading="$e" :subheading="$e" class="mt-9">x</atom:form.section>', ['e' => $evil]);

        expect(domQuery($html, '//section//img'))->toBeEmpty()
            ->and(domClasses(domQuery($html, '//section')[0]))->toContain('mt-9', 'border-t');
    });

    it('works in a form, a form grid and a form modal', function () {
        $inForm = renderBlade('<atom:form><atom:form.section heading="x">a</atom:form.section></atom:form>');
        $inGrid = renderBlade('<atom:form.grid cols="1"><atom:form.section layout="stacked" heading="x">a</atom:form.section></atom:form.grid>');
        $inModal = renderBlade('<atom:form.modal name="m" cols="1"><atom:form.section layout="stacked" heading="x">a</atom:form.section></atom:form.modal>');

        expect(domQuery($inForm, '//form//section'))->toHaveCount(1)
            ->and(domQuery($inGrid, '//section'))->toHaveCount(1)
            ->and(domQuery($inModal, '//dialog//form//section[@data-layout="stacked"]'))->toHaveCount(1)
            ->and(domQuery($inModal, '//dialog//form/*[@data-atom-form-actions]'))->toHaveCount(1);
    });
});

describe('save bar', function () {
    it('is the plain footer without `bar` or `sticky`', function () {
        $bar = domQuery(renderBlade('<atom:form.actions />'), '//*[@data-atom-form-actions]')[0];

        expect(domClasses($bar))->toContain('justify-between', '[&>:only-child]:ms-auto')->not->toContain('sticky')
            ->and($bar->hasAttribute('data-atom-save-bar'))->toBeFalse()
            ->and(domQuery(renderBlade('<atom:form.actions />'), '//button[@type="submit"]'))->toHaveCount(1);
    });

    it('is a sticky card: a solid surface, a border, the panel radius and the pop shadow, 0.75rem above the bottom of the screen', function () {
        $bar = domQuery(renderBlade('<atom:form.actions bar />'), '//*[@data-atom-save-bar]')[0];
        $classes = domClasses($bar);

        expect($classes)->toContain('sticky', 'isolate', 'z-10', 'flex-wrap', 'gap-2', 'rounded-panel', 'border', 'border-border', 'bg-surface', 'shadow-pop', 'px-4', 'py-3')
            ->and(implode(' ', $classes))->toContain('bottom-[calc(0.75rem_+_env(safe-area-inset-bottom,0px))]')
            // no blur, no transparency, no pseudo-element strip, no bleed, no hairline above it
            ->and(implode(' ', $classes))->not->toContain('before:')->not->toContain('after:')->not->toContain('backdrop-blur')->not->toContain('bg-surface/')
            ->and($classes)->not->toContain('border-t', '-bottom-6', 'bottom-0', 'justify-between', '[&>:only-child]:ms-auto');
    });

    it('leaves no strip behind in atom.css: no bleed variables, clip-path or box-shadow spread on the bar', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->not->toContain('--atom-bar-left')->not->toContain('--atom-bar-right')
            ->and($css)->not->toContain('100vmax')
            ->and($css)->not->toMatch('/\\[data-atom-save-bar\\][^{]*\\{/');
    });

    it('takes the page\'s bottom room as its own, less the 0.75rem it floats by, and has no gutter margin or padding', function () {
        $html = renderBlade('<atom:form.actions bar />');
        $classes = implode(' ', domClasses(domQuery($html, '//*[@data-atom-save-bar]')[0]));

        expect($classes)->toContain('mb-[calc((var(--atom-page-pb,0px)_-_0.75rem_-_env(safe-area-inset-bottom,0px))*-1)]')
            ->and($classes)->not->toContain('--atom-gutter')
            ->and($classes)->not->toContain('ms-[')->not->toContain('me-[')->not->toContain('ps-[')->not->toContain('pe-[');
    });

    it('prints the start slot at the start, and the buttons at the end', function () {
        $html = renderBlade('<atom:form.actions bar><x-slot:start><span id="t">Total</span></x-slot:start><button id="b" type="submit">Save</button></atom:form.actions>');

        expect(domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-actions-start]/span[@id="t"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-actions-buttons]/button[@id="b"]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-actions-start]')[0]))->toContain('tabular-nums', 'text-fg-muted', 'gap-x-3.5', 'flex-wrap', '[&_:is(b,strong)]:font-semibold', '[&_:is(b,strong)]:text-fg', '[&_:is(b,strong)]:tabular-nums')
            ->and(domQuery($html, '//*[@data-atom-form-actions-start]/following-sibling::*[@data-atom-form-actions-buttons]'))->toHaveCount(1);
    });

    it('prints no "Unsaved changes" label unless `dirty` is on, and the bar is there either way', function () {
        $off = renderBlade('<atom:form.actions bar><x-slot:start>Total</x-slot:start></atom:form.actions>');
        $on = renderBlade('<atom:form.actions bar dirty><x-slot:start>Total</x-slot:start></atom:form.actions>');

        expect(domQuery($off, '//*[@data-atom-form-dirty]'))->toBeEmpty()
            ->and($off)->not->toContain('formDirty')->not->toContain('Unsaved changes')
            ->and(domQuery($off, '//*[@data-atom-save-bar]'))->toHaveCount(1)
            ->and(domQuery($on, '//*[@data-atom-save-bar]'))->toHaveCount(1);

        $label = domQuery($on, '//*[@data-atom-form-actions-start]/*[@data-atom-form-dirty]');

        expect($label)->toHaveCount(1)
            ->and($label[0]->textContent)->toBe('Unsaved changes')
            ->and($label[0]->getAttribute('x-show'))->toBe('dirty')
            ->and($label[0]->hasAttribute('x-cloak'))->toBeTrue()
            // after what the start slot holds; the formDirty scope is the label's own, not the group's that holds the host's slot
            ->and($label[0]->getAttribute('x-data'))->toBe('formDirty')
            ->and(domQuery($on, '//*[@data-atom-form-actions-start][@x-data]'))->toBeEmpty()
            ->and(domQuery($on, '//*[@data-atom-form-actions-start]')[0]->textContent)->toContain('Total')
            ->and(domQuery($on, '//*[@data-atom-form-actions-start]/*[last()]')[0]->hasAttribute('data-atom-form-dirty'))->toBeTrue();
    });

    it('draws the label with no start slot too, as the bar\'s own start with no group around it, and translates it', function () {
        app('translator')->setLoaded(['*' => ['*' => ['en' => ['Unsaved changes' => 'Perubahan belum disimpan']]]]);

        $html = renderBlade('<atom:form.actions bar dirty />');
        $label = domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-dirty]');

        expect($label)->toHaveCount(1)
            ->and($label[0]->getAttribute('x-show'))->toBe('dirty')
            ->and($label[0]->getAttribute('x-data'))->toBe('formDirty')
            ->and(domQuery($html, '//*[@data-atom-form-actions-start]'))->toBeEmpty()
            ->and($label[0]->textContent)->toBe('Perubahan belum disimpan');
    });

    it('starts a dialog from no page bottom room, so a bar inside one is not pulled out of it', function () {
        $dialog = domQuery(renderBlade('<atom:modal name="m">x</atom:modal>'), '//dialog')[0];

        expect(domClasses($dialog))->toContain('[--atom-page-pb:0px]');
    });

    it('ignores `dirty` outside a page save bar', function () {
        expect(renderBlade('<atom:form.actions dirty />'))->not->toContain('data-atom-form-dirty')
            ->and(renderBlade('<atom:form.actions sticky dirty />'))->not->toContain('data-atom-form-dirty');
    });

    it('has no start group without a start slot, and a default Save without buttons', function () {
        $html = renderBlade('<atom:form.actions bar />');

        expect(domQuery($html, '//*[@data-atom-form-actions-start]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-form-actions-buttons]/button[@type="submit"]'))->toHaveCount(1);
    });

    it('makes the buttons full width on a phone, the submit growing', function () {
        $buttons = domQuery(renderBlade('<atom:form.actions bar />'), '//*[@data-atom-form-actions-buttons]')[0];

        expect(domClasses($buttons))->toContain('w-full', 'sm:w-auto', 'sm:ms-auto', 'max-sm:[&>[type=submit]]:flex-1');
    });

    it('keeps the modal footer as it was: pinned, bleeding to the panel, on the surface', function () {
        $bar = domQuery(renderBlade('<atom:form.actions sticky />'), '//*[@data-atom-form-actions]')[0];

        expect(domClasses($bar))->toContain('sticky', '-bottom-6', '-mx-6', 'px-6', 'pb-6', '-mb-6', 'bg-surface', 'border-t', 'justify-between', '[&>:only-child]:ms-auto')
            ->and($bar->hasAttribute('data-atom-save-bar'))->toBeFalse();
    });

    it('lets the modal footer take a start slot too', function () {
        $html = renderBlade('<atom:form.actions sticky><x-slot:start>Total</x-slot:start><button>x</button></atom:form.actions>');

        expect(domQuery($html, '//*[@data-atom-form-actions-start]'))->toHaveCount(1);
    });

    it('prints form.modal\'s footer as the pinned footer, with its own slots', function () {
        $html = renderBlade('<atom:form.modal name="m"><input><x-slot:actions>Total</x-slot:actions></atom:form.modal>');
        $bar = domQuery($html, '//dialog//form/*[@data-atom-form-actions]')[0];

        expect(domClasses($bar))->toContain('sticky', '-bottom-6')
            ->and(domQuery($html, '//*[@data-atom-form-modal-actions]'))->toHaveCount(1);
    });

    it('is the one primary: the submit button, and nothing else on a page form carries bg-accent', function () {
        $html = renderBlade('<atom:form.actions bar><atom:button variant="ghost">Cancel</atom:button><atom:button>Save draft</atom:button><atom:button type="submit">Save and send</atom:button></atom:form.actions>');

        $primaries = domQuery($html, '//*[@data-atom-button][not(ancestor::template)][contains(concat(" ", normalize-space(@class), " "), " bg-accent ")]');

        expect($primaries)->toHaveCount(1)->and($primaries[0]->getAttribute('type'))->toBe('submit');
    });
});

describe('form.section danger', function () {
    it('is the danger zone: the heading in the danger ink and the fields in a box with a danger hairline', function () {
        $html = renderBlade('<atom:form.section variant="danger" heading="Archive this site" subheading="It stops serving traffic."><button>Archive</button></atom:form.section>');

        expect(domQuery($html, '//section')[0]->getAttribute('data-variant'))->toBe('danger')
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-section-heading]')[0]))->toContain('text-danger-fg')
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-section-body]')[0]))->toContain('rounded-panel', 'border', 'border-danger-fg/30', 'p-4');
    });

    it('is the plain section without it, and for any other value', function (?string $variant) {
        $html = renderBlade('<atom:form.section :variant="$variant" heading="Details"><input></atom:form.section>', ['variant' => $variant]);

        expect(domQuery($html, '//section')[0]->hasAttribute('data-variant'))->toBeFalse()
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-section-heading]')[0]))->not->toContain('text-danger-fg')
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-section-body]')[0]))->not->toContain('border');
    })->with([null, 'nope', 'warning']);
});
