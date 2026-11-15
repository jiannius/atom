<?php

namespace Jiannius\Atom\Services;

/**
 * Whether a choice group draws tiles (`variant="tile"` on `<atom:radio.group>` or `<atom:checkbox.group>`), for the radios and checkboxes in its slot.
 *
 * A group cannot hand its variant to the options in its slot: Blade renders a slot before the component's own view runs, so the options can only read the
 * data the tag was given (`@aware`). Reading the public `variant` was wrong both ways (any `variant="tile"` on a modal, a card or a host component made a lone
 * radio a tile, and a host wrapper with a `variant` of its own, between the group and its radio, hid the group's), so `TagCompiler` writes a key of atom's own,
 * `atomChoiceTile`, on EVERY group (a default or card group publishes "plain", which masks an outer tile group). Like `FormMarker`, the value is
 * `"<tile|plain>|<owner>"` with the Livewire component being rendered as the owner: an option takes the mode of a group it shares an owner with, so a Livewire
 * child inside a tile group reads plain on its first render and on its own update alike.
 */
class ChoiceTile
{
    public const KEY = 'atomChoiceTile';

    /**
     * The value a group tag publishes.
     */
    public static function publish(mixed $variant): string
    {
        return ($variant === 'tile' ? 'tile' : 'plain').'|'.FormMarker::owner();
    }

    /**
     * Whether the option draws a tile: the published mode came from a group in the same Livewire render, and is `tile`.
     */
    public static function resolve(mixed $published): bool
    {
        if (is_string($published) && str_contains($published, '|')) {
            [$mode, $owner] = explode('|', $published, 2);

            return $mode === 'tile' && $owner === FormMarker::owner();
        }

        return false;
    }
}
