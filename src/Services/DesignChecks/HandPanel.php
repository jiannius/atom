<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class HandPanel extends BoxesColour
{
    public const ID = 'hand-panel';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The elements a hand-built box is made of
     *
     * @var array<int, string>
     */
    protected const BOXES = ['div', 'section', 'article', 'aside', 'ul', 'ol', 'dl', 'figure', 'fieldset', 'details', 'form'];

    /**
     * What a field's own shell holds: a box around one of these is a control, not a panel
     *
     * @var array<int, string>
     */
    protected const FIELDS = ['input', 'select', 'textarea'];

    public function title(): string
    {
        return 'A hand-built `rounded-panel border bg-surface` box';
    }

    public function fix(): string
    {
        return 'Use `<atom:card heading="...">` for a padded box (facts, a settings group) or `<atom:panel heading="..." count>` for a list or a section of lines (edge to edge; it clips).';
    }

    public function rule(): string
    {
        return 'no hand `rounded-panel border bg-surface` box';
    }

    public function detect(): string
    {
        return 'A `div`, `section`, `article`, `aside`, list, `figure`, `fieldset`, `details` or `form` whose own classes hold a panel rounding (`rounded-panel`, `rounded-md`, `-lg`, `-xl`, `-2xl`), a border on all sides (`border`, `border-2`) and a fill (`bg-surface`, `bg-surface-sunken`, `bg-white`), all without a variant. Not flagged: a link or a button (`raw-control`), `rounded-full`, a box a field sits straight in, a popover or dialog, an `animate-pulse` placeholder, a host component, an element with a class built from an echo, and the `brand` and `logo` slots.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            if ($node['kind'] !== 'html' || ! in_array($node['tag'], static::BOXES, true) || $this->declined($file, $node)) {
                continue;
            }

            $resting = $this->resting($file, $node);

            if (! $this->any($resting, '/^rounded-(?:panel|md|lg|xl|2xl)$/')
                || ! $this->any($resting, '/^border(?:-[1248])?$/')
                || ! $this->any($resting, '/^bg-(?:surface(?:-sunken)?|white)$/')
                || $this->inBrand($file, $node)) {
                continue;
            }

            yield [$node['offset'], $this->note($file, $node, $resting)];
        }
    }

    /**
     * Whether an element is something other than a box: a popover, a dialog, a placeholder or a field's shell
     */
    protected function declined(File $file, array $node): bool
    {
        $outline = $file->outline();

        if ($outline->has($node, 'popover') || $outline->has($node, 'role') || $this->any($this->resting($file, $node), '/^animate-pulse$/')) {
            return true;
        }

        foreach ($outline->children($node) as $child) {
            if (in_array($child['tag'], static::FIELDS, true) || ($child['kind'] === 'atom' && preg_match('/^(?:input|select|textarea|date-picker|time-picker|tiptap)(?:\.|$)/', (string) $child['component']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The note: a heading row inside it (R3) points at `<atom:panel>`, a padded box at `<atom:card>`
     *
     * @param  array<int, string>  $resting
     */
    protected function note(File $file, array $node, array $resting): string
    {
        $outline = $file->outline();

        foreach ($outline->children($node) as $child) {
            if ($child['kind'] === 'slot') {
                continue;
            }

            $row = $this->resting($file, $child);

            if ($this->any($row, '/^border-b(?:-[1248])?$/') && $this->any($row, '/^p[xytblr]?-(?!0$)/')) {
                return 'with a heading row: `<atom:panel heading>` draws it';
            }

            break;
        }

        return $this->any($resting, '/^p[xytrblse]?-(?!0$)/') ? 'a padded box: `<atom:card>`' : 'use `<atom:card>` or, for a list, `<atom:panel>`';
    }
}
