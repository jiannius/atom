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
        return 'The literal label of an `<atom:button>` or `<atom:menu.item>` that starts with "New ", and one that starts with "Add " in the `primary` slot of `<atom:page.header>` in a view that also has an `<atom:table>` (a list creating a record). "Add line" or "Add part" in a form, a record or an empty state is not read, and neither is a label that is bound or holds an echo.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $list = $outline->atom('table') !== [];

        foreach ($outline->atom(['button', 'menu.item']) as $node) {
            $label = $this->labelOf($outline, $node);

            if ($label === null) {
                continue;
            }

            if (preg_match('/^New\s/', $label)) {
                yield [$node['offset'], '"'.$label.'": a new record is "Create …"'];
            } elseif ($list && preg_match('/^Add\s/', $label) && $this->isHeaderPrimary($file, $node)) {
                yield [$node['offset'], '"'.$label.'" in the list header\'s primary slot: a new record is "Create …"'];
            }
        }
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
