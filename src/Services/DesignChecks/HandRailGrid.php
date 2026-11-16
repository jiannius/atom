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
        return 'An element in an `<atom:page>`, outside a card, panel, table, form, modal or description list, whose classes (static, or a literal in `:class`) hold a two-track `grid-cols-[...]` of a flexible track (`1fr` or `minmax(0,1fr)`) and a fixed one of 12rem or 192px and more, with any variant. A narrower fixed track (a column of figures) is not a rail. A grid built from a bound class, or a `grid-template-columns` style, is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            if (! $this->hasRailGrid($file, $node)) {
                continue;
            }

            if ($this->pageOf($file, $node) === null || $this->insideBox($file, $node)) {
                continue;
            }

            yield [$node['offset'], null];
        }
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
