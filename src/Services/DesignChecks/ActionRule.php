<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;

/**
 * What the action checks (`extra-primary`, `action-cap`, `row-buttons`, `menu-order`, `bar-button-size`, `dup-export`)
 * share: where a button sits (which surface's slot, which screen), whether it is a primary, and whether the tags
 * between two nodes are only markup (so the button is the surface's own and not a component's inner one). The outline
 * has no query for these, so they live here and not in `BladeOutline`.
 */
abstract class ActionRule extends Rule
{
    /**
     * Tags a screen is made of: a modal or a modal form is a screen of its own
     *
     * @var array<int, string>
     */
    protected const SCREENS = ['atom:modal', 'atom:form.modal'];

    /**
     * Atom components that wrap a button and draw nothing of their own around it
     *
     * @var array<int, string>
     */
    protected const WRAPPERS = ['atom:confirm.trigger', 'atom:tooltip'];

    /**
     * The `variant` values that draw the primary
     *
     * @var array<int, string>
     */
    protected const PRIMARY_VARIANTS = ['primary', 'accent'];

    /**
     * Atom components that are a form control, by the first part of their name (`input`, `input.otp`...)
     *
     * @var array<int, string>
     */
    protected const FORM_CONTROLS = ['input', 'select', 'textarea', 'date-picker', 'time-picker', 'checkbox', 'radio', 'toggle', 'slider', 'rating', 'uploader'];

    /**
     * The attributes (modifiers aside) that show or hide a node in the browser: two nodes with one are often exclusive
     *
     * @var array<int, string>
     */
    protected const CLIENT_CONDITIONALS = ['x-show', 'x-if', 'wire:show', ':hidden', 'x-bind:hidden'];

    /**
     * The tags of a layout around a page (`x-app`, `x-layouts.app`, `x-admin-layout`...): its slot is the page, so a button in it is on the screen. A name that ends in `modal`, `dialog`, `drawer`, `sheet` or `confirm` is not one
     */
    protected const LAYOUT_TAG = '/^x-(dynamic-component|app|guest|layout|layouts\.(?:[\w-]+\.)*(?!(?:modal|dialog|drawer|sheet|confirm)$)[\w-]+|[\w-]+-layout)$/';

    /**
     * The slot's name with its case and hyphens folded, so `checked-menu` (a `name` attribute) and `checkedMenu`
     * (`<x-slot:checked-menu>`, as Blade reads it) are one
     */
    protected function slotKey(array $slot): ?string
    {
        return $slot['slot'] === null ? null : strtolower(str_replace('-', '', $slot['slot']));
    }

    /**
     * The slots of an owner tag with one of these names (folded: see `slotKey()`)
     *
     * @param  array<int, string>  $owners  tags such as `atom:table.toolbar`
     * @param  array<int, string>  $names
     * @return array<int, array>
     */
    protected function slots(BladeOutline $outline, array $owners, array $names): array
    {
        $slots = [];

        foreach ($outline->nodes() as $node) {
            if ($node['kind'] !== 'slot' || ! in_array($this->slotKey($node), $names, true)) {
                continue;
            }

            $owner = $outline->owner($node);

            if ($owner !== null && in_array($owner['tag'], $owners, true)) {
                $slots[] = $node;
            }
        }

        return $slots;
    }

    /**
     * The id of the modal or modal form around a node, or -1 for the page itself
     */
    protected function screen(BladeOutline $outline, array $node): int
    {
        foreach ($outline->ancestors($node) as $ancestor) {
            if (in_array($ancestor['tag'], static::SCREENS, true)) {
                return $ancestor['id'];
            }
        }

        return -1;
    }

    /**
     * Whether a tag is only markup around a button: an HTML element, or a component that draws nothing of its own
     */
    protected function isWrapper(array $node): bool
    {
        return $node['kind'] === 'html' || in_array($node['tag'], static::WRAPPERS, true);
    }

    /**
     * Whether every tag between a node and one of its ancestors is only markup. A component in between (a dropdown, a
     * modal, a popover) could draw the button anywhere, so a check that asks this declines then.
     *
     * @param  array<int, string>  $extra  more tags to let through
     */
    protected function reaches(BladeOutline $outline, array $node, array $ancestor, array $extra = []): bool
    {
        foreach ($outline->ancestors($node) as $between) {
            if ($between['id'] === $ancestor['id']) {
                return true;
            }

            if (! $this->isWrapper($between) && ! in_array($between['tag'], $extra, true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Whether a button is the primary: a static `variant="primary"`, or a `type="submit"` with no `variant` (the
     * button draws a submit as the primary). A bound `variant` or `type` is not read: it is not a primary then.
     */
    protected function isPrimary(BladeOutline $outline, array $button): bool
    {
        if ($outline->has($button, 'variant')) {
            return in_array($outline->value($button, 'variant'), static::PRIMARY_VARIANTS, true);
        }

        return $outline->value($button, 'type') === 'submit';
    }

    /**
     * Whether something inside a node edits a value: a form control (atom's or a plain `input`, `select`, `textarea`) or
     * any tag bound with `wire:model` or `x-model`
     */
    protected function hasFormControl(BladeOutline $outline, array $node): bool
    {
        foreach ($outline->descendants($node) as $inner) {
            if ($inner['kind'] === 'html' && in_array($inner['tag'], ['input', 'select', 'textarea'], true)) {
                return true;
            }

            foreach (static::FORM_CONTROLS as $control) {
                if ($inner['kind'] === 'atom' && ($inner['component'] === $control || str_starts_with((string) $inner['component'], $control.'.'))) {
                    return true;
                }
            }

            foreach (array_keys($inner['attributes']) as $name) {
                if (str_starts_with($name, 'wire:model') || str_starts_with($name, 'x-model')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the browser can hide a node: it or a tag around it (up to its slot or screen) has `x-show`, `x-if`,
     * `wire:show` or `:hidden`. Such nodes are paired by no check: two that show in turn would read as two at once.
     */
    protected function clientConditional(BladeOutline $outline, array $node): bool
    {
        foreach ([$node, ...$outline->ancestors($node)] as $each) {
            if ($each['id'] !== $node['id'] && ($each['kind'] === 'slot' || in_array($each['tag'], static::SCREENS, true))) {
                return false;
            }

            foreach (array_keys($each['attributes']) as $name) {
                if (in_array(explode('.', $name, 2)[0], static::CLIENT_CONDITIONALS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a host component (an `<x-...>` that is not a layout) is around a node before its screen's edge: what it does
     * with its slot is not known, so the node may not be on the page at all
     */
    protected function hostWrapped(BladeOutline $outline, array $node): bool
    {
        foreach ($outline->ancestors($node) as $ancestor) {
            if (in_array($ancestor['tag'], static::SCREENS, true)) {
                return false;
            }

            if ($ancestor['kind'] === 'component' && ! preg_match(static::LAYOUT_TAG, $ancestor['tag'])) {
                return true;
            }
        }

        return false;
    }
}
