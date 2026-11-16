<?php

use Illuminate\Support\Facades\Artisan;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

// What the README, the skill, CLAUDE.md and UPGRADE.md say about the command is tied to the command: its name, each of its
// options and the marker grammar. The checks are not listed there (the JSON is the catalogue), so there is nothing else to drift.

describe('design-check: the docs', function () {
    it('names the command and the marker in the README, the skill and CLAUDE.md', function (string $doc) {
        $text = DesignHost::docs()[$doc];

        expect($text)->toContain('atom:design-check')
            ->and($text)->toContain('{{-- atom:design-check ignore')
            ->and($text)->toContain('<reason> --}}');
    })->with(['README', 'skill', 'CLAUDE.md']);

    it('names the command in UPGRADE.md, in the order and in its own paragraph after beta.37', function () {
        $upgrade = DesignHost::docs()['UPGRADE'];

        expect($upgrade)->toContain('then `php artisan atom:design-check` for drift from the design rules')
            ->and($upgrade)->toContain('**Changes after beta.37: `atom:design-check`.**')
            ->and($upgrade)->toContain('{{-- atom:design-check ignore <id>: <reason> --}}');
    });

    it('names every option of the command in the README, the skill and CLAUDE.md, and every marker verb in the README and CLAUDE.md', function () {
        $options = array_keys(Artisan::all()['atom:design-check']->getDefinition()->getOptions());
        $docs = DesignHost::docs();

        foreach (['json', 'all', 'path', 'only'] as $option) {
            expect($options)->toContain($option)
                ->and($docs['README'])->toContain('`--'.$option)
                ->and($docs['skill'])->toContain('`--'.$option.'`');
        }

        foreach (['json', 'all', 'path', 'only'] as $option) {
            expect($docs['CLAUDE.md'])->toContain('`--'.$option);
        }

        foreach (['README', 'CLAUDE.md'] as $doc) {
            expect($docs[$doc])->toContain('`ignore <ids>: <reason>`')
                ->and($docs[$doc])->toContain('`off <ids|all>: <reason>`')
                ->and($docs[$doc])->toContain('`on`');
        }
    });

    it('lists the command in the README\'s installation line and artisan table', function () {
        $readme = DesignHost::docs()['README'];

        expect($readme)->toContain('`atom:upgrade-check` and `atom:design-check`.')
            ->and($readme)->toContain('| `atom:design-check [--json] [--all] [--only=ids] [--path=dir]`');
    });

    it('describes the JSON as the README does: every top-level key the scan returns', function () {
        $dir = sys_get_temp_dir().'/atom-design-check-docs-'.bin2hex(random_bytes(4));
        mkdir($dir.'/resources/views', 0777, true);

        try {
            $keys = array_keys((new DesignCheck)->scan($dir));
        } finally {
            @rmdir($dir.'/resources/views');
            @rmdir($dir.'/resources');
            @rmdir($dir);
        }

        foreach ($keys as $key) {
            expect(DesignHost::docs()['README'])->toContain('`'.$key.'`');
        }
    });
});
