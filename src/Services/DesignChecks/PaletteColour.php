<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PaletteColour extends BoxesColour
{
    public const ID = 'palette-colour';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The colour utilities: a prefix and then a colour
     */
    protected const PREFIXES = 'bg|text|border(?:-[trblxyse])?|ring(?:-offset)?|inset-ring|drop-shadow|text-shadow|divide|outline|fill|stroke|from|via|to|placeholder|decoration|accent|caret|shadow';

    /**
     * Tailwind's palette hues, which take a step
     */
    protected const HUES = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|mauve|olive|mist|taupe';

    /**
     * The start of atom's role and tone tokens: a `dark:` pair on one of these is as wrong as on a hue
     */
    protected const ROLES = 'surface|fg|canvas|accent|danger|warning|success|info|neutral|muted|border|control-border';

    public function title(): string
    {
        return 'A palette colour, or a `dark:` colour pair';
    }

    public function fix(): string
    {
        return 'Colour with role tokens (`bg-surface`, `bg-surface-sunken`, `text-fg`, `text-fg-muted`, `text-fg-subtle`, `border-border` and the tone tokens): they switch themes themselves, so there is no `dark:` pair. A state or a tag is `<atom:status>` or `<atom:badge>`, which take a tone.';
    }

    public function rule(): string
    {
        return 'no palette colours';
    }

    public function detect(): string
    {
        return 'In a Blade file, a class token (a `class`, `:class`, `x-bind:class` or `*-class` attribute, an `@class` argument, an echoed string among them) made of a colour utility prefix, a palette hue and a step, or white or black, with any variant; and any `dark:` colour utility (a hue, white, black, a role token or a colour in brackets). In a PHP file, the same in a string literal that reads as a class list, so a colour method on an enum is seen. One finding for each token. A hex colour in brackets is not flagged; an element in the `brand` or `logo` slot is left alone; a token built from an echo (`bg-{{ $c }}-100`) or a class set from `@php` is not seen. It runs on a view read as text only, too.';
    }

    /**
     * It reads one element's class tokens, never its place in the tree (the brand exemption is read when there is a tree)
     */
    public function structural(): bool
    {
        return false;
    }

    public function kinds(): array
    {
        return ['blade', 'php'];
    }

    public function find(File $file, Context $context): iterable
    {
        $matcher = fn (array $variants, string $utility, string $token): ?string => $this->flag($variants, $utility);

        yield from $file->isBlade() ? $this->scanClasses($file, $matcher) : $this->scanStrings($file, $matcher);
    }

    /**
     * The note for a token that is a palette colour or a `dark:` colour, or null
     *
     * @param  array<int, string>  $variants
     */
    protected function flag(array $variants, string $utility): ?string
    {
        $opacity = '(?:\/(?:\d+|\[[^\]]+\]))?';
        $paletteColour = '/^(?:'.static::PREFIXES.')-(?:(?:'.static::HUES.')-(?:50|[1-9]00|950)|white|black)'.$opacity.'$/';

        if (preg_match($paletteColour, $utility)) {
            return in_array('dark', $variants, true)
                ? '`'.implode(':', [...$variants, $utility]).'`: a palette colour in a `dark:` pair; a role token switches themes itself'
                : '`'.implode(':', [...$variants, $utility]).'`: a palette colour; use a role token';
        }

        if (! in_array('dark', $variants, true)) {
            return null;
        }

        $role = '/^(?:'.static::PREFIXES.')-(?:'.static::ROLES.')(?:-[a-z]+)*'.$opacity.'$/';
        $bracket = '/^(?:'.static::PREFIXES.')-\[(?:color:)?(?:#|rgba?\(|hsla?\(|oklch\()/';

        if (preg_match($role, $utility) || preg_match($bracket, $utility)) {
            return '`'.implode(':', [...$variants, $utility]).'`: a `dark:` colour; a role token switches themes itself';
        }

        return null;
    }
}
