<?php

namespace Jiannius\Atom\Services;

use Illuminate\View\ComponentAttributeBag;

/**
 * What the controls that report to a table's filter bar as a chip (the selects, the date range and the search) agree on: the key
 * a chip is registered under, and the name it shows.
 */
class TableFilterChip
{
    /**
     * The key the chip is registered under: the `wire:model`, else `data-filter-key`. Null when there is neither, and then the
     * control registers nothing (a chip with no key can never be cleared).
     */
    public static function key(ComponentAttributeBag $attributes): mixed
    {
        return $attributes->wire('model')->value() ?: $attributes->get('data-filter-key');
    }

    /**
     * The chip's name: the given label, else the last segment of the key as a heading (`filters.paint_colour` is "Paint Colour"),
     * so a chip is never "null: Red".
     */
    public static function label(mixed $key, ?string $label = null): string
    {
        return t($label ?: (string) str($key)->afterLast('.')->headline());
    }
}
