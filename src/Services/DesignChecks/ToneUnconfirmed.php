<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ToneUnconfirmed extends ToneAndConfirmRule
{
    public const ID = 'tone-unconfirmed';

    public const SEVERITY = DesignCheck::REVIEW;

    public function title(): string
    {
        return 'A warning or danger action that acts without asking';
    }

    public function fix(): string
    {
        return 'Put `confirm` on the element (`<atom:button variant="warning" inverted confirm>`, `<atom:menu.item variant="warning" confirm>`): a bare one asks "<label>?" and repeats the label, and the tone comes from its `variant`.';
    }

    public function rule(): string
    {
        return 'any other `variant="danger"` takes `confirm`';
    }

    public function detect(): string
    {
        return 'An `<atom:button variant="warning|danger">` (a danger that is not `type="delete"`) or an `<atom:menu.item variant="warning|danger|remove">` with a `wire:click` method (or `$wire.method()` in an `x-on:click`) and no `confirm` (static, bound or `:confirm="false"`) or `wire:confirm`. A delete confirms itself, so it is not read. Not flagged: a submit, a link (`href`), an element inside `<atom:confirm.trigger>` or `<atom:modal>`, a click that calls `atom.confirm(` or opens a modal, an Alpine-only handler, and a `wire:click` method that the app\'s PHP (`app/`, `resources/`, `modules/`) shows calling `->confirm(`, which is found by method name: two classes sharing a name hide each other\'s finding.';
    }

    /**
     * It reads the tags around an element, and it reads PHP for the methods that confirm
     */
    public function kinds(): array
    {
        return ['blade', 'php'];
    }

    public function find(File $file, Context $context): iterable
    {
        if (! $file->isBlade() || str_contains($file->text(), '<?php')) {
            foreach ($this->confirmingMethods($file->text()) as $method) {
                $context->add(static::ID, $method);
            }
        }

        if (! $file->isBlade()) {
            return;
        }

        $outline = $file->outline();

        foreach ([...$outline->atom('button'), ...$outline->atom('menu.item')] as $node) {
            $tone = $this->tone($outline, $node);

            if (! in_array($tone, ['warning', 'danger'], true) || $this->hasConfirm($outline, $node) || $outline->has($node, 'href')) {
                continue;
            }

            if ($outline->inside($node, ['atom:confirm.trigger', 'atom:modal']) !== null) {
                continue;
            }

            $methods = $this->methods($node);

            if ($methods === null) {
                continue;
            }

            yield [$node['offset'], '`variant="'.$tone.'"` acts without asking: add `confirm`', ['methods' => $methods]];
        }
    }

    public function finish(array $findings, Context $context): array
    {
        $confirming = array_flip(array_map('strtolower', array_filter($context->get(static::ID), 'is_string')));

        return array_values(array_filter($findings, function ($finding) use ($confirming) {
            foreach ($finding['data']['methods'] ?? [] as $method) {
                if (isset($confirming[strtolower($method)])) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * The Livewire methods an element's click calls, or null when it is not a click that this check reads: no
     * `wire:click` method it can name, or a handler that asks (`atom.confirm(`), opens a dialog or cannot be read
     *
     * @return array<int, string>|null
     */
    protected function methods(array $node): ?array
    {
        $methods = [];

        foreach ($node['attributes'] as $name => $value) {
            if (! str_starts_with($name, 'wire:click') && ! str_starts_with($name, 'x-on:click') && ! str_starts_with($name, '@click')) {
                continue;
            }

            if (! is_string($value) || preg_match('/atom-confirm-show|atom-modal-show|atom\.modal\(|\bconfirm\(/', $value)) {
                return null;
            }

            if (str_starts_with($name, 'wire:click')) {
                if (! preg_match('/^\s*(?:\$parent\.)?([A-Za-z_]\w*)/', $value, $found)) {
                    return null;
                }

                $methods[] = $found[1];

                continue;
            }

            if (preg_match_all('/\$wire\.([A-Za-z_]\w*)\s*\(/', $value, $found)) {
                array_push($methods, ...$found[1]);
            }
        }

        return $methods === [] ? null : $methods;
    }

    /**
     * The methods of a PHP text whose body calls `->confirm(` (any receiver: `$this`, `atom()`, `app('atom')`)
     *
     * @return array<int, string>
     */
    protected function confirmingMethods(string $php): array
    {
        $tokens = token_get_all($php);
        $count = count($tokens);
        $found = [];
        $nullsafe = defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1;

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $at = $this->next($tokens, $i);

            if (($tokens[$at] ?? null) === '&') {
                $at = $this->next($tokens, $at);
            }

            if (! is_array($tokens[$at] ?? null) || $tokens[$at][0] !== T_STRING) {
                continue;
            }

            $name = $tokens[$at][1];
            $open = null;

            for ($k = $at + 1; $k < $count; $k++) {
                if ($tokens[$k] === '{') {
                    $open = $k;

                    break;
                }

                if ($tokens[$k] === ';') {
                    break;
                }
            }

            if ($open === null) {
                continue;
            }

            $depth = 0;

            for ($k = $open; $k < $count; $k++) {
                $token = $tokens[$k];

                if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                } elseif (is_array($token) && in_array($token[0], [T_OBJECT_OPERATOR, $nullsafe], true)) {
                    $call = $this->next($tokens, $k);

                    if (is_array($tokens[$call] ?? null) && $tokens[$call][0] === T_STRING && strtolower($tokens[$call][1]) === 'confirm' && ($tokens[$this->next($tokens, $call)] ?? null) === '(') {
                        $found[] = $name;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * The index of the next token that is not whitespace
     *
     * @param  array<int, mixed>  $tokens
     */
    protected function next(array $tokens, int $at): int
    {
        $at++;

        while (isset($tokens[$at]) && is_array($tokens[$at]) && in_array($tokens[$at][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $at++;
        }

        return $at;
    }
}
