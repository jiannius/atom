<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ActionMisplaced extends ActionRule
{
    public const ID = 'action-misplaced';

    public const SEVERITY = DesignCheck::BREAK;

    public const GROUP = 'Actions';

    public function title(): string
    {
        return 'An action that no surface places, or that loses its condition or wrapper';
    }

    public function fix(): string
    {
        return 'Put each `<atom:action>` in a slot its surface reads: `<atom:page.header>` (`primary`, `actions`, `menu`), `<atom:table.toolbar>` (`actions`, `end`, `menu`), the table\'s `checked` and `checked-menu`, `<atom:card>` or `<atom:panel>` (`actions`, `menu`), or `<atom:table.actions>`. Use `@if` for a condition and `confirm` on the action instead of `<atom:confirm.trigger>`; an action takes no tooltip, and a dropdown or button group around it is left behind: atom takes the action out of what is around it.';
    }

    public function rule(): string
    {
        return 'the surface it sits in places for you';
    }

    public function detect(): string
    {
        return 'An `<atom:action>` with nothing but markup between it and a tag that has no surface to place it (`atom:page`, `atom:form` and its `actions` slot, `atom:form.modal`, `atom:modal`, `atom:empty`, `atom:callout`, a table row or cell, the body of a card or panel), or in a slot that no surface reads (the header\'s `status` or `meta`, a card or panel\'s `heading`, `footer` or `cover`, the table\'s `rows`, `columns`, `header`, `footer` or `empty`): it draws nothing. And one that a surface does place but that has a client condition (`x-show`, `x-if`, `x-for`, `wire:show`, `:hidden`) or a wrapper (`atom:tooltip`, `atom:confirm.trigger`, `atom:dropdown`, `atom:button.group`) around it inside the slot: the surface takes the action out and leaves the condition or the wrapper behind, empty. An `x-show` on the action itself reaches the element and is not flagged. Declined: an action at the top of a file (it may be a partial a surface includes), one inside a host component, or inside any other atom tag, and a slot of a surface it does not know.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('action') as $action) {
            $where = $this->locate($outline, $action);

            if ($where['state'] === 'misplaced') {
                yield [$action['offset'], 'an action '.$where['note'].': it draws nothing'];
            } elseif ($where['state'] === 'placed' && $where['lost'] !== null) {
                yield [$action['offset'], 'an action '.$where['lost']];
            }
        }
    }
}
