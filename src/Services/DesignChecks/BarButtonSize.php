<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\DesignCheck;

class BarButtonSize extends ActionRule
{
    public const ID = 'bar-button-size';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Actions';

    /**
     * The sizes that are smaller than the control height
     *
     * @var array<int, string>
     */
    protected const SMALL = ['sm', 'xs'];

    public function title(): string
    {
        return 'A small button in a toolbar or bulk bar';
    }

    public function fix(): string
    {
        return 'Drop `size` from the `<atom:button>`: toolbar and bulk-bar buttons are the control height, like the search beside them.';
    }

    public function rule(): string
    {
        return 'Toolbar and bulk-bar buttons are the default size';
    }

    public function detect(): string
    {
        return 'An `<atom:button>` with a static `size="sm"` or `size="xs"` (or a bare `sm` or `xs`) in a `<atom:table.toolbar>` or in the table\'s `checked` slot, with nothing but markup, a `<atom:button.group>` or an `<atom:dropdown>` between. A bound `size`, a button in a row, a header or a shell slot, and one inside a menu or a popover are not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('button') as $button) {
            $size = $this->small($outline, $button);

            if ($size !== null && ($bar = $this->bar($outline, $button)) !== null) {
                yield [$button['offset'], $size.' in '.$bar.': its buttons are the control height'];
            }
        }
    }

    /**
     * The small size a button asks for, as it is written (`size="sm"`, or a bare `xs`), or null
     */
    protected function small(BladeOutline $outline, array $button): ?string
    {
        if ($outline->has($button, 'size')) {
            $size = $outline->value($button, 'size');

            return in_array($size, static::SMALL, true) ? '`size="'.$size.'"`' : null;
        }

        foreach (static::SMALL as $size) {
            if (($button['attributes'][$size] ?? null) === true) {
                return '`'.$size.'`';
            }
        }

        return null;
    }

    /**
     * The bar a button is the own of: the toolbar or the bulk bar, or null
     */
    protected function bar(BladeOutline $outline, array $button): ?string
    {
        foreach ($outline->ancestors($button) as $ancestor) {
            if ($this->isWrapper($ancestor) || in_array($ancestor['tag'], ['atom:button.group', 'atom:dropdown'], true)) {
                continue;
            }

            if ($ancestor['kind'] === 'slot') {
                $owner = $outline->owner($ancestor)['tag'] ?? null;

                return match (true) {
                    $owner === 'atom:table.toolbar' && in_array($this->slotKey($ancestor), ['actions', 'end'], true) => 'a toolbar',
                    $owner === 'atom:table' && $this->slotKey($ancestor) === 'checked' => 'a bulk bar',
                    default => null,
                };
            }

            return $ancestor['tag'] === 'atom:table.toolbar' ? 'a toolbar' : null;
        }

        return null;
    }
}
