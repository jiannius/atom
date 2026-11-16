<?php

use Illuminate\Support\Facades\Artisan;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\DesignChecks\Rule;
use Jiannius\Atom\Tests\Support\DesignHost;

describe('design-check: finding the checks', function () {
    it('finds a class for each check in DesignChecks/, the seed checks among them, in report order', function () {
        $rules = (new DesignCheck)->rules();

        expect(array_keys($rules))->toContain('success-button', 'own-overflow')
            ->and(array_keys($rules))->not->toContain('rule', 'file', 'context')
            ->and($rules['own-overflow'])->toBeInstanceOf(Rule::class);
    });

    it('is registered by the provider', function () {
        expect(array_keys(Artisan::all()))->toContain('atom:design-check');
    });

    it('refuses an unknown id for --only', function () {
        expect(fn () => (new DesignCheck)->scan(DesignHost::path('_clean'), ['nope']))->toThrow(InvalidArgumentException::class, 'Unknown check: nope');
    });

    it('scans nothing the same way twice: each scan starts from a clean state', function () {
        $scanner = new DesignCheck;
        $first = $scanner->scan(DesignHost::path('_skips'), ['success-button']);
        $second = $scanner->scan(DesignHost::path('_clean'), ['success-button']);
        $third = $scanner->scan(DesignHost::path('_skips'), ['success-button']);

        expect($first['skipped'])->toBe($third['skipped'])
            ->and(array_column($second['skipped'], 'path'))->not->toContain('resources/views/emails')
            ->and($first['checks']['success-button']['count'])->toBe($third['checks']['success-button']['count']);
    });
});

describe('design-check: the runner and a check', function () {
    it('hands a check the file, keeps its note, and drops its third element from the result', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "<atom:page>\n<atom:button>One</atom:button>\n</atom:page>\n"]);

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-a')]))->scan($dir);

            expect($result['checks']['probe-a']['findings'])->toBe([['file' => 'resources/views/a.blade.php', 'line' => 2, 'text' => '<atom:button>One</atom:button>', 'note' => 'a match']]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('gives every finding a note key, null when the check gave none', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "<atom:button>One</atom:button>\n"]);

        try {
            $findings = (new DesignCheck([DesignHost::probe('probe-bare', ['noNote' => true])]))->scan($dir)['checks']['probe-bare']['findings'];

            expect($findings)->toBe([['file' => 'resources/views/a.blade.php', 'line' => 1, 'text' => '<atom:button>One</atom:button>', 'note' => null]]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('lets a check wait for every file: finish() sees the context and the third element', function () {
        $dir = DesignHost::temp([
            'resources/views/a.blade.php' => "<atom:button>One</atom:button>\n",
            'resources/views/b.blade.php' => "<atom:button>Two</atom:button>\n",
        ]);
        $seen = null;

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-b', ['finish' => function ($findings, $context) use (&$seen) {
                $seen = [$context->get('probe-b'), array_column(array_column($findings, 'data'), 'seen')];

                return array_values(array_filter($findings, fn ($finding) => $finding['file'] !== 'resources/views/a.blade.php'));
            }])]))->scan($dir);

            expect($seen)->toBe([['resources/views/a.blade.php', 'resources/views/b.blade.php'], ['resources/views/a.blade.php', 'resources/views/b.blade.php']])
                ->and(array_column($result['checks']['probe-b']['findings'], 'file'))->toBe(['resources/views/b.blade.php'])
                ->and($result['checks']['probe-b']['findings'][0])->not->toHaveKey('data');
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reports a check that throws and goes on with the rest', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "<atom:button>One</atom:button>\n"]);

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-throws', ['throws' => true]), DesignHost::probe('probe-ok')]))->scan($dir);

            expect($result['errors'])->toBe([['check' => 'probe-throws', 'file' => 'resources/views/a.blade.php', 'error' => 'RuntimeException: boom']])
                ->and($result['checks']['probe-ok']['count'])->toBe(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('runs a check on the kinds it reads only', function () {
        $dir = DesignHost::temp([
            'resources/views/a.blade.php' => "<atom:button>One</atom:button>\n",
            'app/A.php' => "<?php\n// <atom:button>\n\$a = '<atom:button>';\n",
        ]);

        try {
            expect((new DesignCheck([DesignHost::probe('probe-blade')]))->scan($dir)['checks']['probe-blade']['count'])->toBe(1)
                ->and((new DesignCheck([DesignHost::probe('probe-php', ['kinds' => ['php']])]))->scan($dir)['checks']['probe-php']['count'])->toBe(1)
                ->and((new DesignCheck([DesignHost::probe('probe-both', ['kinds' => ['blade', 'php']])]))->scan($dir)['checks']['probe-both']['count'])->toBe(2);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reads a view whose tags do not balance as text only: a structural check skips it, a text check runs', function () {
        $dir = DesignHost::temp([
            'resources/views/open.blade.php' => "<atom:page>\n<atom:card heading=\"Jobs\">\n<atom:button>One</atom:button>\n</atom:page>\n",
            'resources/views/slot.blade.php' => "<atom:page.header title=\"Jobs\">\n<x-slot:actions>\n<atom:button>One</atom:button>\n</atom:page.header>\n",
            'resources/views/fine.blade.php' => "<atom:page>\n<atom:button>One</atom:button>\n</atom:page>\n",
        ]);

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-structural'), DesignHost::probe('probe-text', ['structural' => false])]))->scan($dir);

            expect($result['text_only'])->toBe(['resources/views/open.blade.php', 'resources/views/slot.blade.php'])
                ->and(array_column($result['checks']['probe-structural']['findings'], 'file'))->toBe(['resources/views/fine.blade.php'])
                ->and(array_column($result['checks']['probe-text']['findings'], 'file'))->toBe(['resources/views/fine.blade.php', 'resources/views/open.blade.php', 'resources/views/slot.blade.php'])
                ->and(Artisan::call('atom:design-check', ['--path' => $dir]))->toBe(0)
                ->and(Artisan::output())->toContain('READ AS TEXT ONLY (2)');
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('never reads a tag in a comment or a verbatim block', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "<!-- <atom:button>One</atom:button> -->\n{{-- <atom:button>Two</atom:button> --}}\n@verbatim\n<atom:button>Three</atom:button>\n@endverbatim\n<atom:button>Four</atom:button>\n"]);

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-comments')]))->scan($dir);

            expect(array_column($result['checks']['probe-comments']['findings'], 'line'))->toBe([6]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: failures and a path that is not an app root', function () {
    it('reports a check whose finish() throws, and keeps the findings it had', function () {
        $dir = DesignHost::temp(['resources/views/a.blade.php' => "<atom:button>One</atom:button>\n"]);

        try {
            $result = (new DesignCheck([DesignHost::probe('probe-late', ['throwsInFinish' => true]), DesignHost::probe('probe-ok')]))->scan($dir);

            expect($result['errors'])->toBe([['check' => 'probe-late', 'file' => '(finish)', 'error' => 'RuntimeException: late']])
                ->and($result['checks']['probe-late']['count'])->toBe(1)
                ->and($result['checks']['probe-ok']['count'])->toBe(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('lists the failures like findings: the first 10, then how to see the rest', function () {
        $files = [];

        foreach (range(1, 12) as $i) {
            $files["resources/views/v{$i}.blade.php"] = "<atom:button>x</atom:button>\n";
        }

        $dir = DesignHost::temp($files);

        try {
            $scanner = new DesignCheck([DesignHost::probe('probe-throws', ['throws' => true])]);
            $result = $scanner->scan($dir);

            expect($result['errors'])->toHaveCount(12)
                ->and($scanner->format($result, 10))->toContain('CHECKS THAT FAILED (12)')->toContain('... and 2 more (use --all)')
                ->and(substr_count($scanner->format($result, null), 'RuntimeException: boom'))->toBe(12);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('warns when it read no file, in the report and in the JSON', function () {
        $dir = DesignHost::temp(['app/.keep' => '', 'resources/views/readme.txt' => 'x']);

        try {
            $result = (new DesignCheck)->scan($dir);

            expect($result['warnings'])->toHaveCount(1)->and($result['warnings'][0])->toContain('--path must be the app root')
                ->and((new DesignCheck)->format($result))->toContain('WARNING: No Blade or PHP file was read');
            expect(DesignHost::scan('_clean')['warnings'])->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: what is read, and what is not', function () {
    it('reads the views and the PHP of app/, resources/ and the modules, and nothing under a non-screen path', function () {
        $result = DesignHost::scan('_skips', ['success-button']);
        $skipped = collect($result['skipped'])->mapWithKeys(fn ($skip) => [$skip['path'] => $skip['reason']])->all();

        expect($result['scanned'])->toBe(['blade' => 1, 'php' => 0])
            ->and(DesignHost::found($result))->toBe(DesignHost::expected(DesignHost::path('_skips')))
            ->and(array_keys($skipped))->toEqualCanonicalizing([
                'resources/views/vendor',
                'modules/Billing/resources/views/vendor',
                'resources/views/emails',
                'resources/views/pdf',
                'resources/views/invoices/print',
                'resources/views/Errors',
                'modules/Billing/resources/views/mail',
                'modules/Billing/tests',
                'resources/views/notify.blade.php',
                'tests',
                'config',
                'routes',
                'database',
            ])
            ->and($skipped['resources/views/vendor'])->toContain('atom\'s own markup')
            ->and($skipped['resources/views/emails'])->toContain('not a screen')
            ->and($skipped['resources/views/notify.blade.php'])->toContain('mail');
    });

    it('lists each skipped directory once, not each file in it', function () {
        $dir = DesignHost::copy('_skips');
        file_put_contents($dir.'/resources/views/emails/second.blade.php', '<atom:button variant="success">x</atom:button>');

        try {
            $paths = array_column((new DesignCheck)->scan($dir)['skipped'], 'path');

            expect(array_count_values($paths)['resources/views/emails'])->toBe(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('does not read a directory named for a non-screen outside the views, nor a file named for one', function () {
        $dir = DesignHost::temp([
            'app/Pdf/Invoice.php' => "<?php\n",
            'resources/views/invoices/print.blade.php' => '<atom:button variant="success">x</atom:button>',
            'resources/views/pdf.blade.php' => '<atom:button variant="success">x</atom:button>',
            'resources/views/livewire/mailing/index.blade.php' => '<atom:button variant="success">x</atom:button>',
        ]);

        try {
            $result = (new DesignCheck)->scan($dir, ['success-button']);

            expect($result['checks']['success-button']['count'])->toBe(3);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('holds the base skips: node_modules, a symlink, a file over 2 MB, a minified file, Composer\'s vendor', function () {
        $dir = DesignHost::copy('_skips');
        $outside = DesignHost::temp(['leak.blade.php' => '<atom:button variant="success">x</atom:button>', 'dir/leak.blade.php' => '<atom:button variant="success">x</atom:button>']);
        $button = '<atom:button variant="success">x</atom:button>';

        mkdir($dir.'/resources/views/node_modules/pkg', 0777, true);
        file_put_contents($dir.'/resources/views/node_modules/pkg/x.blade.php', $button);
        file_put_contents($dir.'/resources/views/big.blade.php', $button."\n".str_repeat("<p>filler</p>\n", 200000));
        mkdir($dir.'/modules/Billing/vendor/composer', 0777, true);
        file_put_contents($dir.'/modules/Billing/vendor/composer/x.blade.php', $button);
        symlink($outside.'/leak.blade.php', $dir.'/resources/views/linked.blade.php');
        symlink($outside.'/dir', $dir.'/resources/views/linked-dir');

        try {
            $result = (new DesignCheck)->scan($dir, ['success-button']);
            $skipped = array_column($result['skipped'], 'reason', 'path');

            expect(array_column($result['checks']['success-button']['findings'], 'file'))->toBe(['resources/views/screen.blade.php'])
                ->and($skipped['resources/views/big.blade.php'])->toBe('over 2 MB')
                ->and($skipped['resources/views/linked.blade.php'])->toBe('symlink')
                ->and($skipped['resources/views/linked-dir'])->toBe('symlink')
                ->and(array_keys($skipped))->not->toContain('resources/views/node_modules');
        } finally {
            DesignHost::remove($dir);
            DesignHost::remove($outside);
        }
    });

    it('does not list a third-party script or an oversize stylesheet: they are no business of a design check', function () {
        $dir = DesignHost::temp([
            'resources/js/vendor/lib.js' => 'x',
            'resources/css/app.css' => str_repeat("a { b: c }\n", 300000),
            'resources/views/a.blade.php' => '<p>a</p>',
        ]);

        try {
            expect((new DesignCheck)->scan($dir)['skipped'])->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reads a directory called vendor that is the host\'s own, outside the views', function () {
        $dir = DesignHost::temp(['resources/views/livewire/vendor/index.blade.php' => '<atom:button variant="success">x</atom:button>']);

        try {
            expect((new DesignCheck)->scan($dir, ['success-button'])['checks']['success-button']['count'])->toBe(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('writes nothing anywhere in the host, storage/ included: it never compiles a view', function () {
        $dir = DesignHost::copy('_markers');
        @mkdir($dir.'/storage/framework/views', 0777, true);

        // point Blade's cache at the host, the way a real run on base_path() would
        config(['view.compiled' => $dir.'/storage/framework/views']);

        foreach (['blade.compiler', 'view.engine.resolver', 'view'] as $abstract) {
            $this->app->forgetInstance($abstract);
            Illuminate\Support\Facades\Blade::clearResolvedInstance($abstract);
        }

        $before = DesignHost::snapshot($dir);

        try {
            Artisan::call('atom:design-check', ['--path' => $dir]);
            Artisan::call('atom:design-check', ['--path' => $dir, '--json' => true]);
            Artisan::call('atom:design-check', ['--path' => $dir, '--all' => true, '--only' => 'success-button']);

            expect(DesignHost::snapshot($dir))->toBe($before)
                ->and(glob($dir.'/storage/framework/views/*'))->toBe([]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});
