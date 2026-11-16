<?php

use Illuminate\Support\Facades\Artisan;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\DesignChecks\Context;
use Jiannius\Atom\Services\DesignChecks\File;
use Jiannius\Atom\Services\DesignChecks\Rule;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * A check made in a test, to hold the runner to what it promises one. `ID` is a constant, so each probe is its own
 * class, compiled with its id; it flags every `<atom:button`, leaves its file in the context and hands `finish()` a third element.
 *
 * @param  array<string, mixed>  $behaviour  kinds, structural, throws, finish
 */
function probeWithId(string $id, array $behaviour = []): Rule
{
    return eval('return new class($id, $behaviour) extends \Jiannius\Atom\Services\DesignChecks\Rule {
        public const ID = '.var_export($id, true).';
        public const SEVERITY = "info";
        public const GROUP = "Copy and state";
        public function __construct(protected string $probeId, protected array $behaviour) {}
        public function title(): string { return "Probe"; }
        public function fix(): string { return "Use `<atom:card>`."; }
        public function rule(): string { return "r"; }
        public function detect(): string { return "a probe"; }
        public function kinds(): array { return $this->behaviour["kinds"] ?? ["blade"]; }
        public function structural(): bool { return $this->behaviour["structural"] ?? true; }
        public function find(\Jiannius\Atom\Services\DesignChecks\File $file, \Jiannius\Atom\Services\DesignChecks\Context $context): iterable {
            if ($this->behaviour["throws"] ?? false) { throw new \RuntimeException("boom"); }
            $context->add($this->probeId, $file->path);
            preg_match_all("/<atom:button/", $file->text(), $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [, $offset]) { yield [$offset, "a button", ["seen" => $file->path]]; }
        }
        public function finish(array $findings, \Jiannius\Atom\Services\DesignChecks\Context $context): array {
            return ($this->behaviour["finish"] ?? fn ($findings) => $findings)($findings, $context);
        }
    };');
}

/**
 * A temp host with these files, by path from its root
 *
 * @param  array<string, string>  $files
 */
function designTempHost(array $files): string
{
    $dir = sys_get_temp_dir().'/atom-design-scan-'.bin2hex(random_bytes(6));

    foreach ($files as $path => $content) {
        @mkdir(dirname($dir.'/'.$path), 0777, true);
        file_put_contents($dir.'/'.$path, $content);
    }

    return $dir;
}

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
        $first = $scanner->scan(DesignHost::path('_skips'));
        $second = $scanner->scan(DesignHost::path('_clean'));
        $third = $scanner->scan(DesignHost::path('_skips'));

        expect($first['skipped'])->toBe($third['skipped'])
            ->and(array_column($second['skipped'], 'path'))->not->toContain('resources/views/emails')
            ->and($first['checks']['success-button']['count'])->toBe($third['checks']['success-button']['count']);
    });
});

describe('design-check: the runner and a check', function () {
    it('hands a check the file, keeps its note, and drops its third element from the result', function () {
        $dir = designTempHost(['resources/views/a.blade.php' => "<atom:page>\n<atom:button>One</atom:button>\n</atom:page>\n"]);

        try {
            $result = (new DesignCheck([probeWithId('probe-a')]))->scan($dir);

            expect($result['checks']['probe-a']['findings'])->toBe([['file' => 'resources/views/a.blade.php', 'line' => 2, 'text' => '<atom:button>One</atom:button>', 'note' => 'a button']]);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('lets a check wait for every file: finish() sees the context and the third element', function () {
        $dir = designTempHost([
            'resources/views/a.blade.php' => "<atom:button>One</atom:button>\n",
            'resources/views/b.blade.php' => "<atom:button>Two</atom:button>\n",
        ]);
        $seen = null;

        try {
            $result = (new DesignCheck([probeWithId('probe-b', ['finish' => function ($findings, $context) use (&$seen) {
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
        $dir = designTempHost(['resources/views/a.blade.php' => "<atom:button>One</atom:button>\n"]);

        try {
            $result = (new DesignCheck([probeWithId('probe-throws', ['throws' => true]), probeWithId('probe-ok')]))->scan($dir);

            expect($result['errors'])->toBe([['check' => 'probe-throws', 'file' => 'resources/views/a.blade.php', 'error' => 'RuntimeException: boom']])
                ->and($result['checks']['probe-ok']['count'])->toBe(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('runs a check on the kinds it reads only', function () {
        $dir = designTempHost([
            'resources/views/a.blade.php' => "<atom:button>One</atom:button>\n",
            'app/A.php' => "<?php\n// <atom:button>\n\$a = '<atom:button>';\n",
        ]);

        try {
            expect((new DesignCheck([probeWithId('probe-blade')]))->scan($dir)['checks']['probe-blade']['count'])->toBe(1)
                ->and((new DesignCheck([probeWithId('probe-php', ['kinds' => ['php']])]))->scan($dir)['checks']['probe-php']['count'])->toBe(1)
                ->and((new DesignCheck([probeWithId('probe-both', ['kinds' => ['blade', 'php']])]))->scan($dir)['checks']['probe-both']['count'])->toBe(2);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reads a view whose tags do not balance as text only: a structural check skips it, a text check runs', function () {
        $dir = designTempHost([
            'resources/views/open.blade.php' => "<atom:page>\n<atom:card heading=\"Jobs\">\n<atom:button>One</atom:button>\n</atom:page>\n",
            'resources/views/slot.blade.php' => "<atom:page.header title=\"Jobs\">\n<x-slot:actions>\n<atom:button>One</atom:button>\n</atom:page.header>\n",
            'resources/views/fine.blade.php' => "<atom:page>\n<atom:button>One</atom:button>\n</atom:page>\n",
        ]);

        try {
            $result = (new DesignCheck([probeWithId('probe-structural'), probeWithId('probe-text', ['structural' => false])]))->scan($dir);

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
        $dir = designTempHost(['resources/views/a.blade.php' => "<!-- <atom:button>One</atom:button> -->\n{{-- <atom:button>Two</atom:button> --}}\n@verbatim\n<atom:button>Three</atom:button>\n@endverbatim\n<atom:button>Four</atom:button>\n"]);

        try {
            $result = (new DesignCheck([probeWithId('probe-comments')]))->scan($dir);

            expect(array_column($result['checks']['probe-comments']['findings'], 'line'))->toBe([6]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: what is read, and what is not', function () {
    it('reads the views and the PHP of app/, resources/ and the modules, and nothing under a non-screen path', function () {
        $result = DesignHost::scan('_skips');
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
        $dir = designTempHost([
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
        $outside = designTempHost(['leak.blade.php' => '<atom:button variant="success">x</atom:button>', 'dir/leak.blade.php' => '<atom:button variant="success">x</atom:button>']);
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
        $dir = designTempHost([
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
        $dir = designTempHost(['resources/views/livewire/vendor/index.blade.php' => '<atom:button variant="success">x</atom:button>']);

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
