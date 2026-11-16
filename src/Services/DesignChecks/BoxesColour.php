<?php

namespace Jiannius\Atom\Services\DesignChecks;

/**
 * What the boxes and colour checks share: reading the utility classes an element carries, the brand and logo exemption (Q22)
 * and the scan of class strings. It is abstract, so the folder's discovery skips it.
 *
 * Two readers of classes, on purpose:
 *  - `classes()` of the outline (static and bound string literals, an echoed token left out) for the checks that need a
 *    whole element to look like a box or a control: a token it cannot read is a token it does not count.
 *  - `scanClasses()` and `scanStrings()` for the checks that look for one token (palette, shadow, arbitrary value): they
 *    read the raw text of every class attribute, an echo's string literals included, because one stray token is a finding.
 */
abstract class BoxesColour extends Rule
{
    public const GROUP = 'Boxes and colour';

    /**
     * The slots that are a host's own look, exempt from the token checks
     *
     * @var array<int, string>
     */
    protected const BRAND_SLOTS = ['x-slot:brand', 'x-slot:logo'];

    /**
     * Class words with no hyphen or variant, which a string of classes may hold beside its hyphenated ones
     *
     * @var array<int, string>
     */
    protected const BARE_UTILITIES = [
        'absolute', 'antialiased', 'block', 'border', 'capitalize', 'collapse', 'container', 'contents', 'fixed', 'flex', 'grid', 'grow', 'hidden', 'inline', 'invisible', 'isolate', 'italic', 'lowercase', 'outline', 'overline', 'relative', 'resize', 'ring', 'rounded', 'shadow', 'shrink', 'static', 'sticky', 'table', 'transform', 'transition', 'truncate', 'underline', 'uppercase', 'visible',
    ];

    /**
     * Whether an element sits in the brand or logo slot of the shell
     */
    protected function inBrand(File $file, array $node): bool
    {
        return $file->outline()->inside($node, static::BRAND_SLOTS) !== null;
    }

    /**
     * A class token as its variants and its utility: `md:hover:p-2` is [['md', 'hover'], 'p-2']. A colon inside
     * brackets (`[&:hover]:p-2`, `supports-[display:grid]:p-2`) is not a variant; the important marks are dropped.
     *
     * @return array{0: array<int, string>, 1: string}
     */
    protected function split(string $token): array
    {
        $segments = preg_split('/:(?![^\[]*\])/', $token) ?: [$token];
        $utility = trim((string) array_pop($segments), '!');

        return [$segments, $utility];
    }

    /**
     * The resting utilities of an element: its class tokens that carry no variant (`hover:bg-x` is not the fill)
     *
     * @return array<int, string>
     */
    protected function resting(File $file, array $node): array
    {
        $resting = [];

        foreach ($file->outline()->classes($node) as $token) {
            [$variants, $utility] = $this->split($token);

            if ($variants === []) {
                $resting[] = $utility;
            }
        }

        return $resting;
    }

    /**
     * Whether any utility of a list matches a pattern
     *
     * @param  array<int, string>  $utilities
     */
    protected function any(array $utilities, string $pattern): bool
    {
        foreach ($utilities as $utility) {
            if (preg_match($pattern, $utility)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether nothing but whitespace and Blade control-flow directives (`@if (...)`, `@foreach`) lies between two offsets:
     * text, an echo or an output directive (`@include`, `@livewire`, `@yield`) there means the element at the second is not
     * the first thing in its parent.
     */
    protected function onlyDirectivesBetween(File $file, int $from, int $to): bool
    {
        $gap = substr($file->text(), $from, max(0, $to - $from));
        $gap = preg_replace('/@(?:end)?(?:if|elseif|else|unless|foreach|forelse|for|while|switch|case|default|break|auth|guest|can|elsecan|cannot|canany|isset|empty|php)\b[ \t]*(\((?:[^()]++|(?1))*\))?/', '', $gap);

        return $gap !== null && trim($gap) === '';
    }

    /**
     * The raw text of every class attribute of a tag: `class`, a bound `:class` or `x-bind:class`, any `*-class` prop
     * (`wrapper-class`) and the argument of an `@class([...])`
     *
     * @return array<int, string>
     */
    protected function classValues(array $node): array
    {
        $values = [];

        foreach ($node['attributes'] as $name => $value) {
            if (is_string($value) && ! str_starts_with(ltrim((string) $name, ':'), 'data-') && preg_match('/^(?::|x-bind:)?(?:[a-z0-9]+-)*class(?:es)?$/i', (string) $name)) {
                $values[] = $value;
            }
        }

        foreach ($node['directives'] as $directive) {
            if ($directive['name'] === 'class' && $directive['args'] !== null) {
                $values[] = $directive['args'];
            }
        }

        return $values;
    }

    /**
     * The class-looking words of a raw value: quotes, echo braces and `$variables` are separators, so the literals of
     * `{{ $open ? 'p-2' : 'p-3' }}` and of `@class(['p-2' => $open])` read as words
     *
     * @return array<int, string>
     */
    protected function words(string $value): array
    {
        $value = preg_replace('/\{\{|\}\}|\{!!|!!\}|[\'"`]|\$[A-Za-z_]\w*/', ' ', $value) ?? '';

        return preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Every class token of a Blade file that a matcher names, once for each element. The matcher gets the token's
     * variants and its utility and returns a note (or '' for none) to flag it, or null to let it be. An element in the
     * brand or logo slot is left alone.
     *
     * @param  callable(array<int, string>, string, string): ?string  $matcher
     * @return iterable<int, array{0: int, 1: ?string}>
     */
    protected function scanClasses(File $file, callable $matcher): iterable
    {
        $outline = $file->outline();

        foreach ($outline->nodes() as $node) {
            if ($node['kind'] === 'slot') {
                continue;
            }

            $hits = [];

            foreach ($this->classValues($node) as $value) {
                foreach ($this->words($value) as $word) {
                    [$variants, $utility] = $this->split($word);
                    $note = $matcher($variants, $utility, $word);

                    if ($note !== null) {
                        $hits[$word] = $note;
                    }
                }
            }

            if ($hits === [] || $this->inBrand($file, $node)) {
                continue;
            }

            foreach ($hits as $note) {
                yield [$node['offset'], $note === '' ? null : $note];
            }
        }
    }

    /**
     * Every class token in the string literals of a PHP file that a matcher names. Only a string that reads as a class
     * list counts (each word a class-shaped token with a hyphen, a colon or a bracket, or a bare utility such as `flex`), so a
     * word in a sentence is not a class. An interpolated string is not read.
     *
     * @param  callable(array<int, string>, string, string): ?string  $matcher
     * @return iterable<int, array{0: int, 1: ?string}>
     */
    protected function scanStrings(File $file, callable $matcher): iterable
    {
        $offset = 0;

        foreach (token_get_all($file->text()) as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                yield from $this->scanLiteral($text, $offset, $matcher);
            }

            $offset += strlen($text);
        }
    }

    /**
     * @param  callable(array<int, string>, string, string): ?string  $matcher
     * @return iterable<int, array{0: int, 1: ?string}>
     */
    protected function scanLiteral(string $literal, int $offset, callable $matcher): iterable
    {
        $content = substr($literal, 1, -1);

        if (! preg_match_all('/\S+/', $content, $words, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($words[0] as [$word]) {
            if (! preg_match('/^[!A-Za-z0-9_\-:\[\]\/.%#&>*()=,~+@]+$/', $word)
                || (! preg_match('/[-:\[]/', $word) && ! in_array($word, static::BARE_UTILITIES, true))) {
                return;
            }
        }

        foreach ($words[0] as [$word, $at]) {
            [$variants, $utility] = $this->split($word);
            $note = $matcher($variants, $utility, $word);

            if ($note !== null) {
                yield [$offset + 1 + $at, $note === '' ? null : $note];
            }
        }
    }
}
