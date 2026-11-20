<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;

/**
 * The `confirm` prop of `<atom:button>` and `<atom:menu.item>`: what it asks and how the element is wired to ask it.
 *
 * Internal. A trigger that confirms either carries the gate's JSON (`data-atom-confirm`, read by helpers/confirm-gate.js at click time) or,
 * for a delete with no action of its own, the delete handler (`Atom::deleteConfirmHandler()`), which keeps calling `$wire.delete()`.
 */
class ConfirmProp
{
    /**
     * The text of an array `confirm` that reaches the dialog. `html` is not one: it is printed as markup.
     */
    public const KEYS = ['variant', 'heading', 'subheading', 'message', 'buttonConfirm', 'buttonCancel', 'passphrase', 'password', 'reason', 'reasonLabel', 'reasonPlaceholder'];

    /**
     * Where the element's confirm comes from, or null when it asks nothing.
     *
     * @param  mixed  $confirm  null (the element's own default), false (opt out), true (the default copy), a heading, or an array of dialog options
     * @param  ?string  $variant  the element's variant, which sets the dialog's tone
     * @param  bool  $delete  `type="delete"` on a button, `variant="delete"` on an item
     * @param  bool  $handled  the host wired a click of its own (`wire:click`, `x-on:click`, `@click`)
     * @param  bool  $acts  the element does something without one: a link, or a submit
     * @param  ?string  $label  the slot's HTML
     * @return array{mode: string, config: array<string, mixed>, given: array<string, mixed>}|null  `mode` is `handler` (the delete's own handler) or `gate`
     */
    public static function resolve(mixed $confirm, ?string $variant, bool $delete, bool $handled, bool $acts, ?string $label, ?string $passphrase): ?array
    {
        if ($confirm === false || ($confirm === null && ! $delete)) {
            return null;
        }

        return [
            'mode' => $delete && ! $handled && ! $acts ? 'handler' : 'gate',
            'config' => static::config($confirm, $variant, $delete, $label, $passphrase),
            'given' => static::given($confirm),
        ];
    }

    /**
     * The attributes a trigger merges for its confirm: the delete's own handler, the gate's data, or `$wire.delete()` for a delete that opted out
     * of the dialog and has no action of its own. Empty when the element asks nothing. Shared by `<atom:button>` and `<atom:menu.item>`.
     *
     * @param  mixed  $confirm  the `confirm` prop (see resolve())
     * @param  ?string  $variant  the element's variant
     * @param  bool  $delete  `type="delete"` on a button, `variant="delete"` on an item
     * @param  \Illuminate\View\ComponentAttributeBag  $attributes  the element's attributes, for a click handler of the host's own
     * @param  string  $el  `a` or `button`
     * @param  ?string  $type  the element's type, for a submit
     * @param  ?string  $label  the slot's HTML
     * @param  ?string  $passphrase  the text to type before a delete goes through
     * @param  ?string  $phrase  the alias of `$passphrase`
     * @return array<string, mixed>
     */
    public static function merges(mixed $confirm, ?string $variant, bool $delete, ComponentAttributeBag $attributes, string $el, ?string $type, ?string $label, ?string $passphrase, ?string $phrase = null): array
    {
        $handled = (bool) ($attributes->wire('click')->value() || Attributes::hasLike($attributes, 'x-on:click*', '@click*'));

        $confirmation = static::resolve($confirm, $variant, $delete, $handled, $el === 'a' || $type === 'submit', $label, $passphrase ?: $phrase);

        if (data_get($confirmation, 'mode') === 'handler') {
            return [
                'x-on:click' => app('atom')->deleteConfirmHandler(
                    data_get($confirmation, 'config.passphrase'),
                    (string) data_get($confirmation, 'config.buttonConfirm', ''),
                    $confirmation['given'],
                ),

                'x-on:confirmed' => "\$wire.delete()",
            ];
        }

        if ($confirmation) {
            return ['data-atom-confirm' => json_encode($confirmation['config'])];
        }

        if ($delete && $confirm === false && ! $handled && $el === 'button' && $type !== 'submit') {
            return ['x-on:click' => "\$wire.delete()"];
        }

        return [];
    }

    /**
     * The dialog's options: the tone from the variant, the copy from the label, a delete's own wording, then what `confirm` says.
     *
     * @return array<string, mixed>
     */
    public static function config(mixed $confirm, ?string $variant, bool $delete, ?string $label, ?string $passphrase): array
    {
        $config = [];

        $tone = match ($variant) {
            'danger', 'error', 'delete', 'remove' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => $delete ? 'danger' : null,
        };

        if ($tone) {
            $config['variant'] = $tone;
        }

        /* the label names the dialog and its button, when it is plain text that fits a button (the 40 characters `type="delete"` always used) */
        $plain = static::plainLabel($label);

        if ($delete) {
            $config['heading'] = t('atom::messages.permanently-delete-record');
            $config['message'] = t('atom::messages.are-you-sure-to-delete-this-record');
            $config['plural'] = t('atom::messages.delete-selected-records', []);
        }
        else if ($plain !== '') {
            $config['heading'] = str_ends_with($plain, '?') ? $plain : $plain.'?';
        }

        if ($plain !== '') {
            $config['buttonConfirm'] = $plain;
        }

        if ((string) $passphrase !== '') {
            $config['passphrase'] = $passphrase;
        }

        $given = static::given($confirm);

        $config = [...$config, ...$given];

        /* the count of a bulk delete belongs to the default heading: a heading of the host's own has no `:count` */
        if (array_key_exists('heading', $given)) {
            unset($config['plural']);
        }

        return $config;
    }

    /**
     * What the `confirm` prop itself says, translated once: a string is the heading, an array is dialog options.
     *
     * @return array<string, mixed>
     */
    public static function given(mixed $confirm): array
    {
        $given = match (true) {
            is_array($confirm) => Arr::only($confirm, static::KEYS),
            is_string($confirm) && $confirm !== '' => ['heading' => $confirm],
            default => [],
        };

        /* `phrase` is the alias of `passphrase`, as on the element */
        if (is_array($confirm) && ! array_key_exists('passphrase', $given) && ($confirm['phrase'] ?? null)) {
            $given['passphrase'] = $confirm['phrase'];
        }

        foreach ($given as $key => $value) {
            $given[$key] = match ($key) {
                'heading', 'subheading' => app('atom')->translateOnce($value),
                'message' => Arr::map(app('atom')->messageList($value), fn ($line) => app('atom')->translateOnce($line)),
                'buttonConfirm', 'buttonCancel', 'reasonLabel', 'reasonPlaceholder' => t($value),
                'password', 'reason' => (bool) $value,
                default => $value,
            };
        }

        return $given;
    }

    /**
     * The slot as one line of plain text of 40 characters or fewer, or an empty string when it holds markup, or is too long.
     */
    public static function plainLabel(?string $label): string
    {
        $label = (string) $label;

        if ($label === '' || str_contains($label, '<')) {
            return '';
        }

        $text = html_entity_decode(trim(preg_replace('/\s+/', ' ', $label)), ENT_QUOTES);

        return mb_strlen($text) <= 40 ? $text : '';
    }
}
