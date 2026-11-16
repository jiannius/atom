<?php

namespace Jiannius\Atom\Tests\Support;

use Jiannius\Atom\Services\DesignCheck;

/**
 * What the copy and form checks' own test needs beyond DesignHost: one check run over a temp host of several files
 */
class CopyFormsHost
{
    /**
     * What one check finds in a host of these files (path from its root => source), as [file:line, note]
     *
     * @param  array<string, string>  $files
     * @return array<int, array{0: string, 1: ?string}>
     */
    public static function scan(array $files, string $id): array
    {
        $dir = DesignHost::temp($files);

        try {
            return array_map(fn ($finding) => [$finding['file'].':'.$finding['line'], $finding['note']], (new DesignCheck)->scan($dir, [$id])['checks'][$id]['findings']);
        } finally {
            DesignHost::remove($dir);
        }
    }
}
