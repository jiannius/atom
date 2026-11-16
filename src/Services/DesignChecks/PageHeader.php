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
        return 'An `<atom:page>` in a file with no `<atom:page.header>` that opens with something that does not draw one: nothing in front of its first element, or in it up to its first atom tag, is a host component, a `<livewire:...>` tag, an `@include`, `@livewire`, `@yield` or `@stack`, or a `{{ }}` echo (a host component further down, past an atom tag, does not count) (so `<div>{{ $slot }}</div>` is left alone); and, inside a page, an `<h1>` or an `<atom:heading level="1">` (with no header in the file also an `<atom:heading>` of `size` `lg`, `xl` or 18 and more: with one, a smaller heading is a section\'s), reached through plain `div`, `header`, `section`, `hgroup` or `main` wrappers only (a heading in a card, panel, modal, form, list item, aside or any other component is not a page title), none of them hidden, fixed or absolute, centred (an empty state), or carrying a `role` or `x-show` (an overlay), and not dressed as a box (`rounded`, `shadow`, `ring`, `border`, any `bg-`). A page with a hand title is told about the title, not twice. A header drawn by a partial or a layout in another file is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $hasHeader = $outline->atom('page.header') !== [];
        $titled = [];

        foreach ($outline->nodes() as $node) {
            if ($node['tag'] !== 'h1' && $node['tag'] !== 'atom:heading') {
                continue;
            }

            $page = $this->pageOf($file, $node);

            if ($page === null || ! $this->isTitleCandidate($file, $node, $hasHeader) || ! $this->isHandTitle($file, $node, $page)) {
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
     * An `<h1>`, or a heading drawn as one: level 1 and, on a page with no header, as big as one
     */
    protected function isTitleCandidate(File $file, array $node, bool $hasHeader): bool
    {
        if ($node['tag'] === 'h1') {
            return true;
        }

        $outline = $file->outline();

        if ($outline->value($node, 'level') === '1') {
            return true;
        }

        $size = $outline->value($node, 'size');

        return ! $hasHeader && $size !== null && (in_array($size, ['lg', 'xl'], true) || (ctype_digit($size) && (int) $size >= 18));
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

            if (! in_array($ancestor['tag'], static::WRAPPERS, true) || $this->looksLikeBox($file, $ancestor) || $this->looksLikeOverlay($file, $ancestor)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Whether a wrapper is an overlay, hidden, or a centred empty state: what it holds is not the page's title
     */
    protected function looksLikeOverlay(File $file, array $node): bool
    {
        $outline = $file->outline();

        if ($outline->has($node, 'role') || $outline->has($node, 'x-show') || $outline->has($node, 'hidden') || $outline->has($node, 'aria-modal')) {
            return true;
        }

        foreach ($this->utilities($file, $node) as $utility) {
            if (in_array($utility, ['fixed', 'absolute', 'hidden', 'text-center'], true) || str_starts_with($utility, 'inset-') || str_starts_with($utility, 'z-')) {
                return true;
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
            if (str_starts_with($utility, 'rounded') || str_starts_with($utility, 'shadow') || str_starts_with($utility, 'ring') || str_starts_with($utility, 'bg-') || $utility === 'border') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a page opens with something other than a header, and nothing in front of it, or in its first element, may be drawing one
     */
    protected function opensWithoutHeader(File $file, array $page): bool
    {
        $outline = $file->outline();
        $first = $outline->children($page)[0] ?? null;

        if ($first === null || $first['kind'] === 'component' || $first['kind'] === 'slot') {
            return false;
        }

        $until = $first['offset'];

        if ($first['kind'] === 'html') {
            $until = $first['end'];

            foreach ($outline->descendants($first) as $inside) {
                if ($inside['kind'] === 'atom') {
                    $until = $inside['offset'];

                    break;
                }

                if ($inside['kind'] === 'component') {
                    return false;
                }
            }
        }

        return ! preg_match(static::RENDERS, substr($file->text(), $page['openEnd'], $until - $page['openEnd']));
    }
}
