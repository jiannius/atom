<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class RowButtons extends ActionRule
{
    public const ID = 'row-buttons';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Actions';

    public function title(): string
    {
        return 'A button in a table row';
    }

    public function fix(): string
    {
        return 'Rows show no buttons: put the actions in `<atom:table.actions>` (the one ⋯ menu), and give the row an `href` to open the record.';
    }

    public function rule(): string
    {
        return 'none: `<atom:table.actions>`, and a row with `href` opens the record';
    }

    public function detect(): string
    {
        return 'An `<atom:button>`, or a plain `<button>` with text (not text only a screen reader gets, `sr-only`), in an `<atom:table.row>` or `<atom:table.cell>` (nothing but markup between), in a row that edits nothing, in a table none of whose rows edit anything: no `<atom:input>`, `select`, `textarea`, `date-picker` or other control, no `wire:model` or `x-model` in it. A button in `<atom:table.actions>`, in a dropdown or a modal, an icon-only plain `<button>` (a toggle), `<atom:link>` and a button in the table\'s `footer` slot are not flagged.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $editable = [];

        foreach ($outline->nodes() as $button) {
            if (! $this->isButton($outline, $button)) {
                continue;
            }

            $part = $this->part($outline, $button);

            if ($part === null || $this->inFooter($outline, $button)) {
                continue;
            }

            $row = $part['tag'] === 'atom:table.row' ? $part : ($outline->inside($part, 'atom:table.row') ?? $part);
            $table = $outline->inside($row, 'atom:table');
            $scope = $table['id'] ?? -1;
            $editable[$scope] ??= $table === null ? $this->hasFormControl($outline, $row) : $this->editsRows($outline, $table);

            if (! $editable[$scope] && ! $this->hasFormControl($outline, $row)) {
                yield [$button['offset'], 'a row button: use `table.actions`, and the row\'s `href` to open the record'];
            }
        }
    }

    /**
     * Whether any row of a table edits something: its buttons are then the controls of a form table (line items), not row actions
     */
    protected function editsRows(BladeOutline $outline, array $table): bool
    {
        foreach ($outline->descendants($table) as $node) {
            if ($node['tag'] === 'atom:table.row' && $this->hasFormControl($outline, $node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a button is in the table's `footer` slot (a "Load more", or the totals under the rows)
     */
    protected function inFooter(BladeOutline $outline, array $button): bool
    {
        foreach ($outline->ancestors($button) as $ancestor) {
            if ($ancestor['kind'] === 'slot' && $this->slotKey($ancestor) === 'footer' && ($outline->owner($ancestor)['tag'] ?? null) === 'atom:table') {
                return true;
            }
        }

        return false;
    }

    /**
     * An atom button, or a plain one that has text (an icon-only one is a toggle or an expander)
     */
    protected function isButton(BladeOutline $outline, array $node): bool
    {
        if ($node['tag'] === 'atom:button') {
            return true;
        }

        if ($node['tag'] !== 'button') {
            return false;
        }

        $label = $outline->label($node);

        foreach ($outline->descendants($node) as $inner) {
            if ($label !== null && in_array('sr-only', $outline->classes($inner), true)) {
                $label = trim(str_replace((string) $outline->label($inner), '', $label));
            }
        }

        return ! in_array($label, [null, ''], true);
    }

    /**
     * The row or cell a button belongs to: the nearest one, when only markup is between (a dropdown or a modal in a cell is its own)
     */
    protected function part(BladeOutline $outline, array $button): ?array
    {
        foreach ($outline->ancestors($button) as $ancestor) {
            if (in_array($ancestor['tag'], ['atom:table.row', 'atom:table.cell'], true)) {
                return $ancestor;
            }

            if (! $this->isWrapper($ancestor)) {
                return null;
            }
        }

        return null;
    }
}
