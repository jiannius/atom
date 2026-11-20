<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\UpgradeCheck;

/**
 * The text reports of atom:design-check and atom:upgrade-check are what people
 * read and what CI diffs, so a fixed result must print byte for byte the same
 * whoever builds the headings and the "... and N more" lines. The goldens are
 * in tests/Fixtures/ScanReport.
 */
function scanReportDesignResult(): array
{
    $finding = fn (string $file, int $line, string $text, ?string $note = null) => ['file' => $file, 'line' => $line, 'text' => $text] + ($note ? ['note' => $note] : []);

    return [
        'atom_version' => '4.0.0-beta.99',
        'scanned' => ['blade' => 12, 'php' => 3],
        'warnings' => ['one directory was thin'],
        'skipped' => [['path' => 'resources/js/vendor', 'reason' => 'third-party'], ['path' => 'a.blade.php', 'reason' => 'over 2 MB']],
        'text_only' => ['resources/views/odd.blade.php'],
        'errors' => [
            ['check' => 'a-check', 'file' => 'x.php', 'error' => 'boom'],
            ['check' => 'b-check', 'file' => 'y.php', 'error' => 'bang'],
            ['check' => 'c-check', 'file' => 'z.php', 'error' => 'bam'],
        ],
        'checks' => [
            'own-overflow' => [
                'id' => 'own-overflow', 'severity' => 'break', 'count' => 3, 'title' => 'A hand-built dropdown', 'fix' => 'use the menu slot', 'rule' => 'Never build your own',
                'findings' => [$finding('a.blade.php', 1, '<x>', 'in `page.header`'), $finding('a.blade.php', 2, '<y>'), $finding('b.blade.php', 9, '<z>')],
            ],
            'quiet' => ['id' => 'quiet', 'severity' => 'review', 'count' => 0, 'title' => 'Nothing', 'fix' => '-', 'rule' => '-', 'findings' => []],
            'tone' => [
                'id' => 'tone', 'severity' => 'review', 'count' => 1, 'title' => 'Tone', 'fix' => 'say less', 'rule' => 'Copy',
                'findings' => [$finding('b.blade.php', 4, 'Ünïcode text')],
            ],
        ],
        'suppressed' => [['id' => 'tone', 'file' => 'b.blade.php', 'line' => 7, 'reason' => 'seeded'], ['id' => 'own-overflow', 'file' => 'a.blade.php', 'line' => 8, 'reason' => ''], ['id' => 'tone', 'file' => 'c.blade.php', 'line' => 1, 'reason' => 'third']],
        'markers' => [['file' => 'a.blade.php', 'line' => 3, 'id' => 'tone', 'problem' => 'unused: `ignore` suppressed nothing'], ['file' => 'b.blade.php', 'line' => 5, 'problem' => 'no id']],
        'not_checked' => ['spacing between sections', 'icon choice'],
        'summary' => ['findings' => 4, 'checks_with_findings' => 2, 'checks' => 3, 'by_severity' => ['break' => 3, 'review' => 1, 'info' => 0], 'suppressed' => 3],
    ];
}

function scanReportUpgradeResult(): array
{
    $finding = fn (string $scope, string $file, int $line, string $text, ?string $note = null) => ['scope' => $scope, 'file' => $file, 'line' => $line, 'text' => $text] + ($note ? ['note' => $note] : []);

    return [
        'atom_version' => '3.38.2',
        'guide' => 'UPGRADE.md',
        'scanned' => ['app' => 40, 'tests' => 5],
        'skipped' => [['path' => 'node_modules', 'reason' => 'dependencies'], ['path' => 'big.js', 'reason' => 'minified']],
        'published_views' => ['resources/views/vendor/atom/button.blade.php'],
        'checks' => [
            'date-pretty' => [
                'id' => 'date-pretty', 'severity' => UpgradeCheck::BREAKS, 'count' => 3, 'tests_count' => 2, 'title' => 'pretty() is gone', 'section' => 'Dates', 'codemod' => 'report',
                'findings' => [
                    $finding('app', 'a.php', 1, '->pretty()', 'a note'), $finding('app', 'b.php', 2, '->pretty()'), $finding('app', 'c.php', 3, '->pretty()'),
                    $finding('tests', 't.php', 9, '->pretty()'), $finding('tests', 'u.php', 0, 'whole app'),
                ],
            ],
            'heading-weight' => [
                'id' => 'heading-weight', 'severity' => UpgradeCheck::VISUAL, 'count' => 1, 'tests_count' => 0, 'title' => 'Headings', 'section' => 'Type', 'codemod' => 'auto',
                'findings' => [$finding('app', 'v.blade.php', 6, 'h1')],
            ],
            'only-tests' => [
                'id' => 'only-tests', 'severity' => UpgradeCheck::REVIEW, 'count' => 0, 'tests_count' => 1, 'title' => 'Tests only', 'section' => 'Tests', 'codemod' => 'report',
                'findings' => [$finding('tests', 't.php', 2, 'x')],
            ],
            'none' => ['id' => 'none', 'severity' => UpgradeCheck::REVIEW, 'count' => 0, 'tests_count' => 0, 'title' => 'Nothing', 'section' => '-', 'codemod' => 'auto', 'findings' => []],
        ],
        'components' => [
            ['file' => 'app/Livewire/A.php', 'line' => 8, 'traits' => ['WithTable', 'WithModal'], 'also_present' => ['WithTable'], 'partials' => ['p1', 'p2'], 'notes' => ['one', 'two']],
            ['file' => 'app/Livewire/B.php', 'line' => 9, 'traits' => [], 'also_present' => [], 'partials' => [], 'notes' => []],
        ],
        'host_atom_components' => [['scope' => 'tests', 'file' => 't.php', 'line' => 3, 'name' => 'App\\Traits\\AtomComponent']],
        'tags' => ['atom:button' => ['count' => 12, 'files' => 4, 'restyled' => 2], 'atom:icon.add' => ['count' => 1, 'files' => 1, 'restyled' => 0]],
        'guideline' => ['files' => [
            'CLAUDE.md' => ['state' => 'diverged', 'host_only' => 2, 'installed_only' => 3, 'installed_only_examples' => ['new line'], 'host_only_examples' => ['old line']],
            'AGENTS.md' => ['state' => 'in_sync'],
            'OTHER.md' => ['state' => 'unknown', 'reason' => 'unreadable'],
            'MORE.md' => ['state' => 'not_found'],
        ]],
        'summary' => [
            'findings' => 4, 'checks_with_findings' => 3, 'checks' => 4, 'tests_findings' => 3, 'by_severity' => ['breaks at runtime' => 3, 'visual change' => 1, 'review' => 0],
            'by_codemod' => ['auto' => 1, 'report' => 3], 'components' => 2, 'editor_casts' => 1, 'editor_tags' => 0,
        ],
    ];
}

function scanReportCases(): array
{
    $design = new DesignCheck;
    $upgrade = new UpgradeCheck;

    $emptyDesign = ['atom_version' => null, 'scanned' => ['blade' => 0, 'php' => 0], 'skipped' => [], 'text_only' => [], 'checks' => [], 'suppressed' => [], 'markers' => [], 'not_checked' => [], 'summary' => ['findings' => 0, 'checks_with_findings' => 0, 'checks' => 0, 'by_severity' => ['break' => 0, 'review' => 0, 'info' => 0], 'suppressed' => 0]];
    $emptyUpgrade = ['atom_version' => null, 'guide' => 'g', 'scanned' => ['app' => 0, 'tests' => 0], 'skipped' => [], 'published_views' => [], 'checks' => [], 'components' => [], 'tags' => [], 'guideline' => ['files' => []], 'summary' => ['findings' => 0, 'checks_with_findings' => 0, 'checks' => 0, 'tests_findings' => 0, 'by_severity' => [], 'by_codemod' => ['auto' => 0, 'report' => 0], 'components' => 0, 'editor_casts' => 0, 'editor_tags' => 0]];

    return [
        'design-limit-2' => $design->format(scanReportDesignResult(), 2),
        'design-all' => $design->format(scanReportDesignResult(), null),
        'design-empty' => $design->format($emptyDesign),
        'upgrade-limit-2' => $upgrade->format(scanReportUpgradeResult(), 2),
        'upgrade-all' => $upgrade->format(scanReportUpgradeResult(), null),
        'upgrade-empty' => $upgrade->format($emptyUpgrade),
    ];
}

it('prints a fixed result byte for byte as before', function (string $name) {
    $path = __DIR__.'/../Fixtures/ScanReport/'.$name.'.txt';

    expect(scanReportCases()[$name])->toBe(file_get_contents($path));
})->with(['design-limit-2', 'design-all', 'design-empty', 'upgrade-limit-2', 'upgrade-all', 'upgrade-empty']);
