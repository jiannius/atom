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
        return 'A hand-built settings nav: a selector into the navlist or a gutter override';
    }

    public function fix(): string
    {
        return 'Use `<atom:page.columns>` with `<x-slot:nav><atom:navlist>...</atom:navlist></x-slot:nav>`: a 200px column beside the forms, a sideways strip on a phone, and a save bar that spans both. Write no selector into `[data-atom-navlist-*]` and no `--atom-gutter-s` override.';
    }

    public function rule(): string
    {
        return 'A settings screen is a nav column beside the forms';
    }

    public function detect(): string
    {
        return 'A class token with an arbitrary variant or selector that names `[data-atom-navlist` (`[&_[data-atom-navlist-group-label]]:hidden`, `max-md:[&_[data-atom-navlist-item]]:w-auto`, `[&>[data-atom-navlist]]:flex-row`), or a `--atom-gutter-s` / `--atom-gutter-e` override (`[--atom-gutter-s:0px]`, `lg:[--atom-gutter-s:1rem]`), with any variant, in a class attribute of a Blade file. A style attribute, a stylesheet and a class set from `@php` are not read; an element in the `brand` or `logo` slot is left alone. It runs on a view read as text only, too.';
    }

    /**
     * It reads one element's class tokens, never its place in the tree (the brand exemption is read when there is a tree)
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
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
