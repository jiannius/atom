<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class HandCardTitle extends BoxesColour
{
    public const ID = 'hand-card-title';

    public const SEVERITY = DesignCheck::INFO;

    public function title(): string
    {
        return 'A card or panel titled by a hand-built heading';
    }

    public function fix(): string
    {
        return 'Pass `heading="..."` to `<atom:card>` or `<atom:panel>`; one link goes in its `actions` slot and the rest in its `menu` slot.';
    }

    public function rule(): string
    {
        return 'never a hand `<h3>`';
    }

    public function detect(): string
    {
        return 'An `<atom:card>` (default variant) or `<atom:panel>` with no `heading` prop or slot whose first element is an `<h2>` to `<h6>`, an `<atom:heading>`, or a `div` that starts with one. Nothing but whitespace and Blade directives may come before it. A bound `variant`, an echoed attribute bag, a card in the `brand` or `logo` slot and a title that follows other content are not flagged.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom(['card', 'panel']) as $node) {
            if ($this->declined($file, $node)) {
                continue;
            }

            $title = $this->handTitle($file, $node, 0);

            if ($title !== null && ! $this->inBrand($file, $node)) {
                yield [$node['offset'], 'its first element is a hand-built title (`<'.$title.'>`): use `heading`'];
            }
        }
    }

    /**
     * Whether a card or panel already has its heading, or is not one that takes it
     */
    protected function declined(File $file, array $node): bool
    {
        $outline = $file->outline();

        if ($outline->has($node, 'heading') || $this->spreads($file, $node)) {
            return true;
        }

        if ($node['component'] === 'card' && $outline->has($node, 'variant') && ! in_array($outline->value($node, 'variant'), ['default'], true)) {
            return true;
        }

        foreach ($outline->children($node) as $child) {
            if ($child['kind'] === 'slot' && $child['slot'] === 'heading') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an echoed attribute bag (`{{ $attributes }}`) sits in the tag: it could carry the heading
     */
    protected function spreads(File $file, array $node): bool
    {
        return (bool) preg_match('/\{\{\s*\$attributes/', substr($file->text(), $node['offset'], $node['openEnd'] - $node['offset']));
    }

    /**
     * The tag of a hand-built title that begins a node's content (a heading, or a `div` that begins with one), or null
     */
    protected function handTitle(File $file, array $node, int $depth): ?string
    {
        $outline = $file->outline();
        $cursor = $node['openEnd'];

        foreach ($outline->children($node) as $child) {
            if ($child['kind'] === 'slot') {
                $cursor = $child['end'];

                continue;
            }

            if (! $this->onlyDirectivesBetween($file, $cursor, $child['offset'])) {
                return null;
            }

            if (in_array($child['tag'], ['h2', 'h3', 'h4', 'h5', 'h6', 'atom:heading'], true)) {
                return str_starts_with($child['tag'], 'atom:') ? 'atom:heading' : $child['tag'];
            }

            return $child['tag'] === 'div' && $depth === 0 ? $this->handTitle($file, $child, 1) : null;
        }

        return null;
    }
}
