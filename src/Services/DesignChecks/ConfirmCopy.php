<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class ConfirmCopy extends ToneAndConfirmRule
{
    public const ID = 'confirm-copy';

    public const SEVERITY = DesignCheck::INFO;

    /**
     * What a confirm button must not say: it names no action
     *
     * @var array<int, string>
     */
    protected const GENERIC = ['confirm', 'yes', 'ok', 'okay', 'proceed', 'continue'];

    /**
     * Acronyms of four letters or more that are written in capitals on purpose
     *
     * @var array<int, string>
     */
    protected const ACRONYMS = ['HTML', 'JSON', 'HTTP', 'HTTPS', 'SMTP', 'IMAP', 'OAUTH', 'SAML', 'IBAN', 'SWIFT', 'WHATSAPP', 'LDAP', 'SFTP', 'WEBP', 'JPEG', 'DOCX', 'XLSX', 'SOCSO', 'LHDN', 'KWSP', 'NRIC', 'PDPA', 'SST', 'GST', 'EPF'];

    /**
     * How a confirm call is written: `$this->confirm(`, `atom()->confirm(`, `app('atom')->confirm(` or `atom.confirm(`
     */
    protected const CALL = '/(?:\$this->|(?<![\w$>.])atom\(\)->|(?<![\w$>.])app\(\s*[\'"]atom[\'"]\s*\)->|(?<![\w$>.])atom\.)confirm\s*\(/';

    public function title(): string
    {
        return 'A confirm dialog whose copy does not name the action';
    }

    public function fix(): string
    {
        return 'Use `confirm` on the trigger (`<atom:menu.item variant="warning" confirm>`): it names the dialog and repeats the verb on the button. Or name the action and the object in the heading, the consequence in the message and repeat the verb in `button-confirm`.';
    }

    public function rule(): string
    {
        return 'the confirm button repeats the verb';
    }

    public function detect(): string
    {
        return 'An `<atom:confirm.trigger>` with no `button-confirm` (its button reads "Confirm"); a `button-confirm`, a `\'buttonConfirm\' =>` or a `buttonConfirm:` of Confirm, Yes, OK, Okay, Proceed or Continue; a `$this->confirm(`, `atom()->confirm(`, `app(\'atom\')->confirm(` or `atom.confirm(` with literal arguments and no `buttonConfirm`; and a heading, subheading or message in a literal confirm with an all-caps word of four letters or more (HTML, JSON and the like excepted). The `confirm` prop of a button, an item or an `<atom:action>` is not flagged (its copy is checked for all-caps words), since atom repeats the label. A call whose arguments are a variable or a spread, a tag that spreads `$attributes` and a command (`Console`) are not read.';
    }

    /**
     * It reads one tag's attributes and one call's arguments, never the tree
     */
    public function structural(): bool
    {
        return false;
    }

    public function kinds(): array
    {
        return ['blade', 'php'];
    }

    public function find(File $file, Context $context): iterable
    {
        $text = $file->text();
        $code = $file->isBlade() ? $this->withoutScriptComments($text) : $text;
        $outline = $file->outline();

        /** @var array<int, array{0: int, 1: string}> $hits offset => [priority, note] */
        $hits = [];
        $add = function (int $offset, int $priority, string $note) use (&$hits, $outline) {
            $tag = $outline?->at($offset);

            if ($tag !== null && $offset >= $tag['offset'] && $offset < $tag['openEnd']) {
                $offset = $tag['offset'];
            }

            if (! isset($hits[$offset]) || $hits[$offset][0] > $priority) {
                $hits[$offset] = [$priority, $note];
            }
        };

        if ($outline !== null) {
            foreach ($outline->atom() as $node) {
                $this->readTag($outline, $node, $text, $add);
            }
        }

        // a button text written as a key, in PHP, JavaScript or a bound attribute
        if (preg_match_all('/(?<![\w])buttonConfirm[\'"]?\s*(?:=>|:)\s*(?:(?:t|__|trans)\(\s*)?([\'"])([^\'"\\\\]*)\1/', $code, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($found as $match) {
                if ($this->generic($match[2][0])) {
                    $add($match[0][1], 1, 'the confirm button says "'.trim($match[2][0]).'": repeat the verb ("Archive")');
                }
            }
        }

        if (! ($file->kind === 'php' && $this->isCommand($file))) {
            foreach ($this->calls($code) as [$offset, $args, $receiver]) {
                if ($receiver === '$this->' && ! $this->isComponent($file)) {
                    continue;
                }

                if ($args !== '' && (! preg_match('/^(?:[{\[\'"]|\w+\s*:(?!:))/', $args) || str_contains($args, '...'))) {
                    continue;
                }

                if (! preg_match('/(?<![\w])buttonConfirm\b/', $args)) {
                    $add($offset, 2, 'a confirm with no `buttonConfirm`: its button reads "Confirm"');
                }

                if (($shout = $this->shouting($this->copyOf($args))) !== null) {
                    $add($offset, 3, 'the confirm text has an all-caps word ("'.$shout.'"): write it in sentence case');
                }
            }
        }

        foreach ($hits as $offset => [, $note]) {
            yield [$offset, $note];
        }
    }

    /**
     * The tags a confirm is written on: a trigger, or a button or an item with a `confirm` of its own
     */
    protected function readTag(BladeOutline $outline, array $node, string $text, \Closure $add): void
    {
        $component = $node['component'];

        foreach (['button-confirm', 'buttonConfirm'] as $name) {
            $said = $outline->value($node, $name) === null ? '' : trim($node['attributes'][$name]);

            if ($this->generic($said)) {
                $add($node['offset'], 1, 'the confirm button says "'.$said.'": repeat the verb ("Archive")');
            }
        }

        if ($component === 'confirm.trigger') {
            $tag = substr($text, $node['offset'], $node['openEnd'] - $node['offset']);

            if (! $outline->has($node, 'button-confirm') && ! $outline->has($node, 'buttonConfirm') && ! str_contains($tag, '$attributes')) {
                $add($node['offset'], 2, 'a confirm trigger with no `button-confirm`: its button reads "Confirm"');
            }

            $strings = [];

            foreach (['heading', 'subheading', 'message'] as $name) {
                if (is_string($node['attributes'][$name] ?? null) && ! str_contains($node['attributes'][$name], '{')) {
                    $strings[] = $node['attributes'][$name];
                }
            }

            if (($shout = $this->shouting($strings)) !== null) {
                $add($node['offset'], 3, 'the confirm text has an all-caps word ("'.$shout.'"): write it in sentence case');
            }

            return;
        }

        if (in_array($component, ['button', 'menu.item', 'action'], true)) {
            $strings = [];

            if (is_string($node['attributes']['confirm'] ?? null) && ! str_contains($node['attributes']['confirm'], '{')) {
                $strings[] = $node['attributes']['confirm'];
            }

            foreach ([':confirm', 'x-bind:confirm'] as $name) {
                if (is_string($node['attributes'][$name] ?? null)) {
                    array_push($strings, ...$this->copyOf($node['attributes'][$name]));
                }
            }

            if (($shout = $this->shouting($strings)) !== null) {
                $add($node['offset'], 3, 'the confirm text has an all-caps word ("'.$shout.'"): write it in sentence case');
            }
        }
    }

    /**
     * Whether a text is a confirm button that names no action
     */
    protected function generic(string $text): bool
    {
        return in_array(mb_strtolower(trim($text, " \t\n\r.!?…")), static::GENERIC, true);
    }

    /**
     * The first all-caps word of four letters or more in some strings
     *
     * @param  array<int, string>  $strings
     */
    protected function shouting(array $strings): ?string
    {
        foreach ($strings as $string) {
            if (preg_match_all('/(?<![\p{L}:])\p{Lu}{4,}(?![\p{L}])/u', $string, $words)) {
                foreach ($words[0] as $word) {
                    if (! in_array($word, static::ACRONYMS, true)) {
                        return $word;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The strings a confirm call or array writes as its heading, subheading or message (and a first argument that is
     * a string, which is the message)
     *
     * @return array<int, string>
     */
    protected function copyOf(string $args): array
    {
        $strings = [];
        $args = trim($args);

        if (preg_match('/^([\'"])((?:\\\\.|(?!\1).)*)\1/s', $args, $first)) {
            $strings[] = $first[2];
        }

        preg_match_all('/(?<![\w])[\'"]?(?:heading|subheading|message)[\'"]?\s*(?:=>|:)\s*/', $args, $keys, PREG_OFFSET_CAPTURE);

        foreach ($keys[0] as [$key, $at]) {
            $start = $at + strlen($key);
            $head = substr($args, $start, 1);

            if ($head === '[') {
                $end = $this->close($args, $start, '[', ']');
                $value = $end === null ? '' : substr($args, $start, $end - $start);
            } elseif (preg_match('/\G(?:(?:t|__|trans)\(\s*)?([\'"])/', $args, $quote, 0, $start)) {
                $value = substr($args, $start, 400);
            } else {
                continue;
            }

            preg_match_all('/([\'"])((?:\\\\.|(?!\1).)*)\1/s', $value, $literals, PREG_SET_ORDER);

            foreach ($head === '[' ? $literals : array_slice($literals, 0, 1) as $literal) {
                $strings[] = $literal[2];
            }
        }

        return $strings;
    }

    /**
     * The confirm calls of a text: where each is and the text of its arguments
     *
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    protected function calls(string $text): array
    {
        $calls = [];

        if (! preg_match_all(static::CALL, $text, $found, PREG_OFFSET_CAPTURE)) {
            return $calls;
        }

        foreach ($found[0] as [$match, $at]) {
            $open = $at + strlen($match) - 1;
            $end = $this->close($text, $open, '(', ')');

            if ($end !== null) {
                $calls[] = [$at, trim(substr($text, $open + 1, $end - $open - 1)), str_starts_with($match, '$this->') ? '$this->' : ''];
            }
        }

        return $calls;
    }

    /**
     * Where the bracket opened at `$open` closes, looking past strings, or null when it does not within 20,000 bytes
     */
    protected function close(string $text, int $open, string $left, string $right): ?int
    {
        $depth = 0;
        $quote = null;
        $stop = min(strlen($text), $open + 20000);

        for ($i = $open; $i < $stop; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
            } elseif ($char === $left) {
                $depth++;
            } elseif ($char === $right && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether `$this->confirm(` in this file is atom's: a Livewire component (a `livewire` folder, `AtomComponent`, a class that
     * extends `Component`), which does not define a `confirm()` of its own
     */
    protected function isComponent(File $file): bool
    {
        $text = $file->text();

        if (preg_match('/\bfunction\s+confirm\s*\(/i', $text)) {
            return false;
        }

        return (bool) preg_match('#(^|/)livewire/#i', $file->path) || preg_match('/\bAtomComponent\b|\bextends\s+\\\\?(?:[\w\\\\]*\\\\)?Component\b|\buse\s+Livewire\\\\/', $text);
    }

    /**
     * The text with the comments of its `<script>` blocks blanked, at the same length, so an `atom.confirm(` in a comment is
     * not a call
     */
    protected function withoutScriptComments(string $text): string
    {
        return preg_replace_callback('/(<script\b[^>]*>)(.*?)(<\/script>)/is', function ($found) {
            $js = $found[2];
            $length = strlen($js);
            $quote = null;

            for ($i = 0; $i < $length; $i++) {
                $char = $js[$i];

                if ($quote !== null) {
                    if ($char === '\\') {
                        $i++;
                    } elseif ($char === $quote) {
                        $quote = null;
                    }

                    continue;
                }

                if ($char === '"' || $char === "'" || $char === '`') {
                    $quote = $char;
                } elseif ($char === '/' && ($js[$i + 1] ?? '') === '/') {
                    for (; $i < $length && $js[$i] !== "\n"; $i++) {
                        $js[$i] = ' ';
                    }
                } elseif ($char === '/' && ($js[$i + 1] ?? '') === '*') {
                    $end = strpos($js, '*/', $i + 2);
                    $end = $end === false ? $length : $end + 2;

                    for (; $i < $end; $i++) {
                        if ($js[$i] !== "\n") {
                            $js[$i] = ' ';
                        }
                    }

                    $i--;
                }
            }

            return $found[1].$js.$found[3];
        }, $text) ?? $text;
    }

    /**
     * Whether a PHP file is an artisan command, whose `$this->confirm()` asks in a terminal
     */
    protected function isCommand(File $file): bool
    {
        return (bool) preg_match('#(^|/)Console/#', $file->path) || preg_match('/\bextends\s+\\\\?(?:[\w\\\\]*\\\\)?Command\b/', $file->text());
    }
}
