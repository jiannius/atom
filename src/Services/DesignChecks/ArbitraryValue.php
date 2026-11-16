<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ArbitraryValue extends BoxesColour
{
    public const ID = 'arbitrary-value';

    public const SEVERITY = DesignCheck::INFO;

    public function title(): string
    {
        return 'An arbitrary gap, padding or font size';
    }

    public function fix(): string
    {
        return 'Spacing and type come from atom\'s components and Tailwind\'s scale (`gap-4`, `p-3`, `text-sm`); a side rail is `<atom:page.columns>`.';
    }

    public function rule(): string
    {
        return 'no arbitrary `gap-[..]`, `p-[..]` or `text-[..px]`';
    }

    public function detect(): string
    {
        return 'A class token `gap-[...]`, `gap-x-[...]`, `gap-y-[...]`, `p-[...]` (or `px`, `py`, `pt`, `pr`, `pb`, `pl`, `ps`, `pe`) or `text-[<n>px]` / `text-[<n>rem]`, with any variant, in a class attribute of a Blade file. A value that reads a variable (`var(...)`, `env(...)`, `--x`) is not flagged, nor is a width (`w-[28rem]`); an element in the `brand` or `logo` slot is left alone.';
    }

    public function find(File $file, Context $context): iterable
    {
        yield from $this->scanClasses($file, function (array $variants, string $utility, string $token): ?string {
            if (preg_match('/^(?:gap(?:-[xy])?|p[xytrblse]?)-\[([^\]]+)\]$/', $utility, $found)) {
                return preg_match('/^--|var\(|env\(|theme\(/', $found[1]) ? null : '`'.$token.'`';
            }

            return preg_match('/^text-\[(?:\d+|\d*\.\d+)(?:px|rem)\]$/', $utility) ? '`'.$token.'`' : null;
        });
    }
}
