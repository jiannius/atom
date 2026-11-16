<?php

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\HostScanner;

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

    it('gives one slot name for <x-slot:name>, <x-slot name="name"> and the kebab form, as Blade does', function () {
        $o = outline('<atom:c><x-slot:headerActions><atom:a/></x-slot:headerActions><x-slot name="headerActions"><atom:b/></x-slot><x-slot:header-actions><atom:d/></x-slot:header-actions><x-slot name="header-actions"><atom:e/></x-slot><x-slot:Menu></x-slot:Menu></atom:c>');

        expect(array_column(array_filter($o->nodes(), fn ($node) => $node['kind'] === 'slot'), 'slot'))->toBe(['headerActions', 'headerActions', 'headerActions', 'header-actions', 'Menu'])
            ->and($o->balanced())->toBeTrue()
            ->and($o->slotOf(first($o, 'atom:a'))['name'])->toBe('headerActions')
            ->and($o->slotOf(first($o, 'atom:b'))['name'])->toBe('headerActions')
            ->and($o->inside(first($o, 'atom:a'), 'x-slot:headerActions')['slot'])->toBe('headerActions')
            ->and($o->inside(first($o, 'atom:d'), 'x-slot:header-actions')['slot'])->toBe('headerActions')
            ->and($o->inside(first($o, 'atom:b'), 'x-slot:headeractions'))->toBeNull();
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

    it('ends an element whose closer was left out where the next one starts, or its parent closes', function () {
        $o = outline('<ul><li>A <b>x</b><li>B</ul><p>one<p>two');
        [$one, $two] = array_values(array_filter($o->nodes(), fn ($node) => $node['tag'] === 'li'));
        [$p1, $p2] = array_values(array_filter($o->nodes(), fn ($node) => $node['tag'] === 'p'));

        expect($one['close'])->toBeNull()
            ->and($one['end'])->toBe($two['offset'])
            ->and(array_column($o->descendants($one), 'tag'))->toBe(['b'])
            ->and($two['end'])->toBe(strpos($o->text(), '</ul>'))
            ->and($p1['end'])->toBe($p2['offset'])
            ->and($p2['end'])->toBe(strlen($o->text()))
            ->and($o->parent($two)['tag'])->toBe('ul')
            ->and($o->parent($p2))->toBeNull();
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
            ->and(array_keys($div['attributes']))->toBe(['data-a', 'data-b'])
            ->and(array_keys($div['spans']))->toBe(['data-a', 'data-b'])
            ->and($o->parent(first($o, 'atom:button'))['tag'])->toBe('div');
    });

    it('does not take @else or @endif inside a tag for an attribute, with or without a space', function () {
        $o = outline('<atom:card @if($a) x="1" @else y="2" @endif @elseif($b) z="3" @endisset/><atom:card @if($a) x="1" @endif @else/>');

        expect(array_keys(first($o, 'atom:card')['attributes']))->toBe(['x', 'y', 'z'])
            ->and(array_keys(first($o, 'atom:card', 1)['attributes']))->toBe(['x'])
            ->and($o->balanced())->toBeTrue();
    });

    it('reads quotes and parentheses inside a directive argument', function () {
        $o = outline('@if($a === \')\')<atom:card id="a"/>@elseif($b === "(")<atom:card id="b"/>@endif<atom:card id="c"/><atom:card @class([\'a\' => \')\', "b" => "("]) id="d"/>');

        [$a, $b, $c, $d] = $o->atom('card');

        expect($o->branch($a))->toBe([[1, 0]])
            ->and($o->branch($b))->toBe([[1, 1]])
            ->and($o->branch($c))->toBe([])
            ->and($d['directives'][0]['args'])->toBe('[\'a\' => \')\', "b" => "("]')
            ->and($d['attributes'])->toBe(['id' => 'd']);
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

    it('keeps a style and a script as raw text up to their closer', function () {
        $o = outline('<style>.a > .b { color: red } @if($x) .c { } <atom:card/></style><atom:button id="a"/><script>@if($y) </script><atom:button id="b"/>');

        expect(tags($o))->toBe(['style', 'atom:button', 'script', 'atom:button'])
            ->and(first($o, 'style')['close'])->not->toBeNull()
            ->and(first($o, 'script')['close'])->not->toBeNull()
            ->and($o->branch(first($o, 'atom:button')))->toBe([])
            ->and($o->branch(first($o, 'atom:button', 1)))->toBe([]);

        $open = outline('<STYLE>.a > .b</STYLE><atom:card/><style>unclosed <atom:card/>');

        expect(tags($open))->toBe(['style', 'atom:card', 'style'])
            ->and(first($open, 'style')['close'])->not->toBeNull();
    });

    it('reads {{{ }}} as one echo, whatever is inside', function () {
        $o = outline('{{{ \'}}<atom:card id="a"/>\' }}}<atom:card id="b"/><atom:button label="{{{ $a > 1 }}}" id="c"/>');

        expect(array_column(array_column($o->nodes(), 'attributes'), 'id'))->toBe(['b', 'c'])
            ->and(first($o, 'atom:button')['attributes']['label'])->toBe('{{{ $a > 1 }}}');
    });

    it('reads an unquoted value ending in /> as a self-closing tag, and a lone / as a value', function () {
        $o = outline('<atom:card a=/x/><atom:card b=/>x</atom:card><atom:card c=x/y>z</atom:card>');

        [$a, $b, $c] = $o->atom('card');

        expect($a['selfClosing'])->toBeTrue()
            ->and($a['attributes'])->toBe(['a' => '/x'])
            ->and($b['selfClosing'])->toBeFalse()
            ->and($b['attributes'])->toBe(['b' => '/'])
            ->and($b['close'])->not->toBeNull()
            ->and($c['attributes'])->toBe(['c' => 'x/y'])
            ->and($o->balanced())->toBeTrue();
    });

    it('reads a bare closer with a slot name that is not open as stray, not as the slot open', function () {
        $o = outline('<atom:card><x-slot:a><atom:button/></x-slot:b></atom:card>');

        expect(first($o, 'x-slot')['close'])->toBeNull()
            ->and($o->balanced())->toBeFalse()
            ->and(first($o, 'atom:card')['close'])->not->toBeNull();

        $paired = outline('<x-slot:a><x-slot:b></x-slot:a></x-slot:b>');

        expect(first($paired, 'x-slot')['close'])->not->toBeNull()
            ->and(first($paired, 'x-slot', 1)['end'])->toBe(first($paired, 'x-slot')['close']);
    });

    it('has no closer for a void element, whichever it is', function () {
        foreach (['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'] as $void) {
            $o = outline('<div><'.$void.'><atom:card/></div>');

            expect(first($o, $void)['selfClosing'])->toBeTrue()
                ->and($o->parent(first($o, 'atom:card'))['tag'])->toBe('div');
        }
    });

    it('does not take an unquoted value that ends in a slash for a self-closing tag unless the > follows', function () {
        $o = outline('<atom:card a=/x/ >text</atom:card>');

        expect(first($o, 'atom:card')['selfClosing'])->toBeFalse()
            ->and(first($o, 'atom:card')['attributes'])->toBe(['a' => '/x/'])
            ->and(first($o, 'atom:card')['close'])->not->toBeNull();
    });

    it('steps over @php(...) and @php ... @endphp, and over a @verbatim block, never reading what is inside', function () {
        $o = outline('@php($a = "<atom:badge>")<atom:card id="a"/>@php $x = "<atom:input>"; @endphp<atom:card id="b"/>@verbatim <atom:link/> @if($x) @endverbatim<atom:card id="c"/><atom:card id="d"/>');

        expect(array_column(array_column($o->nodes(), 'attributes'), 'id'))->toBe(['a', 'b', 'c', 'd'])
            ->and($o->branch(first($o, 'atom:card', 2)))->toBe([]);

        $open = outline('<atom:card id="a"/>@verbatim <atom:card id="b"/>');
        $php = outline('<atom:card id="a"/>@php $x = 1; <atom:card id="b"/>');

        expect($open->nodes())->toHaveCount(1)
            ->and($php->nodes())->toHaveCount(2);
    });

    it('ends a Blade comment at --}}, whatever }} is inside it', function () {
        $o = outline('{{-- a }} <atom:card/> --}}<atom:badge/>');

        expect(tags($o))->toBe(['atom:badge']);
    });

    it('does not let a failed tag that read ahead decide where an echo inside it ends', function () {
        $o = outline('<a q="<b z={{ 1 }} k> y={{ 2 '.str_repeat(' ', 30000).'}}');

        expect(tags($o))->toBe(['b'])
            ->and(first($o, 'b')['attributes'])->toBe(['z' => '{{ 1 }}', 'k' => true]);
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

    it('puts a newline on the line it ends', function () {
        $o = outline("ab\ncd\n\nef");

        expect([0, 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 99])->each->toBeInt()
            ->and(array_map(fn ($offset) => $o->line($offset), [0, 2, 3, 5, 6, 7, 8]))->toBe([1, 1, 2, 2, 3, 4, 4]);
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

describe('the reader', function () {
    it('reads what the older scanners do not: names with _, a > in @class, an unquoted echo, absolute spans, tags in strings', function () {
        $blade = "<atom:card data_x=\"1\" @class(['a' => \$b > 1, 'c' => \$d]) title={{ \$t }} :a=\"'<atom:badge>'\" id=\"{{ '<atom:link>' }}\">\n<atom:button wire:click=\"go('</atom:card>')\"/></atom:card>";
        $o = outline($blade);
        $card = first($o, 'atom:card');

        expect(tags($o))->toBe(['atom:card', 'atom:button'])
            ->and(array_keys($card['attributes']))->toBe(['data_x', 'title', ':a', 'id'])
            ->and($card['attributes']['title'])->toBe('{{ $t }}')
            ->and($card['directives'][0]['args'])->toBe("['a' => \$b > 1, 'c' => \$d]")
            ->and($card['openEnd'])->toBe(strpos($blade, ">\n") + 1)
            ->and(substr($blade, $card['spans']['id'][0], 4))->toBe('id="')
            ->and($o->line($o->atom('button')[0]['spans']['wire:click'][0]))->toBe(2)
            ->and($o->balanced())->toBeTrue();
    });
});

/**
 * A view of half a megabyte (two, the most HostScanner reads, for the shapes that need
 * it) that is shaped to be slow to read: something that never ends, repeated.
 */
function degenerate(string $shape, int $bytes = 524288): string
{
    $repeat = fn (string $unit) => str_repeat($unit, intdiv($bytes, strlen($unit)));

    return match ($shape) {
        'open attribute quote' => $repeat('<a x="'),
        'open atom tag' => $repeat('<atom:b '),
        'open echo in an attribute' => $repeat('<a b="{{ '),
        'open attribute quote with a > in reach' => $repeat(str_repeat('<a x="', 2501).'>'),
        'echo with a far closer' => $repeat('<a b="{{ ').'}}',
        'open echo' => $repeat('{{ '),
        'open raw echo' => $repeat('{!! '),
        'open blade comment' => $repeat('{{-- '),
        'open lang' => $repeat('@lang('),
        'open directive argument' => $repeat('@if('),
        'php with no end' => $repeat('@php '),
        'closer with no >' => $repeat('</a '),
        'closer with a far >' => $repeat('</a ').'>',
        'doctype with no >' => $repeat('<!x'),
        'closers of nothing open' => str_repeat('<div>', 14000).$repeat('</span>'),
        'slot closers of another name' => '<x-slot:a>'.str_repeat('<div>', 14000).$repeat('</x-slot:b>'),
        'divs never closed' => $repeat('<div>'),
        'tags never closed' => $repeat('<atom:card><x-slot:a>'),
        'scripts never closed' => $repeat('<script>'),
        'ifs never closed' => $repeat('@if($a)'),
        'foreach and stray endif' => $repeat('@foreach($a as $b)').$repeat('@endif '),
        'foreach under the cap and stray endif' => str_repeat('@foreach($a as $b)', 250).$repeat('@endif '),
        'foreach under the cap and stray else' => str_repeat('@foreach($a as $b)', 250).$repeat('@else '),
        'directive arguments never closed in a tag' => '<a '.str_repeat('@if(', 4000).'>',
        'switch and else' => $repeat('@switch($a) @case(1) @else @endif '),
        'forelse and empty' => $repeat('@forelse($a as $b) @empty @endforelse '),
        'endifs of nothing' => $repeat('@endif '),
        'ifs opened and closed' => $repeat("@if(\$a)<b>@else<i>@endif\n"),
        'branches changing at depth' => str_repeat('@if($a)', 255).$repeat('@else '),
        'buttons' => $repeat("<atom:button>x</atom:button>\n"),
        'newlines' => $repeat("\n"),
        default => throw new InvalidArgumentException($shape),
    };
}

/**
 * The CPU seconds this process has used, so that a busy machine does not fail a bound
 */
function cpuSeconds(): float
{
    $usage = getrusage();

    return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6 + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
}

describe('cost bounds', function () {
    afterEach(fn () => set_time_limit(0));

    it('reads a degenerate view in bounded time and memory', function (string $shape) {
        set_time_limit(60);

        $blade = degenerate($shape, in_array($shape, ['ifs opened and closed', 'closer with a far >'], true) ? 2097152 : 524288);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $start = cpuSeconds();

        $o = outline($blade);

        foreach ($o->nodes() as $node) {
            if ($node['id'] % 997 === 0) {
                $o->label($node);
                $o->branch($node);
                $o->ancestors($node);
                $o->children($node);
                $o->slotOf($node);
            }
        }

        $o->line(strlen($blade));

        expect(cpuSeconds() - $start)->toBeLessThan(6.0)
            ->and(memory_get_peak_usage() - $before)->toBeLessThan(160 * 1024 * 1024)
            ->and(count($o->nodes()))->toBeLessThanOrEqual(50000);
    })->with([
        'open attribute quote', 'open atom tag', 'open echo in an attribute', 'open attribute quote with a > in reach', 'echo with a far closer', 'open echo', 'open raw echo',
        'open blade comment', 'open lang', 'open directive argument', 'php with no end', 'closer with no >', 'closer with a far >', 'doctype with no >', 'directive arguments never closed in a tag',
        'closers of nothing open', 'slot closers of another name', 'divs never closed', 'tags never closed', 'scripts never closed',
        'ifs never closed', 'foreach and stray endif', 'foreach under the cap and stray endif', 'foreach under the cap and stray else', 'switch and else', 'forelse and empty', 'endifs of nothing',
        'ifs opened and closed', 'branches changing at depth', 'buttons', 'newlines',
    ]);

    it('answers every query on a view of 50000 tags without a quadratic walk', function () {
        set_time_limit(60);

        $o = outline('<atom:page>'.str_repeat('<atom:card><x-slot:a><atom:button>Go</atom:button></x-slot:a></atom:card>', 12000).'</atom:page>');

        $start = cpuSeconds();

        foreach ($o->nodes() as $node) {
            $o->children($node);
            $o->ancestors($node);
            $o->inside($node, 'atom:page');
            $o->slotOf($node);
        }

        $page = first($o, 'atom:page');

        expect(count($o->nodes()))->toBe(36001)
            ->and(count($o->children($page)))->toBe(12000)
            ->and(cpuSeconds() - $start)->toBeLessThan(6.0);
    });

    it('keeps memory flat for blocks left open: no copy of the path in each directive', function () {
        memory_reset_peak_usage();
        $before = memory_get_usage();

        $o = outline(str_repeat('@if($a)', 2000).'<atom:card/>');

        expect(memory_get_peak_usage() - $before)->toBeLessThan(8 * 1024 * 1024)
            ->and($o->branchesDeclined())->toBeTrue();
    });

    it('labels every node of a deep balanced view in bounded time', function () {
        set_time_limit(60);

        $o = outline(str_repeat('<atom:button>', 255).str_repeat('<b/>', 49000).str_repeat('</atom:button>', 255));

        $start = cpuSeconds();
        $labels = array_map(fn ($node) => $o->label($node), $o->nodes());

        expect($o->balanced())->toBeTrue()
            ->and(count($labels))->toBe(255 + 49000)
            ->and(cpuSeconds() - $start)->toBeLessThan(6.0)
            ->and($labels[0])->toBeNull()
            ->and($labels[254])->toBeNull()
            ->and($labels[300])->toBe('');

        $small = outline('<atom:button>'.str_repeat('<b>x</b>', 100).'</atom:button>');

        expect($small->label(first($small, 'atom:button')))->toBe(str_repeat('x', 100));
    });

    it('says the view was cut short when the budget is spent, whichever construct is dropped, and still labels what was read', function () {
        $spend = fn (int $calls) => str_repeat('@lang(', $calls).str_repeat('x', 25000);

        $tag = outline('<atom:button>@lang(\'Save\')</atom:button><atom:card label="{{ t(\'Hi\') }}"/>'.$spend(10).'<div></div>');

        expect($tag->balanced())->toBeFalse()
            ->and(tags($tag))->toBe(['atom:button', 'atom:card'])
            ->and($tag->label(first($tag, 'atom:button')))->toBe('Save')
            ->and($tag->label(first($tag, 'atom:card')))->toBe('Hi');

        expect(outline($spend(11))->balanced())->toBeFalse()
            ->and(tags(outline($spend(9).'<div></div>')))->toBe(['div'])
            ->and(outline($spend(9).'<div></div>')->balanced())->toBeTrue();
    });

    it('does not count the cuts before a node against its label', function () {
        $o = outline(str_repeat('<b/>', 12000).'<atom:button>Go <i>now</i></atom:button>');

        expect($o->label(first($o, 'atom:button')))->toBe('Go now');
    });

    it('does not let label() text use up the budget of the scan, so the order of calls changes nothing', function () {
        $o = outline('<atom:card label="'.str_repeat('@lang(', 3000).'"/><atom:card id="after"/>');

        $start = cpuSeconds();
        $first = $o->label(first($o, 'atom:card'));
        $again = $o->label(first($o, 'atom:card'));

        expect($first)->toBeNull()
            ->and($again)->toBeNull()
            ->and(cpuSeconds() - $start)->toBeLessThan(6.0)
            ->and($o->balanced())->toBeTrue();
    });

    it('does not rescan for a closer it has already failed to find, nor for one far away', function () {
        set_time_limit(60);

        $start = cpuSeconds();
        $o = outline(str_repeat('<a b="{{ ', 100000).'}}"/>');

        expect(cpuSeconds() - $start)->toBeLessThan(6.0)
            ->and($o->nodes())->toBeArray();
    });

    it('reads as text a tag, a closer or an argument list longer than MAX_TAG_BYTES, and keeps one that fits', function () {
        $long = str_repeat('x', 20000);
        $fits = str_repeat('x', 19900);

        $tag = outline('<atom:card title="'.$long.'"/>');
        expect($tag->nodes())->toBe([])
            ->and($tag->balanced())->toBeFalse();

        $tag = outline('<atom:card title="'.$fits.'"/>');
        expect($tag->nodes())->toHaveCount(1)
            ->and($tag->balanced())->toBeTrue();

        $closer = outline('<atom:card></atom:card'.str_repeat(' ', 20001).'>');
        expect(first($closer, 'atom:card')['close'])->toBeNull()
            ->and($closer->balanced())->toBeFalse();

        $closer = outline('<atom:card></atom:card'.str_repeat(' ', 19900).'>');
        expect(first($closer, 'atom:card')['close'])->not->toBeNull()
            ->and($closer->balanced())->toBeTrue();

        $args = outline('@if('.$long.')<atom:card/>@endif');
        expect($args->branch(first($args, 'atom:card')))->toBe([[1, 0]]);

        $label = outline('<atom:card label="'.$fits.'"/>');
        expect(strlen($label->label(first($label, 'atom:card'))))->toBe(19900);
    });

    it('reads as text what follows once the budget for failed scans is spent, and keeps what came before', function () {
        $o = outline('<atom:card id="first"/>'.str_repeat('@lang(', 60000).'<atom:card id="last"/><x-slot:a></x-slot:a>');

        expect(array_column(array_column($o->nodes(), 'attributes'), 'id'))->toBe(['first'])
            ->and($o->balanced())->toBeFalse();

        $html = outline('<div>'.str_repeat('@lang(', 60000).'<span>x</span><b>y</b></div>');

        expect($html->balanced())->toBeFalse();

        $fine = outline('<atom:card id="first"/>'.str_repeat('@lang(', 3).'<atom:card id="last"/>');

        expect(array_column(array_column($fine->nodes(), 'attributes'), 'id'))->toBe(['first', 'last'])
            ->and($fine->balanced())->toBeTrue();
    });
});

describe('against HostScanner::parseTags()', function () {
    /**
     * What HostScanner reads of the same text: name, attribute names with their values, and where it is.
     */
    function scannerTags(string $blade): array
    {
        $scanner = new class extends HostScanner {};

        return array_map(fn ($tag) => ['name' => $tag['name'], 'attributes' => $tag['attributes'], 'offset' => $tag['offset'], 'spans' => $tag['spans'], 'attributes_at' => $tag['attributes_at']], $scanner->parseTags($blade));
    }

    it('reads the same attribute names and values where both read a plain tag, with _ in a name', function () {
        $blade = '<atom:card data_x="1" my_attr=2 id="a" disabled/>';

        expect(scannerTags($blade)[0]['attributes'])->toBe(['data_x' => '1', 'my_attr' => '2', 'id' => 'a', 'disabled' => true])
            ->and(first(outline($blade), 'atom:card')['attributes'])->toBe(['data_x' => '1', 'my_attr' => '2', 'id' => 'a', 'disabled' => true]);
    });

    it('reads a > inside @class([... => ...]) as part of the tag, where HostScanner ends the attributes there', function () {
        $blade = "<atom:card @class(['a' => \$b > 1]) id=\"a\"/>";

        expect(array_keys(scannerTags($blade)[0]['attributes']))->toBe(['@class([', 'a'])
            ->and(first(outline($blade), 'atom:card')['attributes'])->toBe(['id' => 'a'])
            ->and(first(outline($blade), 'atom:card')['directives'][0]['name'])->toBe('class');
    });

    it('reads an unquoted {{ $v }} as one value, where HostScanner splits it into three attributes', function () {
        $blade = '<atom:card title={{ $t }} id="a"/>';

        expect(scannerTags($blade)[0]['attributes'])->toBe(['title' => '{{', '$t' => true, '}}' => true, 'id' => 'a'])
            ->and(first(outline($blade), 'atom:card')['attributes'])->toBe(['title' => '{{ $t }}', 'id' => 'a']);
    });

    it('gives spans as offsets into the whole text, where HostScanner gives them from the end of the tag name', function () {
        $blade = "xx\n<atom:card  id=\"a\" b/>";
        $tag = scannerTags($blade)[0];
        $node = first(outline($blade), 'atom:card');

        expect($tag['spans']['id'])->toBe([2, 8])
            ->and(substr($blade, $tag['attributes_at'] + $tag['spans']['id'][0], 6))->toBe('id="a"')
            ->and($node['spans']['id'])->toBe([$tag['attributes_at'] + 2, $tag['attributes_at'] + 8])
            ->and(substr($blade, $node['spans']['id'][0], 6))->toBe('id="a"')
            ->and(substr($blade, $node['spans']['b'][0], 1))->toBe('b');
    });

    it('does not read a tag written inside a string, a script or an echo, where HostScanner reads each one', function () {
        $blade = '<script>const s = "<atom:card id=\'x\'/>";</script>{{ \'<atom:card id="y"/>\' }}<atom:card id="z"/>';

        expect(array_map(fn ($tag) => $tag['attributes']['id'], scannerTags($blade)))->toBe(['x', 'y', 'z'])
            ->and(array_map(fn ($node) => $node['attributes']['id'], outline($blade)->atom()))->toBe(['z']);
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
            ->and($o->inside(first($o, 'button'), 'html:form')['tag'])->toBe('form');
    });

    it('takes the bare HTML names a check asks for, refuses only the ones an atom component shares, and leaves x- components alone', function () {
        $o = outline('<x-forms.field><div><span><a><ul><li><p><section><nav><tr><td><atom:button/></td></tr></nav></section></p></li></ul></a></span></div></x-forms.field>');
        $button = first($o, 'atom:button');

        foreach (['div', 'span', 'a', 'ul', 'li', 'p', 'section', 'nav', 'tr', 'td', 'h1', 'header', 'footer', 'main', 'aside', 'article', 'thead', 'tbody', 'th', 'ol', 'dl'] as $html) {
            expect(fn () => $o->inside($button, $html))->not->toThrow(InvalidArgumentException::class);
        }

        foreach (['button', 'form', 'table', 'label', 'input', 'select', 'textarea', 'link', 'menu', 'kbd', 'caption', 'html', 'dd'] as $both) {
            expect(fn () => $o->inside($button, $both))->toThrow(InvalidArgumentException::class, 'write `atom:'.$both.'` or `html:'.$both.'`');
        }

        foreach (['card', 'page', 'heading', 'badge', 'modal', 'alert'] as $atom) {
            expect(fn () => $o->inside($button, $atom))->not->toThrow(InvalidArgumentException::class);
        }

        expect($o->inside($button, 'x-forms.field')['tag'])->toBe('x-forms.field')
            ->and($o->inside($button, ['div', 'x-forms.field'])['tag'])->toBe('div')
            ->and($o->inside($button, 'x-forms.other'))->toBeNull()
            ->and($o->inside($button, 'html:td')['tag'])->toBe('td');
    });

    it('takes a bare atom name with a dot, and refuses a bare name that is also an atom component', function () {
        $o = outline('<atom:page><atom:page.header><x-slot:actions><atom:button/></x-slot:actions></atom:page.header><atom:card><button>x</button></atom:card></atom:page>');

        expect($o->inside(first($o, 'atom:button'), 'page.header')['tag'])->toBe('atom:page.header')
            ->and($o->inside(first($o, 'atom:button'), ['Page.Header'])['tag'])->toBe('atom:page.header')
            ->and($o->inside(first($o, 'atom:button'), 'x-atom::page.header')['tag'])->toBe('atom:page.header')
            ->and($o->inside(first($o, 'atom:button'), 'atom:page.header')['tag'])->toBe('atom:page.header')
            ->and($o->inside(first($o, 'button'), 'atom:card')['tag'])->toBe('atom:card')
            ->and($o->inside(first($o, 'button'), 'html:button'))->toBeNull()
            ->and($o->inside(first($o, 'button'), 'page.header'))->toBeNull()
            ->and($o->inside(first($o, 'button'), 'div'))->toBeNull();

        foreach (['button', 'table', 'form', ['div', 'input']] as $ambiguous) {
            expect(fn () => $o->inside(first($o, 'button'), $ambiguous))->toThrow(InvalidArgumentException::class, 'is ambiguous');
        }

        expect($o->inside(first($o, 'button'), 'card')['tag'])->toBe('atom:card')
            ->and($o->inside(first($o, 'button'), 'page')['tag'])->toBe('atom:page');

        $o = outline('<form><button>x</button></form>');

        expect($o->inside(first($o, 'button'), 'html:form')['tag'])->toBe('form');
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
        $o = outline(str_repeat('<div>', 200).'<atom:card/>'.str_repeat('</div>', 200));

        expect($o->nodes())->toHaveCount(201)
            ->and(first($o, 'atom:card')['depth'])->toBe(200)
            ->and($o->ancestors(first($o, 'atom:card')))->toHaveCount(200)
            ->and($o->balanced())->toBeTrue();
    });

    it('declines a file nested deeper than it reads, and one with more tags than it reads', function () {
        $deep = outline(str_repeat('<div>', 5000).'<atom:card/>'.str_repeat('</div>', 5000));

        expect($deep->balanced())->toBeFalse()
            ->and(max(array_column($deep->nodes(), 'depth')))->toBeLessThanOrEqual(256)
            ->and(count($deep->ancestors(first($deep, 'atom:card'))))->toBeLessThanOrEqual(256);

        $wide = outline(str_repeat('<i></i>', 60000));

        expect($wide->nodes())->toHaveCount(50000)
            ->and($wide->balanced())->toBeFalse();

        $exact = outline(str_repeat('<i></i>', 50000));

        expect($exact->nodes())->toHaveCount(50000)
            ->and($exact->balanced())->toBeTrue();
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

    it('reads @hasSection and @sectionMissing as @if blocks, closed by @endif', function (string $open) {
        $o = outline('@if($edit)'.$open.'("x")<atom:button id="a"/>@endif<atom:button id="b"/>@else<atom:button id="c"/>@endif<atom:button id="d"/>');

        [$a, $b, $c, $d] = $o->atom('button');

        expect($o->branch($a))->toBe([[1, 0], [2, 0]])
            ->and($o->branch($b))->toBe([[1, 0]])
            ->and($o->branch($c))->toBe([[1, 1]])
            ->and($o->branch($d))->toBe([])
            ->and($o->together($b, $c))->toBeFalse()
            ->and($o->together($a, $c))->toBeFalse()
            ->and($o->together($a, $b))->toBeTrue();
    })->with(['@hasSection', '@sectionMissing', '@hassection']);

    it('drops what is left open inside a block that moves to its next branch', function () {
        $o = outline('@if($a) @foreach($x as $y) <atom:button id="in"/> @else <atom:button id="b"/> @endif <atom:button id="out"/>');

        [$in, $b, $out] = $o->atom('button');

        expect($o->inLoop($in))->toBeTrue()
            ->and($o->inLoop($b))->toBeFalse()
            ->and($o->branch($b))->toBe([[1, 1]])
            ->and($o->branch($out))->toBe([]);
    });

    it('takes a node for a branch and an offset for branchAt, never the one for the other', function () {
        $o = outline('@if($a)<atom:button/>@endif');
        $button = first($o, 'atom:button');

        expect($o->branch($button))->toBe([[1, 0]])
            ->and($o->branchAt($button['offset']))->toBe([[1, 0]])
            ->and(fn () => $o->branch(7))->toThrow(TypeError::class)
            ->and($o->inLoop(0))->toBeFalse()
            ->and($o->together(0, 0))->toBeTrue();
    });

    it('stops reading branches at the 257th open block, and freezes the path there', function () {
        $o = outline(str_repeat('@if($a)', 257).'<atom:button id="a"/>@else<atom:button id="b"/>@endif<atom:button id="c"/>');

        [$a, $b, $c] = $o->atom('button');

        expect($o->branchesDeclined())->toBeTrue()
            ->and($o->branch($b))->toBe($o->branch($a))
            ->and($o->branch($c))->toBe($o->branch($a))
            ->and($o->branch($a))->toHaveCount(256);
    });

    it('says a node is in no loop once the branches are declined, even one opened before', function () {
        $o = outline('@foreach($a as $b)<atom:button id="in"/>'.str_repeat('@if($c)', 260).'<atom:button id="deep"/>');

        [$in, $deep] = $o->atom('button');

        expect($o->branchesDeclined())->toBeTrue()
            ->and($o->inLoop($in))->toBeFalse()
            ->and($o->inLoop($deep))->toBeFalse();

        $fine = outline('@foreach($a as $b)<atom:button/>');

        expect($fine->inLoop(first($fine, 'atom:button')))->toBeTrue();
    });

    it('stops reading branches past MAX_BLOCKS open blocks, and says so', function () {
        $fine = outline(str_repeat('@if($a)', 256).'<atom:button/>');

        expect($fine->branchesDeclined())->toBeFalse()
            ->and($fine->branch(first($fine, 'atom:button')))->toHaveCount(256);

        $deep = outline('@if($a)<atom:button id="a"/>@else<atom:button id="b"/>'.str_repeat('@if($a)', 300).'<atom:button id="c"/>@endif');

        [$a, $b, $c] = $deep->atom('button');

        expect($deep->branchesDeclined())->toBeTrue()
            ->and($deep->together($a, $b))->toBeFalse()
            ->and($deep->together($b, $c))->toBeFalse()
            ->and($deep->inLoop($c))->toBeFalse();
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

    it('lets a label, heading or title attribute win over the text on a component whose text is not its label', function () {
        $o = outline('<atom:card heading="{{ t(\'Customer\') }}">x</atom:card><atom:page.header title="Invoices"><x-slot:primary><atom:button>Save</atom:button></x-slot:primary></atom:page.header><atom:modal heading="H" title="T">x</atom:modal><atom:empty title="Only a title"/>');

        expect($o->label(first($o, 'atom:card')))->toBe('Customer')
            ->and($o->label(first($o, 'atom:page.header')))->toBe('Invoices')
            ->and($o->label(first($o, 'atom:modal')))->toBe('H')
            ->and($o->label(first($o, 'atom:empty')))->toBe('Only a title')
            ->and($o->label(first($o, 'x-slot')))->toBe('Save');
    });

    it('reads the text of a button, link or item, never its title or a label attribute over it', function () {
        $o = outline(implode('', [
            '<atom:button title="Archive">Delete</atom:button>',
            '<atom:button label="Other" title="Tip">Delete</atom:button>',
            '<atom:link title="Tip" href="/x">Open</atom:link>',
            '<atom:menu.item title="Tip">Remove</atom:menu.item>',
            '<atom:navlist.item heading="H">Home</atom:navlist.item>',
            '<atom:tabs.item label="L">Overview</atom:tabs.item>',
            '<atom:button title="Tip">{{ $dynamic }}</atom:button>',
            '<atom:button title="Tip"><atom:icon.add/></atom:button>',
            '<atom:button title="Tip" label="Fallback"><atom:icon.add/></atom:button>',
            '<atom:button heading="Head"><atom:icon.add/></atom:button>',
            '<atom:button :label="$x"><atom:icon.add/></atom:button>',
            '<atom:button label="Self" />',
            '<atom:button title="Tip" />',
            '<atom:button.group title="G">x</atom:button.group>',
        ]));

        expect(array_map(fn ($node) => $o->label($node), array_values(array_filter($o->nodes(), fn ($node) => $node['kind'] === 'atom' && $node['component'] !== 'icon.add'))))->toBe([
            'Delete', 'Delete', 'Open', 'Remove', 'Home', 'Overview',
            null, '', 'Fallback', 'Head', null, 'Self', '', 'G',
        ]);
    });

    it('takes a non-breaking space for whitespace', function () {
        $o = outline("<atom:button>Save&nbsp;</atom:button><atom:button>&nbsp;Save&nbsp;&nbsp;now\u{00A0}</atom:button><atom:card heading=\"Pay&nbsp;now\"/>");

        expect($o->label(first($o, 'atom:button')))->toBe('Save')
            ->and($o->label(first($o, 'atom:button', 1)))->toBe('Save now')
            ->and($o->label(first($o, 'atom:card')))->toBe('Pay now');
    });

    it('reads an element whose closer was left out up to where it ended', function () {
        $o = outline('<ul><li>One<li><b>Two</b> and<li>Three</ul><p>Para one<p>Para two');

        expect(array_map(fn ($node) => $o->label($node), array_values(array_filter($o->nodes(), fn ($node) => in_array($node['tag'], ['li', 'p'], true)))))
            ->toBe(['One', 'Two and', 'Three', 'Para one', 'Para two']);
    });

    it('is null for a bound label that is not a literal, and reads one that is', function () {
        $o = outline('<atom:card :label="$x">A</atom:card><atom:card :label="\'Save\'">A</atom:card><atom:card :label="t(\'Pay now\')">A</atom:card><atom:card x-bind:label="$y">A</atom:card><atom:card label>A</atom:card>');

        expect(array_map(fn ($b) => $o->label($b), $o->atom('card')))->toBe([null, 'Save', 'Pay now', null, null]);
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

    it('drops a literal token that holds a variable or an echo', function () {
        $o = outline('<div :class="$on ? \'p-$n q\' : \'r\'" @class([\'s-{{ $x }}\', \'t\', "u-$v" => $w])></div>');

        expect($o->classes(first($o, 'div')))->toBe(['q', 'r', 't']);
    });

    it('leaves out an echo with quotes inside it in a bound class', function () {
        $o = outline('<div x-bind:class="{ \'a b\': x, \'{{ $d ? \'border-x\' : \'border-y\' }}\': !z, \'{{ $k }}\': q, \'c\': r }" :class="\'d \' . ($e ? \'{{ $f ? \'g\' : \'h\' }}\' : \'i\')"></div>');

        expect($o->classes(first($o, 'div')))->toBe(['d', 'i', 'a', 'b', 'c']);
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
