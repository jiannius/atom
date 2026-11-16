<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class PersistedStaticCrumbs extends PageRule
{
    public const ID = 'persisted-static-crumbs';

    public const SEVERITY = DesignCheck::BREAK;

    public function title(): string
    {
        return 'A fixed `:items` trail inside `@persist`, which keeps the first page\'s crumbs';
    }

    public function fix(): string
    {
        return 'Leave a fixed `<atom:breadcrumbs :items="[...]" />` out of `@persist`; only `<atom:breadcrumbs />` (the component\'s own trail) is persisted.';
    }

    public function rule(): string
    {
        return 'never inside `@persist`';
    }

    public function detect(): string
    {
        return 'An `<atom:breadcrumbs>` with `items` or `:items` between an `@persist(` and its `@endpersist`, of any name. A `@persist` with no `@endpersist`, or a trail written by hand, is not seen.';
    }

    /**
     * It reads one tag's attributes and the directive pairs around it, never nesting
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $trails = array_filter($outline->atom('breadcrumbs'), fn ($trail) => $outline->has($trail, 'items'));

        if ($trails === []) {
            return;
        }

        $spans = $this->persisted($file->text());

        foreach ($trails as $trail) {
            foreach ($spans as [$from, $to]) {
                if ($trail['offset'] > $from && $trail['offset'] < $to) {
                    yield [$trail['offset'], 'inside `@persist`: it would keep the first page\'s crumbs'];

                    break;
                }
            }
        }
    }

    /**
     * The offsets each `@persist(` and its `@endpersist` span, the innermost pairs first and a directive with no partner left out
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected function persisted(string $text): array
    {
        preg_match_all('/(?<![@\w])@(persist\s*\(|endpersist\b)/', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $open = [];
        $spans = [];

        foreach ($found as $match) {
            if (str_starts_with($match[1][0], 'persist')) {
                $open[] = $match[0][1];
            } elseif ($open !== []) {
                $spans[] = [array_pop($open), $match[0][1]];
            }
        }

        return $spans;
    }
}
