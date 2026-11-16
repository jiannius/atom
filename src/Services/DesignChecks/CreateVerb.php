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
        return 'The literal label of an `<atom:button>` that starts with "New " in the `primary` slot of `<atom:page.header>` or inside an `<atom:empty>`, and one that starts with "Add " in that header slot of a list view (one with a paginated `<atom:table>`, or an `<atom:table.filters>`, `<atom:table.search>` or `<atom:table.toolbar>`). "Add line" in a form, a record or an empty state, "New message" or "New chat" on any other button or menu item, and a label that is bound or holds an echo are not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $list = $this->isList($file);

        foreach ($outline->atom('button') as $node) {
            $label = $this->labelOf($outline, $node);

            if ($label === null) {
                continue;
            }

            if (preg_match('/^New\s/', $label) && ($this->isHeaderPrimary($file, $node) || $outline->inside($node, 'atom:empty') !== null)) {
                yield [$node['offset'], '"'.$label.'": a new record is "Create …"'];
            } elseif ($list && preg_match('/^Add\s/', $label) && $this->isHeaderPrimary($file, $node)) {
                yield [$node['offset'], '"'.$label.'" in the list header\'s primary slot: a new record is "Create …"'];
            }
        }
    }

    /**
     * Whether the view is a list: a paginated table, or a table with filters, a search or a toolbar around it (a record's
     * lines table is not one)
     */
    protected function isList(File $file): bool
    {
        $outline = $file->outline();

        foreach ($outline->atom('table') as $table) {
            if ($outline->has($table, 'paginate')) {
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
