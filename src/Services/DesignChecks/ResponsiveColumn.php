<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ResponsiveColumn extends ListRule
{
    public const ID = 'responsive-column';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Lists';

    public function title(): string
    {
        return 'A column hidden by hand at a breakpoint';
    }

    public function fix(): string
    {
        return 'Put `hideable` on `<atom:table.column>` (with a `name`, so the user\'s picker lists it), and `max-sm:hidden` for a phone.';
    }

    public function rule(): string
    {
        return 'not `hidden md:table-cell`';
    }

    public function detect(): string
    {
        return 'An `<atom:table.column>` or `<atom:table.cell>` whose static `class` has `hidden` with `md:`, `lg:`, `xl:` or `2xl:table-cell`, or `max-md:hidden` or a wider `max-*:hidden`. `max-sm:hidden` (and `hidden sm:table-cell`, the same phone rule) is the pattern\'s own and is not flagged; a `:class` or `@class` is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom(['table.column', 'table.cell']) as $node) {
            $note = $this->hiding($this->staticClasses($node));

            if ($note !== null) {
                yield [$node['offset'], $note];
            }
        }
    }

    /**
     * The classes that hide a column below or until a breakpoint wider than a phone, as the note
     *
     * @param  array<int, string>  $tokens
     */
    protected function hiding(array $tokens): ?string
    {
        foreach ($tokens as $token) {
            if (preg_match('/^(?:md|lg|xl|2xl):table-cell$/', $token) && in_array('hidden', $tokens, true)) {
                return '`hidden '.$token.'`';
            }

            if (preg_match('/^max-(?:md|lg|xl|2xl):hidden$/', $token)) {
                return '`'.$token.'`';
            }
        }

        return null;
    }
}
