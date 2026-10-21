<?php

namespace Jiannius\Atom\Services;

use Illuminate\View\ComponentAttributeBag;

/**
 * Attribute-bag lookups atom's own components use.
 *
 * Internal: these used to be macros on every host's ComponentAttributeBag,
 * which no host called, so they are static helpers now and nothing global.
 */
class Attributes
{
    /**
     * Whether any attribute name matches one of the patterns (`x-on:click*`).
     */
    public static function hasLike(ComponentAttributeBag $attributes, string ...$patterns) : bool
    {
        foreach (array_keys($attributes->getAttributes()) as $key) {
            if (str((string) $key)->is($patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The modifier of the wire:model / x-model binding (`.live`, `.blur.debounce`), or
     * whether it holds the given one.
     */
    public static function modifier(ComponentAttributeBag $attributes, ?string $name = null) : string|bool
    {
        $attribute = collect($attributes->whereStartsWith('wire:model')->getAttributes())->keys()->first()
            ?? collect($attributes->whereStartsWith('x-model')->getAttributes())->keys()->first();

        $modifier = (string) str((string) $attribute)->replace('x-model', '')->replace('wire:model', '');

        return $name ? str($modifier)->is('*'.$name.'*') : $modifier;
    }

    /**
     * Mint a stable DOM id for a form control, so a <label for> can point at it.
     *
     * Derived from the field rather than randomised: Livewire's morph falls back to
     * `id` as its key (`key: el => ... : el.id`), so an id that changes per render
     * makes the morph replace the control instead of patching it, losing focus, the
     * caret and any Alpine state holding a reference to the node. A caller-supplied
     * id always wins.
     */
    public static function fieldId(ComponentAttributeBag $attributes, string $prefix, mixed ...$parts) : string
    {
        if (filled($attributes->get('id'))) {
            return $attributes->get('id');
        }

        return $prefix.'-'.substr(md5(collect($parts)->join('|')), 0, 8);
    }
}
