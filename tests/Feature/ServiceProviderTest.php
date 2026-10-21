<?php

it('boots the atom service provider', function () {
    expect(app()->bound('atom'))->toBeTrue();
});

it('registers no migration path, as the package has no database directory', function () {
    expect(is_dir(__DIR__.'/../../database'))->toBeFalse()
        ->and(app('migrator')->paths())->not->toContain(realpath(__DIR__.'/../../src').'/../database/migrations');
});

it('has no editor prop on the page or its shells: the editor styles are not a switch', function () {
    foreach (['html', 'layouts/sidebar', 'layouts/header', 'docs/layout'] as $component) {
        expect(file_get_contents(__DIR__.'/../../components/'.$component.'.blade.php'))
            ->not->toContain("'editor'")
            ->not->toContain(':editor=');
    }
});
