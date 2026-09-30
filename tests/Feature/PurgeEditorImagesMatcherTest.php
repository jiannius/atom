<?php

use Illuminate\Support\Collection;
use Jiannius\Atom\Commands\PurgeEditorImages;

/**
 * Opens the command's matcher up to the tests
 */
class PurgeMatcherProbe extends PurgeEditorImages
{
    /**
     * Index the needles of files with these names, and return them as the
     * reference matcher wants them
     *
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    public function load(array $names): array
    {
        $needles = $this->buildNeedles(['disk' => ['files' => new Collection(array_map(fn ($name) => 'editor/'.$name, $names))]]);
        $this->indexNeedles($needles);

        return $needles;
    }

    /**
     * @return array<int, string>
     */
    public function find(mixed $raw, mixed $decoded): array
    {
        return $this->findNamesIn($raw, $decoded);
    }

    /**
     * @return array<string, string>
     */
    public function tokenNeedlesLeft(): array
    {
        return $this->tokenNeedles;
    }

    /**
     * @return array<string, string>
     */
    public function substringNeedlesLeft(): array
    {
        return $this->substringNeedles;
    }
}

/**
 * The matcher as it was before the token lookup: every needle of every file
 * still unfound against every form of the value, as a plain substring. It is
 * the definition of "referenced" the fast matcher has to agree with, and lives
 * here only.
 *
 * @param  array<string, string>  $needles  needle => file name
 * @return array<int, string>
 */
function purgeReferenceMatch(array $needles, mixed $raw, mixed $decoded): array
{
    $haystacks = [];

    foreach ([$raw, $decoded] as $value) {
        for ($i = 0; is_string($value) && $i < 4; $i++) {
            $haystacks[] = $value;
            $value = rawurldecode($value);
        }
    }

    $haystacks = array_unique($haystacks);
    $found = [];

    foreach ($needles as $needle => $name) {
        if (isset($found[$name])) {
            unset($needles[$needle]);
            continue;
        }

        foreach ($haystacks as $haystack) {
            if (str_contains($haystack, (string) $needle)) {
                $found[$name] = true;
                unset($needles[$needle]);
                break;
            }
        }
    }

    return array_keys($found);
}

/**
 * The names the corpus embeds, and a few it never does. None is another's
 * needle (a b.jpg and a+b.jpg would be: the needle map keeps one owner).
 *
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function purgeCorpusNames(): array
{
    mt_srand(11);

    $atom = [];

    for ($i = 0; $i < 8; $i++) {
        $atom[] = strtolower(substr(md5((string) mt_rand()), 0, 20)).'-'.(1700000000 + mt_rand(0, 99999999)).'.'.['jpg', 'png', 'webp'][$i % 3];
    }

    $embedded = [...$atom,
        'snapshot.jpg', 'my photo.jpg', 'x_y-z.v1.webp', 'dot.name.tar.gz', 'tr-ünï.jpg', '日本語.png', 'a&b.jpg',
        'semi;colon.jpg', '(paren).png', '100%.png', 'plus+sign.jpg', 'q"uote.jpg', 'back\\slash.jpg', 'ünï.pdf',
        '[brackets].jpg', 'UPPER.JPG', 'short.gif', 'a b.jpg', 'it\'s.jpg', 'tab	name.jpg',
    ];

    return [$embedded, ['never-embedded-1712345678.jpg', 'orphan ünï 1.png']];
}

/**
 * Stored values in every shape the command meets, as [raw, decoded] pairs: a
 * name inside HTML, JSON, a CSS url(), srcset, poster, a CSV line, a query
 * string, glued to a prefix or suffix, in each encoding, cut off or
 * percent-encoded whole, beside random noise and near-misses of real names.
 * Seeded, so a failure reproduces.
 *
 * @param  array<int, string>  $names  the names to embed
 * @param  array<int, string>  $others  names to only imitate, never embed
 * @return array<int, array{0: string, 1: string}>
 */
function purgeCorpus(int $seed, array $names, array $others, int $count): array
{
    mt_srand($seed);

    $pick = fn (array $list) => $list[mt_rand(0, count($list) - 1)];

    $encoders = [
        fn ($n) => $n,
        fn ($n) => $n,
        fn ($n) => rawurlencode($n),
        fn ($n) => urlencode($n),
        fn ($n) => htmlspecialchars($n, ENT_QUOTES),
        fn ($n) => substr(json_encode($n), 1, -1),
        fn ($n) => substr(json_encode($n, JSON_UNESCAPED_UNICODE), 1, -1),
    ];

    $wrappers = [
        '<img src="https://cdn.test/editor/{n}" alt="">',
        '<img src=https://cdn.test/editor/{n}>',
        "<a href='https://cdn.test/editor/{n}'>x</a>",
        '<img srcset="https://cdn.test/editor/other.jpg 1x, https://cdn.test/editor/{n} 2x">',
        '<video poster="{n}"></video>',
        '<div style="background:url(https://cdn.test/editor/{n})">',
        '<div style="background:url(\'{n}\')">',
        '<div style=\'background:url("{n}")\'>',
        '{"type":"image","attrs":{"src":"https:\\/\\/cdn.test\\/editor\\/{n}"}}',
        '{"src":"https:\\u002f\\u002fcdn.test\\u002feditor\\u002f{n}"}',
        '{"type":"doc","content":[{"type":"text","text":"{n}"},',
        'name,{n},size',
        'https://cdn.test/editor/{n}?v=3#frag',
        'https://cdn.test/editor/{n}&w=200',
        'x/thumb-{n}',
        'x/thumb_{n}',
        'x/{n}.webp',
        'x/{n}-2x',
        'q?file=a+{n}',
        '{n}',
    ];

    $joiners = [' ', "\n", '<br>', ', ', "\t"];
    $alphabet = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ._-+%/\\"\'<>=?#&(),;:{}[]');
    $all = [...$names, ...$others];

    $noise = function () use ($alphabet) {
        $text = '';

        for ($i = 0, $length = mt_rand(1, 160); $i < $length; $i++) {
            $text .= $alphabet[mt_rand(0, count($alphabet) - 1)];
        }

        return $text;
    };

    $nearMiss = function (string $name) use ($pick): string {
        $at = mt_rand(1, strlen($name) - 1);

        $miss = $pick([
            substr($name, 0, -1),
            substr($name, 2),
            substr($name, 0, $at).'Z'.substr($name, $at + 1),
            substr($name, 0, $at).' '.substr($name, $at),
            strtoupper($name) === $name ? strtolower($name) : strtoupper($name),
            substr($name, 0, intdiv(strlen($name), 2)),
        ]);

        // a near-miss that still holds the whole name is a reference, not a miss
        return str_contains($miss, $name) ? substr($name, 1) : $miss;
    };

    $corpus = [];

    for ($i = 0; $i < $count; $i++) {
        $parts = [];

        for ($p = 0, $number = mt_rand(1, 4); $p < $number; $p++) {
            $roll = mt_rand(0, 9);

            if ($roll < 5) {
                $parts[] = str_replace('{n}', $pick($encoders)($pick($names)), $pick($wrappers));
            }
            elseif ($roll < 8) {
                $parts[] = $noise();
            }
            else {
                $parts[] = $nearMiss($pick($all));
            }
        }

        $value = implode($pick($joiners), $parts);

        $value = match (mt_rand(0, 5)) {
            0 => substr($value, 0, mt_rand(0, strlen($value))),
            1 => rawurlencode($value),
            2 => rawurlencode($value),
            default => $value,
        };

        $corpus[] = mt_rand(0, 1) ? [serialize($value), $value] : [$value, $value];
    }

    return $corpus;
}

describe('atom:purge-editor-images token matcher against the substring definition', function () {
    it('keeps exactly the files the substring matcher keeps, value by value', function (int $seed) {
        [$names, $others] = purgeCorpusNames();
        $probe = new PurgeMatcherProbe;
        $mismatches = [];

        foreach (purgeCorpus($seed, $names, $others, 700) as [$raw, $decoded]) {
            $needles = $probe->load([...$names, ...$others]);

            $fast = $probe->find($raw, $decoded);
            $slow = purgeReferenceMatch($needles, $raw, $decoded);
            sort($fast);
            sort($slow);

            if ($fast !== $slow) {
                $mismatches[] = ['value' => $raw, 'fast only' => array_values(array_diff($fast, $slow)), 'slow only' => array_values(array_diff($slow, $fast))];
            }
        }

        expect($mismatches)->toBe([]);
    })->with([1, 2, 3, 4, 5, 6]);

    it('keeps exactly the same set over a whole run, with found names dropped from the search', function (int $seed) {
        [$names, $others] = purgeCorpusNames();
        $probe = new PurgeMatcherProbe;
        $needles = $probe->load([...$names, ...$others]);
        $fast = [];
        $slow = [];

        foreach (purgeCorpus($seed, $names, $others, 700) as [$raw, $decoded]) {
            foreach ($probe->find($raw, $decoded) as $name) {
                $fast[$name] = true;
            }

            foreach (purgeReferenceMatch($needles, $raw, $decoded) as $name) {
                $slow[$name] = true;
            }
        }

        $fast = array_keys($fast);
        $slow = array_keys($slow);
        sort($fast);
        sort($slow);

        expect($fast)->toBe($slow);
        // not vacuous: the corpus references most of the names, and never the ones it only imitates
        expect(count($slow))->toBeGreaterThan(count($names) - 5);
        expect(array_intersect($slow, $others))->toBe([]);
    })->with([21, 22, 23]);

    it('reports a name once and stops looking for it', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['found.jpg', 'still-looking.jpg', 'sp ace.jpg']);

        expect($probe->find('<img src="found.jpg"> <img src="found.jpg?v=2">', null))->toBe(['found.jpg']);
        expect(array_keys($probe->tokenNeedlesLeft()))->not->toContain('found.jpg');
        expect(array_keys($probe->tokenNeedlesLeft()))->toContain('still-looking.jpg');
        expect($probe->find('found.jpg', null))->toBe([]);
    });

    it('searches a needle that holds a delimiter as a substring, and only those', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['sp ace.jpg', 'plain.jpg']);

        expect(array_keys($probe->substringNeedlesLeft()))->toBe(['sp ace.jpg']);
        expect(array_keys($probe->tokenNeedlesLeft()))->toContain('plain.jpg', 'sp%20ace.jpg', 'sp+ace.jpg');
        expect($probe->find('<p>files/sp ace.jpg</p>', null))->toBe(['sp ace.jpg']);
    });

    it('finds a name only the decoded form of a value holds', function () {
        // the raw value holds no name at all: only the decoded one does
        $probe = new PurgeMatcherProbe;
        $probe->load(['decoded-only.jpg', 'orphan.jpg']);

        expect($probe->find('nothing here', '<img src="https://cdn.test/editor/decoded-only.jpg">'))->toBe(['decoded-only.jpg']);
    });

    it('finds a name only the raw form of a value holds', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['raw-only.jpg', 'orphan.jpg']);

        expect($probe->find('<img src="https://cdn.test/editor/raw-only.jpg">', ['a' => 'not a string']))->toBe(['raw-only.jpg']);
    });

    it('falls back to the substring search when a value cannot be split', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['fallback.jpg', 'orphan.jpg']);
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');

        try {
            ini_set('pcre.jit', '0');
            ini_set('pcre.backtrack_limit', '1');
            $long = str_repeat('word ', 200).'<img src="fallback.jpg">';

            // the split really fails here, or this test proves nothing
            expect(preg_split('/[^a-z]+/', $long, -1, PREG_SPLIT_NO_EMPTY))->toBeFalse();
            expect($probe->find($long, null))->toBe(['fallback.jpg']);
        }
        finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    });
});
