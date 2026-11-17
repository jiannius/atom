<?php

namespace Jiannius\Atom\Services;

use Illuminate\View\Compilers\ComponentTagCompiler;

/**
 * Make custom tag <atom:component/>
 * Thanks to livewire/flux for the inspiration
 */
class TagCompiler extends ComponentTagCompiler
{
    /**
     * Props a component's children read with `@aware`, written out on the tag when the caller gave none. Laravel's `@aware` reads
     * only what a parent was PASSED, never its `@props` defaults, so a child of a nested component with no such prop would read the
     * OUTER component's: an event timeline inside a step's body drew every line as a numbered step. Naming the default on the tag
     * gives the nested one its own. Name => [prop => PHP expression].
     *
     * @var array<string, array<string, string>>
     */
    protected const AWARE_DEFAULTS = [
        'atom::timeline' => ['kind' => "'event'", 'collapsible' => 'true'],
    ];

    /**
     * Compile the component and slot tags within the given string.
     *
     * Blade's own tag pass runs before this precompiler and has already turned `</x-slot>Total` into ` @endslotTotal`: the word is glued
     * onto the directive, which Blade does not know, so the slot never closes and the text is printed. The inline `<atom:icon slot="x"/>Total`
     * does the same in compileSelfClosingTags() below, so both sides of the parent's work are separated.
     */
    public function compile(string $value)
    {
        return $this->separateEndslot(parent::compile($this->separateEndslot($value)));
    }

    /**
     * Put an empty PHP block between `@endslot` and a word glued to it. It compiles to nothing, so the word is printed exactly where it
     * was written (a space would be output). An `@endslot` followed by anything but a word character is left alone, and a verbatim block
     * is already set aside when a precompiler runs.
     */
    protected function separateEndslot(string $value): string
    {
        return preg_replace('/(?<= )@endslot(?=\w)/', '@endslot<?php ?>', $value);
    }

    public function componentString(string $component, array $attributes)
    {
        $bag = $attributes['attributes'] ?? null;

        foreach (static::AWARE_DEFAULTS[$component] ?? [] as $prop => $default) {
            // A tag that spreads an attribute bag (`<atom:timeline {{ $attributes }}>`, inside a host wrapper) may be handed the prop by
            // its caller, and the compiled data puts a written-out prop BEFORE the bag, so a constant would win over the caller's value:
            // read the bag first, the default only when it has none.
            $attributes[$prop] ??= $bag === null
                ? $default
                : "((({$bag}) instanceof \\Illuminate\\View\\ComponentAttributeBag) ? ({$bag})->get('{$prop}') : null) ?? {$default}";
        }

        // A form's marker mode, for the fields in its slot: see FormMarker. A table filter bar publishes `required`, so its fields are never marked optional.
        if (in_array($component, ['atom::form', 'atom::form.modal'], true)) {
            // A bound `:marker="..."` is written into the compiled data twice by Blade itself; it is evaluated once there, into a variable that the
            // published value reads next (the entries of an array are evaluated in order, and the published key is added last).
            if (isset($attributes['marker'])) {
                $attributes['marker'] = "(\$__atomFormMarker = {$attributes['marker']})";
                $marker = '$__atomFormMarker';
            }
            else {
                $marker = $bag === null
                    ? 'null'
                    : "((({$bag}) instanceof \\Illuminate\\View\\ComponentAttributeBag) ? ({$bag})->get('marker') : null)";
            }

            $attributes[FormMarker::KEY] = "\\Jiannius\\Atom\\Services\\FormMarker::publish({$marker})";
        }
        elseif ($component === 'atom::table.filters') {
            $attributes[FormMarker::KEY] = "\\Jiannius\\Atom\\Services\\FormMarker::publish('required')";
        }

        // Whether a choice group draws tiles, for the radios and checkboxes in its slot: see ChoiceTile. EVERY group publishes, so a default or card group
        // nested in a tile one masks it, and a component of the host's that has a `variant` of its own is no longer mistaken for the group.
        if (in_array($component, ['atom::radio.group', 'atom::checkbox.group'], true)) {
            if (isset($attributes['variant'])) {
                $attributes['variant'] = "(\$__atomChoiceVariant = {$attributes['variant']})";
                $variant = '$__atomChoiceVariant';
            }
            else {
                $variant = $bag === null
                    ? 'null'
                    : "((({$bag}) instanceof \\Illuminate\\View\\ComponentAttributeBag) ? ({$bag})->get('variant') : null)";
            }

            $attributes[ChoiceTile::KEY] = "\\Jiannius\\Atom\\Services\\ChoiceTile::publish({$variant})";
        }

        return parent::componentString($component, $attributes);
    }

    /**
     * Compile the opening tags within the given string.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function compileOpeningTags(string $value)
    {
        $pattern = "/
            <
                \s*
                atom[\:]([\w\-\:\.]*)
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                @(?:class)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                @(?:style)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                (\:\\\$)(\w+)
                            )
                            |
                            (?:
                                [\w\-:.@%]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
                (?<![\/=\-])
            >
        /x";

        return preg_replace_callback($pattern, function (array $matches) {
            $this->boundAttributes = [];

            $attributes = $this->getAttributesFromAttributeString($matches['attributes']);

            return $this->componentString('atom::'.$matches[1], $attributes);
        }, $value);
    }

    /**
     * Compile the self-closing tags within the given string.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function compileSelfClosingTags(string $value)
    {
        $pattern = "/
            <
                \s*
                atom[\:]([\w\-\:\.]*)
                \s*
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                @(?:class)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                @(?:style)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                (\:\\\$)(\w+)
                            )
                            |
                            (?:
                                [\w\-:.@%]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
            \/>
        /x";

        return preg_replace_callback($pattern, function (array $matches) {
            $this->boundAttributes = [];

            $attributes = $this->getAttributesFromAttributeString($matches['attributes']);

            // Support inline "slot" attributes...
            if (isset($attributes['slot'])) {
                $slot = $attributes['slot'];

                unset($attributes['slot']);

                return '@slot('.$slot.') ' . $this->componentString('atom::'.$matches[1], $attributes)."\n@endComponentClass##END-COMPONENT-CLASS##" . ' @endslot';
            }

            return $this->componentString('atom::'.$matches[1], $attributes)."\n@endComponentClass##END-COMPONENT-CLASS##";
        }, $value);
    }

    /**
     * Compile the closing tags within the given string.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileClosingTags(string $value)
    {
        return preg_replace("/<\/\s*atom[\:][\w\-\:\.]*\s*>/", ' @endComponentClass##END-COMPONENT-CLASS##', $value);
    }
}