<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class HandCrumbs extends PageRule
{
    public const ID = 'hand-crumbs';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The icons that point right, which a hand trail puts between two crumbs
     *
     * @var array<int, string>
     */
    protected const ARROWS = ['icon.right', 'icon.arrow-right'];

    /**
     * Text between two crumbs: a chevron (arrow) or a slash
     *
     * @var array<string, string>
     */
    protected const GLYPHS = ['›' => 'arrow', '»' => 'arrow', '&rsaquo;' => 'arrow', '&raquo;' => 'arrow', '&#8250;' => 'arrow', '&#187;' => 'arrow', '/' => 'slash'];

    public function title(): string
    {
        return 'A hand-built breadcrumb trail';
    }

    public function fix(): string
    {
        return 'Use `<atom:breadcrumbs :items="[...]" />` in the shell\'s `crumbs` slot for a fixed trail, `<atom:breadcrumbs />` inside `@persist(\'breadcrumbs\')` for the trail the component builds, or `<atom:page.header :crumbs>`: one of them.';
    }

    public function rule(): string
    {
        return 'Never hand-build a title, crumb strip, sidebar toggle';
    }

    public function detect(): string
    {
        return 'A `<nav>` whose static `aria-label` has "breadcrumb" in it (a `t()` or `__()` literal counts) and that holds no `<atom:breadcrumbs>`; or, as siblings of one parent, two links (`<a>`, `<atom:link>`) with a chevron between them (an `<atom:icon.right>` or `<atom:icon.arrow-right>`, or a `›` or `»`), or three or more with a `/` between them (two links and a `/` is a footer\'s list). An arrow inside a link\'s own text is not seen. Atom\'s own header, which draws such a `nav`, is a published view and is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $navs = [];

        foreach ($outline->nodes() as $node) {
            if ($node['tag'] === 'nav' && $this->isBreadcrumbNav($file, $node)) {
                $navs[$node['id']] = true;

                yield [$node['offset'], 'a `<nav>` labelled as a breadcrumb'];
            }
        }

        $groups = [];

        foreach ($outline->nodes() as $node) {
            $groups[$node['parent'] ?? -1][] = $node;
        }

        foreach ($groups as $parent => $siblings) {
            if ($parent !== -1 && $this->insideTrail($file, $outline->node($parent), $navs)) {
                continue;
            }

            foreach ($this->chains($file, $siblings) as $first) {
                yield [$first['offset'], 'links with a separator between them'];
            }
        }
    }

    /**
     * Whether a tag is, or is inside, a nav that is already a finding
     */
    protected function insideTrail(File $file, array $node, array $navs): bool
    {
        foreach ([$node, ...$file->outline()->ancestors($node)] as $each) {
            if (isset($navs[$each['id']])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a `<nav>` is labelled a breadcrumb and does not hold atom's trail
     */
    protected function isBreadcrumbNav(File $file, array $nav): bool
    {
        $label = $nav['attributes']['aria-label'] ?? null;

        if (! is_string($label)) {
            return false;
        }

        if (str_contains($label, '{{') || str_contains($label, '{!!')) {
            preg_match_all('/\b(?:t|__|trans)\(\s*([\'"])(.*?)\1/', $label, $found);

            $label = implode(' ', $found[2]);
        }

        if (stripos($label, 'breadcrumb') === false) {
            return false;
        }

        foreach ($file->outline()->descendants($nav) as $inside) {
            if ($inside['tag'] === 'atom:breadcrumbs') {
                return false;
            }
        }

        return $nav['close'] !== null;
    }

    /**
     * The first link of each run of links with a separator between, among siblings
     *
     * @param  array<int, array>  $siblings
     * @return array<int, array>
     */
    protected function chains(File $file, array $siblings): array
    {
        $found = [];
        $count = count($siblings);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isLink($siblings[$i])) {
                continue;
            }

            $links = 1;
            $types = [];
            $at = $i;

            while (($next = $this->separated($file, $siblings, $at)) !== null) {
                $links++;
                $types[$next[1]] = true;
                $at = $next[0];
            }

            if ($links > 1 && (isset($types['arrow']) || $links >= 3)) {
                $found[] = $siblings[$i];
            }

            $i = $at;
        }

        return $found;
    }

    /**
     * Where the next link is, and what separates it, if a separator lies between the link at `$at` and a link after it
     *
     * @param  array<int, array>  $siblings
     * @return array{0: int, 1: string}|null
     */
    protected function separated(File $file, array $siblings, int $at): ?array
    {
        $text = $file->text();
        $link = $siblings[$at];
        $after = $siblings[$at + 1] ?? null;

        if ($after === null) {
            return null;
        }

        if ($this->isLink($after)) {
            $type = static::GLYPHS[trim(str_replace("\u{a0}", ' ', substr($text, $link['end'], $after['offset'] - $link['end'])))] ?? null;

            return $type === null ? null : [$at + 1, $type];
        }

        $then = $siblings[$at + 2] ?? null;
        $type = $then === null ? null : $this->separatorType($file, $after);

        if ($type === null || ! $this->isLink($then)) {
            return null;
        }

        if (trim(substr($text, $link['end'], $after['offset'] - $link['end'])) !== '' || trim(substr($text, $after['end'], $then['offset'] - $after['end'])) !== '') {
            return null;
        }

        return [$at + 2, $type];
    }

    /**
     * Whether a tag is a link
     */
    protected function isLink(array $node): bool
    {
        return $node['tag'] === 'a' || $node['tag'] === 'atom:link';
    }

    /**
     * What a tag is as a separator: `arrow` or `slash`, or null when it is not one
     */
    protected function separatorType(File $file, array $node): ?string
    {
        $outline = $file->outline();

        if (in_array($node['component'], static::ARROWS, true)) {
            return 'arrow';
        }

        if (! in_array($node['tag'], ['span', 'li', 'div', 'i'], true) || $node['close'] === null) {
            return null;
        }

        $children = $outline->children($node);

        if ($children === []) {
            $label = $outline->label($node);

            return $label === null ? null : (static::GLYPHS[trim(str_replace("\u{a0}", ' ', $label))] ?? null);
        }

        return count($children) === 1 && in_array($children[0]['component'], static::ARROWS, true) && $outline->label($node) === '' ? 'arrow' : null;
    }
}
