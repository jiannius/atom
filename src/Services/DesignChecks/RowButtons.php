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
        return 'An `<atom:button>`, or a plain `<button>` with text, in an `<atom:table.row>` or `<atom:table.cell>` (nothing but markup between), in a row that edits nothing: no `<atom:input>`, `select`, `textarea`, `date-picker` or other control, no `wire:model` or `x-model` in it. A button in `<atom:table.actions>`, in a dropdown or a modal, an icon-only plain `<button>` (a toggle), `<atom:link>` and the table\'s `footer` slot are not flagged.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $button) {
            if (! $this->isButton($outline, $button)) {
                continue;
            }

            $part = $this->part($outline, $button);

            if ($part !== null && ! $this->hasFormControl($outline, $part['tag'] === 'atom:table.row' ? $part : ($outline->inside($part, 'atom:table.row') ?? $part))) {
                yield [$button['offset'], 'a row button: use `table.actions`, and the row\'s `href` to open the record'];
            }
        }
    }

    /**
     * An atom button, or a plain one that has text (an icon-only one is a toggle or an expander)
     */
    protected function isButton(BladeOutline $outline, array $node): bool
    {
        if ($node['tag'] === 'atom:button') {
            return true;
        }

        return $node['tag'] === 'button' && ! in_array($outline->label($node), [null, ''], true);
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
