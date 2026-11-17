<?php

use Jiannius\Atom\Services\ActionPlacement;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\DesignChecks\ActionCap;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * What the checks say about `<atom:action>`, and the corners a fixture line cannot show. What each finds is held by its fixture host
 * (DesignCheckFixturesTest); these hold what it says, and that the checks read the engine's own rules (ActionPlacement), not a copy.
 */

describe('action-misplaced', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'action-misplaced');

    it('is a break, since the action draws nothing', function () {
        expect((new DesignCheck)->rules()['action-misplaced']::SEVERITY)->toBe(DesignCheck::BREAK);
    });

    it('says what is around an action that nothing places', function () use ($notes) {
        expect($notes('<atom:page><atom:action wire:click="a">A</atom:action></atom:page>'))->toBe([1 => 'an action in `<atom:page>`, which has no surface to place it: it draws nothing'])
            ->and($notes('<atom:card><atom:action wire:click="a">A</atom:action></atom:card>'))->toBe([1 => 'an action in the body of `<atom:card>`, which does not place actions: it draws nothing'])
            ->and($notes('<atom:page.header><x-slot:meta><atom:action wire:click="a">A</atom:action></x-slot:meta></atom:page.header>'))
            ->toBe([1 => 'an action in the `meta` slot of `<atom:page.header>`, which does not place actions: it draws nothing'])
            ->and($notes('<atom:form.modal name="m"><x-slot:actions><atom:action wire:click="a">A</atom:action></x-slot:actions></atom:form.modal>'))
            ->toBe([1 => 'an action in a slot of `<atom:form.modal>`, which has no surface to place it: it draws nothing']);
    });

    it('says what a surface leaves behind', function () use ($notes) {
        $in = fn (string $around) => '<atom:page.header><x-slot:actions>'.$around.'</x-slot:actions></atom:page.header>';

        expect($notes($in('<div x-show="open"><atom:action wire:click="a">A</atom:action></div>')))
            ->toBe([1 => 'an action inside `x-show`, which is left behind when the surface places the action: use `@if`'])
            ->and($notes($in('<atom:tooltip text="t"><atom:action wire:click="a">A</atom:action></atom:tooltip>')))
            ->toBe([1 => 'an action inside `<atom:tooltip>`, which is left behind when the surface places the action: an action takes no tooltip, the surface draws it'])
            ->and($notes($in('<atom:confirm.trigger heading="h" button-confirm="b"><atom:action wire:click="a">A</atom:action></atom:confirm.trigger>')))
            ->toBe([1 => 'an action inside `<atom:confirm.trigger>`, which is left behind when the surface places the action: put `confirm` on the action itself'])
            ->and($notes($in('<atom:dropdown><atom:action wire:click="a">A</atom:action></atom:dropdown>')))
            ->toBe([1 => 'an action inside `<atom:dropdown>`, which is left behind when the surface places the action: the surface draws the action itself, so it cannot sit in a trigger or a group']);
    });

    it('reads a modal\'s own header, not the modal, and the header\'s slot, not the page around it', function () use ($notes) {
        expect($notes('<atom:modal name="m"><atom:page.header size="sm"><x-slot:actions><atom:action wire:click="a">A</atom:action></x-slot:actions></atom:page.header></atom:modal>'))->toBe([])
            ->and($notes('<atom:page><atom:page.header><x-slot:actions><atom:action wire:click="a">A</atom:action></x-slot:actions></atom:page.header></atom:page>'))->toBe([]);
    });

    it('declines what it cannot see: the top of a file, a host component, an atom tag it does not know', function () use ($notes) {
        $action = '<atom:action wire:click="a">A</atom:action>';

        expect($notes($action))->toBe([])
            ->and($notes("<x-job-card>{$action}</x-job-card>"))->toBe([])
            ->and($notes("<atom:page><x-job-card>{$action}</x-job-card></atom:page>"))->toBe([])
            ->and($notes("<atom:page><atom:grid>{$action}</atom:grid></atom:page>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions><x-job-card>{$action}</x-job-card></x-slot:actions></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:crumbs>{$action}</x-slot:crumbs></atom:page.header>"))->toBe([])
            ->and($notes("<atom:table>{$action}</atom:table>"))->toBe([]);
    });

    it('does not read the action\'s own condition', function () use ($notes) {
        expect($notes('<atom:page.header><x-slot:actions><atom:action x-show="open" wire:click="a">A</atom:action></x-slot:actions></atom:page.header>'))->toBe([]);
    });
});

describe('action-cap', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'action-cap');
    $header = fn (string $actions, string $attributes = '') => "<atom:page.header{$attributes}><x-slot:actions>{$actions}</x-slot:actions></atom:page.header>";
    $button = '<atom:button>B</atom:button>';
    $action = '<atom:action wire:click="a">A</atom:action>';

    it('counts the buttons and what atom makes visible of the actions, and names both', function () use ($notes, $header, $button, $action) {
        expect($notes($header($button.$button.$action.$action)))->toBe([1 => 'page.header actions: 2 buttons + 2 actions atom shows = 4 (cap 2): make the buttons `<atom:action>`s'])
            ->and($notes($header($button.$button.$button)))->toBe([1 => 'page.header actions: 3 (cap 2)']);
    });

    it('keeps the bulk bar\'s last delete out of the count, beside actions too', function () use ($notes, $button, $action) {
        $bar = fn (string $slot) => "<atom:table><x-slot:checked>{$slot}</x-slot:checked></atom:table>";
        $delete = '<atom:button type="delete">D</atom:button>';

        expect($notes($bar($button.$button.$action.$delete)))->toBe([])
            ->and($notes($bar($button.$button.$button.$action.$delete)))->toBe([1 => 'bulk bar (checked slot): 3 buttons + 1 actions atom shows = 4 (cap 3): make the buttons `<atom:action>`s'])
            ->and($notes($bar($button.$button.$button.$delete)))->toBe([]);
    });

    it('does not model an action with attributes spread into its tag', function () use ($notes, $header, $button, $action) {
        $spread = '<atom:action {{ $attributes }} wire:click="a">A</atom:action>';

        expect($notes($header($button.$button.$spread)))->toBe([])
            ->and($notes($header($button.$button.$action)))->toHaveCount(1);
    });

    it('reads the caps of the engine', function () use ($notes, $header, $button, $action) {
        $cap = ActionPlacement::CAPS['header']['visible'];

        expect($notes($header($button.str_repeat($action, $cap - 1))))->toBe([])
            ->and($notes($header($button.str_repeat($action, $cap))))->toHaveCount(1)
            ->and($notes($header($button.$action, ' size="sm"')))->toHaveCount(ActionPlacement::CAPS['header-sm']['visible'] === 1 ? 1 : 0);
    });

    it('has the surfaces\' caps from ActionPlacement::CAPS, by owner', function () {
        $surfaces = (new ReflectionClassConstant(ActionCap::class, 'SURFACES'))->getValue();
        $caps = collect($surfaces)->mapWithKeys(fn ($row) => [$row[0][0] => $row[2]])->all();

        expect($caps['atom:page.header'])->toBe(ActionPlacement::CAPS['header']['visible'])
            ->and($caps['atom:table.toolbar'])->toBe(ActionPlacement::CAPS['toolbar']['visible'])
            ->and($caps['atom:table'])->toBe(ActionPlacement::CAPS['bulk']['visible'])
            ->and($caps['atom:card'])->toBe(ActionPlacement::CAPS['card']['visible'])
            ->and($caps['atom:panel'])->toBe(ActionPlacement::CAPS['panel']['visible']);
    });

    it('reads the buttons alone when it cannot read the actions: an unreadable primary slot, or over 50 units together', function () use ($notes, $button, $action) {
        $many = str_repeat($action, 30).str_repeat($button, 30);

        expect($notes('<atom:page.header><x-slot:actions>'.$button.$button.$action.'</x-slot:actions><x-slot:primary></x-slot:primary></atom:page.header>'))->toHaveCount(1)
            ->and($notes('<atom:page.header><x-slot:actions>'.$button.$button.$action.'</x-slot:actions><x-slot:primary>{{ $primary }}</x-slot:primary></atom:page.header>'))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$many}</x-slot:actions></atom:page.header>"))->toBe([1 => 'page.header actions: 30 (cap 2)']);
    });
});

describe('menu-order', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'menu-order');
    $menu = fn (string $items) => "<atom:page.header><x-slot:menu>{$items}</x-slot:menu></atom:page.header>";

    it('says a host warning or danger item is not last when an action\'s row prints after it', function () use ($notes, $menu) {
        expect($notes($menu('<atom:menu.item variant="warning" confirm>V</atom:menu.item><atom:action wire:click="a">A</atom:action>')))
            ->toBe([1 => 'a warning item before the actions\' rows: atom prints them after your items, so it is not last; make it `<atom:action tone="warning">`'])
            ->and($notes($menu('<atom:menu.item variant="delete">D</atom:menu.item><atom:action tone="warning" wire:click="a">A</atom:action>')))
            ->toBe([1 => 'a danger item before the actions\' rows: atom prints them after your items, so it is not last; make it `<atom:action tone="delete">`'])
            ->and($notes($menu('<atom:menu.item variant="danger" confirm>D</atom:menu.item><atom:action tone="warning" wire:click="a">A</atom:action>')))
            ->toBe([1 => 'a danger item before the actions\' rows: atom prints them after your items, so it is not last; make it `<atom:action tone="danger">`']);
    });

    it('never reports an action\'s own row, and draws the separator before its first toned row', function () use ($notes, $menu) {
        expect($notes($menu('<atom:action tone="delete" wire:click="d">D</atom:action><atom:action wire:click="a">A</atom:action>')))->toBe([])
            ->and($notes($menu('<atom:menu.item>P</atom:menu.item><atom:action tone="warning" wire:click="a">A</atom:action>')))->toBe([])
            ->and($notes($menu('<atom:menu.item variant="warning" confirm>V</atom:menu.item><atom:action tone="warning" wire:click="a">A</atom:action>')))->toBe([]);
    });

    it('does not model a header whose `size` is bound: it may be `sm`, which holds fewer buttons', function () use ($notes) {
        $actions = str_repeat('<atom:action wire:click="a">A</atom:action>', 3);
        $source = fn (string $size) => "<atom:page.header {$size}><x-slot:actions>{$actions}</x-slot:actions><x-slot:menu><atom:menu.item variant=\"warning\" confirm>V</atom:menu.item></x-slot:menu></atom:page.header>";

        expect($notes($source('size="md"')))->toHaveCount(1)
            ->and($notes($source(':size="$size"')))->toBe([]);
    });

    it('puts an action that overflows from `actions` in the menu, after the host\'s items', function () use ($notes) {
        $source = '<atom:page.header><x-slot:actions>'.str_repeat('<atom:action wire:click="a">A</atom:action>', ActionPlacement::CAPS['header']['visible'] + 1).'</x-slot:actions><x-slot:menu><atom:menu.item variant="delete">D</atom:menu.item></x-slot:menu></atom:page.header>';

        expect($notes($source))->toHaveCount(1);
    });
});

describe('extra-primary', function () {
    $notes = fn (string $source) => DesignHost::notes($source, 'extra-primary');

    it('says why a primary action is drawn as a plain one', function () use ($notes) {
        $action = '<atom:action priority="primary" wire:click="a">A</atom:action>';

        expect($notes("<atom:page.header><x-slot:primary>{$action}</x-slot:primary><x-slot:actions>{$action}</x-slot:actions></atom:page.header>"))
            ->toBe([1 => 'a second `priority="primary"` action in one header is drawn as a plain action: one primary per screen'])
            ->and($notes("<atom:page.header size=\"sm\"><x-slot:actions>{$action}</x-slot:actions></atom:page.header>"))
            ->toBe([1 => 'a `priority="primary"` action in a `size="sm"` header is drawn as a plain action: it has no primary'])
            ->and($notes("<atom:page.header><x-slot:actions>{$action}</x-slot:actions><x-slot:primary><atom:button variant=\"primary\">P</atom:button></x-slot:primary></atom:page.header>"))
            ->toBe([1 => 'a `priority="primary"` action beside the host\'s own `primary` slot is drawn as a plain action: the slot is the primary'])
            ->and($notes("<atom:table><x-slot:checked>{$action}</x-slot:checked></atom:table>"))
            ->toBe([1 => 'a `priority="primary"` action in a bulk bar is drawn as a plain action: the screen\'s primary is the header\'s'])
            ->and($notes("<atom:card><x-slot:actions>{$action}</x-slot:actions></atom:card>"))
            ->toBe([1 => 'a `priority="primary"` action in a card is drawn as a plain action: the screen\'s primary is the header\'s']);
    });

    it('does not count a toned primary, which goes to the menu, and does not read a bound priority', function () use ($notes) {
        expect($notes('<atom:page.header><x-slot:actions><atom:action tone="warning" priority="primary" wire:click="a">A</atom:action><atom:action priority="primary" wire:click="b">B</atom:action><atom:action :priority="$p" wire:click="c">C</atom:action></x-slot:actions></atom:page.header>'))->toBe([]);
    });

    it('judges a callout\'s primary against the header\'s action', function () use ($notes) {
        expect($notes('<atom:page.header><x-slot:primary><atom:action wire:click="a">A</atom:action></x-slot:primary></atom:page.header><atom:callout><atom:button variant="primary">P</atom:button></atom:callout>'))
            ->toBe([1 => 'the header already has the primary: a callout\'s button is a plain one']);
    });

    it('judges a callout\'s primary against a primary action in the `actions` slot too', function () use ($notes) {
        expect($notes('<atom:page.header><x-slot:actions><atom:action priority="primary" wire:click="a">A</atom:action></x-slot:actions></atom:page.header><atom:callout><atom:button variant="primary">P</atom:button></atom:callout>'))
            ->toBe([1 => 'the header already has the primary: a callout\'s button is a plain one']);
    });

    it('counts a header\'s `primary` slot the host fills with markup around the action as the host\'s', function () use ($notes) {
        $action = '<atom:action priority="primary" wire:click="a">A</atom:action>';

        expect($notes("<atom:page.header><x-slot:actions>{$action}</x-slot:actions><x-slot:primary><div class=\"x\"></div></x-slot:primary></atom:page.header>"))->toHaveCount(1)
            ->and($notes("<atom:page.header><x-slot:actions>{$action}</x-slot:actions><x-slot:primary>{{-- nothing --}} @if (\$x) @endif</x-slot:primary></atom:page.header>"))->toBe([])
            ->and($notes("<atom:page.header><x-slot:actions>{$action}</x-slot:actions><x-slot:primary>{{ \$slot }}</x-slot:primary></atom:page.header>"))->toBe([]);
    });
});

describe('tone-missing, confirm-copy, dup-export', function () {
    it('names the tone an action takes', function () {
        $notes = fn (string $source) => DesignHost::notes($source, 'tone-missing');

        expect($notes('<atom:action wire:click="a">Void invoice</atom:action>'))->toBe([1 => '"Void invoice" is a warning: `tone="warning"`'])
            ->and($notes('<atom:action wire:click="a">Delete draft</atom:action>'))->toBe([1 => '"Delete draft" is a danger: `tone="delete"`'])
            ->and($notes('<atom:action icon="delete" wire:click="a">Gone</atom:action>'))->toBe([1 => 'an action with the delete icon is a danger: `tone="delete"`'])
            ->and($notes('<atom:action tone="warning" wire:click="a">Void invoice</atom:action>'))->toBe([]);
    });

    it('reads an action\'s label, then its text, never its title', function () {
        $notes = fn (string $source) => DesignHost::notes($source, 'tone-missing');

        expect($notes('<atom:action title="Delete me" wire:click="a">Edit</atom:action>'))->toBe([])
            ->and($notes('<atom:action :title="$hint" wire:click="a">Void invoice</atom:action>'))->toBe([1 => '"Void invoice" is a warning: `tone="warning"`'])
            ->and($notes('<atom:action label="Void invoice" title="Edit" wire:click="a" />'))->toBe([1 => '"Void invoice" is a warning: `tone="warning"`'])
            ->and($notes('<atom:action :label="$name" wire:click="a" />'))->toBe([]);
    });

    it('reads an action\'s confirm copy like a button\'s', function () {
        $notes = fn (string $source) => DesignHost::notes($source, 'confirm-copy');

        expect($notes('<atom:action tone="warning" confirm="Void THIS invoice?" wire:click="a">Void</atom:action>'))->toBe([1 => 'the confirm text has an all-caps word ("THIS"): write it in sentence case'])
            ->and($notes('<atom:action tone="warning" confirm wire:click="a">Void</atom:action>'))->toBe([]);
    });

    it('reads an action in the toolbar\'s default slot and the bulk bar', function () {
        $found = DesignHost::scanSource('<atom:table><x-slot:header><atom:table.toolbar><atom:action wire:click="a">Export</atom:action></atom:table.toolbar></x-slot:header><x-slot:checked><atom:action wire:click="b">export</atom:action></x-slot:checked></atom:table>', 'dup-export');

        expect(array_column($found, 'note', 'line'))->toBe([1 => '"export" is in the toolbar\'s actions too']);
    });
});
