<?php

namespace Jiannius\Atom\Services;

use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

/**
 * Which fields a form marks: `required` draws the asterisk on a required field, `optional` draws "(optional)" on the others.
 *
 * A form cannot hand its mode to the fields in its slot: Blade renders a slot before the component's own view runs, so the only thing the
 * fields can read is the data the tag was given (`@aware`). `TagCompiler` therefore writes a key of atom's own, `atomFormMarker`, on `<atom:form>`,
 * `<atom:form.modal>` and `<atom:table.filters>` (never the public `marker`, which any host component may use for something else), and `input.field`
 * reads that key. The value is `"<mode>|<owner>"`, where the owner is the Livewire component being rendered. A field only takes the mode of a form
 * it shares an owner with: a Livewire child inside the form is rendered with the form's data on the stack at first, and alone after its own update,
 * so inheriting there would flip its labels on the first click.
 */
class FormMarker
{
    public const KEY = 'atomFormMarker';

    /**
     * `optional` or `required` (any case, surrounding space ignored), else null.
     */
    public static function normalise(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : null;

        return in_array($value, ['optional', 'required'], true) ? $value : null;
    }

    /**
     * The mode of `config('atom.form.marker')`; `required` when it is unset or not a mode.
     */
    public static function configured(): string
    {
        return static::normalise(config('atom.form.marker')) ?? 'required';
    }

    /**
     * The value a form tag publishes: its own `marker` if it is a mode, else the config's, with the Livewire component being rendered.
     */
    public static function publish(mixed $marker): string
    {
        return (static::normalise($marker) ?? static::configured()).'|'.static::owner();
    }

    /**
     * The mode a field draws: the published one when it came from a form in the same Livewire render, else the config's.
     */
    public static function resolve(mixed $published): string
    {
        if (is_string($published) && str_contains($published, '|')) {
            [$mode, $owner] = explode('|', $published, 2);

            if ($owner === static::owner()) {
                return static::normalise($mode) ?? static::configured();
            }
        }

        return static::configured();
    }

    /**
     * The id of the Livewire component being rendered and how deep Livewire's render stack is, or an empty string outside one. The depth tells a
     * form from the fields of an island or a child inside it: an island is rendered again, alone, by the same component, so the id alone would
     * match on its first render (form around it) and not after its own update.
     */
    public static function owner(): string
    {
        if (! class_exists(ExtendBlade::class) || ! method_exists(ExtendBlade::class, 'currentRendering')) {
            return '';
        }

        $component = ExtendBlade::currentRendering();

        if (! is_object($component) || ! method_exists($component, 'getId')) {
            return '';
        }

        return $component->getId().'#'.static::depth();
    }

    /**
     * How many components Livewire is rendering inside one another (an island counts as one more). Livewire keeps the stack in a protected static, so a
     * Livewire that moves it reads as 0 (a form and its fields then match on the component alone).
     */
    protected static function depth(): int
    {
        try {
            return \Closure::bind(fn () => count(static::$livewireComponents), null, ExtendBlade::class)();
        } catch (\Throwable) {
            return 0;
        }
    }
}
