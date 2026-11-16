<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class RawControl extends BoxesColour
{
    public const ID = 'raw-control';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * What a control does not hold: a link or button around one of these is a tile, not a control
     *
     * @var array<int, string>
     */
    protected const BLOCKS = ['div', 'p', 'section', 'article', 'ul', 'ol', 'table', 'figure', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'atom:card', 'atom:panel'];

    public function title(): string
    {
        return 'A hand-styled button or link';
    }

    public function fix(): string
    {
        return 'Use `<atom:button>` (`variant="ghost"`; `href` for a link that looks like a button) or `<atom:link>` for a text link.';
    }

    public function rule(): string
    {
        return 'Buttons are three tiers';
    }

    public function detect(): string
    {
        return 'A raw `<button>` or `<a>` whose own classes (no variant) hold a `rounded*` token, a border width or a `bg-*` fill, and a padding or `h-<n>` token. A text-only link is not flagged; neither is one that holds a block, a picture (`img`, `atom:avatar`) beside text or another element, or several elements besides an icon (a tile or a row), has a `role` other than button or link, shows a state of its own (`aria-pressed`, `aria-selected`, `aria-current`, `aria-checked`, or a bound class that names a selected, active, current, checked or pressed look or compares a value), or sits in the `brand` or `logo` slot.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            if ($node['kind'] !== 'html' || ! in_array($node['tag'], ['a', 'button'], true)) {
                continue;
            }

            $resting = $this->resting($file, $node);

            if (! $this->any($resting, '/^rounded(?!-none$)/')
                || ! ($this->any($resting, '/^border(?:-[trblxyse])?(?:-[1248])?$/') || $this->any($resting, '/^bg-(?!transparent$|inherit$|current$|none$|clip-|cover$|contain$|center$|top$|bottom$|left$|right$|repeat$|no-repeat$|fixed$|local$|scroll$|origin-|blend-|gradient-|linear-|radial(?:-|$)|conic(?:-|$)|size-|position-|auto$)/'))
                || ! ($this->any($resting, '/^p[xytrblse]?-(?!0$)/') || $this->any($resting, '/^h-(?:\d|\[)/'))
                || $this->declined($file, $node)
                || $this->inBrand($file, $node)) {
                continue;
            }

            yield [$node['offset'], 'a hand-styled `<'.$node['tag'].'>`: use `<atom:button>`'];
        }
    }

    /**
     * Whether a control shows a state of its own: an `aria-pressed`, `aria-selected`, `aria-current` or `aria-checked` (bound or
     * not), or a bound class (`:class`, `x-bind:class`, `@class([...])`) that names a selected, active, current, checked or pressed
     * look or compares a value (`$type === 'a'`). A pill, a tile or a segment that marks the chosen one is a toggle, which
     * `<atom:button>` is not.
     */
    protected function reflectsSelection(array $node): bool
    {
        $expressions = [];

        foreach ($node['attributes'] as $name => $value) {
            if (preg_match('/^(?::|x-bind:)?aria-(?:pressed|selected|current|checked)$/', (string) $name)) {
                return true;
            }

            if (is_string($value) && in_array($name, [':class', 'x-bind:class'], true)) {
                $expressions[] = $value;
            }
        }

        foreach ($node['directives'] as $directive) {
            if ($directive['name'] === 'class' && $directive['args'] !== null) {
                $expressions[] = $directive['args'];
            }
        }

        foreach ($expressions as $expression) {
            if (preg_match('/\b(?:selected|active|current|checked|pressed)\b|[=!]==?/i', $expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an element is not a plain control: another role, or a block inside (a tile)
     */
    protected function declined(File $file, array $node): bool
    {
        $outline = $file->outline();
        $role = $outline->has($node, 'role') ? $outline->value($node, 'role') : 'button';

        if (! in_array($role, ['button', 'link'], true) || $this->reflectsSelection($node)) {
            return true;
        }

        $pictures = false;

        foreach ($outline->descendants($node) as $inside) {
            if (in_array($inside['tag'], static::BLOCKS, true)) {
                return true;
            }

            $pictures = $pictures || in_array($inside['tag'], ['img', 'atom:avatar'], true);
        }

        // a glyph (`svg`, `atom:icon.*`) beside the label is a button's own icon; a picture beside text, or several
        // other elements, is a row or a tile
        $others = array_filter($outline->children($node), fn ($child) => $child['tag'] !== 'svg' && ! ($child['kind'] === 'atom' && str_starts_with((string) $child['component'], 'icon.')));

        return count($others) > 1 || ($pictures && ($outline->label($node) !== '' || count($outline->children($node)) > 1));
    }
}
