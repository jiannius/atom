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
    protected const BOXES = ['div', 'section', 'article', 'aside', 'nav', 'header', 'ul', 'ol', 'dl', 'figure', 'fieldset', 'details', 'form'];

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
        return 'A `div`, `section`, `article`, `aside`, `nav`, `header`, list, `figure`, `fieldset`, `details` or `form` whose own classes hold a panel rounding (`rounded-panel`, `rounded-md`, `-lg`, `-xl`, `-2xl`), a border on all sides (`border`, `border-2`) and a fill (`bg-surface`, `bg-surface-sunken`, `bg-white`, with or without an opacity), all without a variant. Not flagged: a link or a button (`raw-control`), `rounded-full`, a small shell around one field (no 16px padding, at most three elements, one field, directly or in one wrapper: a field shell, a labelled checkbox), a dialog, a `border-dashed` or `border-dotted` box (a dropzone), an `inline-*` or `w-fit` box, an Alpine popover (`x-show`, `x-anchor` or `x-transition` on an absolutely or fixed positioned box), an `animate-pulse` placeholder, a keycap, chip or one-line control (`h-1` to `h-12`, `size-1` to `size-12`), a bar that shows on hover (absolute or fixed with `opacity-0`), a host component, and the `brand` and `logo` slots. A class token written with an echo (`bg-{{ $c }}`) is not counted, so a box whose fill comes from one is not flagged; a `bg-surface/50` fill is.';
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
                || ! $this->any($resting, '/^bg-(?:surface(?:-sunken)?|white)(?:\/\d+)?$/')
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

        $resting = $this->resting($file, $node);

        if ($outline->has($node, 'popover') || $outline->has($node, 'role') || $this->any($resting, '/^(?:animate-pulse|border-(?:dashed|dotted)|inline(?:-[a-z]+)?|w-fit)$/')) {
            return true;
        }

        // a keycap, a chip or a one-line control (a fixed height of 3rem or less), and a bar that only shows on hover
        if ($this->any($resting, '/^(?:h|size)-(?:[1-9]|1[0-2])$/') || ($this->any($resting, '/^(?:absolute|fixed)$/') && $this->any($resting, '/^opacity-0$/'))) {
            return true;
        }

        // an Alpine popover or dropdown panel: shown and positioned by the page, not a box in the flow
        foreach (array_keys($node['attributes']) as $name) {
            if (preg_match('/^x-(?:show|anchor|transition)/', (string) $name) && $this->any($resting, '/^(?:absolute|fixed)$/')) {
                return true;
            }
        }

        return $this->isFieldShell($file, $node, $resting);
    }

    /**
     * Whether a box is a small shell around one field: no padding of 16px or more, one to three elements, exactly one
     * field among them (directly or in one wrapper that holds nothing else but a glyph), and nothing else but glyphs and
     * buttons beside it. A box with a heading row, a padded body or a wrapper around several fields is a panel.
     *
     * @param  array<int, string>  $resting
     */
    protected function isFieldShell(File $file, array $node, array $resting): bool
    {
        $outline = $file->outline();
        $children = array_values(array_filter($outline->children($node), fn ($child) => $child['kind'] !== 'slot'));

        if (count($children) > 3 || $this->any($resting, '/^p[xy]?-(?:[4-9]|\d{2,})$/')) {
            return false;
        }

        $fields = 0;

        foreach ($children as $child) {
            if ($this->isField($child)) {
                $fields++;
            } elseif ($this->isAdornment($child)) {
                continue;
            } elseif (in_array($child['tag'], ['label', 'span', 'div'], true)) {
                $inside = $outline->children($child);

                if ($inside === [] && $child['tag'] === 'span') {
                    continue;
                }

                if ($inside === [] || ($child['tag'] === 'div' && count($inside) !== 1)) {
                    return false;
                }

                foreach ($inside as $inner) {
                    if ($this->isField($inner)) {
                        $fields++;
                    } elseif (! $this->isAdornment($inner)) {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }

        return $fields === 1;
    }

    /**
     * Whether a tag is a glyph or a small button beside a field: an icon, an `svg`, a button
     */
    protected function isAdornment(array $node): bool
    {
        return in_array($node['tag'], ['svg', 'button', 'atom:button'], true) || ($node['kind'] === 'atom' && str_starts_with((string) $node['component'], 'icon.'));
    }

    /**
     * Whether a tag is a field
     */
    protected function isField(array $node): bool
    {
        return in_array($node['tag'], static::FIELDS, true)
            || ($node['kind'] === 'atom' && preg_match('/^(?:input|select|textarea|checkbox|radio|toggle|date-picker|time-picker|tiptap)(?:\.|$)/', (string) $node['component']));
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
