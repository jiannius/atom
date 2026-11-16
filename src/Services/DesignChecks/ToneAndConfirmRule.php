<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;

/**
 * What the tone and confirm checks share: the tone an `<atom:button>` or `<atom:menu.item>` wears, the click handlers it
 * carries and whether it already asks. Every reader declines (null or `other`) on a value it cannot read: a bound
 * variant, an echoed type, a tone it does not know.
 */
abstract class ToneAndConfirmRule extends Rule
{
    public const GROUP = 'Tone and confirm';

    /**
     * The attributes that run something when the element is clicked, by their start (`wire:click.prevent` too)
     *
     * @var array<int, string>
     */
    protected const CLICK = ['wire:click', 'x-on:click', '@click'];

    /**
     * The tone an element wears, by what it says itself:
     *  - `delete`: `type="delete"` on a button, `variant="delete"` on an item (both confirm themselves)
     *  - `danger`, `warning`: the tint of the tone (`error` is danger; an item's `remove` is danger too)
     *  - `plain`: nothing toned (the default, `ghost` or `link`, an item with no variant; a button's `variant="delete"` or `remove` draws the default)
     *  - `other`: a choice that is not a tone (`primary`, `success`, `info`, a submit)
     *  - null: bound, echoed or unknown, so a check says nothing
     */
    protected function tone(BladeOutline $outline, array $node): ?string
    {
        $isItem = $node['component'] === 'menu.item';

        if (! $isItem && $outline->has($node, 'type')) {
            $type = $outline->value($node, 'type');

            if ($type === null) {
                return null;
            }

            if ($type === 'delete') {
                return 'delete';
            }

            if ($type === 'submit') {
                return 'other';
            }
        }

        if (! $outline->has($node, 'variant')) {
            return 'plain';
        }

        $variant = $outline->value($node, 'variant');

        if ($variant === null) {
            return null;
        }

        if ($isItem) {
            return match ($variant) {
                'default' => 'plain',
                'warning' => 'warning',
                'danger', 'error', 'remove' => 'danger',
                'delete' => 'delete',
                default => null,
            };
        }

        return match ($variant) {
            'warning' => 'warning',
            'danger', 'error' => 'danger',
            'default', 'link', 'delete', 'remove' => 'plain',
            'ghost' => $this->colourTone($outline, $node, 'plain'),
            'primary', 'accent', 'success', 'info' => 'other',
            default => null,
        };
    }

    /**
     * A ghost button takes its tone from `color`
     */
    protected function colourTone(BladeOutline $outline, array $node, string $default): ?string
    {
        if (! $outline->has($node, 'color')) {
            return $default;
        }

        return match ($outline->value($node, 'color')) {
            null => null,
            'warning' => 'warning',
            'danger', 'error' => 'danger',
            default => $default,
        };
    }

    /**
     * Whether the element is a button or an item that asks before it acts, in any way a host can say so:
     * `confirm` (static, bound or `:confirm="false"`, the opt-out) or Livewire's `wire:confirm`
     */
    protected function hasConfirm(BladeOutline $outline, array $node): bool
    {
        if ($outline->has($node, 'confirm')) {
            return true;
        }

        foreach (array_keys($node['attributes']) as $name) {
            if (str_starts_with($name, 'wire:confirm')) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the element's click attributes say, in order (a bare attribute reads as an empty string)
     *
     * @return array<int, string>
     */
    protected function clicks(array $node): array
    {
        $clicks = [];

        foreach ($node['attributes'] as $name => $value) {
            foreach (static::CLICK as $start) {
                if (str_starts_with($name, $start)) {
                    $clicks[] = is_string($value) ? $value : '';

                    break;
                }
            }
        }

        return $clicks;
    }

    /**
     * Whether the label of an element is plain text a check can read, as lowercase words
     *
     * @return array<int, string>|null
     */
    protected function words(BladeOutline $outline, array $node): ?array
    {
        $label = $outline->label($node);

        if ($label === null) {
            return null;
        }

        return preg_split('/[^\p{L}\p{N}\'’]+/u', mb_strtolower($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
