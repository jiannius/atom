<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

// The pattern pages are what a host copies, so they must pass the check that teaches the rules they show. The demos
// (resources/views/docs/*) show variants on purpose and are not read. One region is excused: the document's paper.

describe('design-check: atom\'s own patterns', function () {
    it('scans clean over every check: no finding, no error, no marker problem, nothing read as text only', function () {
        $dir = DesignHost::patterns();

        try {
            $result = (new DesignCheck)->scan($dir);
            $findings = DesignHost::found($result);

            expect($result['scanned']['blade'])->toBe(count(glob(dirname(__DIR__, 2).'/resources/views/docs/patterns/*.blade.php')))
                ->and(count($result['checks']))->toBe(count((new DesignCheck)->rules()))
                ->and($findings)->toBe([])
                ->and($result['errors'])->toBe([])
                ->and($result['markers'])->toBe([])
                ->and($result['text_only'])->toBe([])
                ->and($result['summary']['findings'])->toBe(0);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('excuses only the document paper, with a reason on each suppression', function () {
        $dir = DesignHost::patterns();

        try {
            $result = (new DesignCheck)->scan($dir);
            $suppressed = $result['suppressed'];

            expect($suppressed)->not->toBeEmpty()
                ->and(array_values(array_unique(array_column($suppressed, 'file'))))->toBe(['resources/views/document-page.blade.php'])
                ->and(array_values(array_unique(array_column($suppressed, 'id'))))->toEqualCanonicalizing(['arbitrary-value', 'shadow'])
                ->and(array_filter(array_column($suppressed, 'reason'), fn ($reason) => trim((string) $reason) === ''))->toBeEmpty();
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('goes red without that region, so the test is not vacuous', function () {
        $dir = DesignHost::patterns();
        $file = $dir.'/resources/views/document-page.blade.php';
        $source = file_get_contents($file);
        $stripped = preg_replace('/\{\{--\s*atom:design-check\s+(?:off|on)\b.*?--\}\}/', '', $source, -1, $count);

        file_put_contents($file, $stripped);

        try {
            $found = DesignHost::found((new DesignCheck)->scan($dir));

            expect($count)->toBe(2)
                ->and(array_values(array_unique(array_map(fn ($key) => substr($key, strrpos($key, ':') + 1), array_keys($found)))))->toEqualCanonicalizing(['arbitrary-value', 'shadow']);
        } finally {
            DesignHost::remove($dir);
        }
    });
});
