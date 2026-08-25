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
