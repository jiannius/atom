<?php

namespace App\Enums;

enum Status: string
{
    case Paid = 'paid';
    case Draft = 'draft';
    case Void = 'void';

    public function colour(): string
    {
        return match ($this) {
            self::Paid => 'bg-green-100 text-green-800', // expect: palette-colour x2
            self::Draft => 'bg-surface-sunken text-fg-muted',
            self::Void => 'rounded-md px-2 text-red-600 dark:text-red-400', // expect: palette-colour x2
        };
    }

    public function dark(): string
    {
        return 'dark:bg-surface'; // expect: palette-colour
    }

    /**
     * bg-zinc-100 is what a comment says: comments are not read
     */
    public function label(): string
    {
        // text-red-500 in a line comment is not read either
        return 'shadow';
    }

    public function interpolated(string $tone): string
    {
        return "bg-{$tone}-100 text-red-500 {$tone}";
    }

    public function sentence(): string
    {
        return 'Use bg-zinc-100 for the paper';
    }

    public function words(): array
    {
        return ['white', 'black', 'zinc-100', 'text-zinc', 'bg-surface'];
    }
}
