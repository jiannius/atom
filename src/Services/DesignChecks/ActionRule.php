<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\ActionPlacement;
use Jiannius\Atom\Services\BladeOutline;
use WeakMap;

/**
 * What the action checks (`action-misplaced`, `extra-primary`, `action-cap`, `row-buttons`, `menu-order`, `bar-button-size`, `dup-export`)
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
     * The surfaces that place `<atom:action>`s (`ActionPlacement`'s names), by owner tag: the surface, the slots it reads (folded names), and
     * whether its default slot is read too. Everything about where an action lands and what it looks like comes from `ActionPlacement::place()`.
     *
     * @var array<string, array{0: string, 1: array<int, string>, 2: bool}>
     */
    protected const PLACING = [
        'atom:page.header' => ['header', ['primary', 'actions', 'menu'], true],
        'atom:table.toolbar' => ['toolbar', ['actions', 'end', 'menu'], true],
        'atom:table' => ['bulk', ['checked', 'checkedmenu'], false],
        'atom:card' => ['card', ['actions', 'menu'], false],
        'atom:panel' => ['panel', ['actions', 'menu'], false],
        'atom:table.actions' => ['row', [], true],
    ];

    /**
     * The slots of a placing surface that no surface reads, so an action in one draws nothing (folded names)
     *
     * @var array<string, array<int, string>>
     */
    protected const UNREAD = [
        'atom:page.header' => ['status', 'meta'],
        'atom:table' => ['rows', 'columns', 'header', 'footer', 'empty'],
        'atom:card' => ['heading', 'footer', 'cover'],
        'atom:panel' => ['heading', 'footer', 'cover'],
    ];

    /**
     * Tags with no surface in them: an action directly inside one (or in one of its slots) is not placed by anything
     *
     * @var array<int, string>
     */
    protected const BARE = ['atom:page', 'atom:form', 'atom:form.actions', 'atom:form.modal', 'atom:modal', 'atom:empty', 'atom:callout', 'atom:table.row', 'atom:table.cell'];

    /**
     * Tags around an action that the surface leaves behind when it takes the action out
     *
     * @var array<int, string>
     */
    protected const LEFT_BEHIND = ['atom:tooltip', 'atom:confirm.trigger', 'atom:dropdown', 'atom:button.group'];

    /**
     * The browser-side conditions and loops that are left behind, empty, around an action the surface placed
     *
     * @var array<int, string>
     */
    protected const LEFT_CONDITIONS = [...self::CLIENT_CONDITIONALS, 'x-for'];

    /**
     * Where each outline's actions were located, per outline
     *
     * @var ?WeakMap<BladeOutline, array<int, array<string, mixed>>>
     */
    protected ?WeakMap $located = null;

    /**
     * Where an action goes, and what is wrong with where it is. Walks the tags around it, nearest first, the way a surface reads its slots:
     *  - `placed`: a surface reads the slot it is in (`owner`, `slot`), with `lost` set when a client condition or a wrapper between them is left behind;
     *  - `misplaced`: nothing places it (`note` says why);
     *  - `unknown`: a host component, an atom tag with its own say, or the top of the file (a partial) is around it: no finding.
     *
     * @return array{state: string, owner?: array, slot?: string, lost?: ?string, note?: string}
     */
    protected function locate(BladeOutline $outline, array $action): array
    {
        $lost = null;

        foreach ($outline->ancestors($action) as $ancestor) {
            if ($ancestor['kind'] === 'html' || ($ancestor['kind'] === 'atom' && in_array($ancestor['tag'], static::LEFT_BEHIND, true))) {
                if ($ancestor['kind'] === 'atom') {
                    $lost ??= match ($ancestor['tag']) {
                        'atom:confirm.trigger' => 'inside `<atom:confirm.trigger>`, which is left behind when the surface places the action: put `confirm` on the action itself',
                        'atom:tooltip' => 'inside `<atom:tooltip>`, which is left behind when the surface places the action: an action takes no tooltip, the surface draws it',
                        default => 'inside `<'.$ancestor['tag'].'>`, which is left behind when the surface places the action: the surface draws the action itself, so it cannot sit in a trigger or a group',
                    };
                }

                foreach (array_keys($ancestor['attributes']) as $name) {
                    if (in_array(explode('.', $name, 2)[0], static::LEFT_CONDITIONS, true)) {
                        $lost ??= 'inside `'.explode('.', $name, 2)[0].'`, which is left behind when the surface places the action: use `@if`';
                    }
                }

                continue;
            }

            if ($ancestor['kind'] === 'slot') {
                $owner = $outline->owner($ancestor);
                $key = $this->slotKey($ancestor);

                if ($owner === null || $key === null) {
                    return ['state' => 'unknown'];
                }

                if (isset(static::PLACING[$owner['tag']])) {
                    if (in_array($key, static::PLACING[$owner['tag']][1], true)) {
                        return ['state' => 'placed', 'owner' => $owner, 'slot' => $key === 'checkedmenu' ? 'checked-menu' : $key, 'lost' => $lost];
                    }

                    return in_array($key, static::UNREAD[$owner['tag']] ?? [], true)
                        ? ['state' => 'misplaced', 'note' => 'in the `'.$ancestor['slot'].'` slot of `<'.$owner['tag'].'>`, which does not place actions']
                        : ['state' => 'unknown'];
                }

                return in_array($owner['tag'], static::BARE, true)
                    ? ['state' => 'misplaced', 'note' => 'in a slot of `<'.$owner['tag'].'>`, which has no surface to place it']
                    : ['state' => 'unknown'];
            }

            if ($ancestor['kind'] === 'atom' && isset(static::PLACING[$ancestor['tag']])) {
                if (static::PLACING[$ancestor['tag']][2]) {
                    return ['state' => 'placed', 'owner' => $ancestor, 'slot' => 'default', 'lost' => $lost];
                }

                return in_array($ancestor['tag'], ['atom:card', 'atom:panel'], true)
                    ? ['state' => 'misplaced', 'note' => 'in the body of `<'.$ancestor['tag'].'>`, which does not place actions']
                    : ['state' => 'unknown'];
            }

            if ($ancestor['kind'] === 'atom' && in_array($ancestor['tag'], static::BARE, true)) {
                return ['state' => 'misplaced', 'note' => 'in `<'.$ancestor['tag'].'>`, which has no surface to place it'];
            }

            if ($ancestor['kind'] === 'component' && preg_match(static::LAYOUT_TAG, $ancestor['tag'])) {
                continue;
            }

            return ['state' => 'unknown'];
        }

        return ['state' => 'unknown'];
    }

    /**
     * Every `<atom:action>` a surface places, as the surface sees it: its node, owner, slot, position in that slot, the `priority` and `tone` it states
     * (null for none) and whether both are static (`modelled`: a bound or unknown value, or attributes spread into the tag, are not read, so the action is left out of what `ActionPlacement` is asked)
     *
     * @return array<int, array<string, mixed>>
     */
    protected function placedActions(BladeOutline $outline): array
    {
        $this->located ??= new WeakMap;

        if (isset($this->located[$outline])) {
            return $this->located[$outline];
        }

        $found = [];
        $positions = [];

        foreach ($outline->atom('action') as $action) {
            $where = $this->locate($outline, $action);

            if ($where['state'] !== 'placed') {
                continue;
            }

            $priority = $outline->has($action, 'priority') ? $outline->value($action, 'priority') : null;
            $tone = $outline->has($action, 'tone') ? $outline->value($action, 'tone') : null;
            $key = $where['owner']['id'].':'.$where['slot'];
            $tag = preg_replace('/"[^"]*"|\'[^\']*\'/s', '', substr($outline->text(), $action['offset'], $action['openEnd'] - $action['offset'])) ?? '';
            $spread = (bool) preg_match('/\{\{|\{!!|\$attributes|@(?:class|style|if|php)\b/', $tag);

            $found[] = [
                'node' => $action,
                'owner' => $where['owner']['id'],
                'slot' => $where['slot'],
                'position' => $positions[$key] = ($positions[$key] ?? -1) + 1,
                'priority' => $priority,
                'tone' => $tone,
                'lost' => $where['lost'],
                'modelled' => ! $spread && (! $outline->has($action, 'priority') || in_array($priority, ActionPlacement::PRIORITIES, true))
                    && (! $outline->has($action, 'tone') || in_array($tone, ActionPlacement::TONES, true)),
            ];
        }

        return $this->located[$outline] = $found;
    }

    /**
     * The actions of one surface owner, in order: all of them, or only the ones `ActionPlacement` can be asked about
     *
     * @return array<int, array<string, mixed>>
     */
    protected function actionsOf(BladeOutline $outline, array $owner, bool $modelledOnly = false): array
    {
        return array_values(array_filter(
            $this->placedActions($outline),
            fn ($action) => $action['owner'] === $owner['id'] && (! $modelledOnly || $action['modelled']),
        ));
    }

    /**
     * The surface of an owner tag, by `ActionPlacement`'s name
     */
    protected function surfaceOf(array $owner): string
    {
        return static::PLACING[$owner['tag']][0];
    }

    /**
     * What `ActionPlacement::place()` needs to know about a surface besides its actions: a `size="sm"` header, and whether the host fills the
     * header's own `primary` slot. Null when it cannot be read (a bound `size`, or a `primary` slot with Blade in it): the surface is not modelled then.
     *
     * @return array{small: bool, hostPrimary: bool, hostMenu: bool}|null
     */
    protected function contextOf(BladeOutline $outline, array $owner): ?array
    {
        $small = false;
        $hostPrimary = false;

        if ($owner['tag'] === 'atom:page.header') {
            if ($outline->has($owner, 'size')) {
                $size = $outline->value($owner, 'size');

                if ($size === null) {
                    return null;
                }

                $small = $size === 'sm';
            }

            foreach ($this->slots($outline, ['atom:page.header'], ['primary']) as $slot) {
                if (($outline->owner($slot)['id'] ?? null) === $owner['id']) {
                    $content = $this->hostContent($outline, $slot);

                    if ($content === null) {
                        return null;
                    }

                    $hostPrimary = $hostPrimary || $content;
                }
            }
        }

        return ['small' => $small, 'hostPrimary' => $hostPrimary, 'hostMenu' => false];
    }

    /**
     * Whether a slot holds anything of the host's besides its actions, comments and Blade control flow (what `hasActualContent()` would see once the
     * markers are out); null when it cannot tell (an echo, a directive it does not know)
     */
    protected function hostContent(BladeOutline $outline, array $slot): ?bool
    {
        if ($slot['close'] === null || $slot['selfClosing']) {
            return $slot['selfClosing'] ? false : null;
        }

        $text = $outline->text();
        $inside = '';
        $at = $slot['openEnd'];

        foreach ($outline->descendants($slot) as $node) {
            if ($node['tag'] === 'atom:action' && $node['offset'] >= $at) {
                $inside .= substr($text, $at, $node['offset'] - $at);
                $at = $node['end'];
            }
        }

        $inside .= substr($text, $at, $slot['close'] - $at);
        $inside = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'], '', $inside) ?? '';
        $inside = preg_replace('/@(?:end)?(?:if|unless|isset|empty|auth|guest|can|cannot|canany|env|production|foreach|forelse|for|while|else|elseif|elseauth|elseguest|elsecan|switch|case|break|default|continue)\b(?:[ \t]*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/i', '', $inside) ?? '';

        if (preg_match('/@|\{\{|\{!!/', $inside)) {
            return null;
        }

        return trim($inside) !== '';
    }

    /**
     * The sets of nodes that can show on one render, one for each node: those whose branch path is the start of its own. Two nodes in the branches of one
     * `@if` chain are never in one set. Empty when the branches could not be read.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array<int, array>>
     */
    protected function chains(BladeOutline $outline, array $nodes): array
    {
        if ($outline->branchesDeclined()) {
            return [];
        }

        $paths = array_map(fn ($node) => count($outline->branch($node)), $nodes);
        $chains = [];

        foreach ($nodes as $i => $node) {
            $chain = [];

            foreach ($nodes as $j => $other) {
                if ($paths[$j] <= $paths[$i] && $outline->together($node, $other)) {
                    $chain[] = $other;
                }
            }

            $chains[implode(',', array_column($chain, 'id'))] = $chain;
        }

        return array_values($chains);
    }

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
