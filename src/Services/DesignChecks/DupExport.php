<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class DupExport extends ActionRule
{
    public const ID = 'dup-export';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Actions';

    public function title(): string
    {
        return 'The same button in the toolbar and the bulk bar';
    }

    public function fix(): string
    {
        return 'Put the action once, in the `<atom:table.toolbar>` `actions` slot; the bulk bar may have its own "Export selected".';
    }

    public function rule(): string
    {
        return 'Export sits once, in the toolbar\'s `actions`';
    }

    public function detect(): string
    {
        return 'A button in the table\'s `checked` slot whose text (case and spaces folded) is also a button\'s in the `actions` or `end` slot of that table\'s toolbar: one inside the table\'s `header` slot, or the one just before the table in the same parent. A button with a text that is not known (an echo) is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('table') as $table) {
            $bulk = $this->buttons($outline, $table, ['atom:table'], ['checked']);
            $toolbar = $this->toolbarLabels($outline, $table);

            foreach ($bulk as $button) {
                $label = $this->label($outline, $button);

                if ($label !== null && in_array($label, $toolbar, true)) {
                    yield [$button['offset'], '"'.$outline->label($button).'" is in the toolbar\'s actions too'];
                }
            }
        }
    }

    /**
     * The labels of the buttons in the toolbar of a table
     *
     * @return array<int, string>
     */
    protected function toolbarLabels(BladeOutline $outline, array $table): array
    {
        $labels = [];

        foreach ($outline->atom('table.toolbar') as $toolbar) {
            $owner = $this->tableOf($outline, $toolbar);

            if ($owner === null || $owner['id'] !== $table['id']) {
                continue;
            }

            foreach ($this->buttons($outline, $toolbar, ['atom:table.toolbar'], ['actions', 'end']) as $button) {
                $label = $this->label($outline, $button);

                if ($label !== null) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * The table a toolbar steers: the one around it, else the first table after it in the same parent
     */
    protected function tableOf(BladeOutline $outline, array $toolbar): ?array
    {
        if (($around = $outline->inside($toolbar, 'atom:table')) !== null) {
            return $around;
        }

        foreach ($outline->atom('table') as $table) {
            if ($table['offset'] > $toolbar['offset'] && $table['parent'] === $toolbar['parent']) {
                return $table;
            }
        }

        return null;
    }

    /**
     * The buttons in an owner's slots with these names: the slot's own, or inside a button group
     *
     * @param  array<int, string>  $owners
     * @param  array<int, string>  $names
     * @return array<int, array>
     */
    protected function buttons(BladeOutline $outline, array $owner, array $owners, array $names): array
    {
        $buttons = [];

        foreach ($this->slots($outline, $owners, $names) as $slot) {
            if (($outline->owner($slot)['id'] ?? null) !== $owner['id']) {
                continue;
            }

            foreach ($outline->descendants($slot) as $node) {
                if ($node['tag'] === 'atom:button' && $this->reaches($outline, $node, $slot, ['atom:button.group'])) {
                    $buttons[] = $node;
                }
            }
        }

        return $buttons;
    }

    /**
     * A button's text (the outline has its spaces folded) in lower case, or null when it is not known or is empty
     */
    protected function label(BladeOutline $outline, array $button): ?string
    {
        $label = $outline->label($button);
        $label = $label === null ? '' : mb_strtolower($label);

        return $label === '' ? null : $label;
    }
}
