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
        'cancel' => ['edit', 'editing', 'edits', 'changes', 'selection', 'reply', 'search', 'form', 'upload', 'uploads', 'import', 'reorder', 'rename', 'add', 'close', 'back'],
        'reset' => ['filter', 'filters', 'search', 'form', 'view', 'zoom', 'password', 'columns', 'sort', 'layout', 'map', 'default', 'defaults'],
        'remove' => ['filter', 'filters'],
        'discard' => ['changes', 'edits'],
    ];

    /**
     * Words that sit between a verb and its object ("Reset all filters", "Reset to defaults", "Reset your password")
     *
     * @var array<int, string>
     */
    protected const FILLERS = ['all', 'to', 'your', 'the', 'my'];

    /**
     * Verbs that are also nouns: a link or a state change that says them is a place on the screen ("Archive", "Trash")
     *
     * @var array<int, string>
     */
    protected const NOUNS = ['archive', 'trash'];

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
        return 'An `<atom:button>` with no tone variant and no `type="delete"` (`default`, `ghost`, `link` or none; a `ghost` takes its tone from `color`), or an `<atom:menu.item>` on the default variant, whose plain-text label starts with a warning verb (archive, void, suspend, deactivate, revoke, reject, decline, cancel, disconnect, reset, unpublish, withdraw, trash) or a danger verb (delete, remove, discard, purge, destroy, erase), or an item with `icon="delete"`. The verb lists are a default: the consequence decides, so an over-toned element is never flagged. A label that is unknown (an echo), a bound variant, a submit, a lone "Cancel", "Reset filters" and the like, a "Remove" in a row (cell, row, card, grid or div, up to three levels) holding a form control and "Discard changes" are not flagged. A button with `variant="delete"` or `variant="remove"` is flagged with its own note: it draws the default and never confirms.';
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

            if (! $item && in_array($outline->value($node, 'variant'), ['delete', 'remove'], true)) {
                yield [$node['offset'], '`variant="'.$outline->value($node, 'variant').'"` is not a button variant: it draws the default; use `type="delete"`'];

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
        $rest = array_slice($words, 1);

        while ($rest !== [] && in_array($rest[0], static::FILLERS, true)) {
            array_shift($rest);
        }

        if (in_array($rest[0] ?? null, static::OBJECTS[$verb] ?? [], true) && ! ($rest[0] === 'add' && ($rest[1] ?? null) === 'on')) {
            return true;
        }

        if ($verb === 'cancel' && ($words[1] ?? null) === 'and' && in_array($words[2] ?? null, ['go', 'close', 'back', 'return'], true)) {
            return true;
        }

        if (in_array($verb, static::NOUNS, true) && $this->isPlace($node)) {
            return true;
        }

        return match ($verb) {
            'cancel' => $alone,
            'reset' => $alone && $outline->inside($node, ['atom:form', 'atom:form.modal', 'table.toolbar', 'table.filters', 'atom:modal']) !== null,
            'discard' => $alone && $outline->inside($node, ['atom:form', 'atom:form.modal', 'atom:modal']) !== null,
            'remove' => $this->inFormRow($outline, $node),
            default => false,
        };
    }

    /**
     * Whether an element only moves around the screen: a link, or a click that sets a property, toggles one or dispatches
     * an event
     */
    protected function isPlace(array $node): bool
    {
        if (isset($node['attributes']['href']) || isset($node['attributes'][':href'])) {
            return true;
        }

        foreach ($this->clicks($node) as $click) {
            if (preg_match('/\$(?:set|toggle|dispatch)\s*\(/', $click)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the element sits in a row with a form control: the "Remove" of a repeated line. The row is the nearest
     * of its three parents that holds one (a cell, a row, a card, a grid or a div). The search stops at a form (past
     * its first level), a modal, a slot or a surface, and after a card, a table row, a `tr` or an `li`, which are a
     * row of their own. A search box (`type="search"`) or filter of a table toolbar, and the selection checkbox of a table row, are not
     * a line's control.
     */
    protected function inFormRow(BladeOutline $outline, array $node): bool
    {
        foreach (array_slice($outline->ancestors($node), 0, 3) as $level => $ancestor) {
            if ($ancestor['kind'] === 'slot' || in_array($ancestor['tag'], ['atom:modal', 'atom:form.modal', 'atom:page', 'atom:panel', 'atom:table', 'atom:table.toolbar', 'atom:page.columns', 'atom:page.header', 'atom:form.actions'], true) || ($ancestor['tag'] === 'atom:form' && $level > 0)) {
                return false;
            }

            foreach ($outline->descendants($ancestor) as $each) {
                if ($this->isLineControl($outline, $each)) {
                    return true;
                }
            }

            if (in_array($ancestor['tag'], ['atom:card', 'atom:table.row', 'tr', 'li'], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Whether a tag is a control that holds a line's value: not a search box or filter, and not a table row's selection checkbox
     */
    protected function isLineControl(BladeOutline $outline, array $each): bool
    {
        $control = ($each['kind'] === 'atom' && $this->isControl((string) $each['component']))
            || ($each['kind'] === 'html' && in_array($each['tag'], ['input', 'select', 'textarea'], true));

        if (! $control) {
            return false;
        }

        if ($outline->value($each, 'type') === 'search' || $outline->inside($each, ['atom:table.toolbar', 'atom:table.search', 'atom:table.filters']) !== null) {
            return false;
        }

        return ! ($each['component'] === 'checkbox' && $outline->inside($each, ['atom:table.row', 'html:tr']) !== null);
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
