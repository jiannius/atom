<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class FiltersChips extends ListRule
{
    public const ID = 'filters-chips';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Lists';

    public function title(): string
    {
        return 'A toolbar\'s filters keep the default chips row';
    }

    public function fix(): string
    {
        return 'Use `chips="inline"` on `<atom:table.filters>`, with `add` on the select and date filters.';
    }

    public function rule(): string
    {
        return 'List pages use `chips="inline"`';
    }

    public function detect(): string
    {
        return 'An `<atom:table.filters>` inside an `<atom:table.toolbar>` with no `chips` attribute at all (the default is `below`). A `chips` that is written, `below` included, or bound is a choice and is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('table.filters') as $filters) {
            if ($outline->inside($filters, 'atom:table.toolbar') === null || $outline->has($filters, 'chips')) {
                continue;
            }

            yield [$filters['offset'], 'in `table.toolbar` with no `chips`: the default is `below`'];
        }
    }
}
