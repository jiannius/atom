<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Arr;
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
     * Whether the `class` attribute sets a width of its own: a `w-*` or `max-w-*` utility under any variant
     * (`md:w-96`, `!w-96`, `w-96!`, `data-[state=open]:w-4`). Not `min-w-*`, `size-*`, nor a width for a child
     * (`[&_svg]:w-4`, `*:w-4`), which style something inside the box. `$includingMax = false` counts `w-*` only.
     */
    public static function hasWidth(ComponentAttributeBag $attributes, bool $includingMax = true) : bool
    {
        $pattern = '/^(?:(?![\[*])(?:[^\s:\[\]]|\[[^\]\s]*\])+:)*!?'.($includingMax ? '(?:max-)?' : '').'w-/';

        foreach (preg_split('/\s+/', trim((string) $attributes->get('class'))) as $token) {
            if (preg_match($pattern, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a `class` string into the tokens that place a box in its parent (width, flex item and margin utilities) and the rest.
     * The layout ones are `w-*`, `min-w-*`, `max-w-*`, `basis-*`, `grow`, `shrink`, the flex item `flex-1|auto|none|initial|grow|shrink|[..]`,
     * `order-*`, `self-*`, `justify-self-*` and every margin (`m-*`, `mx-*`, `ms-*`, `me-*`, negative `-m*`), under any variant (`md:w-96`,
     * `max-sm:w-full`, `!w-96`, `grow!`). A utility for a child (`[&_svg]:w-4`, `*:w-4`), and every other class, stays in the rest, in order.
     *
     * @param string|array<int|string, mixed>|null $class a class string, or a `:class` array (run through `Arr::toCssClasses()`)
     * @return array{0: string, 1: string} [layout classes, other classes], each space-joined
     */
    public static function splitLayoutClasses(string|array|null $class) : array
    {
        $class = is_array($class) ? Arr::toCssClasses($class) : $class;

        $pattern = '/^(?:(?![\[*])(?:[^\s:\[\]]|\[[^\]\s]*\])+:)*!?(?:'
            .'(?:min-|max-)?w-|basis-|order-|self-|justify-self-|-?m[xytrblse]?-|(?:grow|shrink)(?:-|!?$)|flex-(?:\d|auto!?$|initial!?$|none!?$|grow|shrink|\[|\())/';

        $layout = [];
        $rest = [];

        foreach (preg_split('/\s+/', trim((string) $class), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (preg_match($pattern, $token)) {
                $layout[] = $token;
            } else {
                $rest[] = $token;
            }
        }

        return [implode(' ', $layout), implode(' ', $rest)];
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

    /**
     * Why a control is disabled: `'self'` for its own `disabled`, `'inherited'` from a disabled ancestor (`<atom:form disabled>`, a group), or null.
     * A control's own `disabled` wins, `:disabled="false"` included, and the same attribute on a wrapper in a template ends the inheritance below
     * it. Laravel reads the data of the component being rendered, merged with its parent's, before its stack of ancestors, so a disabled component's
     * own view does not see a boundary it renders itself (as `@aware` did). An empty `disabled=""` counts as not disabled. The result is what a control
     * prints as `data-atom-disabled`, which its JS reads at event time. Call it where `disabled` is still in the bag (a control that declares
     * `disabled` in `@props` reads the marker from its bag instead).
     */
    public static function disabledBy(ComponentAttributeBag $attributes) : ?string
    {
        if ($attributes->has('disabled')) {
            return $attributes->get('disabled') ? 'self' : null;
        }

        return app('view')->getConsumableComponentData('disabled', false) ? 'inherited' : null;
    }
}
