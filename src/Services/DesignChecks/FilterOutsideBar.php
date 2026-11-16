<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class FilterOutsideBar extends ListRule
{
    public const ID = 'filter-outside-bar';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Lists';

    /**
     * Components that bind `filters.*` on purpose, and the ones inside them (`<atom:table.trashed>` binds `_table`, never `filters`)
     *
     * @var array<int, string>
     */
    protected const OWNERS = ['atom:table.filters', 'atom:table.search', 'atom:table.views', 'atom:empty'];

    public function title(): string
    {
        return 'A control that narrows a list sits outside `<atom:table.filters>`';
    }

    public function fix(): string
    {
        return 'Move the control into `<atom:table.filters>`, where it gets a chip and Clear all. A show-inactive toggle is a filter select or `<atom:table.trashed>`, never a button.';
    }

    public function rule(): string
    {
        return 'Every control that narrows a list sits in `<atom:table.filters>`';
    }

    public function detect(): string
    {
        return 'In a view that has an `<atom:table>`: a tag with `wire:model*` or `x-model*` bound to `filters.…`, a `wire:click`, `x-on:` or `@` handler that runs `$set(\'filters.…\')`, `$wire.set(\'filters.…\')` or `$wire.filters.… =`, outside `<atom:table.filters>` (its `more` slot included), `<atom:table.search>`, `<atom:table.views>`, `<atom:empty>` and the table\'s `empty` slot. A view with no table (a chart\'s own filters) is not read, and neither is a binding built from an echo or a bound attribute.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        if ($outline->atom('table') === []) {
            return;
        }

        foreach ($outline->nodes() as $node) {
            $key = $this->boundKey($node);

            if ($key === null || $this->isAllowedHere($file, $node)) {
                continue;
            }

            yield [$node['offset'], 'binds `filters.'.$key.'` outside `<atom:table.filters>`'];
        }
    }

    /**
     * The filter key a tag binds, from its model or its click handler
     */
    protected function boundKey(array $node): ?string
    {
        foreach ($node['attributes'] as $name => $value) {
            if (! is_string($value) || str_contains($value, '{{') || str_contains($value, '{!!')) {
                continue;
            }

            if (preg_match('/^(?:wire:model|x-model)(?:\.|$)/', $name) && preg_match('/^\s*filters\.([A-Za-z_][\w.]*)\s*$/', $value, $found)) {
                return $found[1];
            }

            if (! preg_match('/^(?:wire:click|x-on:|@)/', $name)) {
                continue;
            }

            if (preg_match('/(?:\$set|\$wire\.set)\(\s*[\'"]filters\.([A-Za-z_][\w.]*)/', $value, $found)
                || preg_match('/\$wire\.filters\.([A-Za-z_][\w.]*)\s*=(?!=)/', $value, $found)) {
                return $found[1];
            }
        }

        return null;
    }

    /**
     * Whether the tag is one that binds a filter on purpose: it is, or sits inside, the filter bar, the search, the views
     * or an empty state (a "Clear filters" next step), or is in the table's own `empty` slot
     */
    protected function isAllowedHere(File $file, array $node): bool
    {
        $outline = $file->outline();

        if (in_array($node['tag'], static::OWNERS, true) || $outline->inside($node, static::OWNERS) !== null) {
            return true;
        }

        foreach ($outline->ancestors($node) as $ancestor) {
            if ($ancestor['kind'] === 'slot' && $ancestor['slot'] === 'empty' && ($outline->owner($ancestor)['tag'] ?? null) === 'atom:table') {
                return true;
            }
        }

        return false;
    }
}
