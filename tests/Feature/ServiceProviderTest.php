<?php

it('boots the atom service provider', function () {
    expect(app()->bound('atom'))->toBeTrue();
});

it('registers no migration path, as the package has no database directory', function () {
    $package = realpath(__DIR__.'/../..');

    expect(is_dir($package.'/database'))->toBeFalse();

    // a path may be written with `..` segments and need not exist, so fold them away before comparing
    foreach (app('migrator')->paths() as $path) {
        $folded = $path;

        while (($next = preg_replace('#/[^/]+/\.\./#', '/', $folded, 1)) !== $folded) {
            $folded = $next;
        }

        expect(str_starts_with($folded, $package.'/database'))->toBeFalse($path);
    }
});

it('has no editor prop on the page or its shells: the editor styles are not a switch', function () {
    $components = array_merge(
        [__DIR__.'/../../components/html.blade.php', __DIR__.'/../../components/docs/layout.blade.php'],
        glob(__DIR__.'/../../components/layouts/*.blade.php'),
    );

    expect(count($components))->toBeGreaterThan(3);

    foreach ($components as $file) {
        expect(file_get_contents($file))
            ->not->toContain("'editor'")
            ->not->toContain(':editor=');
    }
});
