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
        return 'Put the list in `<atom:panel>`: it clips (`overflow-clip`), so the pager pins to the screen. Never `overflow-hidden` or a scroll box around a table: use the panel, or drop the overflow class.';
    }

    public function rule(): string
    {
        return 'it clips and never hides its overflow';
    }

    public function detect(): string
    {
        return 'An `<atom:table>` with `paginate` (static or bound, but not a literal null, false, empty or `[]`) and the sticky pager on (a static `sticky-pager` is a string, so `sticky-pager="false"` still pins; a bound one counts only when literally `true`), inside an `<atom:card>` (it scrolls) or an element whose static `class` has `overflow-hidden`, `overflow-auto`, `overflow-scroll`, `overflow-y-auto|hidden|scroll` or `overflow-x-auto|hidden|scroll`; reported at that box, once, naming the first table in it. Not read: a box with a bounded height (`h-*` other than `h-auto|fit|min|max`, `size-*`, `max-h-*`, `fixed`, `absolute`, `inset-*`), which is its own scroll box, an `<atom:modal>`, `<dialog>`, `<atom:dropdown>` or an element with `popover` between them (the search ends there), a box that also writes `overflow-visible`, `overflow-clip` or a responsive `overflow-*`, and one that gets its overflow from a `:class`, `@class` or a host component\'s own markup.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $seen = [];

        foreach ($outline->atom('table') as $table) {
            if (! $this->isPaginated($table) || ! $this->pagerPins($table)) {
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
     * Whether the table has a paginator: `paginate` is written, and not as a literal null, false, empty or `[]`
     */
    protected function isPaginated(array $table): bool
    {
        foreach (['paginate', ':paginate'] as $name) {
            if (! array_key_exists($name, $table['attributes'])) {
                continue;
            }

            $value = $table['attributes'][$name];

            return ! (is_string($value) && in_array(strtolower(trim($value)), ['', 'null', 'false', '[]', 'array()'], true));
        }

        return false;
    }

    /**
     * Whether the table pins its pager: no `sticky-pager`, a static one (a string, so even "false" is on in Blade, but an empty one is
     * off), or a bound one that is literally true. A bound one that is false or an expression is the host's choice.
     */
    protected function pagerPins(array $table): bool
    {
        foreach ($table['attributes'] as $name => $value) {
            if (in_array($name, ['sticky-pager', 'stickyPager'], true)) {
                if (is_string($value) && (trim($value) === '' || str_contains($value, '{{') || str_contains($value, '{!!'))) {
                    return false;
                }

                continue;
            }

            if (in_array($name, [':sticky-pager', ':stickyPager', 'x-bind:sticky-pager'], true) && strtolower(trim((string) $value)) !== 'true') {
                return false;
            }
        }

        return true;
    }

    /**
     * The nearest box around a table that scrolls or hides its overflow; null when a box that scrolls on its own comes first
     * (a modal, a dialog, a popover) or the box has a bounded height (a scroll box the pager can pin to)
     */
    protected function scrollingBox(File $file, array $table): ?array
    {
        $outline = $file->outline();

        foreach ($outline->ancestors($table) as $ancestor) {
            if (in_array($ancestor['tag'], ['atom:modal', 'atom:dropdown', 'dialog'], true) || $outline->has($ancestor, 'popover')) {
                return null;
            }

            $tokens = $this->staticClasses($ancestor);

            if ($this->overflowsOff($tokens)) {
                continue;
            }

            if ($ancestor['tag'] === 'atom:card' || $this->clipsOrScrolls($tokens)) {
                return $this->hasBoundedHeight($tokens) ? null : $ancestor;
            }
        }

        return null;
    }

    /**
     * Whether a class list gives the box a height of its own, or takes it out of the flow, at any breakpoint
     *
     * @param  array<int, string>  $tokens
     */
    protected function hasBoundedHeight(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $utility = substr($token, ((int) strrpos($token, ':')) + (str_contains($token, ':') ? 1 : 0));

            if (preg_match('/^(?:(?:max-)?h-.+|size-.+|inset-.+|fixed|absolute)$/', $utility) && ! preg_match('/^h-(?:auto|fit|min|max)$/', $utility)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class list takes the overflow back (`overflow-visible`, `overflow-clip`, which beats the card's own) or varies it by breakpoint, so that it is unsure
     *
     * @param  array<int, string>  $tokens
     */
    protected function overflowsOff(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $utility = substr($token, ((int) strrpos($token, ':')) + (str_contains($token, ':') ? 1 : 0));

            if (in_array($token, ['overflow-visible', 'overflow-clip'], true) || (str_contains($token, ':') && str_starts_with($utility, 'overflow-'))) {
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
