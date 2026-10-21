<?php

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Jiannius\Atom\Services\Attributes;

describe('fieldId()', function () {
    it('derives the same id for the same field, so a morph keeps the control', function () {
        $bag = new ComponentAttributeBag();

        expect(Attributes::fieldId($bag, 'atom-input', 'email', 'Email', 'text'))
            ->toBe(Attributes::fieldId($bag, 'atom-input', 'email', 'Email', 'text'))
            ->toStartWith('atom-input-')
            ->toMatch('/^atom-input-[0-9a-f]{8}$/');
    });

    it('keeps the exact ids the components mint, because a morph key must survive an upgrade', function () {
        $bag = new ComponentAttributeBag();

        // the prefix and parts of real call sites (input, textarea, select): the formula, and the literal ids it gives
        expect(Attributes::fieldId($bag, 'atom-input', 'email', 'Email', 'text'))->toBe('atom-input-'.substr(md5('email|Email|text'), 0, 8))
            ->and(Attributes::fieldId($bag, 'atom-input', 'email', 'Email', 'text'))->toBe('atom-input-7b0da0fb')
            ->and(Attributes::fieldId($bag, 'atom-textarea', 'bio', 'Bio'))->toBe('atom-textarea-35d1a459')
            ->and(Attributes::fieldId($bag, 'atom-select', 'country', 'Country', 'listbox'))->toBe('atom-select-0d5bdb57');
    });

    it('changes the id when any part of the field changes', function () {
        $bag = new ComponentAttributeBag();

        expect(Attributes::fieldId($bag, 'atom-input', 'email', 'Email'))
            ->not->toBe(Attributes::fieldId($bag, 'atom-input', 'email', 'Work email'))
            ->not->toBe(Attributes::fieldId($bag, 'atom-input', 'mail', 'Email'))
            ->not->toBe(Attributes::fieldId($bag, 'atom-select', 'email', 'Email'));
    });

    it('lets a caller-supplied id win, and ignores a blank one', function () {
        expect(Attributes::fieldId(new ComponentAttributeBag(['id' => 'mine']), 'atom-input', 'email'))->toBe('mine')
            ->and(Attributes::fieldId(new ComponentAttributeBag(['id' => '']), 'atom-input', 'email'))->toStartWith('atom-input-');
    });
});

describe('modifier()', function () {
    it('reads the modifier of a wire:model binding', function () {
        $bag = new ComponentAttributeBag(['wire:model.live.debounce.300ms' => 'name']);

        expect(Attributes::modifier($bag))->toBe('.live.debounce.300ms')
            ->and(Attributes::modifier($bag, 'live'))->toBeTrue()
            ->and(Attributes::modifier($bag, 'blur'))->toBeFalse();
    });

    it('reads an x-model binding too', function () {
        $bag = new ComponentAttributeBag(['x-model.blur' => 'name']);

        expect(Attributes::modifier($bag))->toBe('.blur')
            ->and(Attributes::modifier($bag, 'blur'))->toBeTrue();
    });

    it('is empty without a binding, and without a modifier', function () {
        expect(Attributes::modifier(new ComponentAttributeBag()))->toBe('')
            ->and(Attributes::modifier(new ComponentAttributeBag(), 'blur'))->toBeFalse()
            ->and(Attributes::modifier(new ComponentAttributeBag(['wire:model' => 'name'])))->toBe('');
    });
});

describe('hasLike()', function () {
    it('matches an attribute name against wildcard patterns', function () {
        $bag = new ComponentAttributeBag(['wire:click.prevent' => 'go', 'class' => 'x']);

        expect(Attributes::hasLike($bag, 'x-on:click*', 'wire:click*'))->toBeTrue()
            ->and(Attributes::hasLike($bag, 'wire:click*'))->toBeTrue()
            ->and(Attributes::hasLike($bag, 'x-on:click*'))->toBeFalse();
    });

    it('is false for an empty bag and for a bare name', function () {
        expect(Attributes::hasLike(new ComponentAttributeBag(), 'wire:click*'))->toBeFalse()
            ->and(Attributes::hasLike(new ComponentAttributeBag(['disabled' => true]), 'wire:click*'))->toBeFalse();
    });
});

describe('in the components', function () {
    beforeEach(fn () => view()->share('errors', new ViewErrorBag));

    it('marks a table row clickable when it carries a click handler, and only then', function () {
        $wire = renderBlade('<atom:table.row wire:click="open(1)"><td>a</td></atom:table.row>');
        $alpine = renderBlade('<atom:table.row x-on:click="open"><td>a</td></atom:table.row>');
        $plain = renderBlade('<atom:table.row><td>a</td></atom:table.row>');

        expect($wire)->toContain('cursor-pointer')
            ->and($alpine)->toContain('cursor-pointer')
            ->and($plain)->not->toContain('cursor-pointer');
    });

    it('gives a labelled input the same id on every render, and honours the caller\'s id', function () {
        $first = renderBlade('<atom:input name="email" label="Email"/>');
        $second = renderBlade('<atom:input name="email" label="Email"/>');
        $own = renderBlade('<atom:input name="email" label="Email" id="mine"/>');

        preg_match('/id="(atom-input-[0-9a-f]{8})"/', $first, $id);

        expect($id)->not->toBeEmpty()
            ->and($second)->toContain('id="'.$id[1].'"')
            ->and($own)->toContain('id="mine"')
            ->and($own)->not->toContain($id[1]);
    });
});
