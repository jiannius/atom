<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class StatusAsBadge extends CopyFormsRule
{
    public const ID = 'status-as-badge';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Copy and state';

    /**
     * Where a status expression ends: `->status`, `?->state()`, `['stage']`, or a variable of that name
     */
    protected const STATUS_EXPRESSION = '/(?:(?:\?->|->)\s*(?:status|state|stage)\s*(?:\(\s*\))?|\[\s*([\'"])(?:status|state|stage)\1\s*\]|^\$(?:status|state|stage))\s*$/i';

    /**
     * The columns that hold a record's state
     *
     * @var array<int, string>
     */
    protected const COLUMNS = ['status', 'state'];

    public function title(): string
    {
        return 'A badge showing a workflow status';
    }

    public function fix(): string
    {
        return '`<atom:status :value="$invoice->status" />`: it takes the tone from the enum\'s `tone()` or `color()` and the word from its `label()`. A badge is for what classifies (a tier, a type, a tag, a count).';
    }

    public function rule(): string
    {
        return 'If it changes through the workflow, it is `<atom:status>`';
    }

    public function detect(): string
    {
        return 'An `<atom:badge>` (a) in the `status` slot of `<atom:page.header>`, (b) with a bound `:status` whose expression ends in `status`, `state` or `stage` (`->status`, `[\'status\']`, `$status`, `->status()`), or (c) inside an `<atom:table.cell column="status">` (or `state`). A type enum (`:status="$x->type"`) is the badge\'s own use and is not flagged; any other expression is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('badge') as $badge) {
            $note = $this->headerSlot($file, $badge) ?? $this->statusExpression($file, $badge) ?? $this->statusColumn($file, $badge);

            if ($note !== null) {
                yield [$badge['offset'], $note];
            }
        }
    }

    /**
     * (a) A badge that is the page header's status
     */
    protected function headerSlot(File $file, array $badge): ?string
    {
        $slot = $file->outline()->slotOf($badge);

        if ($slot === null || $slot['name'] !== 'status' || ! $slot['direct'] || ($slot['owner']['tag'] ?? null) !== 'atom:page.header') {
            return null;
        }

        return 'in the header\'s `status` slot: use `<atom:status :value>`';
    }

    /**
     * (b) A badge handed a state: `:status="$invoice->status"`
     */
    protected function statusExpression(File $file, array $badge): ?string
    {
        $expression = $badge['attributes'][':status'] ?? $badge['attributes']['x-bind:status'] ?? null;

        if (! is_string($expression) || ! preg_match(static::STATUS_EXPRESSION, trim($expression))) {
            return null;
        }

        return '`:status="'.trim($expression).'"` is a workflow state: use `<atom:status :value>`';
    }

    /**
     * (c) A badge in a status column
     */
    protected function statusColumn(File $file, array $badge): ?string
    {
        $outline = $file->outline();
        $cell = $outline->inside($badge, 'atom:table.cell');

        if ($cell === null || ! in_array($outline->value($cell, 'column'), static::COLUMNS, true)) {
            return null;
        }

        return 'in a `'.$outline->value($cell, 'column').'` column: use `<atom:status :value>`';
    }
}
