<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes and messages of the action checks, and the corners a fixture line cannot show. What each finds is held by its
 * fixture host (DesignCheckFixturesTest); these hold what it says.
 */

describe('extra-primary', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'extra-primary');

    it('says which surface the primary does not belong in', function () use ($notes) {
        $button = '<atom:button variant="primary">a</atom:button>';

        expect($notes("<atom:empty><x-slot:action>{$button}</x-slot:action></atom:empty>"))->toBe([1 => 'an empty state\'s next step is a plain button, not the primary'])
            ->and($notes("<atom:table.toolbar><x-slot:actions>{$button}</x-slot:actions></atom:table.toolbar>"))->toBe([1 => 'no primary in a toolbar: the header\'s primary is the screen\'s one'])
            ->and($notes("<atom:table><x-slot:checked>{$button}</x-slot:checked></atom:table>"))->toBe([1 => 'no primary in a bulk bar: the header\'s primary is the screen\'s one'])
            ->and($notes("<atom:table><x-slot:checked-menu>{$button}</x-slot:checked-menu></atom:table>"))->toBe([1 => 'no primary in a bulk bar: the header\'s primary is the screen\'s one'])
            ->and($notes("<atom:table.row><atom:table.actions>{$button}</atom:table.actions></atom:table.row>"))->toBe([1 => 'no primary in a table row: a row opens the record or has `<atom:table.actions>`']);
    });

    it('does not take a slot of another component called checked for the bulk bar', function () use ($notes) {
        expect($notes('<atom:card><x-slot:checked><atom:button variant="primary">a</atom:button></x-slot:checked></atom:card>'))->toBe([]);
    });

    it('lets a callout have the primary only while the header has none', function () use ($notes) {
        $callout = '<atom:callout><atom:button variant="primary">a</atom:button></atom:callout>';

        expect($notes("<atom:page.header title=\"a\"/>{$callout}"))->toBe([])
            ->and($notes("<atom:page.header title=\"a\"><x-slot:primary><atom:button variant=\"primary\">b</atom:button></x-slot:primary></atom:page.header>{$callout}"))
            ->toBe([1 => 'the header already has the primary: a callout\'s button is a plain one']);
    });

    it('reads a header in a modal as that modal\'s own', function () use ($notes) {
        $header = '<atom:page.header title="a"><x-slot:primary><atom:button variant="primary">b</atom:button></x-slot:primary></atom:page.header>';

        expect($notes("<atom:modal name=\"m\">{$header}</atom:modal><atom:callout><atom:button variant=\"primary\">c</atom:button></atom:callout>"))->toBe([]);
    });

    it('counts a bare submit and the old name of the primary, and not a variant it cannot read', function () use ($notes) {
        $header = '<atom:page.header title="a"><x-slot:primary><atom:button variant="primary">h</atom:button></x-slot:primary></atom:page.header>';
        $note = 'a second primary on one screen: the next step for the status, or the form\'s submit';

        expect($notes("{$header}<atom:button type=\"submit\">a</atom:button>"))->toBe([1 => $note])
            ->and($notes("{$header}<atom:button variant=\"accent\">a</atom:button>"))->toBe([1 => $note])
            ->and($notes("{$header}<atom:button type=\"submit\" variant=\"default\">a</atom:button>"))->toBe([])
            ->and($notes("{$header}<atom:button type=\"submit\" :variant=\"\$v\">a</atom:button>"))->toBe([])
            ->and($notes("{$header}<atom:button :type=\"\$t\">a</atom:button>"))->toBe([])
            ->and($notes("{$header}<atom:button variant=\"{{ \$v }}\">a</atom:button>"))->toBe([]);
    });

    it('says what is wrong with a second primary in a form and with a modal form\'s', function () use ($notes) {
        expect($notes('<atom:form><atom:button type="submit">a</atom:button><atom:button variant="primary">b</atom:button></atom:form>'))
            ->toBe([1 => 'one primary in a form, its submit: a second submit takes `variant="default"`'])
            ->and($notes('<atom:form.modal name="m"><x-slot:actions><atom:button variant="primary">a</atom:button></x-slot:actions></atom:form.modal>'))
            ->toBe([1 => 'a modal form draws its own Save as the primary: this one is a plain button']);
    });

    it('does not pair the two primaries of the branches of one `@if` or a `@switch`, and pairs one that is always there', function () use ($notes) {
        $one = '<atom:button variant="primary">a</atom:button>';

        expect($notes("@if (\$a) {$one} @elseif (\$b) {$one} @else {$one} @endif"))->toBe([])
            ->and($notes("@switch(\$a) @case(1) {$one} @break @case(2) {$one} @break @endswitch"))->toBe([])
            ->and($notes("@if (\$a) {$one} @endif\n@if (\$b) {$one} @endif"))->toBe([])
            ->and($notes("@if (\$a) {$one} @endif\n{$one}"))->toHaveCount(1)
            ->and($notes("@if (\$a) @if (\$b) {$one} @endif @endif\n@if (\$a) {$one} @endif"))->toBe([]);
    });

    it('says a primary in a loop is one per item, unless the form or the modal around it is the item', function () use ($notes) {
        $one = '<atom:button variant="primary">a</atom:button>';

        expect($notes("@foreach (\$x as \$y) {$one} @endforeach"))->toBe([1 => 'a primary in a loop is one per item: the screen has one'])
            ->and($notes("@foreach (\$x as \$y) <atom:form>{$one}</atom:form> @endforeach"))->toBe([])
            ->and($notes("@foreach (\$x as \$y) <atom:modal name=\"m\">{$one}</atom:modal> @endforeach"))->toBe([])
            ->and($notes("<atom:form>@foreach (\$x as \$y) {$one} @endforeach</atom:form>"))->toHaveCount(1);
    });

    it('counts a primary in a table row that edits nothing, and not one in a row that does', function () use ($notes) {
        $one = '<atom:button variant="primary">a</atom:button>';

        expect($notes("<atom:table.row><atom:table.cell>{$one}</atom:table.cell></atom:table.row>"))->toHaveCount(1)
            ->and($notes("<atom:table.row><atom:table.cell><atom:input/></atom:table.cell><atom:table.cell>{$one}</atom:table.cell></atom:table.row>"))->toBe([])
            ->and($notes("<atom:table.row><atom:table.cell><span wire:model=\"a\"></span></atom:table.cell><atom:table.cell>{$one}</atom:table.cell></atom:table.row>"))->toBe([]);
    });

    it('is a review, and reads no view whose tags do not balance', function () use ($notes) {
        $rule = (new DesignCheck)->rules()['extra-primary'];

        expect($rule->structural())->toBeTrue()->and($rule::SEVERITY)->toBe(DesignCheck::REVIEW)
            ->and(DesignHost::scanSource('<atom:card><atom:button variant="primary">a</atom:button><atom:button variant="primary">b</atom:button>', 'extra-primary'))->toBe([]);
    });
});

describe('action-cap', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'action-cap');
    $buttons = fn (int $count, string $attributes = '') => str_repeat("<atom:button{$attributes}>a</atom:button>", $count);

    it('names the surface and its count, at the first button over the cap', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header title=\"a\"><x-slot:actions>{$buttons(3)}</x-slot:actions></atom:page.header>"))->toBe([1 => 'page.header actions: 3 (cap 2)'])
            ->and($notes("<atom:table.toolbar><x-slot:actions>{$buttons(2)}</x-slot:actions><x-slot:end>{$buttons(1)}</x-slot:end></atom:table.toolbar>"))->toBe([1 => 'table.toolbar actions: 3 (cap 2)'])
            ->and($notes("<atom:table><x-slot:checked>{$buttons(4)}</x-slot:checked></atom:table>"))->toBe([1 => 'bulk bar (checked slot): 4 (cap 3)'])
            ->and($notes("<atom:card><x-slot:actions>{$buttons(2)}</x-slot:actions></atom:card>"))->toBe([1 => 'card actions: 2 (cap 1)'])
            ->and($notes("<atom:panel><x-slot:actions>{$buttons(2)}</x-slot:actions></atom:panel>"))->toBe([1 => 'panel actions: 2 (cap 1)'])
            ->and($notes("<atom:empty><x-slot:action>{$buttons(2)}</x-slot:action></atom:empty>"))->toBe([1 => 'empty state action: 2 (cap 1): keep one plain button, an empty state has no menu']);
    });

    it('puts the finding on the button that passes the cap', function () use ($buttons) {
        $source = "<atom:page.header title=\"a\"><x-slot:actions>\n<atom:button>1</atom:button>\n<atom:button>2</atom:button>\n<atom:button>3</atom:button>\n<atom:button>4</atom:button>\n</x-slot:actions></atom:page.header>";

        expect(array_keys(DesignHost::notes($source, 'action-cap')))->toBe([4]);
    });

    it('lowers a header\'s cap with size="sm", and does not read a size it cannot', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header size=\"sm\"><x-slot:actions>{$buttons(2)}</x-slot:actions></atom:page.header>"))->toBe([1 => 'page.header actions: 2 (cap 1)'])
            ->and($notes("<atom:page.header size=\"md\"><x-slot:actions>{$buttons(2)}</x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header :size=\"\$s\"><x-slot:actions>{$buttons(3)}</x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:card size=\"sm\"><x-slot:actions>{$buttons(2)}</x-slot:actions></atom:card>"))->toHaveCount(1);
    });

    it('counts a group or a dropdown as one, and not what is inside a component', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header><x-slot:actions>{$buttons(1)}<atom:button.group>{$buttons(4)}</atom:button.group></x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(1)}<atom:dropdown>{$buttons(1)}<atom:menu>{$buttons(3)}</atom:menu></atom:dropdown></x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(1)}<atom:dropdown>{$buttons(1)}</atom:dropdown><atom:dropdown>{$buttons(1)}</atom:dropdown></x-slot:actions></atom:page.header>"))->toHaveCount(1)
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(2)}<atom:modal name=\"m\">{$buttons(3)}</atom:modal></x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(2)}<atom:confirm.trigger>{$buttons(1)}</atom:confirm.trigger></x-slot:actions></atom:page.header>"))->toHaveCount(1);
    });

    it('does not count the branches of one `@if` together, and counts what is always there', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header><x-slot:actions>{$buttons(2)} @if (\$a) {$buttons(1)} @else {$buttons(1)} @endif</x-slot:actions></atom:page.header>"))->toHaveCount(1)
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(1)} @if (\$a) {$buttons(1)} @else {$buttons(1)} @endif</x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(1)} @if (\$a) {$buttons(1)} @endif @if (\$b) {$buttons(1)} @endif</x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>@if (\$a) {$buttons(1)} @if (\$b) {$buttons(2)} @endif @endif</x-slot:actions></atom:page.header>"))->toHaveCount(1);
    });

    it('does not count a delete in the last place of a bulk bar, and counts any other (one that is not last included)', function () use ($notes, $buttons) {
        $delete = '<atom:button type="delete">Delete</atom:button>';

        expect($notes("<atom:table><x-slot:checked>{$buttons(3)}{$delete}</x-slot:checked></atom:table>"))->toBe([])
            ->and($notes("<atom:table><x-slot:checked>{$buttons(2)}{$delete}{$buttons(1)}</x-slot:checked></atom:table>"))->toHaveCount(1)
            ->and($notes("<atom:table><x-slot:checked>{$buttons(4)}{$delete}</x-slot:checked></atom:table>"))->toHaveCount(1)
            ->and($notes("<atom:table><x-slot:checked>{$buttons(3)}<atom:button variant=\"danger\">a</atom:button></x-slot:checked></atom:table>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(2)}{$delete}</x-slot:actions></atom:page.header>"))->toHaveCount(1);
    });

    it('does not read a slot another component owns, the shell\'s included', function () use ($notes, $buttons) {
        expect($notes("<x-dynamic-component component=\"atom::layouts.sidebar\"><x-slot:actions>{$buttons(4)}</x-slot:actions></x-dynamic-component>"))->toBe([])
            ->and($notes("<atom:layouts.sidebar><x-slot:actions>{$buttons(4)}</x-slot:actions></atom:layouts.sidebar>"))->toBe([])
            ->and($notes("<atom:tabs><x-slot:actions>{$buttons(4)}</x-slot:actions></atom:tabs>"))->toBe([])
            ->and($notes("<atom:page.header>{$buttons(4)}</atom:page.header>"))->toBe([]);
    });

    it('reads the slot written with a name attribute', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header><x-slot name=\"actions\">{$buttons(3)}</x-slot></atom:page.header>"))->toHaveCount(1);
    });

    it('is one finding for a surface, however many buttons are over', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header><x-slot:actions>{$buttons(9)}</x-slot:actions></atom:page.header>"))->toBe([1 => 'page.header actions: 9 (cap 2)']);
    });
});

describe('row-buttons', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'row-buttons');
    $row = fn (string $cell) => "<atom:table.row><atom:table.cell>{$cell}</atom:table.cell></atom:table.row>";

    it('names the row\'s menu and its href', function () use ($notes, $row) {
        expect($notes($row('<atom:button>a</atom:button>')))->toBe([1 => 'a row button: use `table.actions`, and the row\'s `href` to open the record']);
    });

    it('reads a plain button only when it has text', function () use ($notes, $row) {
        expect($notes($row('<button>Open</button>')))->toHaveCount(1)
            ->and($notes($row('<button><svg></svg></button>')))->toBe([])
            ->and($notes($row('<button title="Expand"></button>')))->toBe([])
            ->and($notes($row('<button>{{ $x }}</button>')))->toBe([])
            ->and($notes($row('<atom:button icon="eye" />')))->toHaveCount(1);
    });

    it('does not read what a component draws around the button', function () use ($notes, $row) {
        expect($notes($row('<atom:dropdown><atom:button>a</atom:button></atom:dropdown>')))->toBe([])
            ->and($notes($row('<atom:modal name="m"><atom:button>a</atom:button></atom:modal>')))->toBe([])
            ->and($notes($row('<atom:confirm.trigger><atom:button>a</atom:button></atom:confirm.trigger>')))->toHaveCount(1)
            ->and($notes($row('<div><span><atom:button>a</atom:button></span></div>')))->toHaveCount(1)
            ->and($notes('<atom:table.row><atom:table.actions><atom:button>a</atom:button></atom:table.actions></atom:table.row>'))->toBe([]);
    });

    it('does not flag a row that edits something, however the control is written', function () use ($notes) {
        $button = '<atom:button>a</atom:button>';

        foreach (['<atom:input/>', '<atom:select/>', '<atom:textarea/>', '<atom:date-picker/>', '<atom:checkbox/>', '<input>', '<select></select>', '<textarea></textarea>', '<div wire:model.live="a"></div>', '<div x-model="a"></div>'] as $control) {
            expect($notes("<atom:table.row><atom:table.cell>{$control}</atom:table.cell><atom:table.cell>{$button}</atom:table.cell></atom:table.row>"))->toBe([], $control);
        }

        expect($notes("<atom:table.row><atom:table.cell><atom:table.checkbox/></atom:table.cell><atom:table.cell>{$button}</atom:table.cell></atom:table.row>"))->toHaveCount(1);
    });

    it('is a review', function () {
        expect((new DesignCheck)->rules()['row-buttons']::SEVERITY)->toBe(DesignCheck::REVIEW);
    });
});

describe('menu-order', function () {
    $notes = fn (string $items, string $wrap = 'atom:menu') => DesignHost::notes("<{$wrap}>{$items}</{$wrap}>", 'menu-order');
    $default = '<atom:menu.item>a</atom:menu.item>';
    $warning = '<atom:menu.item variant="warning">b</atom:menu.item>';
    $danger = '<atom:menu.item variant="delete">c</atom:menu.item>';
    $separator = '<atom:menu.separator/>';

    it('says what order a menu takes', function () use ($notes, $default, $warning, $danger, $separator) {
        expect($notes($danger.$default))->toBe([1 => 'a default item after a warning or danger one: utilities come first'])
            ->and($notes($danger.$warning))->toBe([1 => 'a warning item after a danger one: danger goes last'])
            ->and($notes($default.$warning))->toBe([1 => 'the first warning or danger item follows a default one: put `<atom:menu.separator>` before it'])
            ->and($notes($default.$separator.$warning.$danger))->toBe([])
            ->and($notes($default.$default.$separator.$danger))->toBe([])
            ->and($notes($warning.$danger))->toBe([]);
    });

    it('asks for one separator, before the first toned item only', function () use ($notes, $default, $warning, $danger, $separator) {
        expect($notes($default.$separator.$warning.$danger.$danger))->toBe([])
            ->and($notes($default.$warning.$danger))->toHaveCount(1);
    });

    it('reads danger from delete, remove and danger, and warning from warning', function () use ($notes, $warning) {
        foreach (['danger', 'delete', 'remove'] as $variant) {
            expect($notes("<atom:menu.item variant=\"{$variant}\">a</atom:menu.item><atom:menu.item>b</atom:menu.item>"))->toHaveCount(1, $variant);
            expect($notes("<atom:menu.item variant=\"{$variant}\">a</atom:menu.item>{$warning}"))->toHaveCount(1, $variant);
        }
    });

    it('says a hand separator is not the menu\'s', function () use ($notes, $default) {
        expect($notes($default.'<atom:separator/>'.$default))->toBe([1 => 'a hand-built separator in a menu: use `<atom:menu.separator>`'])
            ->and($notes($default.'<hr>'.$default))->toHaveCount(1)
            ->and($notes($default.'<atom:separator/>'.$default, 'div'))->toBe([]);
    });

    it('counts a hand separator as the break before the first toned item, so only the separator is flagged', function () use ($notes, $default, $warning) {
        expect($notes($default.'<atom:separator/>'.$warning))->toHaveCount(1);
    });

    it('reads every menu of a surface, and not a slot called menu of another component', function () use ($default, $danger) {
        $items = $danger.$default;
        $one = fn (string $source) => count(DesignHost::notes($source, 'menu-order'));

        expect($one("<atom:page.header><x-slot:menu>{$items}</x-slot:menu></atom:page.header>"))->toBe(1)
            ->and($one("<atom:table.toolbar><x-slot:menu>{$items}</x-slot:menu></atom:table.toolbar>"))->toBe(1)
            ->and($one("<atom:card><x-slot:menu>{$items}</x-slot:menu></atom:card>"))->toBe(1)
            ->and($one("<atom:panel><x-slot:menu>{$items}</x-slot:menu></atom:panel>"))->toBe(1)
            ->and($one("<atom:table><x-slot:checked-menu>{$items}</x-slot:checked-menu></atom:table>"))->toBe(1)
            ->and($one("<atom:table.row><atom:table.actions>{$items}</atom:table.actions></atom:table.row>"))->toBe(1)
            ->and($one("<atom:tabs><x-slot:menu>{$items}</x-slot:menu></atom:tabs>"))->toBe(0)
            ->and($one("<atom:card><x-slot:checked-menu>{$items}</x-slot:checked-menu></atom:card>"))->toBe(0);
    });

    it('does not compare the branches of one `@if`, or an item whose variant it cannot read', function () use ($notes, $default, $warning, $danger) {
        expect($notes("@if (\$a) {$warning} @else {$default} @endif"))->toBe([])
            ->and($notes("@if (\$a) {$danger} @elseif (\$b) {$warning} @endif"))->toBe([])
            ->and($notes("{$danger}<atom:menu.item :variant=\"\$v\">a</atom:menu.item>"))->toBe([])
            ->and($notes("{$default}<atom:menu.item :variant=\"\$v\">a</atom:menu.item>{$warning}"))->toBe([])
            ->and($notes("{$danger}@if (\$a) {$default} @endif"))->toHaveCount(1);
    });

    it('keeps the items of a menu inside another one apart', function () use ($default, $danger) {
        $source = "<atom:page.header><x-slot:menu>{$default}<atom:dropdown><atom:menu>{$danger}{$default}</atom:menu></atom:dropdown></x-slot:menu></atom:page.header>";

        expect(DesignHost::notes($source, 'menu-order'))->toHaveCount(1);
    });

    it('is info', function () {
        expect((new DesignCheck)->rules()['menu-order']::SEVERITY)->toBe(DesignCheck::INFO);
    });
});

describe('bar-button-size', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'bar-button-size');

    it('says which bar and how the size is written', function () use ($notes) {
        expect($notes('<atom:table.toolbar><x-slot:actions><atom:button size="sm">a</atom:button></x-slot:actions></atom:table.toolbar>'))->toBe([1 => '`size="sm"` in a toolbar: its buttons are the control height'])
            ->and($notes('<atom:table><x-slot:checked><atom:button xs>a</atom:button></x-slot:checked></atom:table>'))->toBe([1 => '`xs` in a bulk bar: its buttons are the control height'])
            ->and($notes('<atom:table.toolbar><atom:button size=" XS ">a</atom:button></atom:table.toolbar>'))->toBe([1 => '`size="xs"` in a toolbar: its buttons are the control height']);
    });

    it('does not read a size it cannot, or one that is not small', function () use ($notes) {
        foreach (['size="md"', 'size="lg"', ':size="$s"', 'size="{{ $s }}"', ''] as $size) {
            expect($notes("<atom:table.toolbar><x-slot:actions><atom:button {$size}>a</atom:button></x-slot:actions></atom:table.toolbar>"))->toBe([], $size);
        }
    });

    it('reads only the bar\'s own slots', function () use ($notes) {
        $small = '<atom:button size="sm">a</atom:button>';

        expect($notes("<atom:card><x-slot:actions>{$small}</x-slot:actions></atom:card>"))->toBe([])
            ->and($notes("<atom:table><x-slot:rows>{$small}</x-slot:rows></atom:table>"))->toBe([])
            ->and($notes("<atom:table.toolbar><x-slot:menu>{$small}</x-slot:menu></atom:table.toolbar>"))->toBe([])
            ->and($notes("<atom:card><x-slot:checked>{$small}</x-slot:checked></atom:card>"))->toBe([])
            ->and($notes("<atom:table.toolbar><atom:dropdown><atom:menu>{$small}</atom:menu></atom:dropdown></atom:table.toolbar>"))->toBe([])
            ->and($notes("<atom:table.toolbar><atom:dropdown>{$small}</atom:dropdown></atom:table.toolbar>"))->toHaveCount(1)
            ->and($notes("<atom:table.toolbar><atom:button.group>{$small}</atom:button.group></atom:table.toolbar>"))->toHaveCount(1);
    });

    it('is info', function () {
        expect((new DesignCheck)->rules()['bar-button-size']::SEVERITY)->toBe(DesignCheck::INFO);
    });
});

describe('dup-export', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'dup-export');
    $toolbar = fn (string $buttons, string $slot = 'actions') => "<atom:table.toolbar><x-slot:{$slot}>{$buttons}</x-slot:{$slot}></atom:table.toolbar>";
    $table = fn (string $buttons, string $slot = 'checked') => "<atom:table><x-slot:{$slot}>{$buttons}</x-slot:{$slot}></atom:table>";

    it('names the label and says it is in the toolbar already', function () use ($notes, $toolbar, $table) {
        expect($notes($toolbar('<atom:button>Export</atom:button>').$table('<atom:button>Export</atom:button>')))->toBe([1 => '"Export" is in the toolbar\'s actions too']);
    });

    it('folds case and spaces, and reads the end slot too', function () use ($notes, $toolbar, $table) {
        expect($notes($toolbar("<atom:button>Send  \n list</atom:button>", 'end').$table('<atom:button>send list</atom:button>')))->toHaveCount(1);
    });

    it('does not read a label it cannot know, or an empty one', function () use ($notes, $toolbar, $table) {
        expect($notes($toolbar('<atom:button>{{ $a }}</atom:button><atom:button icon="x"/>').$table('<atom:button>{{ $a }}</atom:button><atom:button icon="x"/>')))->toBe([]);
    });

    it('takes the toolbar in the table\'s header slot, or the one above it in the same parent, and no other', function () use ($notes, $toolbar) {
        $export = '<atom:button>Export</atom:button>';
        $inside = "<atom:table><x-slot:header>{$toolbar($export)}</x-slot:header><x-slot:checked>{$export}</x-slot:checked></atom:table>";
        $above = "<atom:panel>{$toolbar($export)}<atom:table><x-slot:checked>{$export}</x-slot:checked></atom:table></atom:panel>";
        $apart = "<atom:panel>{$toolbar($export)}</atom:panel><atom:panel><atom:table><x-slot:checked>{$export}</x-slot:checked></atom:table></atom:panel>";
        $below = "<atom:panel><atom:table><x-slot:checked>{$export}</x-slot:checked></atom:table>{$toolbar($export)}</atom:panel>";

        expect($notes($inside))->toHaveCount(1)
            ->and($notes($above))->toHaveCount(1)
            ->and($notes($apart))->toBe([])
            ->and($notes($below))->toBe([]);
    });

    it('does not read the toolbar\'s other slots, or a button that is not its own', function () use ($notes, $toolbar, $table) {
        $export = '<atom:button>Export</atom:button>';

        expect($notes($toolbar($export, 'menu').$table($export)))->toBe([])
            ->and($notes($toolbar("<atom:dropdown>{$export}</atom:dropdown>").$table($export)))->toBe([])
            ->and($notes($toolbar($export).$table($export, 'rows')))->toBe([]);
    });

    it('is info', function () {
        expect((new DesignCheck)->rules()['dup-export']::SEVERITY)->toBe(DesignCheck::INFO);
    });
});

describe('extra-primary: forms, the browser and host components', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'extra-primary');
    $header = '<atom:page.header title="a"><x-slot:primary><atom:button variant="primary">h</atom:button></x-slot:primary></atom:page.header>';
    $screen = 'a second primary on one screen: the next step for the status, or the form\'s submit';

    it('leaves a primary in a form to its form, in either direction', function () use ($notes, $header, $screen) {
        $submit = '<atom:button type="submit">a</atom:button>';

        expect($notes("{$header}<atom:form>{$submit}</atom:form><atom:form>{$submit}</atom:form>"))->toBe([])
            ->and($notes("{$header}<form>{$submit}</form><form>{$submit}</form>"))->toBe([])
            ->and($notes("{$header}<atom:form.section>{$submit}</atom:form.section><atom:form.section>{$submit}</atom:form.section>"))->toBe([])
            ->and($notes("<atom:form>{$submit}</atom:form><atom:button variant=\"primary\">b</atom:button>"))->toBe([])
            ->and($notes("{$header}<atom:button type=\"submit\" form=\"f\">a</atom:button>"))->toBe([1 => $screen]);
    });

    it('says a second primary in one form is a second, in a plain form and a section too', function () use ($notes) {
        $two = '<atom:button type="submit">a</atom:button><atom:button variant="primary">b</atom:button>';
        $note = 'one primary in a form, its submit: a second submit takes `variant="default"`';

        expect($notes("<form>{$two}</form>"))->toBe([1 => $note])
            ->and($notes("<atom:form.section>{$two}</atom:form.section>"))->toBe([1 => $note])
            ->and($notes("<atom:form><atom:form.section>{$two}</atom:form.section></atom:form>"))->toBe([1 => $note])
            ->and($notes("<atom:form><atom:form.section><atom:button type=\"submit\">a</atom:button></atom:form.section><atom:form.section><atom:button type=\"submit\">b</atom:button></atom:form.section></atom:form>"))->toHaveCount(1);
    });

    it('does not take a primary flagged for where it sits as the form\'s one', function () use ($notes) {
        expect($notes('<atom:form><atom:table.toolbar><x-slot:actions><atom:button variant="primary">a</atom:button></x-slot:actions></atom:table.toolbar><atom:button type="submit">b</atom:button></atom:form>'))
            ->toHaveCount(1);
    });

    it('does not pair what the browser shows in turn, on the node or a tag around it up to its slot', function () use ($notes, $header) {
        $one = '<atom:button variant="primary">a</atom:button>';

        foreach (['x-show="a"', 'x-show.transition="a"', 'wire:show="a"', ':hidden="$a"', 'x-bind:hidden="a"'] as $attribute) {
            expect($notes("{$header}<div {$attribute}>{$one}</div>"))->toBe([], $attribute);
        }

        expect($notes("{$header}<template x-if=\"a\">{$one}</template>"))->toBe([])
            ->and($notes("{$header}<atom:button variant=\"primary\" x-show=\"a\">a</atom:button>"))->toBe([])
            ->and($notes("<div x-show=\"a\">{$header}</div>{$one}"))->toHaveCount(1)
            ->and($notes("{$header}<div x-show=\"a\">{$one}</div>{$one}"))->toHaveCount(1)
            ->and($notes("<atom:empty x-show=\"a\"><x-slot:action>{$one}</x-slot:action></atom:empty>"))->toHaveCount(1)
            ->and($notes("<atom:form><atom:button type=\"submit\">a</atom:button><atom:button type=\"submit\" x-show=\"a\">b</atom:button></atom:form>"))->toBe([])
            ->and($notes("{$header}<div x-show=\"a\"><atom:modal name=\"m\">{$one}{$one}</atom:modal></div>"))->toHaveCount(1);
    });

    it('does not read a primary in a host component, and reads one in a layout', function () use ($notes, $header) {
        $one = '<atom:button variant="primary">a</atom:button>';

        expect($notes("{$header}<x-confirm-modal>{$one}</x-confirm-modal>"))->toBe([])
            ->and($notes("{$header}<x-card><x-slot:footer>{$one}</x-slot:footer></x-card>"))->toBe([])
            ->and($notes("<x-layouts.app>{$header}{$one}</x-layouts.app>"))->toHaveCount(1)
            ->and($notes("<x-app-layout>{$header}{$one}</x-app-layout>"))->toHaveCount(1)
            ->and($notes("<x-app.shell>{$header}{$one}</x-app.shell>"))->toHaveCount(1)
            ->and($notes("<x-admin-layout>{$header}{$one}</x-admin-layout>"))->toHaveCount(1)
            ->and($notes("<x-dynamic-component component=\"x\">{$header}{$one}</x-dynamic-component>"))->toHaveCount(1)
            ->and($notes("<x-confirm-modal><atom:modal name=\"m\">{$one}{$one}</atom:modal></x-confirm-modal>"))->toHaveCount(1);
    });
});

describe('action-cap: the browser, size and the empty state', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'action-cap');
    $buttons = fn (int $count) => str_repeat('<atom:button>a</atom:button>', $count);

    it('does not count what the browser shows in turn', function () use ($notes, $buttons) {
        $wrap = fn (string $inside) => "<atom:page.header><x-slot:actions>{$inside}</x-slot:actions></atom:page.header>";

        expect($notes($wrap($buttons(2).'<atom:button x-show="a">b</atom:button>')))->toBe([])
            ->and($notes($wrap($buttons(2).'<div wire:show="a"><atom:button>b</atom:button></div>')))->toBe([])
            ->and($notes($wrap($buttons(2).'<template x-if="a"><atom:button>b</atom:button></template>')))->toBe([])
            ->and($notes($wrap($buttons(3).'<atom:button x-show="a">b</atom:button>')))->toHaveCount(1);
    });

    it('says an empty state has no menu', function () use ($notes, $buttons) {
        expect($notes("<atom:empty><x-slot:action>{$buttons(2)}</x-slot:action></atom:empty>"))->toBe([1 => 'empty state action: 2 (cap 1): keep one plain button, an empty state has no menu'])
            ->and((new DesignCheck)->rules()['action-cap']->fix())->toContain('has no menu');
    });

    it('does not read a surface with more than 50 actions', function () use ($notes, $buttons) {
        expect($notes("<atom:page.header><x-slot:actions>{$buttons(50)}</x-slot:actions></atom:page.header>"))->toHaveCount(1)
            ->and($notes("<atom:page.header><x-slot:actions>{$buttons(51)}</x-slot:actions></atom:page.header>"))->toBe([]);
    });
});

describe('row-buttons: tables of lines, toggles and footers', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'row-buttons');

    it('does not read a table when any of its rows edits something', function () use ($notes) {
        $row = fn (string $cell) => "<atom:table.row><atom:table.cell>{$cell}</atom:table.cell></atom:table.row>";

        expect($notes('<atom:table>'.$row('<atom:input/>').$row('<atom:button>a</atom:button>').'</atom:table>'))->toBe([])
            ->and($notes('<atom:table>'.$row('<atom:button>a</atom:button>').$row('<div x-model="a"></div>').'</atom:table>'))->toBe([])
            ->and($notes('<atom:table>'.$row('<atom:button>a</atom:button>').'</atom:table><atom:table>'.$row('<atom:input/>').'</atom:table>'))->toHaveCount(1)
            ->and($notes('<atom:table><x-slot:header><atom:table.toolbar><atom:input/></atom:table.toolbar></x-slot:header>'.$row('<atom:button>a</atom:button>').'</atom:table>'))->toHaveCount(1);
    });

    it('does not take text that only a screen reader gets for the button\'s text', function () use ($notes) {
        $cell = fn (string $button) => "<atom:table.row><atom:table.cell>{$button}</atom:table.cell></atom:table.row>";

        expect($notes($cell('<button><span class="sr-only">Expand</span><atom:icon.chevron-down/></button>')))->toBe([])
            ->and($notes($cell('<button><span class="sr-only">Expand</span> Details</button>')))->toHaveCount(1)
            ->and($notes($cell('<button>Details<span class="sr-only"> of the job</span></button>')))->toHaveCount(1);
    });

    it('does not read the table\'s footer slot, totals included', function () use ($notes) {
        $totals = '<atom:table.totals><atom:table.row><atom:table.cell><atom:button>a</atom:button></atom:table.cell></atom:table.row></atom:table.totals>';

        expect($notes("<atom:table><x-slot:footer>{$totals}</x-slot:footer></atom:table>"))->toBe([])
            ->and($notes("<atom:table><x-slot:rows>{$totals}</x-slot:rows></atom:table>"))->toHaveCount(1)
            ->and($notes("<atom:card><x-slot:footer>{$totals}</x-slot:footer></atom:card>"))->toHaveCount(1);
    });
});
