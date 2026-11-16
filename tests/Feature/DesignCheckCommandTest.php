<?php

use Illuminate\Support\Facades\Artisan;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

describe('atom:design-check: the report', function () {
    it('groups by severity and check, prints each rule and fix once, then the sites', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('own-overflow'), '--only' => 'own-overflow']);
        $out = Artisan::output();

        expect($out)->toStartWith('atom design check')
            ->toContain('Read only: nothing was written.')
            ->toContain('A finding is a candidate from a text scan, not a proof.')
            ->toContain('{{-- atom:design-check ignore <id>: <reason> --}}')
            ->toContain('treat the file like source code')
            ->toContain("REVIEW\n------\n[own-overflow] 6 - A hand-built ⋯ dropdown\n    Fix: ")
            ->toContain('    Rule: Design rules, Never build your own ⋯ dropdown')
            ->toContain('resources/views/menus.blade.php:5  <atom:dropdown> {{-- expect: own-overflow --}}  <- in `page.header`: use its `menu` slot')
            ->toContain("FILES WITH THE MOST FINDINGS\n----------------------------\nresources/views/menus.blade.php  6 (own-overflow 6)")
            ->toContain('NOT CHECKED (left to a person)')
            ->toContain("SUMMARY\n-------\n6 finding(s) in 1 of 1 checks: 0 break, 6 review, 0 info; 0 suppressed.")
            ->not->toContain('INFO');
    });

    it('shows the first 10 findings of a check and says how to see the rest, or all with --all', function () {
        $dir = DesignHost::copy('_clean');
        file_put_contents($dir.'/resources/views/many.blade.php', str_repeat("<atom:button variant=\"success\">x</atom:button>\n", 12));

        try {
            Artisan::call('atom:design-check', ['--path' => $dir, '--only' => 'success-button']);
            $out = Artisan::output();

            expect(substr_count($out, 'resources/views/many.blade.php:'))->toBe(10)
                ->and($out)->toContain('... and 2 more (use --all, or --only=success-button)')
                ->and($out)->toContain('resources/views/many.blade.php  12 (success-button 12)');

            Artisan::call('atom:design-check', ['--path' => $dir, '--only' => 'success-button', '--all' => true]);

            expect(substr_count(Artisan::output(), '<atom:button variant="success">x</atom:button>'))->toBe(12)
                ->and(Artisan::output())->not->toContain('more (use --all');
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('prints a quoted <info> as it is, not as a console style', function () {
        $dir = DesignHost::copy('_clean');
        file_put_contents($dir.'/resources/views/info.blade.php', "<atom:button variant=\"success\"><info>x</info></atom:button>\n");

        try {
            Artisan::call('atom:design-check', ['--path' => $dir, '--only' => 'success-button']);

            expect(Artisan::output())->toContain('<atom:button variant="success"><info>x</info></atom:button>');
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('prints no check group when nothing is found, and still the summary', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('_clean')]);

        expect(Artisan::output())->not->toContain('REVIEW')->not->toContain('INFO')->not->toContain('BREAK')
            ->toContain('0 finding(s) in 0 of '.count((new DesignCheck)->ruleIds()).' checks: 0 break, 0 review, 0 info; 0 suppressed.');
    });

    it('lists what it did not read, and the marker problems and what was suppressed', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('_skips')]);

        expect(Artisan::output())->toContain("NOT READ (13)\n-------------")
            ->toContain('resources/views/emails  (a mail, PDF, print or error view: not a screen)')
            ->toContain('tests  (not screens: the design rules read views and components)');

        Artisan::call('atom:design-check', ['--path' => DesignHost::path('_markers'), '--only' => 'success-button,own-overflow']);

        expect(Artisan::output())->toContain('SUPPRESSED (10)')
            ->toContain('[success-button] resources/views/ignore.blade.php:4  the export is the next step here')
            ->toContain('[success-button] resources/views/ignore.blade.php:21  (no reason given)')
            ->toContain('Marker problems (8):')
            ->toContain('resources/views/ignore.blade.php:8  [own-overflow] unused: `ignore` suppressed nothing');
    });

    it('exits 0 whatever it finds', function () {
        expect(Artisan::call('atom:design-check', ['--path' => DesignHost::path('own-overflow')]))->toBe(0);
    });
});

describe('atom:design-check: --json', function () {
    it('prints every check, even at zero, with what a person needs to act on it', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('_clean'), '--json' => true]);
        $json = json_decode(Artisan::output(), true);

        expect(array_keys($json))->toBe(['root', 'atom_version', 'only', 'scanned', 'checks', 'suppressed', 'markers', 'skipped', 'text_only', 'errors', 'warnings', 'not_checked', 'summary'])
            ->and(array_keys($json['checks']))->toBe((new DesignCheck)->ruleIds())
            ->and($json['checks']['own-overflow'])->toHaveKeys(['id', 'title', 'group', 'severity', 'fix', 'rule', 'detect', 'count', 'suppressed', 'findings'])
            ->and($json['checks']['own-overflow']['count'])->toBe(0)
            ->and($json['checks']['own-overflow']['findings'])->toBe([])
            ->and($json['summary'])->toBe(['checks' => count($json['checks']), 'findings' => 0, 'checks_with_findings' => 0, 'by_severity' => ['break' => 0, 'review' => 0, 'info' => 0], 'suppressed' => 0, 'marker_problems' => 0]);
    });

    it('gives every finding as file, line, text and the note', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('success-button'), '--json' => true]);
        $json = json_decode(Artisan::output(), true);

        expect($json['checks']['success-button']['findings'][0])->toBe(['file' => 'resources/views/buttons.blade.php', 'line' => 9, 'text' => '<atom:button variant="ghost" color="success">Mark as inspected</atom:button> {{-- expect: success-button --}}', 'note' => '`color="success"`'])
            ->and($json['checks']['success-button']['count'])->toBe(7);
    });
});

describe('atom:design-check: --only and --path', function () {
    it('runs just the named checks, and says so in the JSON', function () {
        Artisan::call('atom:design-check', ['--path' => DesignHost::path('own-overflow'), '--json' => true, '--only' => 'success-button']);
        $json = json_decode(Artisan::output(), true);

        expect(array_keys($json['checks']))->toBe(['success-button'])
            ->and($json['only'])->toBe(['success-button'])
            ->and($json['summary']['findings'])->toBe(0);

        Artisan::call('atom:design-check', ['--path' => DesignHost::path('own-overflow'), '--json' => true, '--only' => ' own-overflow , success-button,own-overflow']);

        expect(json_decode(Artisan::output(), true)['only'])->toBe(['own-overflow', 'success-button']);
    });

    it('refuses an unknown id with exit 1, naming it and the known ones', function () {
        expect(Artisan::call('atom:design-check', ['--path' => DesignHost::path('_clean'), '--only' => 'own-overflow,nope']))->toBe(1)
            ->and(Artisan::output())->toContain('Unknown check: nope')->toContain('success-button')
            ->and(Artisan::output())->not->toContain('atom design check');
    });

    it('refuses --only with no ids, in any spelling', function () {
        foreach (['--only=,', '--only=', '--only= , '] as $option) {
            expect(Artisan::call('atom:design-check', ['--path' => DesignHost::path('_clean'), '--only' => substr($option, 7)]))->toBe(1)
                ->and(Artisan::output())->toContain('--only needs check ids')->and(Artisan::output())->not->toContain('atom design check');
        }
    });

    it('refuses a path that is not an app root (resources/views, say) with exit 1, and says what the root is', function () {
        expect(Artisan::call('atom:design-check', ['--path' => DesignHost::path('own-overflow').'/resources/views']))->toBe(1)
            ->and(Artisan::output())->toContain('Nothing to read in')->toContain('--path is the app root')->toContain('not resources/views');
    });

    it('warns, and still exits 0, when the root has a directory but no Blade or PHP file', function () {
        $dir = DesignHost::temp(['app/.keep' => '']);

        try {
            expect(Artisan::call('atom:design-check', ['--path' => $dir]))->toBe(0)
                ->and(Artisan::output())->toContain('WARNING: No Blade or PHP file was read');
            expect(Artisan::call('atom:design-check', ['--path' => $dir, '--json' => true]))->toBe(0);
            expect(json_decode(Artisan::output(), true)['warnings'])->toHaveCount(1);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('refuses a path that is not a directory with exit 1', function () {
        expect(Artisan::call('atom:design-check', ['--path' => '/no/such/dir']))->toBe(1)
            ->and(Artisan::output())->toContain('Not a directory: /no/such/dir');
    });

    it('takes no option it should not: no --fail-on, no --except, no --output', function () {
        $options = array_keys(Artisan::all()['atom:design-check']->getDefinition()->getOptions());

        expect($options)->not->toContain('fail-on', 'except', 'output')
            ->and($options)->toContain('json', 'all', 'path', 'only');
    });
});
