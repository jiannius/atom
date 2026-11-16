<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class ToneMissing extends ToneAndConfirmRule
{
    public const ID = 'tone-missing';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * The verbs that start a warning: the record stays
     *
     * @var array<int, string>
     */
    protected const WARNING = ['archive', 'void', 'suspend', 'deactivate', 'revoke', 'reject', 'decline', 'cancel', 'disconnect', 'reset', 'unpublish', 'withdraw', 'trash'];

    /**
     * The verbs that start a danger: the data goes or cannot come back
     *
     * @var array<int, string>
     */
    protected const DANGER = ['delete', 'remove', 'discard', 'purge', 'destroy', 'erase'];

    /**
     * What may follow a verb that then names a thing the screen resets or abandons (`Reset filters`, `Cancel edit`),
     * not an action on a record
     *
     * @var array<string, array<int, string>>
     */
    protected const OBJECTS = [
        'cancel' => ['edit', 'editing', 'edits', 'changes', 'selection', 'reply', 'search', 'form'],
        'reset' => ['filter', 'filters', 'search', 'form', 'view', 'zoom', 'password', 'columns', 'sort', 'layout', 'map'],
        'remove' => ['filter', 'filters'],
        'discard' => ['changes', 'edits'],
    ];

    /**
     * Tags that read a value: a row holding one is a line of a form
     *
     * @var array<int, string>
     */
    protected const CONTROLS = ['input', 'select', 'textarea', 'checkbox', 'radio', 'switch', 'date-picker', 'time-picker', 'date-range', 'slider', 'rating', 'editor', 'tiptap', 'uploader'];

    public function title(): string
    {
        return 'A button or menu item whose verb says warning or danger, with no tone';
    }

    public function fix(): string
    {
        return 'A warning is `<atom:button variant="warning" inverted>` or `<atom:menu.item variant="warning">`, each with `confirm`; a danger is `<atom:button type="delete">` or `<atom:menu.item variant="delete">`.';
    }

    public function rule(): string
    {
        return 'Tone follows the consequence, not the verb';
    }

    public function detect(): string
    {
        return 'An `<atom:button>` with no tone variant and no `type="delete"` (`default`, `ghost`, `link` or none; a `ghost` takes its tone from `color`), or an `<atom:menu.item>` on the default variant, whose plain-text label starts with a warning verb (archive, void, suspend, deactivate, revoke, reject, decline, cancel, disconnect, reset, unpublish, withdraw, trash) or a danger verb (delete, remove, discard, purge, destroy, erase), or an item with `icon="delete"`. The verb lists are a default: the consequence decides, so an over-toned element is never flagged. A label that is unknown (an echo), a bound variant, a submit, a lone "Cancel", "Reset filters" and the like, a "Remove" in a row of form controls and "Discard changes" are not flagged.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ([...$outline->atom('button'), ...$outline->atom('menu.item')] as $node) {
            if ($this->tone($outline, $node) !== 'plain') {
                continue;
            }

            $item = $node['component'] === 'menu.item';

            if ($item && $outline->value($node, 'icon') === 'delete') {
                yield [$node['offset'], 'an item with the delete icon is a danger: `variant="delete"`'];

                continue;
            }

            $words = $this->words($outline, $node);

            if ($words === null || $words === []) {
                continue;
            }

            $tone = match (true) {
                in_array($words[0], static::WARNING, true) => 'warning',
                in_array($words[0], static::DANGER, true) => 'danger',
                default => null,
            };

            if ($tone === null || $this->exempt($outline, $node, $words)) {
                continue;
            }

            $label = mb_substr(trim((string) $outline->label($node)), 0, 40);

            yield [$node['offset'], '"'.$label.'" is a '.$tone.': '.match (true) {
                $tone === 'warning' && $item => '`variant="warning"` with `confirm`',
                $tone === 'warning' => '`variant="warning" inverted` with `confirm`',
                $item => '`variant="delete"`',
                default => '`type="delete"`',
            }];
        }
    }

    /**
     * Whether the label is about the screen or a line the user has not saved, not about a record
     *
     * @param  array<int, string>  $words
     */
    protected function exempt(BladeOutline $outline, array $node, array $words): bool
    {
        $verb = $words[0];
        $alone = count($words) === 1;

        if (in_array($words[1] ?? null, static::OBJECTS[$verb] ?? [], true)) {
            return true;
        }

        return match ($verb) {
            'cancel' => $alone,
            'reset' => $alone && $outline->inside($node, ['atom:form', 'table.toolbar', 'table.filters', 'atom:modal']) !== null,
            'discard' => $alone && $outline->inside($node, ['atom:form', 'atom:modal']) !== null,
            'remove' => $this->inFormRow($outline, $node),
            default => false,
        };
    }

    /**
     * Whether the element sits beside a form control: the "Remove" of a repeated line
     */
    protected function inFormRow(BladeOutline $outline, array $node): bool
    {
        $parent = $outline->parent($node);

        if ($parent === null || ! ($parent['kind'] === 'html' || in_array($parent['tag'], ['atom:form', 'atom:form.grid', 'atom:form.section'], true))) {
            return false;
        }

        foreach ($outline->descendants($parent) as $each) {
            if ($each['kind'] === 'atom' && $this->isControl((string) $each['component'])) {
                return true;
            }

            if ($each['kind'] === 'html' && in_array($each['tag'], ['input', 'select', 'textarea'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function isControl(string $component): bool
    {
        foreach (static::CONTROLS as $control) {
            if ($component === $control || str_starts_with($component, $control.'.')) {
                return true;
            }
        }

        return false;
    }
}
