<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\ActionPlacement;
use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class ExtraPrimary extends ActionRule
{
    public const ID = 'extra-primary';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Actions';

    public function title(): string
    {
        return 'A second primary button';
    }

    public function fix(): string
    {
        return 'One `variant="primary"` per screen: the header\'s `primary` slot (an `<atom:action priority="primary">` there is the same), or the form\'s submit. Anything else is a plain `<atom:button>` or `<atom:action>` (a second submit takes `variant="default"`).';
    }

    public function rule(): string
    {
        return 'One `variant="primary"` per screen';
    }

    public function detect(): string
    {
        return 'A primary is an `<atom:button>` with a static `variant="primary"`, or `type="submit"` and no `variant`. A modal and a `<atom:form.modal>` (which draws its own Save) are screens of their own. A primary in a form (`<atom:form>`, `<form>`, or an `<atom:form.section>` that saves on its own), or one that names a form by a `form="id"` attribute, belongs to it: only a second one in the same form is flagged, and it is never paired with a primary of the screen. A primary that the browser can hide (`x-show`, `<template x-if>`, `wire:show`, `:hidden`) or that sits in a host component other than a layout is not paired. Flagged: one in `<atom:empty>`, `<atom:table.toolbar>`, the table\'s `checked` slot or a row (not a row that edits something); one in an `<atom:callout>` when the screen\'s `<atom:page.header>` has a `primary` slot; a second in one form; one in a loop (one per item); and a second that can show with an earlier one on a screen (the branches of one `@if` chain never do, and two separate `@if` blocks are not paired). A bound `variant` or `type` is not read. An `<atom:action>` is a primary where `ActionPlacement::place()` takes it: the first untoned `priority="primary"` action (or one in the header\'s `primary` slot) of a `page.header` that is not `size="sm"` and whose `primary` slot the host does not fill; it counts toward the screen\'s one like a button, so a callout\'s primary is judged against it. Flagged on the action: a `priority="primary"` one drawn as a plain action (in a toolbar, bulk bar, card, panel or row, in a `size="sm"` header, beside the host\'s `primary` slot, or a second in one header). A toned action is never a primary, and a bound `priority` or `tone` is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $primaries = array_values(array_filter($outline->atom('button'), fn ($button) => $this->isPrimary($outline, $button)));

        if ($outline->branchesDeclined()) {
            return;
        }

        [$taken, $notes] = $this->actionPrimaries($outline);

        foreach ($notes as $offset => $note) {
            yield [$offset, $note];
        }

        $primaries = [...$primaries, ...$taken];
        usort($primaries, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        if ($primaries === []) {
            return;
        }

        $headed = $this->screensWithHeaderPrimary($outline, $taken);
        $inForm = [];
        $kept = [];

        foreach ($primaries as $primary) {
            if ($this->hostWrapped($outline, $primary)) {
                continue;
            }

            $screen = $this->screen($outline, $primary);
            $form = $this->form($outline, $primary);
            $scope = $form['id'] ?? $this->formAttribute($outline, $primary);
            $placement = $this->placement($outline, $primary, $headed);

            if ($placement === false) {
                continue;
            }

            $conditional = $this->clientConditional($outline, $primary);
            $note = $placement
                ?? ($conditional ? null : $this->secondInForm($outline, $primary, $form, $scope, $inForm))
                ?? $this->inLoop($outline, $primary, $form, $screen);

            if ($note === null && ! $conditional) {
                if ($scope !== null) {
                    $inForm[$scope][] = $primary;
                } else {
                    foreach ($kept[$screen] ?? [] as $earlier) {
                        if ($outline->together($earlier, $primary)) {
                            $note = 'a second primary on one screen: the next step for the status, or the form\'s submit';

                            break;
                        }
                    }

                    if ($note === null) {
                        $kept[$screen][] = $primary;
                    }
                }
            }

            if ($note !== null) {
                yield [$primary['offset'], $note];
            }
        }
    }

    /**
     * The screens (a modal's id, or -1 for the page) whose `page.header` has a `primary` slot, or an `<atom:action>` the header draws as its primary
     *
     * @param  array<int, array>  $taken  the actions a header takes for its primary
     * @return array<int, true>
     */
    protected function screensWithHeaderPrimary(BladeOutline $outline, array $taken = []): array
    {
        $screens = [];

        foreach ($this->slots($outline, ['atom:page.header'], ['primary']) as $slot) {
            $screens[$this->screen($outline, $slot)] = true;
        }

        foreach ($taken as $action) {
            $screens[$this->screen($outline, $action)] = true;
        }

        return $screens;
    }

    /**
     * What `ActionPlacement::place()` does with the primaries of each surface, over every set of actions that can show together: the actions a header draws as
     * its primary (they count toward the one of the screen), and where an action says `priority="primary"` and is drawn as a plain one (another surface, a
     * `size="sm"` header, a header whose `primary` slot the host fills, a second one in a header). A toned action is never a primary: it goes to the menu,
     * and is not counted here. A bound `priority` or `tone` is not read.
     *
     * @return array{0: array<int, array>, 1: array<int, string>} the taken actions' nodes, and the notes by offset
     */
    protected function actionPrimaries(BladeOutline $outline): array
    {
        $taken = [];
        $notes = [];
        $owners = [];

        foreach ($this->placedActions($outline) as $action) {
            if ($action['modelled']) {
                $owners[$action['owner']] = true;
            }
        }

        foreach (array_keys($owners) as $ownerId) {
            $owner = $outline->node($ownerId);
            $actions = $this->actionsOf($outline, $owner, true);
            $surface = $this->surfaceOf($owner);
            $context = $this->contextOf($outline, $owner);
            $byId = [];

            foreach ($actions as $action) {
                $byId[$action['node']['id']] = $action;
            }

            if ($context === null || count($actions) > 50) {
                continue;
            }

            foreach ($this->chains($outline, array_column($actions, 'node')) as $chain) {
                $items = array_map(fn ($node) => $byId[$node['id']], $chain);
                $placed = ActionPlacement::place($surface, $items, $context);

                if ($placed['primary'] !== null) {
                    $taken[$placed['primary']['node']['id']] = $placed['primary']['node'];
                }

                foreach ($items as $item) {
                    if (ActionPlacement::priorityOf($item) !== 'primary' || $item['tone'] !== null || $item['node']['id'] === ($placed['primary']['node']['id'] ?? null)) {
                        continue;
                    }

                    $notes[$item['node']['offset']] ??= match (true) {
                        $surface !== 'header' => 'a `priority="primary"` action in a '.(['bulk' => 'bulk bar', 'row' => 'table row'][$surface] ?? $surface).' is drawn as a plain action: the screen\'s primary is the header\'s',
                        $context['small'] => 'a `priority="primary"` action in a `size="sm"` header is drawn as a plain action: it has no primary',
                        $context['hostPrimary'] => 'a `priority="primary"` action beside the host\'s own `primary` slot is drawn as a plain action: the slot is the primary',
                        default => 'a second `priority="primary"` action in one header is drawn as a plain action: one primary per screen',
                    };
                }
            }
        }

        usort($taken, fn ($a, $b) => $a['offset'] <=> $b['offset']);
        ksort($notes);

        return [array_values($taken), $notes];
    }

    /**
     * The form a button submits: the nearest `<atom:form>`, plain `<form>` or `<atom:form.modal>` before a modal's edge, else
     * the `<atom:form.section>` it is in (a section that saves on its own). Null when it is in none: a primary of the screen.
     */
    protected function form(BladeOutline $outline, array $button): ?array
    {
        $section = null;

        foreach ($outline->ancestors($button) as $ancestor) {
            if (in_array($ancestor['tag'], ['atom:form', 'form', 'atom:form.modal'], true)) {
                return $ancestor;
            }

            if ($ancestor['tag'] === 'atom:form.section') {
                $section ??= $ancestor;
            }

            if ($ancestor['tag'] === 'atom:modal') {
                break;
            }
        }

        return $section;
    }

    /**
     * The form a button names with `form="id"` outside any form, as a key (the id, or a key of its own when the id is bound, so it
     * is never paired); null when it names none
     */
    protected function formAttribute(BladeOutline $outline, array $button): ?string
    {
        if (! $outline->has($button, 'form')) {
            return null;
        }

        return 'form:'.($outline->value($button, 'form') ?? '#'.$button['id']);
    }

    /**
     * The note when a primary sits where none belongs: an empty state, a toolbar, a bulk bar, a row, or a callout under a header that has one.
     * False when it is in a row that edits something: a control of the line, not a primary to count.
     *
     * @param  array<int, true>  $headed
     */
    protected function placement(BladeOutline $outline, array $button, array $headed): string|false|null
    {
        foreach ($outline->ancestors($button) as $ancestor) {
            if (in_array($ancestor['tag'], static::SCREENS, true)) {
                return null;
            }

            $note = match (true) {
                $ancestor['tag'] === 'atom:empty' => 'an empty state\'s next step is a plain button, not the primary',
                $ancestor['tag'] === 'atom:table.toolbar' => 'no primary in a toolbar: the header\'s primary is the screen\'s one',
                $ancestor['kind'] === 'slot' && in_array($this->slotKey($ancestor), ['checked', 'checkedmenu'], true) && ($outline->owner($ancestor)['tag'] ?? null) === 'atom:table' => 'no primary in a bulk bar: the header\'s primary is the screen\'s one',
                $ancestor['tag'] === 'atom:callout' && isset($headed[$this->screen($outline, $ancestor)]) => 'the header already has the primary: a callout\'s button is a plain one',
                default => null,
            };

            if ($note !== null) {
                return $note;
            }

            if (in_array($ancestor['tag'], ['atom:table.row', 'atom:table.cell', 'atom:table.actions'], true)) {
                return $this->hasFormControl($outline, $this->row($outline, $ancestor)) ? false : 'no primary in a table row: a row opens the record or has `<atom:table.actions>`';
            }
        }

        return null;
    }

    /**
     * The row a row, cell or actions tag belongs to (the tag itself when it is not inside a row)
     */
    protected function row(BladeOutline $outline, array $part): array
    {
        return $part['tag'] === 'atom:table.row' ? $part : ($outline->inside($part, 'atom:table.row') ?? $part);
    }

    /**
     * The note when a form already has a primary that can show with this one, or when this is in a modal form (which draws its own Save)
     *
     * @param  array<int|string, array<int, array>>  $inForm
     */
    protected function secondInForm(BladeOutline $outline, array $button, ?array $form, int|string|null $scope, array $inForm): ?string
    {
        if ($scope === null) {
            return null;
        }

        if ($form !== null && $form['tag'] === 'atom:form.modal') {
            return 'a modal form draws its own Save as the primary: this one is a plain button';
        }

        foreach ($inForm[$scope] ?? [] as $earlier) {
            if ($outline->together($earlier, $button)) {
                return 'one primary in a form, its submit: a second submit takes `variant="default"`';
            }
        }

        return null;
    }

    /**
     * The note for a primary in a loop (one per item): not when the form or the modal around it is the item, which then has its own
     */
    protected function inLoop(BladeOutline $outline, array $button, ?array $form, int $screen): ?string
    {
        if (! $outline->inLoop($button) || ($form !== null && $outline->inLoop($form)) || ($screen !== -1 && $outline->inLoop($screen))) {
            return null;
        }

        return 'a primary in a loop is one per item: the screen has one';
    }
}
