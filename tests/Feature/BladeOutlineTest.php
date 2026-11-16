<?php

use Jiannius\Atom\Services\BladeOutline;

/**
 * The outline of a view, from a snippet.
 */
function outline(string $blade): BladeOutline
{
    return new BladeOutline($blade);
}

/**
 * The tags of an outline, in order, as their names.
 */
function tags(BladeOutline $outline): array
{
    return array_column($outline->nodes(), 'tag');
}

/**
 * The first node with a tag name (a component name for an atom tag).
 */
function first(BladeOutline $outline, string $tag, int $nth = 0): array
{
    $found = array_values(array_filter($outline->nodes(), fn ($node) => $node['tag'] === $tag));

    return $found[$nth];
}

/**
 * The text a node's offset reads in the outline's source, to prove a node points where it says.
 */
function textAt(BladeOutline $outline, array $node, int $length = 12): string
{
    return substr($outline->text(), $node['offset'], $length);
}

describe('pairing', function () {
    it('reads self-closing and paired tags', function () {
        $o = outline('<atom:page><atom:icon.add /><atom:button>Save</atom:button></atom:page>');

        expect(tags($o))->toBe(['atom:page', 'atom:icon.add', 'atom:button']);

        $icon = first($o, 'atom:icon.add');
        expect($icon['selfClosing'])->toBeTrue()
            ->and($icon['close'])->toBeNull()
            ->and($icon['end'])->toBe($icon['openEnd'])
            ->and($icon['component'])->toBe('icon.add')
            ->and($icon['kind'])->toBe('atom');

        $button = first($o, 'atom:button');
        expect($button['selfClosing'])->toBeFalse()
            ->and(substr($o->text(), $button['close'], 14))->toBe('</atom:button>')
            ->and($button['parent'])->toBe(first($o, 'atom:page')['id'])
            ->and($button['depth'])->toBe(1)
            ->and($o->balanced())->toBeTrue();
    });

    it('reads the x-atom:: spelling as the same tag', function () {
        $o = outline('<x-atom::card heading="A"><x-atom::button>Go</x-atom::button></x-atom::card>');

        expect(tags($o))->toBe(['atom:card', 'atom:button'])
            ->and($o->atom('card'))->toHaveCount(1)
            ->and($o->atom('atom:button'))->toHaveCount(1)
            ->and($o->atom())->toHaveCount(2)
            ->and($o->balanced())->toBeTrue();
    });

    it('closes a slot with </x-slot> or </x-slot:name>', function () {
        $o = outline('<atom:page.header><x-slot:actions><atom:button/></x-slot:actions><x-slot:menu><atom:menu.item/></x-slot></atom:page.header>');

        expect($o->balanced())->toBeTrue()
            ->and(first($o, 'x-slot')['slot'])->toBe('actions')
            ->and(first($o, 'x-slot', 1)['slot'])->toBe('menu')
            ->and(first($o, 'x-slot')['close'])->not->toBeNull()
            ->and(first($o, 'x-slot', 1)['close'])->not->toBeNull()
            ->and($o->parent(first($o, 'atom:menu.item'))['slot'])->toBe('menu');
    });

    it('reads <x-slot name="..."> and a bound slot name', function () {
        $o = outline('<atom:card><x-slot name="actions"><atom:button/></x-slot><x-slot :name="$which"><atom:link/></x-slot></atom:card>');

        expect(first($o, 'x-slot')['slot'])->toBe('actions')
            ->and(first($o, 'x-slot', 1)['slot'])->toBeNull()
            ->and($o->balanced())->toBeTrue();
    });

    it('pairs nested tags of the same name by depth', function () {
        $o = outline('<atom:card id="a"><atom:card id="b">x</atom:card>y</atom:card>');

        $outer = first($o, 'atom:card');
        $inner = first($o, 'atom:card', 1);

        expect($inner['parent'])->toBe($outer['id'])
            ->and($inner['close'])->toBeLessThan($outer['close'])
            ->and($inner['depth'])->toBe(1)
            ->and($o->balanced())->toBeTrue();
    });

    it('does not wait for a closer on a void element', function () {
        $o = outline('<atom:card><img src="a.png"><br><input type="text"><hr/><atom:button>Go</atom:button></atom:card>');

        expect(first($o, 'img')['selfClosing'])->toBeTrue()
            ->and(first($o, 'br')['close'])->toBeNull()
            ->and($o->parent(first($o, 'atom:button'))['tag'])->toBe('atom:card')
            ->and($o->balanced())->toBeTrue();
    });

    it('ignores a stray HTML closer and keeps HTML optional closers apart from balance', function () {
        $o = outline('<atom:card></div><ul><li>a<li>b</ul><p>one<p>two</atom:card>');

        expect($o->balanced())->toBeTrue()
            ->and(first($o, 'li', 1)['parent'])->toBe(first($o, 'ul')['id'])
            ->and(first($o, 'p', 1)['parent'])->toBe(first($o, 'atom:card')['id']);
    });

    it('reads a > inside a {{ }} in an attribute', function () {
        $o = outline('<atom:button label="{{ $a > 1 ? "x" : "y" }}" :disabled="$b > 2">Go</atom:button><atom:badge/>');

        expect(tags($o))->toBe(['atom:button', 'atom:badge'])
            ->and(first($o, 'atom:button')['attributes'][':disabled'])->toBe('$b > 2')
            ->and(first($o, 'atom:button')['attributes']['label'])->toBe('{{ $a > 1 ? "x" : "y" }}')
            ->and($o->balanced())->toBeTrue();
    });

    it('reads a > and the other quote inside a quoted attribute', function () {
        $o = outline('<atom:button x-on:click="open = a > b ? \'x\' : \'y\'" wire:click=\'go("1>2")\'>Go</atom:button>');

        expect(first($o, 'atom:button')['attributes'])->toBe(['x-on:click' => "open = a > b ? 'x' : 'y'", 'wire:click' => 'go("1>2")'])
            ->and($o->balanced())->toBeTrue();
    });

    it('records attributes with absolute spans', function () {
        $o = outline("<atom:button\n    variant=\"primary\"\n    disabled\n    :label=\"\$x\"\n/>");
        $button = first($o, 'atom:button');

        expect($button['attributes'])->toBe(['variant' => 'primary', 'disabled' => true, ':label' => '$x'])
            ->and(substr($o->text(), $button['spans']['variant'][0], $button['spans']['variant'][1] - $button['spans']['variant'][0]))->toBe('variant="primary"')
            ->and(substr($o->text(), $button['spans']['disabled'][0], $button['spans']['disabled'][1] - $button['spans']['disabled'][0]))->toBe('disabled')
            ->and($o->line($button['spans'][':label'][0]))->toBe(4)
            ->and($button['selfClosing'])->toBeTrue();
    });

    it('reads unquoted values and short Alpine attributes', function () {
        $o = outline('<atom:button size=sm @click="go()" x-on:keydown.escape.window="close()" wire:click.prevent="save">Go</atom:button>');

        expect(first($o, 'atom:button')['attributes'])->toBe(['size' => 'sm', '@click' => 'go()', 'x-on:keydown.escape.window' => 'close()', 'wire:click.prevent' => 'save']);
    });

    it('skips directives written inside a tag and keeps @class', function () {
        $o = outline('<div @class([\'p-4\', \'font-bold\' => $b]) @if($x) data-a="1" @else data-b="2" @endif><atom:button/></div>');
        $div = first($o, 'div');

        expect($div['directives'])->toHaveCount(2)
            ->and($div['directives'][0]['name'])->toBe('class')
            ->and($div['directives'][1]['name'])->toBe('if')
            ->and($div['attributes'])->toHaveKeys(['data-a', 'data-b'])
            ->and($o->parent(first($o, 'atom:button'))['tag'])->toBe('div');
    });

    it('ignores atom tags in an HTML comment, a Blade comment, an echo, PHP and a script', function () {
        $o = outline(implode("\n", [
            '<!-- <atom:button>old</atom:button> -->',
            '{{-- <atom:card> --}}',
            '{{ "<atom:badge>" }}',
            '{!! \'<atom:badge>\' !!}',
            '<?php echo "<atom:link>"; ?>',
            '@php $x = "<atom:input>"; @endphp',
            '@php($y = "<atom:select>")',
            '@include(\'a\', [\'x\' => \'<atom:tabs>\'])',
            '<script>const t = "<atom:menu>"; if (a <b) {}</script>',
            '<style>a > b { }</style>',
            '<atom:page/>',
        ]));

        expect(tags($o))->toBe(['script', 'style', 'atom:page'])
            ->and($o->balanced())->toBeTrue();
    });

    it('does not read a bare < as a tag', function () {
        $o = outline('<p>1 < 2 and a<3 and <5</p><atom:card/>');

        expect(tags($o))->toBe(['p', 'atom:card']);
    });

    it('keeps line numbers', function () {
        $o = outline("<atom:page>\n  <atom:card>\n    <atom:button/>\n  </atom:card>\n</atom:page>");

        expect($o->line(first($o, 'atom:page')['offset']))->toBe(1)
            ->and($o->line(first($o, 'atom:card')['offset']))->toBe(2)
            ->and($o->line(first($o, 'atom:button')['offset']))->toBe(3)
            ->and($o->line(first($o, 'atom:card')['close']))->toBe(4);
    });

    it('finds the tag at an offset', function () {
        $o = outline('<atom:page><atom:card>text</atom:card><atom:tabs/></atom:page>');
        $card = first($o, 'atom:card');

        expect($o->at($card['openEnd'] + 1)['tag'])->toBe('atom:card')
            ->and($o->at($card['end'] + 1)['tag'])->toBe('atom:tabs')
            ->and($o->at(0)['tag'])->toBe('atom:page')
            ->and($o->at(strlen($o->text()) + 5))->toBeNull();
    });
});

describe('queries', function () {
    it('walks parents, ancestors, children and descendants', function () {
        $o = outline('<atom:page>@if($a)<atom:card><div><atom:button/></div><atom:link/></atom:card>@endif<atom:tabs/></atom:page>');

        $page = first($o, 'atom:page');
        $card = first($o, 'atom:card');
        $button = first($o, 'atom:button');

        expect(array_column($o->ancestors($button), 'tag'))->toBe(['div', 'atom:card', 'atom:page'])
            ->and($o->parent($button)['tag'])->toBe('div')
            ->and($o->parent($page))->toBeNull()
            ->and(array_column($o->children($page), 'tag'))->toBe(['atom:card', 'atom:tabs'])
            ->and(array_column($o->children($card), 'tag'))->toBe(['div', 'atom:link'])
            ->and($o->firstChild($card)['tag'])->toBe('div')
            ->and($o->firstChild(first($o, 'atom:tabs')))->toBeNull()
            ->and(array_column($o->descendants($page), 'tag'))->toBe(['atom:card', 'div', 'atom:button', 'atom:link', 'atom:tabs'])
            ->and($o->children($button['id']))->toBe([]);
    });

    it('takes an id wherever it takes a node', function () {
        $o = outline('<atom:page><atom:card/></atom:page>');

        expect($o->parent(1)['tag'])->toBe('atom:page')
            ->and($o->node(1)['tag'])->toBe('atom:card')
            ->and($o->node(9))->toBeNull()
            ->and($o->parent(9))->toBeNull()
            ->and($o->ancestors(9))->toBe([])
            ->and($o->label(9))->toBeNull();
    });

    it('finds the nearest ancestor by whole tag name', function () {
        $o = outline('<atom:page><atom:card><x-slot:actions><atom:button/></x-slot:actions></atom:card><atom:table><form><button>x</button></form></atom:table></atom:page>');

        $button = first($o, 'atom:button');

        expect($o->inside($button, 'atom:card')['tag'])->toBe('atom:card')
            ->and($o->inside($button, ['atom:table', 'atom:card'])['tag'])->toBe('atom:card')
            ->and($o->inside($button, 'x-atom::page')['tag'])->toBe('atom:page')
            ->and($o->inside($button, 'x-slot')['slot'])->toBe('actions')
            ->and($o->inside($button, 'x-slot:actions')['slot'])->toBe('actions')
            ->and($o->inside($button, 'x-slot:menu'))->toBeNull()
            ->and($o->inside($button, 'atom:table'))->toBeNull()
            ->and($o->inside(first($o, 'button'), 'atom:table')['tag'])->toBe('atom:table')
            ->and($o->inside(first($o, 'button'), 'form')['tag'])->toBe('form')
            ->and($o->inside(first($o, 'button'), 'card'))->toBeNull();
    });

    it('keeps an HTML button apart from an atom button by name', function () {
        $o = outline('<button>a</button><atom:button>b</atom:button>');

        expect($o->atom('button'))->toHaveCount(1)
            ->and($o->atom('button')[0]['tag'])->toBe('atom:button')
            ->and(first($o, 'button')['kind'])->toBe('html');
    });

    it('classifies atom, slot, component and html tags', function () {
        $o = outline('<atom:card/><x-slot:a></x-slot><x-forms.input/><livewire:invoices.list/><x-dynamic-component :component="$c"/><div/>');

        expect(array_column($o->nodes(), 'kind'))->toBe(['atom', 'slot', 'component', 'component', 'component', 'html']);
    });
});

describe('slot owner', function () {
    it('gives the shell its own actions slot, not the page header inside it', function () {
        $o = outline(<<<'BLADE'
<atom:layouts.sidebar>
    <x-slot:actions>
        <atom:button size="sm">Previous</atom:button>
    </x-slot:actions>
    <atom:page>
        <atom:page.header title="Invoice">
            <x-slot:actions>
                <atom:button>Print</atom:button>
            </x-slot:actions>
        </atom:page.header>
    </atom:page>
</atom:layouts.sidebar>
BLADE);

        $previous = $o->slotOf(first($o, 'atom:button'));
        $print = $o->slotOf(first($o, 'atom:button', 1));

        expect($o->balanced())->toBeTrue()
            ->and($previous['name'])->toBe('actions')
            ->and($previous['owner']['component'])->toBe('layouts.sidebar')
            ->and($previous['direct'])->toBeTrue()
            ->and($print['name'])->toBe('actions')
            ->and($print['owner']['component'])->toBe('page.header')
            ->and($print['direct'])->toBeTrue();
    });

    it('skips HTML elements between the slot and its owner', function () {
        $o = outline('<atom:card><div><x-slot:actions><atom:button/></x-slot:actions></div></atom:card>');

        expect($o->slotOf(first($o, 'atom:button'))['owner']['tag'])->toBe('atom:card')
            ->and($o->owner(first($o, 'x-slot'))['tag'])->toBe('atom:card');
    });

    it('names an x-dynamic-component as the owner', function () {
        $o = outline('<x-dynamic-component :component="$shell"><x-slot:actions><atom:button/></x-slot:actions></x-dynamic-component>');

        expect($o->slotOf(first($o, 'atom:button'))['owner']['tag'])->toBe('x-dynamic-component');
    });

    it('says when a tag sits in another component inside a slot', function () {
        $o = outline('<atom:page.header><x-slot:actions><atom:card><atom:button/></atom:card></x-slot:actions></atom:page.header>');

        $slot = $o->slotOf(first($o, 'atom:button'));

        expect($slot['name'])->toBe('actions')
            ->and($slot['direct'])->toBeFalse()
            ->and($slot['owner']['component'])->toBe('page.header')
            ->and($o->slotOf(first($o, 'atom:card'))['direct'])->toBeTrue();
    });

    it('returns null outside a slot, and an owner of null for a slot with none', function () {
        $o = outline('<atom:card><atom:button/></atom:card><x-slot:lonely><atom:link/></x-slot:lonely>');

        expect($o->slotOf(first($o, 'atom:button')))->toBeNull()
            ->and($o->slotOf(first($o, 'atom:link'))['owner'])->toBeNull()
            ->and($o->owner(first($o, 'x-slot')))->toBeNull();
    });
});

describe('balanced', function () {
    it('is false for an unclosed atom tag', function () {
        expect(outline('<atom:page><atom:page.header title="x"></atom:page>')->balanced())->toBeFalse()
            ->and(outline('<atom:page.header title="x">')->balanced())->toBeFalse();
    });

    it('is false for an unclosed slot', function () {
        expect(outline('<atom:card><x-slot:actions><atom:button/></atom:card>')->balanced())->toBeFalse();
    });

    it('is false for a stray atom or slot closer', function () {
        expect(outline('<atom:card></atom:card></atom:card>')->balanced())->toBeFalse()
            ->and(outline('<atom:card></x-slot></atom:card>')->balanced())->toBeFalse();
    });

    it('is false for an atom tag with no >', function () {
        expect(outline('<atom:card heading="x"')->balanced())->toBeFalse()
            ->and(outline('<atom:card heading="x><p>x</p>')->balanced())->toBeFalse();
    });

    it('is false when an HTML closer cuts an atom tag off', function () {
        $o = outline('<div><atom:card>x</div>');

        expect($o->balanced())->toBeFalse()
            ->and(first($o, 'atom:card')['close'])->toBeNull();
    });

    it('is true for unclosed HTML, which HTML allows', function () {
        expect(outline('<div><span><atom:card/>')->balanced())->toBeTrue();
    });

    it('is true for a conditional HTML wrapper that opens and closes in two @if blocks', function () {
        expect(outline('@if($a)<div class="x">@endif<atom:card/>@if($a)</div>@endif')->balanced())->toBeTrue();
    });
});

describe('malformed input', function () {
    it('does not throw, and keeps what it can read', function (string $blade) {
        $o = outline($blade);

        expect($o->nodes())->toBeArray()
            ->and($o->balanced())->toBeBool();

        foreach ($o->nodes() as $node) {
            expect($node['offset'])->toBeGreaterThanOrEqual(0)
                ->and($node['openEnd'])->toBeGreaterThan($node['offset'])
                ->and($node['end'])->toBeGreaterThanOrEqual($node['openEnd'])
                ->and($node['end'])->toBeLessThanOrEqual(strlen($blade))
                ->and($node['parent'] === null || $node['parent'] < $node['id'])->toBeTrue();

            $o->label($node);
            $o->classes($node);
            $o->branch($node);
            $o->slotOf($node);
        }
    })->with([
        'empty' => [''],
        'only text' => ['hello'],
        'a lone <' => ['<'],
        'a lone </' => ['</'],
        'an open tag name only' => ['<atom:button'],
        'a closer with no >' => ['<atom:button></atom:button'],
        'an open quote' => ['<atom:button label="x'],
        'an unclosed echo' => ['<atom:button label="{{ $x"></atom:button>'],
        'an unclosed echo in text' => ['<atom:button>{{ $x</atom:button>'],
        'an unclosed comment' => ['<atom:card><!-- open'],
        'an unclosed blade comment' => ['<atom:card>{{-- open <atom:button/>'],
        'an unclosed php block' => ['<?php echo 1; <atom:card/>'],
        'an unclosed @php' => ['@php $a = 1; <atom:card/>'],
        'an unclosed @verbatim' => ['@verbatim <atom:card/>'],
        'an unclosed directive argument' => ['@if($a <atom:card/>'],
        'a directive closer with nothing open' => ['@endif @endforeach @else @elseif($x) @case(1) <atom:card/>'],
        'an unclosed script' => ['<script><atom:card/>'],
        'stray closers only' => ['</atom:card></x-slot></div></x-slot:a>'],
        'only closers of other names' => ['<atom:card></atom:page></atom:card>'],
        'a slot closer of another name' => ['<x-slot:a></x-slot:b>'],
        'nested unclosed' => [str_repeat('<atom:card><div>', 50)],
        'binary junk' => ["\0\1\2<atom:card\xff\xfe>\0</atom:card>"],
        'an @ at the end' => ['<atom:card/>@'],
        'unicode' => ['<atom:button>日本語 {{ t("保存") }}</atom:button>'],
    ]);

    it('reports an unclosed atom tag as such and does not throw', function () {
        $o = outline('<atom:page><atom:page.header title="x"><atom:card>');

        expect($o->balanced())->toBeFalse()
            ->and(first($o, 'atom:card')['close'])->toBeNull()
            ->and(first($o, 'atom:card')['end'])->toBe(strlen($o->text()))
            ->and($o->label(first($o, 'atom:card')))->toBeNull();
    });

    it('survives a corpus of real-looking markup cut and mutated at every point', function () {
        $source = <<<'BLADE'
<atom:layouts.sidebar>
    <x-slot:actions><atom:button size="sm" wire:click="prev">@lang('Previous')</atom:button></x-slot:actions>
    <atom:page>
        <atom:page.header title="{{ t('Invoice') }}" :crumbs="[['label' => 'A > B', 'href' => '/x']]">
            <x-slot:status><atom:status :value="$invoice->status" /></x-slot:status>
            @if ($a)
                <x-slot:primary><atom:button variant="primary" @class(['x' => $b])>Save</atom:button></x-slot:primary>
            @elseif ($b)
                <x-slot name="primary"><atom:button>Other</atom:button></x-slot>
            @else
                <x-slot:menu><atom:menu.item variant="delete">{{ $label }}</atom:menu.item></x-slot:menu>
            @endif
        </atom:page.header>
        @forelse ($rows as $row)
            <atom:table.row :href="route('a', $row)"><atom:table.cell>{{ $row->name }}</atom:table.cell></atom:table.row>
        @empty
            <atom:empty heading="None"><x-slot:action><atom:button>Create</atom:button></x-slot:action></atom:empty>
        @endforelse
        @switch($x) @case(1) <atom:badge/> @break @default <atom:badge/> @endswitch
        <script>const a = "<atom:card>"; if (1 < 2) {}</script>
        <!-- <atom:card> -->
    </atom:page>
</atom:layouts.sidebar>
BLADE;

        $n = strlen($source);

        for ($cut = 0; $cut <= $n; $cut += 3) {
            $o = outline(substr($source, 0, $cut));
            expect($o->balanced())->toBeBool();

            foreach ($o->nodes() as $node) {
                $o->label($node);
                $o->branch($node);
                $o->slotOf($node);
                $o->children($node);
            }
        }

        mt_srand(7);
        $alphabet = ['<', '>', '/', '"', "'", '{{', '}}', '@if(', ')', '@endif', '@else', '</atom:card>', '<atom:card>', '<x-slot:a>', '</x-slot>', '{!!', '!!}', '--}}', '{{--', '@foreach($a as $b)', '@endforeach', '@empty', '@php', '@endphp', "\n"];

        for ($round = 0; $round < 300; $round++) {
            $mutated = $source;

            for ($edit = 0; $edit < 4; $edit++) {
                $at = mt_rand(0, strlen($mutated));
                $mutated = substr($mutated, 0, $at).$alphabet[array_rand($alphabet)].substr($mutated, $at + mt_rand(0, 6));
            }

            $o = outline($mutated);

            foreach ($o->nodes() as $node) {
                expect($node['end'])->toBeGreaterThanOrEqual($node['openEnd'])
                    ->and($node['end'])->toBeLessThanOrEqual(strlen($mutated));
                $o->label($node);
                $o->classes($node);
                $o->branch($node);
                $o->inLoop($node);
                $o->slotOf($node);
            }
        }
    });

    it('reads a deeply nested file without recursing', function () {
        $o = outline(str_repeat('<div>', 5000).'<atom:card/>'.str_repeat('</div>', 5000));

        expect($o->nodes())->toHaveCount(5001)
            ->and(first($o, 'atom:card')['depth'])->toBe(5000)
            ->and($o->ancestors(first($o, 'atom:card')))->toHaveCount(5000)
            ->and($o->balanced())->toBeTrue();
    });
});

describe('branch paths', function () {
    it('gives the branches of one chain different paths that never show together', function () {
        $o = outline('@if($a)<atom:button id="one"/>@elseif($b)<atom:button id="two"/>@else<atom:button id="three"/>@endif<atom:button id="after"/>');

        [$one, $two, $three, $after] = $o->atom('button');

        expect($o->branch($one))->toBe([[1, 0]])
            ->and($o->branch($two))->toBe([[1, 1]])
            ->and($o->branch($three))->toBe([[1, 2]])
            ->and($o->branch($after))->toBe([])
            ->and($o->together($one, $two))->toBeFalse()
            ->and($o->together($two, $three))->toBeFalse()
            ->and($o->together($one, $three))->toBeFalse()
            ->and($o->together($one, $after))->toBeTrue()
            ->and($o->together($three, $after))->toBeTrue()
            ->and($o->together($one, $one))->toBeTrue();
    });

    it('nests ifs, and a path that is a prefix of another shows together with it', function () {
        $o = outline('<atom:button id="top"/>@if($a)<atom:button id="a"/>@if($b)<atom:button id="ab"/>@else<atom:button id="anotb"/>@endif<atom:button id="a2"/>@endif');

        [$top, $a, $ab, $anotb, $a2] = $o->atom('button');

        expect($o->branch($ab))->toBe([[1, 0], [2, 0]])
            ->and($o->branch($anotb))->toBe([[1, 0], [2, 1]])
            ->and($o->branch($a2))->toBe([[1, 0]])
            ->and($o->together($a, $ab))->toBeTrue()
            ->and($o->together($a2, $ab))->toBeTrue()
            ->and($o->together($ab, $anotb))->toBeFalse()
            ->and($o->together($top, $anotb))->toBeTrue();
    });

    it('does not count two separate @if blocks together', function () {
        $o = outline('@if($a)<atom:button id="x"/>@endif @if($b)<atom:button id="y"/>@endif');

        [$x, $y] = $o->atom('button');

        expect($o->together($x, $y))->toBeFalse();
    });

    it('reads @switch with @case and @default', function () {
        $o = outline('@switch($s) @case(1) <atom:badge id="one"/> @break @case(2) <atom:badge id="two"/> @break @default <atom:badge id="d"/> @endswitch<atom:badge id="after"/>');

        [$one, $two, $default, $after] = $o->atom('badge');

        expect($o->together($one, $two))->toBeFalse()
            ->and($o->together($two, $default))->toBeFalse()
            ->and($o->together($one, $after))->toBeTrue()
            ->and(count($o->branch($one)))->toBe(1)
            ->and($o->branch($after))->toBe([]);
    });

    it('reads @forelse, with its bare @empty as the second branch, apart from @empty($x)', function () {
        $o = outline('@forelse($rows as $row)<atom:badge id="row"/>@empty<atom:badge id="none"/>@endforelse @empty($list)<atom:badge id="e1"/>@else<atom:badge id="e2"/>@endempty');

        [$row, $none, $e1, $e2] = $o->atom('badge');

        expect($o->together($row, $none))->toBeFalse()
            ->and($o->inLoop($row))->toBeTrue()
            ->and($o->inLoop($none))->toBeFalse()
            ->and($o->together($e1, $e2))->toBeFalse()
            ->and($o->inLoop($e1))->toBeFalse()
            ->and($o->together($row, $e1))->toBeFalse()
            ->and($o->branch($e1)[0][1])->toBe(0)
            ->and($o->branch($e2)[0][1])->toBe(1);
    });

    it('reads @unless, @isset, @auth, @guest, @can, @cannot, @canany, @env, @production, @error and @session', function (string $open, string $middle, string $close) {
        $o = outline($open.'<atom:button id="a"/>'.$middle.'<atom:button id="b"/>'.$close.'<atom:button id="c"/>');

        [$a, $b, $c] = $o->atom('button');

        expect($o->branch($a))->not->toBe([])
            ->and($o->together($a, $b))->toBeFalse()
            ->and($o->branch($c))->toBe([])
            ->and($o->together($a, $c))->toBeTrue();
    })->with([
        'unless' => ['@unless($a)', '@else', '@endunless'],
        'isset' => ['@isset($a)', '@else', '@endisset'],
        'auth' => ['@auth', '@else', '@endauth'],
        'auth with a guard' => ['@auth(\'admin\')', '@else', '@endauth'],
        'guest' => ['@guest', '@else', '@endguest'],
        'can' => ['@can(\'update\', $post)', '@elsecan(\'create\')', '@endcan'],
        'cannot' => ['@cannot(\'update\', $post)', '@else', '@endcannot'],
        'canany' => ['@canany([\'a\', \'b\'], $post)', '@elsecanany([\'c\'])', '@endcanany'],
        'env' => ['@env(\'local\')', '@else', '@endenv'],
        'production' => ['@production', '@else', '@endproduction'],
        'error' => ['@error(\'email\')', '@else', '@enderror'],
        'session' => ['@session(\'status\')', '@else', '@endsession'],
        'if with a space before the parenthesis' => ['@if ($a)', '@else', '@endif'],
    ]);

    it('marks a loop body and reads past a loop', function () {
        $o = outline('<atom:button id="out"/>@foreach($rows as $row)<atom:button id="in"/>@if($x)<atom:button id="deep"/>@endif @endforeach <atom:button id="out2"/>@for($i = 0; $i < 3; $i++)<atom:button id="for"/>@endfor @while($x)<atom:button id="while"/>@endwhile');

        [$out, $in, $deep, $out2, $for, $while] = $o->atom('button');

        expect($o->inLoop($out))->toBeFalse()
            ->and($o->inLoop($in))->toBeTrue()
            ->and($o->inLoop($deep))->toBeTrue()
            ->and($o->inLoop($out2))->toBeFalse()
            ->and($o->inLoop($for))->toBeTrue()
            ->and($o->inLoop($while))->toBeTrue()
            ->and($o->branch($out2))->toBe([]);
    });

    it('is not fooled by a directive inside a string, an echo or an attribute', function () {
        $o = outline('{{ "@if(x)" }}<atom:button id="a" label="@if($x) a @endif"/>@include(\'a\', [\'t\' => \'@endif\'])<atom:button id="b"/>');

        [$a, $b] = $o->atom('button');

        expect($o->branch($a))->toBe([])
            ->and($o->branch($b))->toBe([])
            ->and($o->together($a, $b))->toBeTrue();
    });

    it('does not take an email address or an @@ escape for a directive', function () {
        $o = outline('<p>mail me@if.example or @@if($x)</p><atom:button/>');

        expect($o->branch(first($o, 'atom:button')))->toBe([]);
    });

    it('survives a closer with nothing open, and an @else with no @if', function () {
        $o = outline('@endif @else @elseif($a) @endforeach <atom:button id="a"/> @if($x) <atom:button id="b"/> @endforeach @endif <atom:button id="c"/>');

        [$a, $b, $c] = $o->atom('button');

        expect($o->branch($a))->toBe([])
            ->and($o->branch($b))->toBe([[1, 0]])
            ->and($o->branch($c))->toBe([]);
    });

    it('drops blocks left open inside a block that closes', function () {
        $o = outline('@if($a) @foreach($b as $c) <atom:button id="in"/> @endif <atom:button id="out"/>');

        [$in, $out] = $o->atom('button');

        expect($o->inLoop($in))->toBeTrue()
            ->and($o->branch($out))->toBe([]);
    });

    it('reads the path at an offset', function () {
        $o = outline('ab @if($a)xx @endif');

        expect($o->branchAt(0))->toBe([])
            ->and($o->branchAt(11))->toBe([[1, 0]])
            ->and($o->branchAt(strlen($o->text())))->toBe([]);
    });
});

describe('label', function () {
    it('reads the text inside, with tags removed', function () {
        $o = outline('<atom:button><atom:icon.add /> Save   <span class="x">and</span> close</atom:button>');

        expect($o->label(first($o, 'atom:button')))->toBe('Save and close');
    });

    it('keeps the literals of t(), __(), trans() and @lang()', function () {
        $o = outline(implode('', [
            '<atom:button>{{ t(\'Save\') }}</atom:button>',
            '<atom:button>{{ __("Delete") }}</atom:button>',
            '<atom:button>{!! trans(\'Archive\') !!}</atom:button>',
            '<atom:button>@lang(\'Print\')</atom:button>',
            '<atom:button>{{ t(\'Add\', 1) }} {{ t(\'line\') }}</atom:button>',
            '<atom:button>{{ t(\'It\\\'s\') }}</atom:button>',
            '<atom:button>Save &amp; close</atom:button>',
        ]));

        expect(array_map(fn ($b) => $o->label($b), $o->atom('button')))->toBe(['Save', 'Delete', 'Archive', 'Print', 'Add line', "It's", 'Save & close']);
    });

    it('is null for any other echo, an interpolated string or a directive', function (string $inner) {
        $o = outline('<atom:button>'.$inner.'</atom:button>');

        expect($o->label(first($o, 'atom:button')))->toBeNull();
    })->with([
        'a variable' => ['{{ $label }}'],
        'a property' => ['{{ $post->title }}'],
        'a call that is not a translation' => ['{{ ucfirst($a) }}'],
        'a translation of a variable' => ['{{ t($key) }}'],
        'a translation wrapped in a call' => ['{{ ucfirst(t(\'Save\')) }}'],
        'a translation then more' => ['{{ t(\'Save\') . $x }}'],
        'an interpolated string' => ['{{ t("Save $x") }}'],
        'a concatenated first argument' => ['{{ t(\'Save\' . $x) }}'],
        'text beside an echo' => ['Save {{ $count }}'],
        'an @if' => ['@if($a) Save @else Update @endif'],
        'an @lang of a variable' => ['@lang($key)'],
        'an unclosed echo' => ['{{ $x'],
    ]);

    it('lets a label, heading or title attribute win over the text', function () {
        $o = outline('<atom:button label="Save" title="Tip">Other</atom:button><atom:card heading="{{ t(\'Customer\') }}">x</atom:card><atom:page.header title="Invoices"/><atom:button title="Tip">Other</atom:button>');

        expect($o->label(first($o, 'atom:button')))->toBe('Save')
            ->and($o->label(first($o, 'atom:card')))->toBe('Customer')
            ->and($o->label(first($o, 'atom:page.header')))->toBe('Invoices')
            ->and($o->label(first($o, 'atom:button', 1)))->toBe('Tip');
    });

    it('is null for a bound label that is not a literal, and reads one that is', function () {
        $o = outline('<atom:button :label="$x">A</atom:button><atom:button :label="\'Save\'">A</atom:button><atom:button :label="t(\'Pay now\')">A</atom:button><atom:button x-bind:label="$y">A</atom:button><atom:button label>A</atom:button>');

        expect(array_map(fn ($b) => $o->label($b), $o->atom('button')))->toBe([null, 'Save', 'Pay now', null, null]);
    });

    it('does not read the title of an HTML element as its label', function () {
        $o = outline('<a title="Tooltip" href="/x">Open</a>');

        expect($o->label(first($o, 'a')))->toBe('Open');
    });

    it('is empty for a self-closing tag with no label, and null for one cut off', function () {
        $o = outline('<atom:button icon="more" /><atom:button>never closed');

        expect($o->label(first($o, 'atom:button')))->toBe('')
            ->and($o->label(first($o, 'atom:button', 1)))->toBeNull();
    });

    it('collapses whitespace across lines', function () {
        $o = outline("<atom:menu.item>\n    Save\n    as   template\n</atom:menu.item>");

        expect($o->label(first($o, 'atom:menu.item')))->toBe('Save as template');
    });

    it('reads a label with a nested tag that holds an echo in an attribute', function () {
        $o = outline('<atom:button><atom:icon.add class="{{ $c }}" /> Add <atom:badge :value="$n"/></atom:button>');

        expect($o->label(first($o, 'atom:button')))->toBe('Add');
    });
});

describe('classes', function () {
    it('reads a static class, splitting on whitespace', function () {
        $o = outline("<div class=\"  rounded-panel\n border  p-4 \"/>");

        expect($o->classes(first($o, 'div')))->toBe(['rounded-panel', 'border', 'p-4']);
    });

    it('drops a token that touches an echo', function () {
        $o = outline('<div class="flex px-{{ $n }} {{ $extra }} gap-2"></div>');

        expect($o->classes(first($o, 'div')))->toBe(['flex', 'gap-2']);
    });

    it('reads string literals in :class, x-bind:class and @class', function () {
        $o = outline('<div class="a" :class="$on ? \'b c\' : \'d\'" x-bind:class="{ \'e\': open, \'f g\': x }" @class([\'h\', \'i j\' => $k, \'a\']) ></div>');

        expect($o->classes(first($o, 'div')))->toBe(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);
    });

    it('is empty for a tag with none', function () {
        $o = outline('<atom:card/>');

        expect($o->classes(first($o, 'atom:card')))->toBe([])
            ->and($o->classes(7))->toBe([]);
    });
});

describe('attributes', function () {
    it('tells a static attribute from a bound one', function () {
        $o = outline('<atom:button variant=" Primary " :size="$s" x-bind:icon="i" wire:click="{{ $x }}" disabled>x</atom:button>');
        $button = first($o, 'atom:button');

        expect($o->has($button, 'variant'))->toBeTrue()
            ->and($o->has($button, 'size'))->toBeTrue()
            ->and($o->has($button, 'icon'))->toBeTrue()
            ->and($o->has($button, 'disabled'))->toBeTrue()
            ->and($o->has($button, 'confirm'))->toBeFalse()
            ->and($o->value($button, 'variant'))->toBe('primary')
            ->and($o->value($button, 'size'))->toBeNull()
            ->and($o->value($button, 'wire:click'))->toBeNull()
            ->and($o->value($button, 'disabled'))->toBeNull();
    });
});
