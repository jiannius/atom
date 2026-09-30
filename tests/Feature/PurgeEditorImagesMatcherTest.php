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
     * @return array<string, array<int, string>>
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
     * @return array<string, array<int, string>>
     */
    public function tokenNeedlesLeft(): array
    {
        return $this->tokenNeedles;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function substringNeedlesLeft(): array
    {
        return $this->substringNeedles;
    }
}

/**
 * The forms of a value the matcher searches. Without $extended, the original
 * ones: the raw and decoded value and their rawurldecode()d forms, three levels
 * deep. With it, also what the command added since: each level read with
 * urldecode() too (a "+" read as a space), and each form with
 * its JSON escapes (\uXXXX, \n \t \r \b \f) read as a slash. Written out here
 * on purpose, apart from the command, so the two can disagree.
 *
 * @return array<int, string>
 */
function purgeForms(mixed $raw, mixed $decoded, bool $extended): array
{
    $forms = [];

    foreach ([$raw, $decoded] as $value) {
        if (! is_string($value)) {
            continue;
        }

        // each level is read both ways, so a value urlencoded, rawurlencoded or a mix of the two decodes fully
        $levels = [[$value]];

        for ($i = 0; $i < 3; $i++) {
            $next = [];

            foreach (end($levels) as $form) {
                $next[] = rawurldecode($form);

                if ($extended) {
                    $next[] = urldecode($form);
                }
            }

            $levels[] = array_values(array_unique($next));
        }

        foreach ($levels as $level) {
            foreach ($level as $form) {
                $forms[] = $form;

                if ($extended) {
                    $forms[] = preg_replace('/\\\\(?:u[0-9a-fA-F]{4}|[nrtbf])/', '/', $form);
                }
            }
        }
    }

    return array_unique($forms);
}

/**
 * The matcher as it was before the token lookup: every needle of every file
 * against every form of the value, as a plain substring. It is the definition
 * of "referenced" the fast matcher is held to, and lives here only. One change
 * from the original: a needle maps to a list of names (two files can share
 * one), and a hit finds all of them.
 *
 * @param  array<string, array<int, string>>  $needles  needle => file names
 * @return array<int, string>
 */
function purgeReferenceMatch(array $needles, mixed $raw, mixed $decoded, bool $extended = false): array
{
    $haystacks = purgeForms($raw, $decoded, $extended);
    $found = [];

    foreach ($needles as $needle => $names) {
        foreach ($haystacks as $haystack) {
            if (str_contains($haystack, (string) $needle)) {
                foreach ($names as $name) {
                    $found[$name] = true;
                }

                break;
            }
        }
    }

    return array_keys($found);
}

/**
 * Whether some needle of the name sits in one of the forms with a boundary on
 * both sides: not glued, on either side, to a letter, a digit or a "%". Such an
 * occurrence is one the token matcher must find, so a name it missed while
 * this is true is a bug, not the documented relaxation.
 *
 * @param  array<string, array<int, string>>  $needles
 * @param  array<int, string>  $forms
 */
function purgeHasBoundedOccurrence(string $name, array $needles, array $forms): bool
{
    foreach ($needles as $needle => $names) {
        if (! in_array($name, $names, true)) {
            continue;
        }

        foreach ($forms as $form) {
            for ($at = strpos($form, (string) $needle); $at !== false; $at = strpos($form, (string) $needle, $at + 1)) {
                $left = $at > 0 ? $form[$at - 1] : '';
                $right = $form[$at + strlen((string) $needle)] ?? '';

                if (! preg_match('/[A-Za-z0-9%]/', $left) && ! preg_match('/[A-Za-z0-9%]/', $right)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * The names the corpus embeds, and a few it never does. a b.jpg and a+b.jpg
 * are here on purpose: the first's urlencode()d needle is the second's name.
 *
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function purgeCorpusNames(): array
{
    $random = new \Random\Randomizer(new \Random\Engine\Mt19937(11));
    $atom = [];

    for ($i = 0; $i < 8; $i++) {
        $atom[] = 'q'.strtolower(substr(md5((string) $random->getInt(0, PHP_INT_MAX)), 0, 19)).'-'.(1700000000 + $random->getInt(0, 99999999)).'.'.['jpg', 'png', 'webp'][$i % 3];
    }

    $embedded = [...$atom,
        'snapshot.jpg', 'my photo.jpg', 'x_y-z.v1.webp', 'dot.name.tar.gz', 'tr-ünï.jpg', '日本語.png', 'a&b.jpg',
        'semi;colon.jpg', '(paren).png', '100%.png', 'plus+sign.jpg', 'q"uote.jpg', 'back\\slash.jpg', 'ünï.pdf',
        '[brackets].jpg', 'UPPER.JPG', 'short.gif', 'a b.jpg', 'a+b.jpg', 'it\'s.jpg', 'tab	name.jpg',
        'Screenshot 2026-09-30 at 10.00.00.png', '图片 (1).jpg',
    ];

    return [$embedded, ['never-embedded-1712345678.jpg', 'orphan ünï 1.png']];
}

/**
 * Stored values in every shape the command meets, as [raw, decoded] pairs: a
 * name inside HTML, JSON, a CSS url(), srcset, poster, a CSV line, a query
 * string, beside CJK punctuation, curly quotes, NBSP, U+3000 and emoji, behind
 * a JSON escape, glued to a prefix or suffix at a boundary, in each encoding,
 * cut off or percent-encoded whole (rawurlencode or urlencode, up to three
 * levels), beside random noise and near-misses of real names. With $glued, also
 * a name glued straight onto letters and digits and encoded four levels deep,
 * the shapes the token rule gave up on purpose. Seeded through its own engine,
 * so a failure reproduces and nothing else's random numbers move.
 *
 * @param  array<int, string>  $names  the names to embed
 * @param  array<int, string>  $others  names to only imitate, never embed
 * @return array<int, array{0: string, 1: string}>
 */
function purgeCorpus(int $seed, array $names, array $others, int $count, bool $glued = false): array
{
    $random = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
    $int = fn (int $min, int $max) => $random->getInt($min, $max);
    $pick = fn (array $list) => $list[$random->getInt(0, count($list) - 1)];

    // [encoder, whether it adds a level of percent-encoding]
    $encoders = [
        [fn ($n) => $n, false],
        [fn ($n) => $n, false],
        [fn ($n) => rawurlencode($n), true],
        [fn ($n) => urlencode($n), true],
        [fn ($n) => htmlspecialchars($n, ENT_QUOTES), false],
        [fn ($n) => substr(json_encode($n), 1, -1), false],
        [fn ($n) => substr(json_encode($n, JSON_UNESCAPED_UNICODE), 1, -1), false],
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
        // CJK punctuation, curly quotes, NBSP, U+3000, emoji, fullwidth brackets
        '图片：/editor/{n}，请看',
        '“{n}”',
        '（{n}）',
        "\xC2\xA0{n}\xC2\xA0",
        "\xE3\x80\x80{n}\xE3\x80\x80",
        '😀{n}😀',
        '中文{n}文中',
        // JSON control escapes and \uXXXX of a non-ASCII character glued to a name
        '{"text":"line\\n{n}\\tnext\\r{n}\\b\\f{n}"}',
        '{"a":"\\u4e2d{n}\\u6587"}',
        '{"a":"\\u002f{n}\\u0022"}',
        '{"src":"https:\\u002F\\u002Fcdn.test\\u002Feditor\\u002F{n}"}',
        '{"a":"\\u4E2D{n}\\u6587"}',
    ];

    $glueWrappers = ['x{n}', '{n}z', 'img123{n}', '{n}9', 'a{n}.bak', '{n}x-2x'];

    $joiners = [' ', "\n", '<br>', ', ', "\t", "\xC2\xA0", "\xE3\x80\x80", '，', '。', "\r\n"];
    $alphabet = mb_str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ._-+%/\\"\'<>=?#&(),;:{}[]中文é😀“”：，');
    $all = [...$names, ...$others];

    $noise = function () use ($alphabet, $int) {
        $text = '';

        for ($i = 0, $length = $int(1, 160); $i < $length; $i++) {
            $text .= $alphabet[$int(0, count($alphabet) - 1)];
        }

        return $text;
    };

    $nearMiss = function (string $name) use ($pick, $int): string {
        $at = $int(1, strlen($name) - 1);

        $miss = $pick([
            substr($name, 0, -1),
            substr($name, 3),
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
        $encoded = false;

        for ($p = 0, $number = $int(1, 4); $p < $number; $p++) {
            $roll = $int(0, 9);

            if ($roll < 5) {
                [$encode, $adds] = $pick($encoders);
                $encoded = $encoded || $adds;
                $parts[] = str_replace('{n}', $encode($pick($names)), $pick($glued && $int(0, 3) === 0 ? $glueWrappers : $wrappers));
            }
            elseif ($roll < 8) {
                $parts[] = $noise();
            }
            else {
                $parts[] = $nearMiss($pick($all));
            }
        }

        $value = implode($pick($joiners), $parts);

        // a name encoded inside a value encoded whole is one level deeper, and three is as far as the command decodes
        $levels = $int(1, $glued ? 4 : ($encoded ? 2 : 3));

        $value = match ($int(0, 7)) {
            0 => substr($value, 0, $int(0, strlen($value))),
            1, 2 => array_reduce(range(1, $levels), fn ($carry) => rawurlencode($carry), $value),
            3, 4 => array_reduce(range(1, $levels), fn ($carry) => urlencode($carry), $value),
            5 => rawurlencode(urlencode($value)),
            default => $value,
        };

        $corpus[] = $int(0, 1) ? [serialize($value), $value] : [$value, $value];
    }

    return $corpus;
}

describe('atom:purge-editor-images token matcher against the substring definition', function () {
    it('keeps exactly what the substring matcher keeps over the same forms, value by value', function (int $seed) {
        [$names, $others] = purgeCorpusNames();
        $probe = new PurgeMatcherProbe;
        $mismatches = [];

        foreach (purgeCorpus($seed, $names, $others, 700) as [$raw, $decoded]) {
            $needles = $probe->load([...$names, ...$others]);

            $fast = $probe->find($raw, $decoded);
            $slow = purgeReferenceMatch($needles, $raw, $decoded, true);
            $original = purgeReferenceMatch($needles, $raw, $decoded);
            sort($fast);
            sort($slow);

            if ($fast !== $slow || array_diff($original, $fast)) {
                $mismatches[] = ['value' => $raw, 'fast only' => array_values(array_diff($fast, $slow)), 'slow only' => array_values(array_diff($slow, $fast)), 'lost from the original' => array_values(array_diff($original, $fast))];
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

            foreach (purgeReferenceMatch($needles, $raw, $decoded, true) as $name) {
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

    it('keeps a superset of the original matcher, losing only names glued to letters, digits or a percent escape', function (int $seed) {
        [$names, $others] = purgeCorpusNames();
        $probe = new PurgeMatcherProbe;
        $unexplained = [];
        $relaxed = 0;

        foreach (purgeCorpus($seed, $names, $others, 700, true) as [$raw, $decoded]) {
            $needles = $probe->load([...$names, ...$others]);
            $fast = $probe->find($raw, $decoded);
            $forms = purgeForms($raw, $decoded, true);

            foreach (array_diff(purgeReferenceMatch($needles, $raw, $decoded), $fast) as $lost) {
                $relaxed++;

                // the only allowed loss: every occurrence is glued. One with a boundary on both sides must have been found.
                if (purgeHasBoundedOccurrence($lost, $needles, $forms)) {
                    $unexplained[] = ['value' => $raw, 'lost' => $lost];
                }
            }
        }

        expect($unexplained)->toBe([]);
        // not vacuous: the glued shapes really are in the corpus, and really are lost
        expect($relaxed)->toBeGreaterThan(20);
    })->with([31, 32, 33, 34]);

    it('does not move the global random generator', function () {
        mt_srand(99);
        $expected = mt_rand();
        mt_srand(99);

        [$names, $others] = purgeCorpusNames();
        purgeCorpus(1, $names, $others, 50, true);

        expect(mt_rand())->toBe($expected);
    });

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

    it('searches a name with a non-ASCII character as a substring, and its percent-encoded form as a token', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['日本語.png', 'tr-ünï.jpg']);

        expect(array_keys($probe->substringNeedlesLeft()))->toContain('日本語.png', 'tr-ünï.jpg');
        expect(array_keys($probe->tokenNeedlesLeft()))->toContain(rawurlencode('日本語.png'), rawurlencode('tr-ünï.jpg'));
        expect(array_keys($probe->tokenNeedlesLeft()))->not->toContain('日本語.png');
    });

    it('finds a name beside CJK punctuation, curly quotes, NBSP, U+3000 and emoji', function (string $value) {
        $probe = new PurgeMatcherProbe;
        $probe->load(['glued-cjk.jpg', 'orphan.jpg']);

        expect($probe->find($value, null))->toBe(['glued-cjk.jpg']);
    })->with([
        'fullwidth colon and comma' => '图片：/editor/glued-cjk.jpg，请看',
        'curly quotes' => '“glued-cjk.jpg”',
        'fullwidth brackets' => '（glued-cjk.jpg）',
        'NBSP' => "\xC2\xA0glued-cjk.jpg\xC2\xA0",
        'U+3000' => "\xE3\x80\x80glued-cjk.jpg\xE3\x80\x80",
        'emoji' => '😀glued-cjk.jpg😀',
        'CJK text' => '中文glued-cjk.jpg文中',
    ]);

    it('finds a name behind a JSON control escape', function (string $value) {
        $probe = new PurgeMatcherProbe;
        $probe->load(['glued-esc.jpg', 'orphan.jpg']);

        expect($probe->find($value, null))->toBe(['glued-esc.jpg']);
    })->with([
        'newline' => '{"text":"line\\nglued-esc.jpg"}',
        'tab' => '{"text":"a\\tglued-esc.jpg"}',
        'carriage return' => '{"text":"a\\rglued-esc.jpg"}',
        'backspace' => '{"text":"a\\bglued-esc.jpg"}',
        'form feed' => '{"text":"a\\fglued-esc.jpg"}',
        'unicode escape' => '{"text":"a\\u002fglued-esc.jpg"}',
        'upper-case unicode escape' => '{"text":"a\\u002Fglued-esc.jpg"}',
    ]);

    it('finds a spaced name inside a value that is urlencoded whole, a space as a plus', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['my photo (1).jpg', 'orphan.jpg']);

        expect($probe->find('%3Cimg+src%3D%22%2Feditor%2Fmy+photo+%281%29.jpg', null))->toBe(['my photo (1).jpg']);
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

    it('does not abort when the JSON-escape variant cannot be made, and still searches the value itself', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['fallback.jpg', 'orphan.jpg']);
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');

        try {
            ini_set('pcre.jit', '0');
            ini_set('pcre.backtrack_limit', '1');
            // holds a backslash, so the variant is tried, and PCRE gives up on it
            $long = str_repeat('word ', 200).'\\u002f<img src="fallback.jpg">';

            expect(preg_replace('/\\\\(?:u[0-9a-fA-F]{4}|[nrtbf])/', '/', $long))->toBeNull();
            expect($probe->find($long, null))->toBe(['fallback.jpg']);
        }
        finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    });

    it('keeps the memory of a scan near the size of a huge value, whatever it is dense in', function (string $value) {
        if (! function_exists('memory_reset_peak_usage')) {
            $this->markTestSkipped('memory_reset_peak_usage() needs PHP 8.2');
        }

        $probe = new PurgeMatcherProbe;
        $probe->load(['bounded-memory.jpg', 'orphan.jpg']);

        gc_collect_cycles();
        memory_reset_peak_usage();
        $base = memory_get_usage();

        $found = $probe->find($value.' bounded-memory.jpg', null);

        $peak = memory_get_peak_usage() - $base;

        // it still finds the name, and holds a few copies of the value at most, never the tens the token lists and forms once cost
        expect($found)->toBe(['bounded-memory.jpg']);
        expect($peak)->toBeLessThan(8 * strlen($value));
    })->with([
        'dense in %, + and a' => [str_repeat('%2B%2B+a', 140000)],
        'dense in boundaries' => [str_repeat('a-', 600000)],
        'plain words' => [str_repeat('lorem ipsum ', 100000)],
    ]);

    it('keeps every file a shared needle stands for', function () {
        // a b.jpg urlencodes to a+b.jpg, which is also the name of another file
        $probe = new PurgeMatcherProbe;
        $probe->load(['a b.jpg', 'a+b.jpg', 'orphan.jpg']);

        expect($probe->tokenNeedlesLeft()['a+b.jpg'])->toEqualCanonicalizing(['a b.jpg', 'a+b.jpg']);
        expect($probe->find('<img src="https://cdn.test/editor/a+b.jpg">', null))->toEqualCanonicalizing(['a b.jpg', 'a+b.jpg']);
    });

    it('keeps every file a shared needle stands for, whichever way the needle is found', function () {
        // no extension, so the shared needle "a%20b" has no boundary and is a whole token
        $whole = new PurgeMatcherProbe;
        $whole->load(['a b', 'a%20b']);
        expect($whole->find('<img src=/x/a%20b>', null))->toEqualCanonicalizing(['a b', 'a%20b']);

        // q"x.jpg json-encodes to q\"x.jpg, another file's name; both hold a delimiter, so both are substring needles
        $substring = new PurgeMatcherProbe;
        $substring->load(['q"x.jpg', 'q\\"x.jpg']);
        expect(array_keys($substring->substringNeedlesLeft()))->toContain('q\\"x.jpg');
        expect($substring->find('{"text":"see q\\"x.jpg"}', null))->toEqualCanonicalizing(['q"x.jpg', 'q\\"x.jpg']);
    });

    it('keeps looking for the other file when one that shares a needle is found first', function () {
        $probe = new PurgeMatcherProbe;
        $probe->load(['a b.jpg', 'a+b.jpg']);

        // "a b.jpg" written out is found through its own needle only: the shared one must stay for the next value
        expect($probe->find('files/a b.jpg', null))->toBe(['a b.jpg']);
        expect($probe->find('files/a+b.jpg', null))->toBe(['a+b.jpg']);
    });
});
