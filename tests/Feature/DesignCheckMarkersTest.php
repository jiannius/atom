<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * A check that reads PHP, so a marker in a PHP file has a finding to suppress: it flags each `return '` line.
 */
$phpProbe = ['kinds' => ['php'], 'structural' => false, 'pattern' => "/return '/"];

describe('design-check markers', function () {
    beforeEach(function () {
        $this->result = DesignHost::scan('_markers', ['success-button', 'own-overflow']);
        $this->suppressed = collect($this->result['suppressed'])->map(fn ($item) => $item['file'].':'.$item['line'].':'.$item['id'])->all();
    });

    it('leaves exactly the marked lines: a marker covers one line, an HTML comment is no marker', function () {
        expect(DesignHost::found($this->result))->toBe(DesignHost::expected(DesignHost::path('_markers')));
    });

    it('ignores the next line that has code', function () {
        expect($this->suppressed)->toContain('resources/views/ignore.blade.php:4:success-button');
    });

    it('ignores the line a marker trails', function () {
        expect($this->suppressed)->toContain('resources/views/ignore.blade.php:6:success-button');
    });

    it('takes several ids, and skips blank lines to the next line with code', function () {
        expect($this->suppressed)->toContain('resources/views/ignore.blade.php:10:success-button');
    });

    it('covers a region from `off` to `on`, and only that', function () {
        expect($this->suppressed)->toContain('resources/views/ignore.blade.php:15:success-button')
            ->toContain('resources/views/ignore.blade.php:16:success-button')
            ->not->toContain('resources/views/ignore.blade.php:18:success-button');
    });

    it('runs an `off` with no `on` to the end of the file', function () {
        expect($this->suppressed)->toContain('resources/views/off-to-end.blade.php:3:success-button')
            ->toContain('resources/views/off-to-end.blade.php:4:success-button');
    });

    it('covers every check from `off all`, and does not judge it unused', function () {
        expect($this->suppressed)->toContain('resources/views/off-all.blade.php:3:success-button')
            ->toContain('resources/views/off-all.blade.php:4:own-overflow');
    });

    it('keeps the reason beside each suppression', function () {
        $reasons = collect($this->result['suppressed'])->mapWithKeys(fn ($item) => [$item['file'].':'.$item['line'] => $item['reason']]);

        expect($reasons['resources/views/ignore.blade.php:4'])->toBe('the export is the next step here')
            ->and($reasons['resources/views/off-all.blade.php:3'])->toBe('a printed statement, not a screen');
    });

    it('still honours a marker with no reason, and reports it', function () {
        expect($this->suppressed)->toContain('resources/views/ignore.blade.php:21:success-button')
            ->and(DesignHost::problems($this->result))->toContain(['resources/views/ignore.blade.php', null, 'no reason given']);
    });

    it('reports an unknown id, and does not apply it', function () {
        expect(DesignHost::problems($this->result))->toContain(['resources/views/ignore.blade.php', 'no-such-check', 'unknown check id'])
            ->and(DesignHost::problems($this->result))->toContain(['app/Tags.php', 'bogus-id', 'unknown check id'])
            ->and($this->suppressed)->not->toContain('resources/views/ignore.blade.php:27:success-button');
    });

    it('reports an ignore that suppressed nothing, for a check that ran', function () {
        expect(DesignHost::problems($this->result))
            ->toContain(['resources/views/ignore.blade.php', 'success-button', 'unused: `ignore` suppressed nothing'])
            ->toContain(['resources/views/ignore.blade.php', 'own-overflow', 'unused: `ignore` suppressed nothing']);
    });

    it('does not judge a marker for a check that did not run: another --only, another file kind', function () {
        $only = DesignHost::scan('_markers', ['own-overflow']);

        // success-button is not in this run: its markers are neither applied nor unused
        expect(DesignHost::problems($only))->not->toContain(['resources/views/ignore.blade.php', 'success-button', 'unused: `ignore` suppressed nothing'])
            ->and(array_unique(array_column($only['suppressed'], 'id')))->toBe(['own-overflow'])
            // and the PHP marker for a check that reads no PHP is not unused either
            ->and(array_filter(DesignHost::problems($this->result), fn ($problem) => $problem[0] === 'app/Tags.php' && str_contains($problem[2], 'unused')))->toBe([]);
    });

    it('does not judge a marker for a structural check on a view read as text only', function () {
        $dir = DesignHost::copy('_markers');
        file_put_contents($dir.'/resources/views/ignore.blade.php', "<atom:card heading=\"Jobs\">\n{{-- atom:design-check ignore own-overflow: kept --}}\n<atom:dropdown><atom:button icon=\"more\"/></atom:dropdown>\n");

        try {
            $result = (new DesignCheck)->scan($dir, ['own-overflow']);

            expect($result['text_only'])->toContain('resources/views/ignore.blade.php')
                ->and(array_filter(DesignHost::problems($result), fn ($problem) => $problem[0] === 'resources/views/ignore.blade.php'))->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reports `all` on an ignore, and an `on` with no `off`', function () {
        expect(DesignHost::problems($this->result))->toContain(['resources/views/ignore.blade.php', null, '`all` is for `off` only: name the checks to ignore'])
            ->toContain(['resources/views/ignore.blade.php', null, '`on` with no matching `off` before it']);
    });
});

describe('design-check markers in PHP', function () use ($phpProbe) {
    it('suppresses a PHP finding from a marker above, a trailing one and a block comment', function () use ($phpProbe) {
        $dir = sys_get_temp_dir().'/atom-design-php-'.bin2hex(random_bytes(4));
        mkdir($dir.'/app', 0777, true);
        file_put_contents($dir.'/app/A.php', <<<'PHP'
        <?php

        class A
        {
            public function a()
            {
                // atom:design-check ignore php-probe: the line comment above
                return 'a';
            }

            public function b()
            {
                return 'b'; # atom:design-check ignore php-probe: a trailing hash comment
            }

            public function c()
            {
                /* atom:design-check ignore php-probe: a block comment */
                return 'c';
            }

            public function d()
            {
                /**
                 * atom:design-check ignore php-probe: a docblock
                 */
                return 'd';
            }

            public function e()
            {
                return 'e';
            }

            public function f()
            {
                $x = 'atom:design-check ignore php-probe: a string is no marker';
                return 'f';
            }
        }
        PHP);

        try {
            $result = (new DesignCheck([DesignHost::probe('php-probe', $phpProbe)]))->scan($dir);

            expect(collect($result['checks']['php-probe']['findings'])->pluck('line')->all())->toBe([32, 38])
                ->and(collect($result['suppressed'])->pluck('line')->all())->toBe([8, 13, 19, 27])
                ->and($result['markers'])->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reports a PHP marker that suppressed nothing when the check reads PHP', function () use ($phpProbe) {
        $dir = sys_get_temp_dir().'/atom-design-php-'.bin2hex(random_bytes(4));
        mkdir($dir.'/app', 0777, true);
        file_put_contents($dir.'/app/A.php', "<?php\n// atom:design-check ignore php-probe: nothing below\n\$a = 1;\n");

        try {
            $result = (new DesignCheck([DesignHost::probe('php-probe', $phpProbe)]))->scan($dir);

            expect(DesignHost::problems($result))->toBe([['app/A.php', 'php-probe', 'unused: `ignore` suppressed nothing']]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: text that is not a marker', function () {
    it('does not honour, and does not report, a marker inside an HTML comment or a verbatim block', function () {
        $result = DesignHost::scan('_markers', ['success-button', 'own-overflow']);

        expect(collect($result['checks']['success-button']['findings'])->where('file', 'resources/views/not-markers.blade.php')->pluck('line')->all())->toBe([4, 9])
            ->and(array_filter(DesignHost::problems($result), fn ($problem) => $problem[0] === 'resources/views/not-markers.blade.php'))->toBe([]);
    });

    it('does not read a marker from a string in a PHP file', function () {
        $dir = DesignHost::temp(['app/A.php' => "<?php\n\$a = '// atom:design-check ignore php-probe: in a string';\nreturn 'x';\n"]);

        try {
            $result = (new DesignCheck([DesignHost::probe('php-probe', ['kinds' => ['php'], 'structural' => false, 'pattern' => "/return '/"])]))->scan($dir);

            expect($result['checks']['php-probe']['count'])->toBe(1)->and($result['markers'])->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: an `on` closes by id', function () {
    it('ends only the ids it names, and a bare `on` or `on all` ends them all', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => implode("\n", [
            '{{-- atom:design-check off success-button, own-overflow: both --}}',                                            // 1
            '<atom:button variant="success">a</atom:button>',                                                              // 2 suppressed
            '<atom:dropdown><atom:button icon="more"/></atom:dropdown>',                                                   // 3 suppressed
            '{{-- atom:design-check on success-button --}}',                                                               // 4
            '<atom:button variant="success">b</atom:button>',                                                              // 5 found
            '<atom:dropdown><atom:button icon="more"/></atom:dropdown>',                                                   // 6 suppressed
            '{{-- atom:design-check on --}}',                                                                              // 7
            '<atom:dropdown><atom:button icon="more"/></atom:dropdown>',                                                   // 8 found
            '{{-- atom:design-check off success-button: again --}}',                                                       // 9
            '<atom:button variant="success">c</atom:button>',                                                              // 10 suppressed
            '{{-- atom:design-check on all --}}',                                                                          // 11
            '<atom:button variant="success">d</atom:button>',                                                              // 12 found
            '{{-- atom:design-check on own-overflow --}}',                                                                 // 13 nothing open: reported
            '',
        ])]);

        try {
            $result = (new DesignCheck)->scan($dir, ['success-button', 'own-overflow']);

            expect(array_map(fn ($f) => $f['line'], $result['checks']['success-button']['findings']))->toBe([5, 12])
                ->and(array_map(fn ($f) => $f['line'], $result['checks']['own-overflow']['findings']))->toBe([8])
                ->and(DesignHost::problems($result))->toBe([['resources/views/a.blade.php', null, '`on` with no matching `off` before it']])
                ->and($result['markers'][0]['line'])->toBe(13);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reports an `off <ids>` that suppressed nothing, as it does an `ignore`', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "{{-- atom:design-check off own-overflow: nothing here --}}\n<atom:button>x</atom:button>\n"]);

        try {
            expect(DesignHost::problems((new DesignCheck)->scan($dir, ['own-overflow'])))->toBe([['resources/views/a.blade.php', 'own-overflow', 'unused: `off` suppressed nothing']]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});
