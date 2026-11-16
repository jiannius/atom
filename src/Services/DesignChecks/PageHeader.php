<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PageHeader extends PageRule
{
    public const ID = 'page-header';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The HTML elements a hand title may sit in between it and the page: a row or a wrapper, never a box
     *
     * @var array<int, string>
     */
    protected const WRAPPERS = ['div', 'header', 'section', 'hgroup', 'main'];

    /**
     * What a partial or an echo in front of the first element may be rendering: the header could be there
     */
    protected const RENDERS = '/@(?:include\w*|livewire|each|component|embed|yield|stack)\b|\{\{|\{!!/';

    public function title(): string
    {
        return 'A page with no page header, or a hand-built page title';
    }

    public function fix(): string
    {
        return 'Open the page with `<atom:page.header title="...">`; its status, facts, actions, menu and primary are slots of it.';
    }

    public function rule(): string
    {
        return 'A screen is `<atom:page>` opening with `<atom:page.header>`';
    }

    public function detect(): string
    {
        return 'An `<atom:page>` in a file with no `<atom:page.header>`, whose first element is not a host component, a `<livewire:...>` tag, an `@include` or `@livewire` (any of which may draw the header), or a `{{ }}` echo; and, inside a page, an `<h1>` or an `<atom:heading>` with `size` `lg`, `xl` or a number of 18 or more, reached through plain `div`, `header` or `section` wrappers only (a heading in a card, panel, modal, form or any other component is not a page title). A page with a hand title is told about the title, not twice. A header drawn by a partial or a layout in another file is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $hasHeader = $outline->atom('page.header') !== [];
        $titled = [];

        foreach ($outline->nodes() as $node) {
            if (! $this->isTitleCandidate($file, $node)) {
                continue;
            }

            $page = $this->pageOf($file, $node);

            if ($page === null || ! $this->isHandTitle($file, $node, $page)) {
                continue;
            }

            $titled[$page['id']] = true;

            yield [$node['offset'], 'A hand-built page title: use `<atom:page.header title="...">`'];
        }

        if ($hasHeader) {
            return;
        }

        foreach ($outline->atom('page') as $page) {
            if (isset($titled[$page['id']]) || ! $this->opensWithoutHeader($file, $page)) {
                continue;
            }

            yield [$page['offset'], 'This page has no `<atom:page.header>`'];
        }
    }

    /**
     * An `<h1>`, or a heading drawn as big as one
     */
    protected function isTitleCandidate(File $file, array $node): bool
    {
        if ($node['tag'] === 'h1') {
            return true;
        }

        if ($node['tag'] !== 'atom:heading') {
            return false;
        }

        $size = $file->outline()->value($node, 'size');

        return $size !== null && (in_array($size, ['lg', 'xl'], true) || (ctype_digit($size) && (int) $size >= 18));
    }

    /**
     * Whether only plain wrappers (a `div`, `header` or `section`: not a card, a list item or an aside) lie between the title and its page
     */
    protected function isHandTitle(File $file, array $node, array $page): bool
    {
        foreach ($file->outline()->ancestors($node) as $ancestor) {
            if ($ancestor['id'] === $page['id']) {
                return true;
            }

            if (! in_array($ancestor['tag'], static::WRAPPERS, true) || $this->looksLikeBox($file, $ancestor)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Whether a wrapper is dressed as a box (a hand card holds its own heading)
     */
    protected function looksLikeBox(File $file, array $node): bool
    {
        foreach ($this->utilities($file, $node) as $utility) {
            if (str_starts_with($utility, 'rounded') || str_starts_with($utility, 'shadow') || str_starts_with($utility, 'ring') || str_starts_with($utility, 'bg-surface')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a page opens with something other than a header, and nothing in front of it may be drawing one
     */
    protected function opensWithoutHeader(File $file, array $page): bool
    {
        $first = $file->outline()->children($page)[0] ?? null;

        if ($first === null) {
            return false;
        }

        if ($first['kind'] === 'component' || $first['kind'] === 'slot' || str_starts_with($first['tag'], 'livewire:')) {
            return false;
        }

        $before = substr($file->text(), $page['openEnd'], $first['offset'] - $page['openEnd']);

        return ! preg_match(static::RENDERS, $before);
    }
}
