<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PageBlockMargin extends PageRule
{
    public const ID = 'page-block-margin';

    public const SEVERITY = DesignCheck::INFO;

    /**
     * A vertical margin that adds room: `m-4`, `mt-6`, `mb-2`, `my-3`, `-mt-2` (not `0` and not `auto`, which add none)
     */
    protected const MARGIN = '/^-?m[tby]?-(?!0$|auto$)/';

    public function title(): string
    {
        return 'A vertical margin between a page\'s blocks';
    }

    public function fix(): string
    {
        return 'Remove it: `<atom:page>` spaces its blocks 22px apart (and `<atom:page.columns>` its rail\'s cards 14px); a block that needs its own room is a sign of one that should be an `<atom:card>` or `<atom:panel>`.';
    }

    public function rule(): string
    {
        return 'No margins or `space-y-*` between a page\'s blocks';
    }

    public function detect(): string
    {
        return 'A vertical margin (`m-*`, `mt-*`, `mb-*`, `my-*`, a negative one too, any variant; `0` and `auto` add none) in the classes of a direct child of `<atom:page>`, of `<atom:page.columns>`\' main column or of its `rail` slot, and `space-y-*` on `<atom:page>` itself. A margin deeper inside a block, `mx-*` and `ms-*` are not seen.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('page') as $page) {
            $space = $this->first($file, $page, fn ($utility) => str_starts_with($utility, 'space-y-'));

            if ($space !== null) {
                yield [$page['offset'], '`'.$space.'` on `<atom:page>`'];
            }
        }

        foreach ($outline->nodes() as $node) {
            $where = $this->block($file, $node);

            if ($where === null) {
                continue;
            }

            $margin = $this->first($file, $node, fn ($utility) => (bool) preg_match(static::MARGIN, $utility));

            if ($margin !== null) {
                yield [$node['offset'], '`'.$margin.'` '.$where];
            }
        }
    }

    /**
     * Where a tag is a block of the page: `null` when it is not
     */
    protected function block(File $file, array $node): ?string
    {
        $parent = $file->outline()->parent($node);

        if ($parent === null || $node['kind'] === 'slot') {
            return null;
        }

        if ($parent['tag'] === 'atom:page') {
            return 'on a block of `<atom:page>`';
        }

        if ($parent['tag'] === 'atom:page.columns') {
            return 'on a block of the main column';
        }

        if ($parent['kind'] === 'slot' && $parent['slot'] === 'rail' && ($file->outline()->owner($parent)['tag'] ?? null) === 'atom:page.columns') {
            return 'on a card of the rail';
        }

        return null;
    }

    /**
     * The first utility of a tag that the test takes
     */
    protected function first(File $file, array $node, callable $test): ?string
    {
        foreach ($this->utilities($file, $node) as $utility) {
            if ($test($utility)) {
                return $utility;
            }
        }

        return null;
    }
}
