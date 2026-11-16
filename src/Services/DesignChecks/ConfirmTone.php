<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ConfirmTone extends ToneAndConfirmRule
{
    public const ID = 'confirm-tone';

    public const SEVERITY = DesignCheck::REVIEW;

    public function title(): string
    {
        return 'A confirm wrapper whose dialog tone differs from the element inside';
    }

    public function fix(): string
    {
        return 'Drop the wrapper and put `confirm` on the element (`<atom:menu.item variant="warning" confirm>`): the dialog takes the element\'s tone from its `variant`.';
    }

    public function rule(): string
    {
        return 'The tone comes from the element\'s `variant`';
    }

    public function detect(): string
    {
        return 'An `<atom:confirm.trigger>` (its `variant` is the dialog\'s tone; none is the accent dialog) around exactly one `<atom:button>` or `<atom:menu.item>` that is a warning or a danger (a delete is a danger) and no other link or button, when the two tones differ. A bound `variant`, a wrapper around a plain element, a plain button or more than one element is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('confirm.trigger') as $trigger) {
            $variant = null;

            if ($outline->has($trigger, 'variant')) {
                $variant = $outline->value($trigger, 'variant');

                if ($variant === null) {
                    continue;
                }
            }

            $dialog = match ($variant) {
                'danger' => 'danger',
                'warning' => 'warning',
                'success' => 'success',
                default => 'accent',
            };

            $elements = [];
            $foreign = false;

            foreach ($outline->descendants($trigger) as $node) {
                if ($node['kind'] === 'atom' && in_array($node['component'], ['button', 'menu.item'], true)) {
                    $elements[] = $node;
                } elseif ($node['kind'] === 'component' || ($node['kind'] === 'html' && in_array($node['tag'], ['a', 'button'], true))) {
                    $foreign = true;
                }
            }

            if ($foreign || count($elements) !== 1) {
                continue;
            }

            $element = match ($this->tone($outline, $elements[0])) {
                'warning' => 'warning',
                'danger', 'delete' => 'danger',
                default => null,
            };

            if ($element === null || $element === $dialog) {
                continue;
            }

            yield [$trigger['offset'], 'the dialog is '.$dialog.' and the '.($elements[0]['component'] === 'button' ? 'button' : 'item').' is '.$element.': drop the wrapper and use `confirm`'];
        }
    }
}
