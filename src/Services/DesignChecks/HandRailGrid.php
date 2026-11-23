<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class HandRailGrid extends PageRule
{
    public const ID = 'hand-rail-grid';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The smallest fixed track, in pixels, that reads as a rail: the page's own is 320, and a 120 or 170 pixel
     * track is a column of figures in a row
     */
    protected const MIN_TRACK = 192;

    /**
     * `grid-cols-[minmax(0,1fr)_20rem]` or `grid-cols-[1fr_320px]`, and the mirror of either, with the variants removed
     */
    protected const GRID = '/^grid-cols-\[(?:(?:minmax\(0,_?1fr\)|1fr)_(\d+(?:\.\d+)?)(px|rem)|(\d+(?:\.\d+)?)(px|rem)_(?:minmax\(0,_?1fr\)|1fr))\]$/';

    /**
     * The HTML elements that hold blocks: a rail grid arranges these, where a key and its value are inline text
     *
     * @var array<int, string>
     */
    protected const BLOCKS = ['div', 'section', 'aside', 'article', 'main', 'form', 'ul', 'ol', 'nav', 'header', 'footer', 'details', 'fieldset'];

    public function title(): string
    {
        return 'A hand-built grid for a page\'s rail';
    }

    public function fix(): string
    {
        return 'Use `<atom:page.columns>` with `<x-slot:rail label="...">`: a 320px rail beside the main column by the room there is, `sticky` if it should stay in view.';
    }

    public function rule(): string
    {
        return '`<atom:page.columns>` draws both';
    }

    public function detect(): string
    {
        return 'An element in an `<atom:page>`, outside a card, panel, table, form, modal or description list (a form around the whole page does not count, and a `<form>` that is itself the grid does), whose classes (static, or a literal in `:class`) hold a two-track `grid-cols-[...]` of a flexible track (`1fr` or `minmax(0,1fr)`) and a fixed one of 12rem or 192px and more, with any variant but one that sends it to the children (`*:`, `[&>div]:`), and that has a block among its children (a `div`, `section`, `aside`, an atom or host component: a key and its value in two `span`s is no rail). A narrower fixed track (a column of figures) is not a rail, and neither is a fixed first track whose first child, or a child of that, is a `<nav>`, `<atom:navlist>` or `<atom:tabs>` (a side menu: `hand-settings-nav` reports that grid instead). A grid built from a bound class, or a `grid-template-columns` style, is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        foreach ($this->grids($file) as [$node, $sideNav]) {
            if (! $sideNav) {
                yield [$node['offset'], null];
            }
        }
    }

    /**
     * The hand-built settings grids: the ones `find()` leaves to `hand-settings-nav` because the fixed track comes first and holds a
     * navigation (the offsets in `$file->text()`, one per grid)
     *
     * @return iterable<int, int>
     */
    public function settingsGrids(File $file): iterable
    {
        foreach ($this->grids($file) as [$node, $sideNav]) {
            if ($sideNav) {
                yield $node['offset'];
            }
        }
    }

    /**
     * The grids of a file that read as a hand-built rail layout (a page's two-track grid outside a box, holding a block), each with
     * whether its fixed track comes first and holds a navigation
     *
     * @return iterable<int, array{0: array, 1: bool}>
     */
    protected function grids(File $file): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            if (! $this->hasRailGrid($file, $node)) {
                continue;
            }

            $page = $this->pageOf($file, $node);

            if ($page === null || $this->isBoxItself($node) || $this->insideBox($file, $node, $page) || ! $this->hasBlockChild($file, $node)) {
                continue;
            }

            yield [$node, $this->hasSideNav($file, $node)];
        }
    }

    /**
     * Whether a tag is a box that arranges its own inside: any but a form, which may itself be the page's grid
     */
    protected function isBoxItself(array $node): bool
    {
        return $this->isBox($node) && ! in_array($node['tag'], ['form', 'atom:form'], true);
    }

    /**
     * Whether the fixed track comes first and its first child, or a child of that, is a navigation (a `<nav>`, `<atom:navlist>` or `<atom:tabs>`): a
     * side menu, which is not a rail but the `nav` slot of `<atom:page.columns>`. It is not left alone: `hand-settings-nav` reports such a grid
     * (`settingsGrids()`), so a hand-built settings layout is flagged once, under that id, and never silently
     */
    protected function hasSideNav(File $file, array $node): bool
    {
        $fixedFirst = false;

        foreach ($this->utilities($file, $node) as $utility) {
            if (preg_match('/^grid-cols-\[\d+(?:\.\d+)?(?:px|rem)_/', $utility)) {
                $fixedFirst = true;
            }
        }

        $outline = $file->outline();
        $first = $fixedFirst ? ($outline->children($node)[0] ?? null) : null;

        if ($first === null) {
            return false;
        }

        // the first child, or one level in (`<aside><nav>`, `<div><atom:navlist>`)
        foreach ([$first, ...$outline->children($first)] as $candidate) {
            if ($candidate['tag'] === 'nav' || in_array($candidate['component'] ?? null, ['navlist', 'tabs'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a tag holds a block: an element that holds blocks, or a component
     */
    protected function hasBlockChild(File $file, array $node): bool
    {
        foreach ($file->outline()->children($node) as $child) {
            if (in_array($child['kind'], ['atom', 'component'], true) || in_array($child['tag'], static::BLOCKS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a tag's classes hold the grid
     */
    protected function hasRailGrid(File $file, array $node): bool
    {
        foreach ($this->utilities($file, $node) as $utility) {
            if (! preg_match(static::GRID, $utility, $parts)) {
                continue;
            }

            $size = $parts[1] !== '' ? (float) $parts[1] : (float) $parts[3];
            $unit = $parts[1] !== '' ? $parts[2] : $parts[4];

            if ($size * ($unit === 'rem' ? 16 : 1) >= static::MIN_TRACK) {
                return true;
            }
        }

        return false;
    }
}
