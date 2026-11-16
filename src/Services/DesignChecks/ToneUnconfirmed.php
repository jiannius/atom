<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ToneUnconfirmed extends ToneAndConfirmRule
{
    public const ID = 'tone-unconfirmed';

    public const SEVERITY = DesignCheck::REVIEW;

    /**
     * A method that only opens a dialog: confirm…, ask…, open…
     */
    protected const OPENS = '/^(?:confirm|ask|open)/i';

    /**
     * What `$wire.` calls that is the component's plumbing, not an action of the host's
     *
     * @var array<int, string>
     */
    protected const WIRE_HELPERS = ['set', 'dispatch', 'toggle', 'modal', 'call', 'get', 'refresh', 'entangle'];

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
        return 'An `<atom:button variant="warning|danger">` (a danger that is not `type="delete"`) or an `<atom:menu.item variant="warning|danger|remove">` with a `wire:click` method (or `$wire.method()` in an `x-on:click`) and no `confirm` (static, bound or `:confirm="false"`) or `wire:confirm`. A delete confirms itself, so it is not read. Not flagged: a submit, a link (`href`), an element inside `<atom:confirm.trigger>` or `<atom:modal>`, a click that calls `atom.confirm(` or opens a modal, an Alpine-only handler, and a `wire:click` method (or one named `confirm…`, `ask…`, `open…`) that the app\'s PHP (`app/`, `resources/`, `modules/`) shows asking, by `->confirm(`, `->modal(` or the `atom-confirm-show` event in its body or in a `$this->method()` it calls (one level), a `$wire.set(` and the like, and `onclick="confirm(`. Methods are found by name, so two classes sharing a name hide each other\'s finding; a Volt closure (`$name = function () {`) in a `<?php` block is read, a method written some other way is a miss.';
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
            foreach ($this->methodFacts($file->text()) as $fact) {
                $context->add(static::ID, $fact);
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
        $facts = array_filter($context->get(static::ID), 'is_array');
        $confirming = [];

        foreach ($facts as $fact) {
            if ($fact['asks']) {
                $confirming[strtolower($fact['name'])] = true;
            }
        }

        // one level: a method that hands over to one that asks (read from the methods that ask themselves, so it stops there)
        $asking = $confirming;

        foreach ($facts as $fact) {
            foreach ($fact['calls'] as $call) {
                if (isset($asking[strtolower($call)])) {
                    $confirming[strtolower($fact['name'])] = true;
                }
            }
        }

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
     * `wire:click` method it can name, a method that opens a dialog (`confirm…`, `ask…`, `open…`), or a handler that
     * asks (`atom.confirm(`, `onclick="confirm(`), opens a modal or cannot be read
     *
     * @return array<int, string>|null
     */
    protected function methods(array $node): ?array
    {
        $methods = [];

        if (is_string($node['attributes']['onclick'] ?? null) && str_contains($node['attributes']['onclick'], 'confirm(')) {
            return null;
        }

        foreach ($node['attributes'] as $name => $value) {
            if (! str_starts_with($name, 'wire:click') && ! str_starts_with($name, 'x-on:click') && ! str_starts_with($name, '@click')) {
                continue;
            }

            if (! is_string($value) || preg_match('/atom-confirm-show|atom-modal-show|atom\.modal\(|\bconfirm\(/', $value)) {
                return null;
            }

            if (str_starts_with($name, 'wire:click')) {
                if (! preg_match('/^\s*(?:\$parent\.)?([A-Za-z_]\w*)/', $value, $found) || preg_match(static::OPENS, $found[1])) {
                    return null;
                }

                $methods[] = $found[1];

                continue;
            }

            if (preg_match_all('/\$wire\.([A-Za-z_]\w*)\s*\(/', $value, $found)) {
                foreach ($found[1] as $method) {
                    if (preg_match(static::OPENS, $method)) {
                        return null;
                    }

                    if (! in_array($method, static::WIRE_HELPERS, true)) {
                        $methods[] = $method;
                    }
                }
            }
        }

        return $methods === [] ? null : $methods;
    }

    /**
     * What each method of a PHP text does, by name: whether its body asks (calls `->confirm(` or `->modal(` on anything,
     * or names the `atom-confirm-show` event) and which methods of `$this` it calls. A Volt closure
     * (`$suspend = function () {...}`) is a method named for its variable.
     *
     * @return array<int, array{name: string, asks: bool, calls: array<int, string>}>
     */
    protected function methodFacts(string $php): array
    {
        $tokens = token_get_all($php);
        $count = count($tokens);
        $facts = [];
        $nullsafe = defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1;

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $at = $this->next($tokens, $i);

            if (($tokens[$at] ?? null) === '&') {
                $at = $this->next($tokens, $at);
            }

            if (is_array($tokens[$at] ?? null) && $tokens[$at][0] === T_STRING) {
                $name = $tokens[$at][1];
            } elseif (($tokens[$at] ?? null) === '(') {
                $name = $this->closureName($tokens, $i);
            } else {
                continue;
            }

            if ($name === null) {
                continue;
            }

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

            $asks = false;
            $calls = [];
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
                } elseif (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && str_contains($token[1], 'atom-confirm-show')) {
                    $asks = true;
                } elseif (is_array($token) && in_array($token[0], [T_OBJECT_OPERATOR, $nullsafe], true)) {
                    $call = $this->next($tokens, $k);

                    if (! is_array($tokens[$call] ?? null) || $tokens[$call][0] !== T_STRING || ($tokens[$this->next($tokens, $call)] ?? null) !== '(') {
                        continue;
                    }

                    $called = strtolower($tokens[$call][1]);

                    if (in_array($called, ['confirm', 'modal'], true)) {
                        $asks = true;
                    }

                    $before = $this->previous($tokens, $k);

                    if (is_array($tokens[$before] ?? null) && $tokens[$before][0] === T_VARIABLE && $tokens[$before][1] === '$this') {
                        $calls[] = $tokens[$call][1];
                    }
                }
            }

            $facts[] = ['name' => $name, 'asks' => $asks, 'calls' => array_values(array_unique($calls))];
        }

        return $facts;
    }

    /**
     * The name of a closure written as `$name = function (...)`, or null
     *
     * @param  array<int, mixed>  $tokens
     */
    protected function closureName(array $tokens, int $function): ?string
    {
        $equals = $this->previous($tokens, $function);

        if (($tokens[$equals] ?? null) !== '=') {
            return null;
        }

        $variable = $this->previous($tokens, $equals);

        if (is_array($tokens[$variable] ?? null) && $tokens[$variable][0] === T_VARIABLE && $tokens[$variable][1] !== '$this') {
            return substr($tokens[$variable][1], 1);
        }

        return null;
    }

    /**
     * The index of the previous token that is not whitespace
     *
     * @param  array<int, mixed>  $tokens
     */
    protected function previous(array $tokens, int $at): int
    {
        $at--;

        while ($at > 0 && is_array($tokens[$at]) && in_array($tokens[$at][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $at--;
        }

        return $at;
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
