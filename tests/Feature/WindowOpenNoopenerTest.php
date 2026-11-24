<?php

/**
 * A page opened with `window.open(url, '_blank')` gets `window.opener` unless `noopener` is asked for, so a URL that comes
 * from data (a toast's `url`, a lightbox item) could navigate the opening page. Every call in atom's own files names it.
 */
it('opens a new window with noopener everywhere in components and scripts', function () {
    $roots = [__DIR__.'/../../components', __DIR__.'/../../resources/js', __DIR__.'/../../resources/views'];
    $calls = 0;
    $missing = [];

    foreach ($roots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match_all('/\bwindow\.open\((?:[^()]|\([^()]*\))*\)/', $line, $matches)) {
                    foreach ($matches[0] as $call) {
                        $calls++;

                        if (! str_contains($call, 'noopener')) {
                            $missing[] = str_replace(realpath(__DIR__.'/../..').'/', '', $file->getRealPath()).':'.($number + 1);
                        }
                    }
                }
            }
        }
    }

    expect($calls)->toBeGreaterThanOrEqual(2)
        ->and($missing)->toBe([]);
});
