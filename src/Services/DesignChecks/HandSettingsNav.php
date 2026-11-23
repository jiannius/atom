<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class HandSettingsNav extends BoxesColour
{
    public const ID = 'hand-settings-nav';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Page and header';

    public function title(): string
    {
        return 'A hand-built settings nav: a grid with a nav as its fixed first track, a selector into the navlist or a gutter override';
    }

    public function fix(): string
    {
        return 'Review it: if this is a settings screen, use `<atom:page.columns>` with `<x-slot:nav><atom:navlist>...</atom:navlist></x-slot:nav>`: a 200px column beside the forms, a sideways strip on a phone, and a save bar that spans both, with no grid of your own, no selector into `[data-atom-navlist-*]` and no `--atom-gutter-s` override. A navlist selector or a gutter override that is there for another reason, or a side menu that is not a settings layout, is fine: ignore it (`atom:design-check ignore hand-settings-nav`).';
    }

    public function rule(): string
    {
        return 'A settings screen is a nav column beside the forms';
    }

    public function detect(): string
    {
        return 'A class token with an arbitrary variant or selector that names `[data-atom-navlist` (`[&_[data-atom-navlist-group-label]]:hidden`, `max-md:[&_[data-atom-navlist-item]]:w-auto`, `[&>[data-atom-navlist]]:flex-row`), or a `--atom-gutter-s` / `--atom-gutter-e` override (`[--atom-gutter-s:0px]`, `lg:[--atom-gutter-s:1rem]`), with any variant, in a class attribute of a Blade file; and, in a view whose atom tags balance, the plain hand-built settings grid that `hand-rail-grid` leaves alone: an element in an `<atom:page>`, outside a card, panel, table, modal or description list, with a two-track `grid-cols-[...]` of a fixed first track of 12rem or 192px and more and a flexible one, whose first child, or a child of that, is a `<nav>`, `<atom:navlist>` or `<atom:tabs>`. It is a prompt to look, not proof: a navlist selector or a gutter override can have another reason, and a side menu in a grid is not always a settings screen. A style attribute, a stylesheet and a class set from `@php` are not read; an element in the `brand` or `logo` slot is left alone (for the tokens).';
    }

    /**
     * The class tokens are read one element at a time, never by their place in the tree (the brand exemption is read when there is a tree);
     * the grid is read from the tree, and only when the view balances (see `find()`)
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
        if ($file->structural()) {
            foreach ((new HandRailGrid)->settingsGrids($file) as $offset) {
                yield [$offset, 'a grid with a navigation as its fixed first track (a hand-built settings layout?)'];
            }
        }

        yield from $this->scanClasses($file, function (array $variants, string $utility, string $token): ?string {
            if (str_contains($token, '[data-atom-navlist')) {
                return '`'.$token.'` (a selector into the navlist\'s internals)';
            }

            if (preg_match('/^\[--atom-gutter-[se]:/', $utility)) {
                return '`'.$token.'` (a gutter override)';
            }

            return null;
        });
    }
}
