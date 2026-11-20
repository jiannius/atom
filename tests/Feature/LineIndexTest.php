<?php

use Jiannius\Atom\Services\LineIndex;

it('lists where each line starts', function () {
    expect(LineIndex::starts(''))->toBe([0])
        ->and(LineIndex::starts('abc'))->toBe([0])
        ->and(LineIndex::starts("a\nbb\n\nc"))->toBe([0, 2, 5, 6])
        ->and(LineIndex::starts("a\n"))->toBe([0, 2])
        ->and(LineIndex::starts("a\r\nb"))->toBe([0, 3]);
});

it('names the 1-based line of every offset, a newline included in the line it ends', function () {
    $text = "one\ntwo\n\nfour";
    $starts = LineIndex::starts($text);

    $expected = [1, 1, 1, 1, 2, 2, 2, 2, 3, 4, 4, 4, 4];

    foreach ($expected as $offset => $line) {
        expect(LineIndex::lineAt($starts, $offset))->toBe($line, "offset {$offset}");
    }

    expect(LineIndex::lineAt($starts, 500))->toBe(4);
});

it('agrees with a linear scan on every offset of a random text', function () {
    $random = new Random\Randomizer(new Random\Engine\Mt19937(7));
    $text = '';

    foreach (range(1, 400) as $ignored) {
        $text .= $random->getInt(0, 3) === 0 ? "\n" : 'x';
    }

    $starts = LineIndex::starts($text);

    foreach (range(0, strlen($text) + 3) as $offset) {
        $linear = 1;

        foreach ($starts as $index => $start) {
            if ($start > $offset) {
                break;
            }

            $linear = $index + 1;
        }

        expect(LineIndex::lineAt($starts, $offset))->toBe($linear);
    }
});
