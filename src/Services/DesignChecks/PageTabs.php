<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PageTabs extends PageRule
{
    public const ID = 'page-tabs';

    public const SEVERITY = DesignCheck::INFO;

    public function title(): string
    {
        return '`<atom:tabs>` as a page\'s own block';
    }

    public function fix(): string
    {
        return 'A record\'s sections are `<atom:page.tabs :tabs="[...]" />` (link tabs with counts, under the header); `<atom:tabs>` switches inside a panel or a card.';
    }

    public function rule(): string
    {
        return '`<atom:page.header>` first, then stats, `<atom:page.tabs>` and content as siblings';
    }

    public function detect(): string
    {
        return 'An `<atom:tabs>` that is a direct child of `<atom:page>` (an `@if` between them is ignored). One in a card, panel, modal, form or any wrapper is not seen. Tabs that switch content on the same page are a legitimate use, which is why it is only a note.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('tabs') as $tabs) {
            $parent = $outline->parent($tabs);

            if ($parent !== null && $parent['tag'] === 'atom:page') {
                yield [$tabs['offset'], null];
            }
        }
    }
}
