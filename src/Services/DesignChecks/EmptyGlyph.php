<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class EmptyGlyph extends CopyFormsRule
{
    public const ID = 'empty-glyph';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Copy and state';

    /**
     * A fallback to a dash: `?? '—'`, `?: '--'`. The long marks (an em or en dash, a horizontal bar, two hyphens) are
     * never a separator or a minus; a lone hyphen is one often enough to be read only where it can only be a value
     */
    protected const FALLBACK = '/(?:\?\?|\?:)\s*([\'"])(—|–|―|-{1,2})\1/u';

    /**
     * A dash as the `value` of a fact: `['label' => 'Site', 'value' => '—']`
     */
    protected const FACT_VALUE = '/[\'"]value[\'"]\s*=>\s*([\'"])(—|–|―|-{2})\1/u';

    /**
     * What stands before a lone hyphen fallback that makes it a value: an array's `'value' =>` on the same expression
     */
    protected const VALUE_BEFORE = '/[\'"]value[\'"]\s*=>[^,;\[\]]*$/';

    public function title(): string
    {
        return 'A typed dash for a missing value';
    }

    public function fix(): string
    {
        return 'Leave the value empty: `<atom:dd>`, `<atom:table.cell>` and a labelled `<atom:page.header>` fact draw a muted "—" (and "Not set" for screen readers). To hide a fact, leave it out of the array.';
    }

    public function rule(): string
    {
        return 'A missing value is never typed';
    }

    public function detect(): string
    {
        return 'In Blade and PHP: (a) a fallback to a dash (`?? \'—\'`, `?: \'--\'`, and `?: \'-\'` only inside an echo or as an array\'s `\'value\'`), (b) `\'value\' => \'—\'` in an array, (c) an `<atom:dd>` or `<atom:table.cell>` whose only text is a dash, (d) `filler="—"`. A dash inside text ("2–3 days"), `implode(\' - \', …)`, a ternary (`$x ? $x : \'—\'`), `=> \'-\'`, a `<code>` or `<pre>` sample, and a PHP file under a mail, PDF, print or export directory are not read.';
    }

    /**
     * Its text clauses need no nesting; the element clause reads it only when the view balances
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
        if ($this->printsNoScreen($file)) {
            return;
        }

        $text = $file->text();
        $outline = $file->outline();
        $echoes = $file->isBlade() ? $this->echoRanges($text) : [];
        $seen = [];

        preg_match_all(static::FALLBACK, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($found as $match) {
            $offset = $match[0][1];

            if (($match[2][0] === '-' && ! $this->isValue($text, $offset, $echoes)) || ($outline && $this->inCode($outline, $offset))) {
                continue;
            }

            $seen[$offset] = true;

            yield [$offset, 'a fallback to `'.$match[2][0].'`'];
        }

        preg_match_all(static::FACT_VALUE, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($found as $match) {
            if (! isset($seen[$match[0][1]]) && ! ($outline && $this->inCode($outline, $match[0][1]))) {
                $seen[$match[0][1]] = true;

                yield [$match[0][1], 'a fact whose value is `'.$match[2][0].'`'];
            }
        }

        if ($outline === null) {
            return;
        }

        foreach ($outline->atom() as $node) {
            $note = $this->filler($node) ?? (
                $file->structural() && in_array($node['component'], ['dd', 'table.cell'], true) && $this->isDash($this->innerText($outline, $node))
                    ? 'the only text is a dash'
                    : null
            );

            if ($note !== null && ! isset($seen[$node['offset']]) && ! $this->inCode($outline, $node['offset'])) {
                $seen[$node['offset']] = true;

                yield [$node['offset'], $note];
            }
        }
    }

    /**
     * Whether a lone-hyphen fallback can only be a value: inside an echo, or an array's `'value'`
     *
     * @param  array<int, array{0: int, 1: int}>  $echoes
     */
    protected function isValue(string $text, int $offset, array $echoes): bool
    {
        foreach ($echoes as [$start, $end]) {
            if ($offset > $start && $offset < $end) {
                return true;
            }
        }

        return preg_match(static::VALUE_BEFORE, substr($text, max(0, $offset - 160), min(160, $offset))) === 1;
    }

    /**
     * (d) `filler="—"` or `:filler="'—'"`
     */
    protected function filler(array $node): ?string
    {
        $static = $node['attributes']['filler'] ?? null;

        if (is_string($static) && $this->isDash($static)) {
            return '`filler="'.trim($static).'"` types the dash atom draws';
        }

        $bound = $node['attributes'][':filler'] ?? null;

        if (is_string($bound) && preg_match('/^\s*([\'"])(.*)\1\s*$/s', $bound, $literal) && $this->isDash($literal[2])) {
            return '`:filler` types the dash atom draws';
        }

        return null;
    }
}
