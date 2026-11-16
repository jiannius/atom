<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class CreateVerb extends CopyFormsRule
{
    public const ID = 'create-verb';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Copy and state';

    public function title(): string
    {
        return 'A new record made with "New" or "Add"';
    }

    public function fix(): string
    {
        return '"Create …" makes a new record ("Create invoice", the list header\'s `<atom:button variant="primary">`); "Add …" adds a line or a child inside a form or a record ("Add line").';
    }

    public function rule(): string
    {
        return '"Create …" makes a new record';
    }

    public function detect(): string
    {
        return 'The literal label of an `<atom:button>` or `<atom:link>` that starts with "New " and wears `icon="add"` (or `plus`) or `variant="primary"`, or sits in a slot of `<atom:page.header>`, in `<atom:table.toolbar>`, in the `toolbar` or `actions` slot of `<atom:panel>`, in the `actions` slot of the shell (`<atom:layouts.*>`) or in `<atom:empty>`; and a button that starts with "Add " in the header\'s `primary` slot of a list view (a table with a `paginate` that is not `false`, or an `<atom:table.filters>`, `<atom:table.search>` or `<atom:table.toolbar>`). "Add line" in a form, a record or an empty state, "New message" or "New chat" on a plain button or link elsewhere, a menu item, and a label that is bound or holds an echo are not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $list = $this->isList($file);

        foreach ($outline->atom(['button', 'link']) as $node) {
            $label = $this->labelOf($outline, $node);

            if ($label === null) {
                continue;
            }

            if (preg_match('/^New\s/', $label) && $this->makesRecord($file, $node)) {
                yield [$node['offset'], '"'.$label.'": a new record is "Create …"'];
            } elseif ($list && $node['component'] === 'button' && preg_match('/^Add\s/', $label) && $this->isHeaderPrimary($file, $node)) {
                yield [$node['offset'], '"'.$label.'" in the list header\'s primary slot: a new record is "Create …"'];
            }
        }
    }

    /**
     * Whether a "New …" button or link makes a record: it wears the add icon or the primary variant, or sits where a record is
     * created (a page header slot, a table toolbar, a panel's toolbar or actions slot, the shell's actions slot, an empty state).
     * A plain button elsewhere ("New message", "New chat") is the app's own verb.
     */
    protected function makesRecord(File $file, array $node): bool
    {
        $outline = $file->outline();

        if (in_array($outline->value($node, 'icon'), ['add', 'plus'], true) || $outline->value($node, 'variant') === 'primary') {
            return true;
        }

        if ($outline->inside($node, ['atom:empty', 'atom:table.toolbar']) !== null) {
            return true;
        }

        $slot = $outline->slotOf($node);
        $owner = $slot['owner']['tag'] ?? null;

        return $owner === 'atom:page.header'
            || ($owner === 'atom:panel' && in_array($slot['name'], ['toolbar', 'actions'], true))
            || ($slot !== null && $slot['name'] === 'actions' && str_starts_with((string) $owner, 'atom:layouts.'));
    }

    /**
     * Whether the view is a list: a paginated table, or a table with filters, a search or a toolbar around it (a record's
     * lines table is not one)
     */
    protected function isList(File $file): bool
    {
        $outline = $file->outline();

        foreach ($outline->atom('table') as $table) {
            $paginate = $table['attributes'][':paginate'] ?? $table['attributes']['x-bind:paginate'] ?? null;

            if ($outline->has($table, 'paginate') && ! (is_string($paginate) && in_array(strtolower(trim($paginate)), ['false', 'null', '0', "''", '[]'], true))) {
                return true;
            }
        }

        return $outline->atom(['table.filters', 'table.search', 'table.toolbar']) !== [];
    }

    /**
     * Whether a node is the page header's primary action
     */
    protected function isHeaderPrimary(File $file, array $node): bool
    {
        $slot = $file->outline()->slotOf($node);

        return $slot !== null && $slot['name'] === 'primary' && ($slot['owner']['tag'] ?? null) === 'atom:page.header';
    }
}
