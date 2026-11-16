<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class TitleCase extends CopyFormsRule
{
    public const ID = 'title-case';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Copy and state';

    /**
     * The facts left for `finish()`: every lowercase word of every label read, by label
     */
    protected const VOCABULARY = 'title-case.words';

    /**
     * Components whose `label` names a field
     *
     * @var array<int, string>
     */
    protected const FIELDS = ['input', 'select', 'textarea', 'checkbox', 'radio', 'date-picker', 'time-picker', 'uploader', 'toggle', 'dd', 'stat', 'tiptap'];

    /**
     * Components whose `heading` is a title
     *
     * @var array<int, string>
     */
    protected const HEADED = ['card', 'panel', 'form.section', 'empty', 'callout'];

    /**
     * Months and weekdays, in full and short: capitalised wherever they stand
     *
     * @var array<int, string>
     */
    protected const CALENDAR_WORDS = ['january', 'february', 'march', 'april', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'mon', 'tue', 'tues', 'wed', 'thu', 'thur', 'thurs', 'fri', 'sat', 'sun'];

    /**
     * The named arguments of a toast or a confirm that are text
     *
     * @var array<int, string>
     */
    protected const TEXT_ARGUMENTS = ['message', 'heading', 'subheading', 'buttonConfirm', 'buttonCancel'];

    public function title(): string
    {
        return 'A title-cased label';
    }

    public function fix(): string
    {
        return 'Sentence case in every `<atom:button>`, `<atom:menu.item>`, heading and label: "Save as template", not "Save As Template". Only the first word and proper nouns take a capital; a proper noun the check misreads is marked `atom:design-check ignore title-case: <reason>`.';
    }

    public function rule(): string
    {
        return 'Sentence case for every title, heading, label, button, menu item, confirm and toast';
    }

    public function detect(): string
    {
        return 'A heuristic, with no list of names. It reads literal text and `t()`, `__()` or `@lang()` literals of a button, menu item, `<atom:heading>`, `<atom:table.column>`, a field\'s `label`, the `title` of `<atom:page.header>`, the `heading` of a card, panel, `<atom:form.section>`, `<atom:empty>` or callout, the `label` keys in `:tabs` and `:facts`, and in PHP the literal text of `toast(` and `confirm(`. A word counts when it matches `[A-Z][a-z]+`, is not the first, and does not follow a `: . ? ! ( "` or a dash. A label is flagged with two such words, or with one whose lowercase form is used in another label of the app (so a brand name alone is not). Acronyms, mixed case (`MyInvois`), words with a digit, months and weekdays, quoted text and option labels are not counted. A proper noun of two words is flagged (mark it); one that stands alone is not.';
    }

    public function kinds(): array
    {
        return ['blade', 'php'];
    }

    public function find(File $file, Context $context): iterable
    {
        if ($this->printsNoScreen($file)) {
            return;
        }

        $flagged = [];

        foreach ($file->isBlade() ? $this->bladeLabels($file) : $this->phpLabels($file) as [$offset, $label]) {
            $words = $this->capitalised($label);
            $key = $file->path.':'.$offset.':'.md5($label);

            $context->add(static::VOCABULARY, [$key, $this->lowercaseWords($label)]);

            if ($words === [] || isset($flagged[$offset])) {
                continue;
            }

            $flagged[$offset] = true;

            yield [$offset, '"'.$label.'"'.(count($words) > 1 ? ' capitalises '.implode(', ', $words) : ' capitalises '.$words[0].', a word the app writes in lowercase elsewhere'), ['key' => $key, 'words' => $words]];
        }
    }

    /**
     * A label with one capitalised word stands only when the app writes that word in lowercase in another label
     */
    public function finish(array $findings, Context $context): array
    {
        $seen = [];

        foreach ($context->get(static::VOCABULARY) as [$key, $words]) {
            foreach ($words as $word) {
                $seen[$word][$key] = true;
            }
        }

        return array_values(array_filter($findings, function ($finding) use ($seen) {
            $words = $finding['data']['words'] ?? [];

            if (count($words) > 1) {
                return true;
            }

            $others = array_diff_key($seen[strtolower($words[0] ?? '')] ?? [], [$finding['data']['key'] ?? '' => true]);

            return $others !== [];
        }));
    }

    /**
     * Every label a Blade file shows, as [offset of its tag, text]
     *
     * @return iterable<int, array{0: int, 1: string}>
     */
    protected function bladeLabels(File $file): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom() as $node) {
            $component = $node['component'];

            if ($component === 'button' || $component === 'menu.item' || $component === 'heading' || $component === 'table.column') {
                $label = $this->labelOf($outline, $node);
            } elseif ($component === 'page.header') {
                $label = $this->attributeText($outline, $node, 'title');
            } elseif (in_array($component, static::HEADED, true)) {
                $label = $this->attributeText($outline, $node, 'heading');
            } elseif ($this->isField($component)) {
                $label = $this->attributeText($outline, $node, 'label');
            } else {
                $label = null;
            }

            if (is_string($label) && $label !== '') {
                yield [$node['offset'], $label];
            }

            foreach ([':tabs' => 'tabs', ':facts' => 'page.header'] as $attribute => $owner) {
                if ($component === $owner && is_string($node['attributes'][$attribute] ?? null)) {
                    preg_match_all('/[\'"]label[\'"]\s*=>\s*(?:(?:t|__)\(\s*)?([\'"])((?:\\\\.|(?!\1).)*)\1/s', $node['attributes'][$attribute], $items);

                    foreach ($items[2] as $item) {
                        if (! str_contains($item, '$') && trim($item) !== '') {
                            yield [$node['offset'], trim($item)];
                        }
                    }
                }
            }
        }
    }

    /**
     * The literal text of the toasts and confirms of a PHP file, as [offset of the call, text]
     *
     * @return iterable<int, array{0: int, 1: string}>
     */
    protected function phpLabels(File $file): iterable
    {
        $text = $file->text();

        preg_match_all('/(?:->|::)\s*(?:toast|confirm)\s*\(/', $text, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$call, $offset]) {
            $arguments = $this->arguments($text, $offset + strlen($call) - 1);

            foreach ($arguments === null ? [] : $this->literals($arguments[0]) as $literal) {
                yield [$offset, $literal];
            }
        }
    }

    /**
     * The text arguments of a call that are plain literals: the first positional one, and the named ones that are text
     *
     * @return array<int, string>
     */
    protected function literals(string $arguments): array
    {
        $literals = [];

        foreach ($this->split($arguments) as $position => $argument) {
            $named = preg_match('/^\s*(\w+)\s*:(?!:)\s*(.*)$/s', $argument, $parts);

            if ($named ? ! in_array($parts[1], static::TEXT_ARGUMENTS, true) : $position !== 0) {
                continue;
            }

            if (preg_match('/^\s*(?:(?:t|__)\(\s*)?([\'"])((?:\\\\.|(?!\1).)*)\1\s*(?:,[^)]*)?\)?\s*$/s', $named ? $parts[2] : $argument, $text)
                && ! ($text[1] === '"' && preg_match('/[$]|\{\$/', $text[2]))
                && trim($text[2]) !== '') {
                $literals[] = trim(stripcslashes($text[2]));
            }
        }

        return $literals;
    }

    /**
     * The top-level arguments of a call
     *
     * @return array<int, string>
     */
    protected function split(string $arguments): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;

        for ($i = 0, $n = strlen($arguments); $i < $n; $i++) {
            $char = $arguments[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif (in_array($char, ['(', '['], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']'], true)) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = substr($arguments, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($arguments, $start);

        return $parts;
    }

    /**
     * Whether a component's `label` names a field (an option's label is data, not copy)
     */
    protected function isField(string $component): bool
    {
        if (str_ends_with($component, '.option')) {
            return false;
        }

        foreach (static::FIELDS as $field) {
            if ($component === $field || str_starts_with($component, $field.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The words of a label that look capitalised by habit: `[A-Z][a-z]+`, not the first of the label or of a sentence,
     * not after an opening bracket or quote, not a month or a weekday, not an abbreviation (`Inc.`); quoted text is left out
     *
     * @return array<int, string>
     */
    protected function capitalised(string $label): array
    {
        $label = preg_replace('/"[^"]*"|“[^”]*”/u', ' ', $label) ?? $label;
        $words = [];
        $starts = true;

        foreach (preg_split('/\s+/u', trim($label), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $opened = preg_match('/^[(\[\'‘“"]/u', $token) === 1;
            $bare = trim($token, '()[]\'"‘’“”,;');
            $word = rtrim($bare, ':.?!');

            if (! $starts && ! $opened && $bare === $word && preg_match('/^[A-Z][a-z]+$/', $word) && ! in_array(strtolower($word), static::CALENDAR_WORDS, true)) {
                $words[] = $word;
            }

            $starts = preg_match('/[:.?!]$/', $bare) === 1 || in_array($token, ['—', '–', '-'], true);
        }

        return $words;
    }

    /**
     * The words of a label that are written in lowercase
     *
     * @return array<int, string>
     */
    protected function lowercaseWords(string $label): array
    {
        preg_match_all('/(?<![\p{L}\p{N}])[a-z]+(?![\p{L}\p{N}])/u', $label, $found);

        return array_values(array_unique($found[0]));
    }
}
