<?php

use Jiannius\Atom\Traits\AtomComponent;

/**
 * The trait is mixed into every consuming app's Livewire components, and a class
 * method silently beats a trait method of the same name — no error, the feature
 * behind it just stops working. So the set of names it occupies is API: adding
 * one is a decision, not a side effect of writing a helper.
 *
 * If this fails, the name may well be fine to add — update the list here and the
 * two places that document it (README "Names the trait occupies", and the Boost
 * guidelines, which propagate into every host app's CLAUDE.md).
 *
 * Reflection flattens `use`d traits into the declaring class, so the list also
 * covers what WithPagination and WithFileUploads bring along — which is the point:
 * a host component sees one namespace, not three.
 */
it('occupies only the names it documents', function () {
    $methods = collect((new ReflectionClass(AtomComponent::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn ($method) => $method->getName())
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe([
        // WithFileUploads
        '_finishUpload',
        '_removeUpload',
        '_startUpload',
        '_uploadErrored',

        // atom
        'action',
        'alert',
        'clearTableSelectAll',
        'command',
        'confirm',

        // WithPagination
        'getPage',

        // atom
        'getTableCheckboxes',

        // WithPagination
        'gotoPage',

        // atom
        'isTableSelectAll',
        'isTableShowSelected',
        'isTableShowTrashed',
        'modal',
        'mountAtomComponent',

        // WithPagination
        'nextPage',
        'previousPage',
        'queryStringHandlesPagination',
        'resetPage',

        // atom
        'resetTableCheckboxes',
        'selectAllTableMatching',

        // WithPagination
        'setPage',

        // atom
        'tableRowsQuery',
        'tableSelection',
        'tableSelectionQuery',
        'toast',
        'toggleTableShowSelected',
        'updatedAtomComponent',
        'verifyRecaptcha',
        'wirekey',
    ]);
});

/**
 * The short helpers are documented as safe for a host component to shadow, which
 * is only true while atom never calls them on the component itself: shadowing one
 * then swaps out sugar the host was calling anyway, rather than breaking machinery
 * the host can't see. The moment something in the package starts calling
 * `$this->toast(...)`, that promise is void — so pin it.
 */
it('never calls its own host-facing helpers, so shadowing them stays harmless', function () {
    // glob()'s ** matches a single level in PHP, which would quietly skip the
    // deeper components — walk the trees instead
    $walk = function (string $dir, string $suffix) {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if (str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    };

    $files = collect([
        ...$walk(__DIR__.'/../../src', '.php'),
        ...$walk(__DIR__.'/../../components', '.blade.php'),
    ])->reject(fn ($path) => str_contains($path, '/src/Commands/'));   // console Commands have their own confirm()

    // a walk that finds nothing would pass every assertion below it
    expect($files->count())->toBeGreaterThan(300);

    $helpers = ['modal', 'command', 'toast', 'alert', 'confirm', 'action', 'wirekey', 'verifyRecaptcha'];

    foreach ($files as $path) {
        $contents = file_get_contents($path);

        foreach ($helpers as $helper) {
            expect($contents)->not->toContain(
                '$this->'.$helper.'(',
                basename($path).' calls $this->'.$helper.'() — that name is documented as safe for a host to shadow',
            );
        }
    }
});

it('claims no name a host component is likely to want for its own data', function () {
    $reflection = new ReflectionClass(AtomComponent::class);

    // the computed property feeding <atom:table :paginate="..."> is the host's to
    // name — atom must never define or look for one of these
    foreach (['items', 'rows', 'records', 'data', 'results', 'list', 'query', 'filters'] as $name) {
        expect($reflection->hasMethod($name))->toBeFalse("AtomComponent must not define {$name}()");
        expect($reflection->hasProperty($name))->toBeFalse("AtomComponent must not declare \${$name}");
    }
});

it('declares its own state under an underscore prefix', function () {
    $properties = collect((new ReflectionClass(AtomComponent::class))->getProperties())
        ->map(fn ($property) => $property->getName())
        ->reject(fn ($name) => $name === 'paginators')   // Livewire's, via WithPagination
        ->values();

    $properties->each(fn ($name) => expect($name)->toStartWith('_'));

    expect($properties->all())->toBe(['_breadcrumbs', '_table', '_editor', '_recaptcha']);
});
