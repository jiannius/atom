<?php

use Illuminate\Support\Facades\Blade;
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

describe('hasWidth()', function () {
    it('counts a w-* or max-w-* utility, under any variant or modifier, as a width', function (string $class) {
        expect(Attributes::hasWidth(new ComponentAttributeBag(['class' => $class])))->toBeTrue();
    })->with([
        'plain' => 'w-96',
        'a theme width' => 'w-md',
        'a max width' => 'max-w-md',
        'a fraction' => 'w-1/2',
        'arbitrary' => 'w-[28rem]',
        'a calc' => 'w-[calc(100%-2rem)]',
        'a css variable' => 'w-(--x)',
        'important first' => '!w-96',
        'important last' => 'w-96!',
        'a breakpoint' => 'md:w-96',
        'a breakpoint max width' => 'sm:max-w-lg',
        'a state' => 'hover:w-4',
        'an arbitrary-value variant' => 'data-[state=open]:w-4',
        'stacked variants' => 'dark:md:w-4',
        'among others' => 'rounded-none p-4 w-96 bg-red-500',
    ]);

    it('does not count what is not a width for the box itself', function (?string $class) {
        expect(Attributes::hasWidth(new ComponentAttributeBag($class === null ? [] : ['class' => $class])))->toBeFalse();
    })->with([
        'no class' => [null],
        'empty' => [''],
        'a min width' => ['min-w-0'],
        'a min width under a variant' => ['md:min-w-96'],
        'size' => ['size-96'],
        'a height' => ['h-96'],
        'a width for a child' => ['[&_svg]:w-4'],
        'a width for a child under a variant' => ['sm:[&>img]:w-8'],
        'the star variant' => ['*:w-4'],
        'a word that merely contains it' => ['show-w-x'],
    ]);

    it('counts w-* only when the max width is left out', function () {
        $max = new ComponentAttributeBag(['class' => 'max-w-lg']);
        $both = new ComponentAttributeBag(['class' => 'max-w-lg md:w-96']);

        expect(Attributes::hasWidth($max, false))->toBeFalse()
            ->and(Attributes::hasWidth($both, false))->toBeTrue();
    });
});

describe('splitLayoutClasses()', function () {
    it('sends width, flex item and margin utilities, under any variant, to the layout half', function (string $class) {
        expect(Attributes::splitLayoutClasses($class))->toBe([$class, '']);
    })->with([
        'width' => 'w-96',
        'min width' => 'min-w-0',
        'max width' => 'max-w-sm',
        'a breakpoint' => 'sm:w-96',
        'a max breakpoint' => 'max-sm:w-full',
        'stacked variants' => 'dark:md:w-4',
        'important first' => '!w-96',
        'important last' => 'w-96!',
        'arbitrary' => 'w-[28rem]',
        'basis' => 'basis-1/2',
        'grow' => 'grow',
        'grow zero' => 'grow-0',
        'shrink' => 'shrink',
        'shrink zero' => 'md:shrink-0',
        'flex 1' => 'flex-1',
        'flex auto' => 'flex-auto',
        'flex none' => 'flex-none',
        'flex arbitrary' => 'flex-[2_1_0%]',
        'order' => 'order-last',
        'self' => 'self-end',
        'justify self' => 'justify-self-end',
        'margin' => 'm-4',
        'margin x' => 'mx-auto',
        'margin y' => 'my-2',
        'margin start' => 'ms-2',
        'margin end' => 'me-2',
        'margin top' => 'mt-1',
        'margin right' => 'mr-1',
        'margin bottom' => 'mb-1',
        'margin left' => 'ml-auto',
        'a negative margin' => '-mt-2',
        'a negative margin under a variant' => 'md:-mx-4',
    ]);

    it('leaves everything else, and what styles a child, in the rest half', function (string $class) {
        expect(Attributes::splitLayoutClasses($class))->toBe(['', $class]);
    })->with([
        'a background' => 'bg-surface',
        'a border' => 'border-accent',
        'a font' => 'font-mono',
        'padding' => 'px-4',
        'a height' => 'h-10',
        'size' => 'size-8',
        'a flex container class' => 'flex-col',
        'flex wrap' => 'flex-wrap',
        'flex' => 'flex',
        'a grid class' => 'gap-2',
        'a text class that starts with m' => 'mono',
        'min height' => 'min-h-0',
        'a width for a child' => '[&_svg]:w-4',
        'the star variant' => '*:w-4',
        'a growing word' => 'grower',
        'rounded' => 'rounded-lg',
    ]);

    it('splits a mixed string in order, and takes null or empty', function () {
        expect(Attributes::splitLayoutClasses("bg-surface  sm:w-96\tfont-mono ms-auto"))->toBe(['sm:w-96 ms-auto', 'bg-surface font-mono'])
            ->and(Attributes::splitLayoutClasses(null))->toBe(['', ''])
            ->and(Attributes::splitLayoutClasses('  '))->toBe(['', '']);
    });
});

describe('disabledBy()', function () {
    beforeEach(function () {
        Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');
    });

    /** The answers of every probe in the markup, in document order. */
    function disabledAnswers(string $template): array
    {
        return array_map(fn (DOMElement $el) => $el->getAttribute('data-by'), domQuery(renderBlade($template), '//i[@data-by]'));
    }

    it('answers null with no ancestor and no attribute', function () {
        expect(disabledAnswers('<x-disabled-probe />'))->toBe(['none']);
    });

    it('answers self for its own true, and null for its own false or null', function () {
        expect(disabledAnswers('<x-disabled-probe disabled /><x-disabled-probe :disabled="true" /><x-disabled-probe :disabled="false" /><x-disabled-probe :disabled="null" />'))
            ->toBe(['self', 'self', 'none', 'none']);
    });

    it('answers inherited from a disabled ancestor, and null from an enabled one', function () {
        expect(disabledAnswers('<x-disabled-wrapper disabled><x-disabled-probe /></x-disabled-wrapper><x-disabled-wrapper><x-disabled-probe /></x-disabled-wrapper>'))
            ->toBe(['inherited', 'none']);
    });

    it('answers inherited through a form and a plain card between', function () {
        expect(disabledAnswers('<atom:form disabled><atom:card><x-disabled-probe /></atom:card></atom:form>'))->toBe(['inherited'])
            ->and(disabledAnswers('<atom:form><atom:card><x-disabled-probe /></atom:card></atom:form>'))->toBe(['none']);
    });

    it('lets its own disabled win over a disabled ancestor, in both directions', function () {
        expect(disabledAnswers('<atom:form disabled><x-disabled-probe :disabled="false" /><x-disabled-probe disabled /></atom:form>'))->toBe(['none', 'self'])
            ->and(disabledAnswers('<atom:form><x-disabled-probe disabled /></atom:form>'))->toBe(['self']);
    });

    it('stops at a wrapper with :disabled="false", the boundary a control can use', function () {
        expect(disabledAnswers('<atom:form disabled><x-disabled-wrapper :disabled="false"><x-disabled-probe /></x-disabled-wrapper><x-disabled-probe /></atom:form>'))
            ->toBe(['none', 'inherited']);
    });

    it('leaves nothing behind for a sibling that follows a disabled form', function () {
        expect(disabledAnswers('<atom:form disabled><x-disabled-probe /></atom:form><x-disabled-probe /><atom:form><x-disabled-probe /></atom:form>'))
            ->toBe(['inherited', 'none', 'none']);
    });

    it('lets a disabled component\'s own view see its disabled before a nearer boundary, as @aware did', function () {
        // Laravel merges the data of the component being rendered with its parent's and reads that before the stack, so a
        // `:disabled="false"` wrapper inside the view of a disabled component does not end the inheritance for what that view
        // renders; the boundary holds for what a template puts under it (the test above), not for a component's own view
        expect(disabledAnswers('<x-disabled-host disabled />'))->toBe(['inherited'])
            ->and(disabledAnswers('<x-disabled-host />'))->toBe(['none']);
    });
});
