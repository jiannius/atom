<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * The dialog options a trigger carries for the click gate, decoded, or null when it carries none.
 *
 * @param  array<string, mixed>  $data
 * @return ?array<string, mixed>
 */
function gateOf(string $template, array $data = []): ?array
{
    foreach (domQuery(renderBlade($template, $data), '//*[@data-atom-confirm]') as $node) {
        return json_decode($node->getAttribute('data-atom-confirm'), true);
    }

    return null;
}

/**
 * The same trigger as a button and as a menu item: `$make('wire:click="x" confirm', 'Label', 'warning')`.
 *
 * @return array<string, Closure>
 */
function confirmTriggers(): array
{
    return [
        'button' => fn (string $attributes, string $label = 'Archive invoice', ?string $variant = null) => '<atom:button '.$attributes.($variant ? ' variant="'.$variant.'"' : '').'>'.$label.'</atom:button>',
        'menu item' => fn (string $attributes, string $label = 'Archive invoice', ?string $variant = null) => '<atom:menu.item '.$attributes.($variant ? ' variant="'.$variant.'"' : '').'>'.$label.'</atom:menu.item>',
    ];
}

foreach (confirmTriggers() as $name => $make) {
    describe("the confirm prop on a {$name}", function () use ($make) {
        it('is data for the click gate, and not an attribute of its own', function () use ($make) {
            $html = renderBlade($make('wire:click="archive" confirm'));

            expect($html)->toContain('data-atom-confirm=')
                ->toContain('wire:click="archive"')
                ->not->toContain(' confirm=')->not->toContain(' confirm ')
                ->not->toContain('x-on:confirmed')->not->toContain('atom.confirm({');
        });

        it('asks nothing without it, or with it off', function () use ($make) {
            expect(renderBlade($make('wire:click="archive"')))->not->toContain('data-atom-confirm')
                ->and(renderBlade($make('wire:click="archive" :confirm="false"')))->not->toContain('data-atom-confirm');
        });

        it('draws the default copy from a plain label: the heading is the label and a question mark, the button repeats the label', function () use ($make) {
            expect(gateOf($make('wire:click="archive" confirm')))->toEqual(['heading' => 'Archive invoice?', 'buttonConfirm' => 'Archive invoice']);
        });

        it('does not double a question mark', function () use ($make) {
            expect(gateOf($make('wire:click="archive" confirm', 'Archive it?')))->toEqual(['heading' => 'Archive it?', 'buttonConfirm' => 'Archive it?']);
        });

        it('keeps a 40 character label and leaves a 41 character one to the dialog', function () use ($make) {
            expect(gateOf($make('wire:click="x" confirm', str_repeat('a', 40))))->toEqual(['heading' => str_repeat('a', 40).'?', 'buttonConfirm' => str_repeat('a', 40)])
                ->and(gateOf($make('wire:click="x" confirm', str_repeat('a', 41))))->toBe([])
                ->and(gateOf($make('wire:click="x" confirm', str_repeat('é', 40))))->toHaveKey('buttonConfirm')
                ->and(gateOf($make('wire:click="x" confirm', str_repeat('é', 41))))->toBe([]);
        });

        it('draws no copy of its own from a label that is markup or empty', function () use ($make) {
            expect(gateOf($make('wire:click="x" confirm', '<b>Archive</b> it')))->toBe([])
                ->and(gateOf($make('wire:click="x" confirm', '')))->toBe([]);
        });

        it('reads a string as the heading, keeping the label for the button', function () use ($make) {
            expect(gateOf($make('wire:click="x" confirm="Cancel this job?"', 'Cancel job')))->toEqual(['heading' => 'Cancel this job?', 'buttonConfirm' => 'Cancel job']);
        });

        it('takes the options of an array: every one of the dialog but html, typed', function () use ($make) {
            $confirm = [
                'heading' => 'Archive this client?', 'message' => 'It leaves every list.', 'subheading' => 'Sure?',
                'buttonConfirm' => 'Archive client', 'buttonCancel' => 'Keep it', 'passphrase' => 'archive', 'password' => 1,
                'reason' => 'yes', 'reasonLabel' => 'Why', 'reasonPlaceholder' => 'e.g. closed', 'variant' => 'info',
                'html' => '<img src=x onerror=alert(1)>', 'onAccepted' => 'nope', 'wireId' => 'nope',
            ];

            expect(gateOf($make('wire:click="x" :confirm="$confirm"'), ['confirm' => $confirm]))->toEqual([
                'variant' => 'info',
                'heading' => 'Archive this client?',
                'buttonConfirm' => 'Archive client',
                'subheading' => 'Sure?',
                'message' => ['It leaves every list.'],
                'buttonCancel' => 'Keep it',
                'passphrase' => 'archive',
                'password' => true,
                'reason' => true,
                'reasonLabel' => 'Why',
                'reasonPlaceholder' => 'e.g. closed',
            ]);
        });

        it('lets the array override the label\'s copy and the element\'s tone', function () use ($make) {
            $config = gateOf($make('wire:click="x" :confirm="$confirm"', 'Archive', 'warning'), ['confirm' => ['variant' => 'success', 'buttonConfirm' => 'Yes, archive']]);

            expect($config)->toEqual(['variant' => 'success', 'heading' => 'Archive?', 'buttonConfirm' => 'Yes, archive']);
        });

        it('translates the heading and the message once, and takes a list of lines or a key and replacements', function () use ($make) {
            app('translator')->setLoaded(['*' => ['*' => ['en' => ['Archive :name?' => 'Arkibkan :name?', 'Hello' => 'Selamat']]]]);

            $config = gateOf($make('wire:click="x" :confirm="$confirm"'), ['confirm' => ['heading' => ['Archive :name?', ['name' => 'Ana']], 'message' => ['Hello', 'Two']]]);

            expect($config['heading'])->toBe('Arkibkan Ana?')
                ->and($config['message'])->toBe(['Selamat', 'Two']);

            // an already-translated heading is not looked up again
            app('translator')->setLoaded(['*' => ['*' => ['en' => ['Archive :name?' => 'Arkibkan :name?', 'Arkibkan Ana?' => 'twice']]]]);
            expect(gateOf($make('wire:click="x" :confirm="$confirm"'), ['confirm' => ['heading' => ['Archive :name?', ['name' => 'Ana']]]])['heading'])->toBe('Arkibkan Ana?');
        });

        it('prints a heading with quotes, angle brackets and an attribute breakout as text', function () use ($make) {
            $heading = 'Say "hi" <script>alert(1)</script> \' onmouseover=alert(1) x=\'';
            $html = renderBlade($make('wire:click="x" :confirm="$confirm"'), ['confirm' => $heading]);

            expect($html)->not->toContain('<script>');

            $nodes = domQuery($html, '//*[@data-atom-confirm]');

            expect($nodes)->toHaveCount(1)
                ->and(json_decode($nodes[0]->getAttribute('data-atom-confirm'), true)['heading'])->toBe($heading)
                ->and($nodes[0]->hasAttribute('onmouseover'))->toBeFalse();
        });

        it('takes a passphrase from the prop, its alias or the array, the array first', function () use ($make) {
            expect(gateOf($make('wire:click="x" confirm passphrase="remove-me"')))->toHaveKey('passphrase', 'remove-me')
                ->and(gateOf($make('wire:click="x" confirm phrase="remove-me"')))->toHaveKey('passphrase', 'remove-me')
                ->and(gateOf($make('wire:click="x" :confirm="$confirm" passphrase="a"'), ['confirm' => ['passphrase' => 'b']]))->toHaveKey('passphrase', 'b')
                ->and(gateOf($make('wire:click="x" confirm')))->not->toHaveKey('passphrase');
        });

        it('stays on a disabled element, for the gate to refuse', function () use ($make) {
            $nodes = domQuery(renderBlade($make('wire:click="x" confirm disabled')), '//*[@data-atom-confirm]');

            expect($nodes)->toHaveCount(1)->and($nodes[0]->hasAttribute('disabled'))->toBeTrue();
        });

        it('wraps any wiring: wire:click with modifiers, x-on:click, @click, a link, a submit', function () use ($make) {
            foreach (['wire:click.prevent="x(1)"', 'x-on:click="open = true"', '@click="open = true"', 'href="/x"', 'type="submit"'] as $wiring) {
                expect(gateOf($make($wiring.' confirm')), $wiring)->toHaveKey('heading');
            }
        });
    });
}

describe('the tone of the dialog', function () {
    foreach (confirmTriggers() as $name => $make) {
        foreach (['warning' => 'warning', 'danger' => 'danger', 'success' => 'success', 'default' => null] as $variant => $tone) {
            it("follows variant=\"{$variant}\" of a {$name}", function () use ($make, $variant, $tone) {
                // a menu item has no `success` look of its own, but its dialog is still drawn in it
                expect(gateOf($make('wire:click="x" confirm', 'Go', $variant))['variant'] ?? null)->toBe($tone);
            });
        }
    }

    it('is danger for a delete, a remove and an error, and an array variant wins', function () {
        expect(gateOf('<atom:menu.item variant="remove" wire:click="x" confirm>Go</atom:menu.item>')['variant'])->toBe('danger')
            ->and(gateOf('<atom:menu.item variant="delete" wire:click="x">Go</atom:menu.item>')['variant'])->toBe('danger')
            ->and(gateOf('<atom:button variant="error" wire:click="x" confirm>Go</atom:button>')['variant'])->toBe('danger')
            ->and(gateOf('<atom:button type="delete" variant="ghost" color="danger" wire:click="x">Go</atom:button>')['variant'])->toBe('danger')
            ->and(gateOf('<atom:button type="delete" wire:click="x" :confirm="[\'variant\' => \'warning\']">Go</atom:button>')['variant'])->toBe('warning')
            ->and(gateOf('<atom:button variant="warning" inverted wire:click="x" confirm>Go</atom:button>')['variant'])->toBe('warning');
    });

    it('is the dialog\'s own for a tone with no dialog colour', function () {
        expect(gateOf('<atom:button variant="info" wire:click="x" confirm>Go</atom:button>'))->not->toHaveKey('variant')
            ->and(gateOf('<atom:button variant="primary" wire:click="x" confirm>Go</atom:button>'))->not->toHaveKey('variant');
    });
});

describe('a delete that has an action of its own', function () {
    it('confirms by default, in danger, with the delete wording and the bulk count', function () {
        foreach ([
            '<atom:button type="delete" wire:click="remove">Delete invoice</atom:button>',
            '<atom:menu.item variant="delete" wire:click="remove">Delete invoice</atom:menu.item>',
            '<atom:button type="delete" x-on:click="go()">Delete invoice</atom:button>',
            '<atom:button type="delete" href="/gone">Delete invoice</atom:button>',
            '<atom:menu.item variant="delete" href="/gone">Delete invoice</atom:menu.item>',
        ] as $template) {
            expect(gateOf($template), $template)->toEqual([
                'variant' => 'danger',
                'heading' => 'Delete this record?',
                'message' => "This can't be undone.",
                'plural' => 'Delete :count records?',
                'buttonConfirm' => 'Delete invoice',
            ]);

            expect(renderBlade($template))->not->toContain('x-on:confirmed')->not->toContain('atom.confirm({');
        }
    });

    it('takes its copy from `confirm`, and a heading of its own drops the bulk count', function () {
        expect(gateOf('<atom:button type="delete" wire:click="x" confirm="Remove it for good?">Delete</atom:button>'))->toEqual([
            'variant' => 'danger',
            'heading' => 'Remove it for good?',
            'message' => "This can't be undone.",
            'buttonConfirm' => 'Delete',
        ]);

        expect(gateOf('<atom:button type="delete" wire:click="x" :confirm="[\'message\' => \'Its lines go too.\']">Delete</atom:button>'))
            ->toHaveKey('plural')->toHaveKey('message', ['Its lines go too.']);
    });

    it('opts out with :confirm="false", and then asks nothing at all', function () {
        foreach ([
            '<atom:button type="delete" wire:click="remove" :confirm="false">Delete</atom:button>',
            '<atom:menu.item variant="delete" wire:click="remove" :confirm="false">Delete</atom:menu.item>',
            '<atom:button type="delete" href="/gone" :confirm="false">Delete</atom:button>',
        ] as $template) {
            expect(renderBlade($template), $template)->not->toContain('data-atom-confirm')->not->toContain('atom.confirm({')->not->toContain('x-on:confirmed');
        }
    });

    it('is asked once: `confirm` beside a delete is the one dialog, not a second', function () {
        $html = renderBlade('<atom:button type="delete" wire:click="remove" confirm="Sure?">Delete</atom:button>');

        expect(substr_count($html, 'data-atom-confirm='))->toBe(1)->and($html)->not->toContain('atom.confirm(');
    });
});

describe('a delete with no action of its own', function () {
    it('keeps the delete handler: one dialog, then $wire.delete()', function () {
        foreach ([
            '<atom:button type="delete">Delete</atom:button>',
            '<atom:menu.item variant="delete">Delete</atom:menu.item>',
        ] as $template) {
            expect(renderBlade($template), $template)->toContain('atom.confirm({')->toContain('x-on:confirmed="$wire.delete()"')->not->toContain('data-atom-confirm');
        }
    });

    it('takes the copy of `confirm` into that one dialog, and still counts a bulk delete without a heading of its own', function () {
        $html = renderBlade('<atom:button type="delete" :confirm="[\'message\' => \'Gone for good.\', \'buttonCancel\' => \'Keep\']">Delete</atom:button>');

        expect($html)->toContain('x-on:confirmed="$wire.delete()"')->not->toContain('data-atom-confirm')
            ->toContain('heading: count')
            ->toContain('Gone for good.')->toContain('buttonCancel')
            ->and(renderBlade('<atom:button type="delete" confirm>Delete</atom:button>'))->not->toContain('...JSON.parse(');

        // the host's options spread after the counted heading, so a heading of its own wins
        $own = renderBlade('<atom:button type="delete" confirm="Really delete?">Delete</atom:button>');

        expect(strpos($own, '...JSON.parse('))->toBeGreaterThan(strpos($own, 'heading: count'))->and($own)->toContain('Really delete?');
    });

    it('sends the typed passphrase and the label through the same handler', function () {
        $html = renderBlade('<atom:menu.item variant="delete" confirm="Delete it?" passphrase="gone">Delete</atom:menu.item>');

        expect($html)->toContain('passphrase: &#039;gone&#039;')->toContain('buttonConfirm: &#039;Delete&#039;');
    });

    it('still deletes, with no dialog, when it opts out', function () {
        $button = renderBlade('<atom:button type="delete" :confirm="false">Delete</atom:button>');
        $item = renderBlade('<atom:menu.item variant="delete" :confirm="false">Delete</atom:menu.item>');

        expect($button)->toContain('x-on:click="$wire.delete()"')->not->toContain('atom.confirm')->not->toContain('data-atom-confirm')->not->toContain('x-on:confirmed')
            ->and($item)->toContain('x-on:click="$wire.delete()"')->not->toContain('atom.confirm')->not->toContain('data-atom-confirm');
    });
});

describe('the confirm prop and atom()->confirm() read a message the same way', function () {
    it('gives the same message list for a string, a [key, replacements] pair, a list of either and an Htmlable', function (mixed $message, array $expected) {
        $fake = new class {
            public array $dispatched = [];

            public function dispatch(string $event, ...$params): void
            {
                $this->dispatched[] = $params;
            }

            public function getId(): string
            {
                return 'fake-id';
            }
        };

        withLivewireContext($fake, fn () => app('atom')->confirm(message: $message));

        $given = \Jiannius\Atom\Services\ConfirmProp::given(['message' => $message])['message'];

        expect($given)->toBe($fake->dispatched[0]['message'])->and($given)->toBe($expected);
    })->with([
        'a string' => ['Are you sure?', ['Are you sure?']],
        'a pair' => [[':name is going', ['name' => 'Ada']], ['Ada is going']],
        'a pair inside a list' => [[['Hello :name', ['name' => 'Ada']], 'Second line'], ['Hello Ada', 'Second line']],
        'a list' => [['First', 'Second'], ['First', 'Second']],
        'an Htmlable' => [new \Illuminate\Support\HtmlString('<b>Bold</b>'), ['<b>Bold</b>']],
    ]);
});

describe('ConfirmProp::merges()', function () {
    $merges = fn (mixed $confirm, bool $delete, string $attributes = '', string $el = 'button', ?string $type = 'button', ?string $phrase = null) => \Jiannius\Atom\Services\ConfirmProp::merges(
        $confirm, null, $delete, new \Illuminate\View\ComponentAttributeBag($attributes === '' ? [] : ['wire:click' => $attributes]), $el, $type, 'Delete', null, $phrase,
    );

    it('is empty for an element that asks nothing', function () use ($merges) {
        expect($merges(null, false))->toBe([])->and($merges(false, false))->toBe([]);
    });

    it('carries the gate as data for a plain confirm, and for a delete with an action of its own', function () use ($merges) {
        expect(array_keys($merges(true, false)))->toBe(['data-atom-confirm'])
            ->and(array_keys($merges(null, true, 'go')))->toBe(['data-atom-confirm'])
            ->and(array_keys($merges(null, true, '', 'a')))->toBe(['data-atom-confirm']);
    });

    it('keeps the delete handler for a bare delete, and $wire.delete() when it opts out, but not for a submit', function () use ($merges) {
        expect(array_keys($merges(null, true)))->toBe(['x-on:click', 'x-on:confirmed'])
            ->and($merges(false, true))->toBe(['x-on:click' => '$wire.delete()'])
            ->and($merges(false, true, '', 'button', 'submit'))->toBe([]);
    });

    it('reads the phrase as the passphrase', function () use ($merges) {
        expect(json_decode($merges(true, false, '', 'button', 'button', 'gone')['data-atom-confirm'], true)['passphrase'])->toBe('gone');
    });
});
