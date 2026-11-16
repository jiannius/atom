<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PanelOverflowHidden extends ListRule
{
    public const ID = 'panel-overflow-hidden';

    public const SEVERITY = DesignCheck::BREAK;

    public const GROUP = 'Lists';

    public function title(): string
    {
        return 'A box that hides or scrolls its overflow around a paginated table';
    }

    public function fix(): string
    {
        return 'Put the list in `<atom:panel>`: it clips (`overflow-clip`), so the pager pins to the screen. Never `overflow-hidden` or a scroll box around a table.';
    }

    public function rule(): string
    {
        return 'it clips and never hides its overflow';
    }

    public function detect(): string
    {
        return 'An `<atom:table>` with `paginate` (static or bound) and the sticky pager on (no `sticky-pager` that is false or bound), inside an `<atom:card>` (it scrolls) or an element whose static `class` has `overflow-hidden`, `overflow-auto`, `overflow-scroll`, `overflow-y-auto|hidden|scroll` or `overflow-x-auto|hidden|scroll`; reported at that box, once, naming the first table in it. An `<atom:modal>` between them ends the search (it is its own scroll box, where pinning is right), and a box that also writes `overflow-visible` or a responsive `overflow-*` is not read. A box that gets its overflow from a `:class`, `@class` or a host component\'s own markup is not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $seen = [];

        foreach ($outline->atom('table') as $table) {
            if (! $outline->has($table, 'paginate') || ! $this->pagerPins($table)) {
                continue;
            }

            $box = $this->scrollingBox($file, $table);

            if ($box === null || isset($seen[$box['id']])) {
                continue;
            }

            $seen[$box['id']] = true;

            yield [$box['offset'], 'holds the pager of the table on line '.$file->line($table['offset']).', and it scrolls or hides its overflow: use `<atom:panel>`'];
        }
    }

    /**
     * Whether the table pins its pager: `sticky-pager` is absent, bare or literally true. False, empty or an expression is the host's choice.
     */
    protected function pagerPins(array $table): bool
    {
        foreach (['sticky-pager', ':sticky-pager', 'x-bind:sticky-pager'] as $name) {
            $value = $table['attributes'][$name] ?? true;

            if (is_string($value) && strtolower(trim($value)) !== 'true') {
                return false;
            }
        }

        return true;
    }

    /**
     * The nearest box around a table that scrolls or hides its overflow; null when a modal comes first
     */
    protected function scrollingBox(File $file, array $table): ?array
    {
        foreach ($file->outline()->ancestors($table) as $ancestor) {
            if ($ancestor['tag'] === 'atom:modal') {
                return null;
            }

            $tokens = $this->staticClasses($ancestor);

            if ($this->overflowsOff($tokens)) {
                continue;
            }

            if ($ancestor['tag'] === 'atom:card' || $this->clipsOrScrolls($tokens)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Whether a class list takes the overflow back (`overflow-visible`) or varies it by breakpoint, so that it is unsure
     *
     * @param  array<int, string>  $tokens
     */
    protected function overflowsOff(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $utility = substr($token, ((int) strrpos($token, ':')) + (str_contains($token, ':') ? 1 : 0));

            if ($token === 'overflow-visible' || (str_contains($token, ':') && str_starts_with($utility, 'overflow-'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    protected function clipsOrScrolls(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (preg_match('/^overflow-(?:hidden|auto|scroll)$/', $token) || preg_match('/^overflow-[xy]-(?:auto|hidden|scroll)$/', $token)) {
                return true;
            }
        }

        return false;
    }
}
