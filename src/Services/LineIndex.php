<?php

namespace Jiannius\Atom\Services;

/**
 * Turns a byte offset in a text into a 1-based line number: the offsets at which each line starts are listed once, then looked up by bisection.
 */
class LineIndex
{
    /**
     * Byte offsets at which each line starts
     *
     * @return array<int, int>
     */
    public static function starts(string $source): array
    {
        $starts = [0];
        $offset = 0;

        while (($position = strpos($source, "\n", $offset)) !== false) {
            $starts[] = $position + 1;
            $offset = $position + 1;
        }

        return $starts;
    }

    /**
     * The 1-based line an offset is on
     *
     * @param  array<int, int>  $starts  from starts()
     */
    public static function lineAt(array $starts, int $offset): int
    {
        $low = 0;
        $high = count($starts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($starts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low + 1;
    }
}
