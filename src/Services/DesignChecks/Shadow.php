<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class Shadow extends BoxesColour
{
    public const ID = 'shadow';

    public const SEVERITY = DesignCheck::REVIEW;

    public function title(): string
    {
        return 'A box shadow';
    }

    public function fix(): string
    {
        return 'No shadow: a box is `<atom:card>` or `<atom:panel>` (a fill and a hairline). Only the raised layer (`<atom:modal>`, `<atom:menu>`, `<atom:toast>`) casts one, and it brings its own.';
    }

    public function rule(): string
    {
        return 'no `shadow-*`';
    }

    public function detect(): string
    {
        return 'A class token `shadow`, `shadow-2xs` through `shadow-2xl`, `shadow-inner` or `shadow-[...]`, with any variant, in a class attribute of a Blade file. `shadow-none`, atom\'s `shadow-pop` and a shadow colour are not flagged; an element in the `brand` or `logo` slot is left alone; a class set from `@php` is not seen. It runs on a view read as text only, too.';
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
            if (preg_match('/^shadow(?:-(?:2xs|xs|sm|md|lg|xl|2xl|inner))?$|^shadow-\[/', $utility)) {
                return '`'.$token.'`';
            }

            return null;
        });
    }
}
