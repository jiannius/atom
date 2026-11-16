<?php

namespace Jiannius\Atom\Services\DesignChecks;

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
        return 'One `variant="primary"` per screen: the header\'s `primary` slot, or the form\'s submit. Anything else is a plain `<atom:button>` (a second submit takes `variant="default"`).';
    }

    public function rule(): string
    {
        return 'One `variant="primary"` per screen';
    }

    public function detect(): string
    {
        return 'A primary is an `<atom:button>` with a static `variant="primary"`, or `type="submit"` and no `variant`. A modal and a `<atom:form.modal>` (which draws its own Save) are screens of their own. Flagged: one in `<atom:empty>`, `<atom:table.toolbar>`, the table\'s `checked` slot or a row (not a row that edits something); one in an `<atom:callout>` when the screen\'s `<atom:page.header>` has a `primary` slot; a second in one `<atom:form>`; one in a loop (one per item); and a second that can show with an earlier one on a screen (the branches of one `@if` chain never do, and two separate `@if` blocks are not paired). A bound `variant` or `type` is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $primaries = array_values(array_filter($outline->atom('button'), fn ($button) => $this->isPrimary($outline, $button)));

        if ($primaries === [] || $outline->branchesDeclined()) {
            return;
        }

        $headed = $this->screensWithHeaderPrimary($outline);
        $inForm = [];
        $kept = [];

        foreach ($primaries as $primary) {
            $screen = $this->screen($outline, $primary);
            $form = $this->form($outline, $primary);
            $placement = $this->placement($outline, $primary, $headed);

            if ($placement === false) {
                continue;
            }

            $note = $placement
                ?? $this->secondInForm($outline, $primary, $form, $inForm)
                ?? $this->inLoop($outline, $primary, $form, $screen);

            if ($form !== null) {
                $inForm[$form['id']][] = $primary;
            }

            if ($note === null) {
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

            if ($note !== null) {
                yield [$primary['offset'], $note];
            }
        }
    }

    /**
     * The screens (a modal's id, or -1 for the page) whose `page.header` has a `primary` slot
     *
     * @return array<int, true>
     */
    protected function screensWithHeaderPrimary(BladeOutline $outline): array
    {
        $screens = [];

        foreach ($this->slots($outline, ['atom:page.header'], ['primary']) as $slot) {
            $screens[$this->screen($outline, $slot)] = true;
        }

        return $screens;
    }

    /**
     * The form a button submits: the nearest `<atom:form>` or `<atom:form.modal>` before a modal's edge
     */
    protected function form(BladeOutline $outline, array $button): ?array
    {
        foreach ($outline->ancestors($button) as $ancestor) {
            if (in_array($ancestor['tag'], ['atom:form', 'atom:form.modal'], true)) {
                return $ancestor;
            }

            if ($ancestor['tag'] === 'atom:modal') {
                return null;
            }
        }

        return null;
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
     * @param  array<int, array<int, array>>  $inForm
     */
    protected function secondInForm(BladeOutline $outline, array $button, ?array $form, array $inForm): ?string
    {
        if ($form === null) {
            return null;
        }

        if ($form['tag'] === 'atom:form.modal') {
            return 'a modal form draws its own Save as the primary: this one is a plain button';
        }

        foreach ($inForm[$form['id']] ?? [] as $earlier) {
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
