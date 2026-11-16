<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

/**
 * What the copy and form checks (status-as-badge, empty-glyph, enum-title, title-case, create-verb, modal-title,
 * modal-footer-padding, danger-section, typed-optional) share: reading a label or a literal the way a screen shows
 * it, and telling a PHP file that builds a mail, a PDF or an export (not a screen) from one that does not.
 */
abstract class CopyFormsRule extends Rule
{
    /**
     * What a typed missing value looks like (atom draws its own mark)
     *
     * @var array<int, string>
     */
    protected const DASHES = ['—', '–', '―', '--', '-'];

    /**
     * Directory names, besides the views' own non-screen ones, whose PHP prints a file and not a screen
     *
     * @var array<int, string>
     */
    protected const NON_SCREEN_PHP = ['export', 'exports', 'csv'];

    /**
     * Whether a string is only a typed dash (after decoding entities such as `&mdash;`)
     */
    protected function isDash(?string $text): bool
    {
        return $text !== null && in_array(trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')), static::DASHES, true);
    }

    /**
     * Whether a PHP file builds something that is not a screen (a mail, a PDF, a print, an export): its directory
     * is one of the views' non-screen segments, or it holds a mailable or a mail message. A Blade file is never
     * one, since the scan has already left those views out.
     */
    protected function printsNoScreen(File $file): bool
    {
        if ($file->isBlade()) {
            return false;
        }

        $segments = array_map('strtolower', array_slice(explode('/', str_replace('\\', '/', $file->path)), 0, -1));

        if (array_intersect($segments, [...DesignCheck::NON_SCREEN_SEGMENTS, ...static::NON_SCREEN_PHP]) !== []) {
            return true;
        }

        return preg_match('/\b(?:Mailable|MailMessage)\b/', $file->text()) === 1;
    }

    /**
     * What a node's own attribute reads as: a static value or a `t('…')` literal; null when absent or dynamic. Only
     * `label`, `heading` and `title` can be read, in the outline's order, so an attribute that another one would
     * outrank on the node is skipped (null) rather than misread.
     */
    protected function attributeText(BladeOutline $outline, array $node, string $attribute): ?string
    {
        $order = ['label', 'heading', 'title'];

        foreach (array_slice($order, 0, array_search($attribute, $order, true)) as $earlier) {
            if ($outline->has($node, $earlier)) {
                return null;
            }
        }

        return $outline->has($node, $attribute) ? $outline->label($node) : null;
    }

    /**
     * What a node reads as, from its own text and attributes (`BladeOutline::label()`); null when a slot sits inside
     * it, because the outline reads a slot's text (a menu item's `end` hint, "Ctrl K") as part of the label
     */
    protected function labelOf(BladeOutline $outline, array $node): ?string
    {
        foreach ($outline->descendants($node) as $inside) {
            if ($inside['kind'] === 'slot') {
                return null;
            }
        }

        return $outline->label($node);
    }

    /**
     * The literal text between a node's tags: tags removed, entities decoded, whitespace squashed. Null when the
     * node has no closer or holds an echo or a directive (anything that is not plain text).
     */
    protected function innerText(BladeOutline $outline, array $node): ?string
    {
        if ($node['selfClosing'] || $node['close'] === null) {
            return null;
        }

        $inner = substr($outline->text(), $node['openEnd'], $node['close'] - $node['openEnd']);

        if (str_contains($inner, '{{') || str_contains($inner, '{!!') || str_contains($inner, '@')) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * Whether an offset is inside a `<code>` or `<pre>`: documentation showing markup, not a screen
     */
    protected function inCode(BladeOutline $outline, int $offset): bool
    {
        $node = $outline->at($offset);

        foreach ($node === null ? [] : [$node, ...$outline->ancestors($node)] as $each) {
            if ($each['kind'] === 'html' && in_array($each['tag'], ['code', 'pre'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `{{ }}` and `{!! !!}` ranges of a Blade text, as [start, end] pairs
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected function echoRanges(string $text): array
    {
        preg_match_all('/\{\{.*?\}\}|\{!!.*?!!\}/s', $text, $found, PREG_OFFSET_CAPTURE);

        return array_map(fn ($echo) => [$echo[1], $echo[1] + strlen($echo[0])], $found[0]);
    }

    /**
     * The text between a `(` and its `)`, quotes and nested brackets respected; null when it never closes (or is
     * over 4,000 bytes). `$open` is the offset of the `(`.
     *
     * @return array{0: string, 1: int}|null the arguments, and the offset after the `)`
     */
    protected function arguments(string $text, int $open): ?array
    {
        $depth = 0;
        $quote = null;
        $limit = min(strlen($text), $open + 4000);

        for ($i = $open; $i < $limit; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                if (--$depth === 0) {
                    return [substr($text, $open + 1, $i - $open - 1), $i + 1];
                }
            }
        }

        return null;
    }
}
