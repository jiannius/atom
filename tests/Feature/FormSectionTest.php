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

        expect(domClasses($bar))->toContain('justify-between')->not->toContain('sticky')
            ->and($bar->hasAttribute('data-atom-save-bar'))->toBeFalse()
            ->and(domQuery(renderBlade('<atom:form.actions />'), '//button[@type="submit"]'))->toHaveCount(1);
    });

    it('is sticky at the bottom of the screen with a blurred surface and a hairline', function () {
        $bar = domQuery(renderBlade('<atom:form.actions bar />'), '//*[@data-atom-save-bar]')[0];

        expect(domClasses($bar))->toContain('sticky', 'bottom-0', 'border-t', 'bg-surface/95', 'backdrop-blur-sm')
            ->and(domClasses($bar))->not->toContain('-bottom-6');
    });

    it('bleeds to the page gutter and sits inside it, with fallbacks outside a page', function () {
        $html = renderBlade('<atom:form.actions bar />');
        $classes = implode(' ', domClasses(domQuery($html, '//*[@data-atom-save-bar]')[0]));

        expect($classes)->toContain('ms-[calc(var(--atom-gutter-s,1rem)*-1)]')
            ->and($classes)->toContain('me-[calc(var(--atom-gutter-e,1rem)*-1)]')
            ->and($classes)->toContain('ps-[var(--atom-gutter-s,1rem)]')
            ->and($classes)->toContain('pe-[var(--atom-gutter-e,1rem)]')
            ->and($classes)->toContain('env(safe-area-inset-bottom');
    });

    it('prints the start slot at the start, and the buttons at the end', function () {
        $html = renderBlade('<atom:form.actions bar><x-slot:start><span id="t">Total</span></x-slot:start><button id="b" type="submit">Save</button></atom:form.actions>');

        expect(domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-actions-start]/span[@id="t"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-save-bar]/*[@data-atom-form-actions-buttons]/button[@id="b"]'))->toHaveCount(1)
            ->and(domClasses(domQuery($html, '//*[@data-atom-form-actions-start]')[0]))->toContain('tabular-nums')
            ->and(domQuery($html, '//*[@data-atom-form-actions-start]/following-sibling::*[@data-atom-form-actions-buttons]'))->toHaveCount(1);
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

        expect(domClasses($bar))->toContain('sticky', '-bottom-6', '-mx-6', 'px-6', 'pb-6', '-mb-6', 'bg-surface', 'border-t', 'justify-between')
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

    it('is the one primary: the submit button, and nothing else on a page form carries bg-primary', function () {
        $html = renderBlade('<atom:form.actions bar><atom:button variant="ghost">Cancel</atom:button><atom:button>Save draft</atom:button><atom:button type="submit">Save and send</atom:button></atom:form.actions>');

        $primaries = domQuery($html, '//*[@data-atom-button][contains(concat(" ", normalize-space(@class), " "), " bg-primary ")]');

        expect($primaries)->toHaveCount(1)->and($primaries[0]->getAttribute('type'))->toBe('submit');
    });
});
