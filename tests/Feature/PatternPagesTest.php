<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Services\Docs;

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

// The pattern pages in /atom/docs: whole screens in each app shell, built from one shared nav partial.
describe('pattern pages', function () {
    it('has a view for every listed pattern', function (string $name) {
        expect(view()->exists('atom::docs.patterns.'.$name))->toBeTrue();
    })->with(array_keys(Docs::PATTERNS));

    it('renders each pattern in its shell', function (string $name, string $shell) {
        $html = view('atom::docs.patterns.'.$name)->render();

        expect(domQuery($html, '//*[@data-atom-nav-shell="'.$shell.'"]//a[@data-atom-navlist-item]'))->not->toBeEmpty()
            ->and(domQuery($html, '//main[@data-atom-main]//*[@data-testid="pattern-page"]'))->toHaveCount(1);
    })->with([
        'sidebar' => ['app-shell-sidebar', 'sidebar'],
        'header' => ['app-shell-header', 'header'],
    ]);

    it('draws the very same nav markup in both shells', function () {
        $navOf = function (string $name): string {
            $html = view('atom::docs.patterns.'.$name)->render();
            $nav = domQuery($html, '//*[@data-atom-nav-shell][1]//nav[@data-atom-navlist]')[0];

            return $nav->ownerDocument->saveHTML($nav);
        };

        expect($navOf('app-shell-header'))->toBe($navOf('app-shell-sidebar'));
    });

    it('demos the same slots in both shells, built from atom components', function (string $name) {
        $html = view('atom::docs.patterns.'.$name)->render();

        expect(domQuery($html, '//*[@data-atom-shell-crumbs][contains(., "Invoices")]'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-context]//button'))->not->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-shell-search]//input'))->not->toBeEmpty()
            ->and(domQuery($html, '//main//*[@data-atom-table]//*[@data-atom-badge]'))->toHaveCount(4);
    })->with(['app-shell-sidebar', 'app-shell-header']);

    it('does not choke on a page query that is an array', function (string $name) {
        request()->query->set('page', ['x']);

        expect(fn () => view('atom::docs.patterns.'.$name)->render())->not->toThrow(Throwable::class);
    })->with(['app-shell-sidebar', 'app-shell-header']);

    it('marks the current page from the query string, in both shells', function (string $name) {
        request()->query->set('page', 'customers');

        $html = view('atom::docs.patterns.'.$name)->render();
        $current = domQuery($html, '//*[@data-atom-navlist-item][@aria-current="page"]');

        expect($current)->toHaveCount(1)->and($current[0]->getAttribute('href'))->toBe('?page=customers');
    })->with(['app-shell-sidebar', 'app-shell-header']);
});

describe('docs navigation', function () {
    it('lists the patterns under their own group', function () {
        // the docs routes exist in the local environment only
        if (! Route::has('atom.docs.pattern')) {
            Route::get('/atom/docs', fn () => '')->name('atom.docs');
            Route::get('/atom/docs/{component}', fn () => '')->name('atom.docs.show');
            Route::get('/atom/docs/patterns/{pattern}', fn () => '')->name('atom.docs.pattern');
            app('router')->getRoutes()->refreshNameLookups();
        }

        $html = renderBlade('<atom:docs.layout>x</atom:docs.layout>');

        expect($html)->toContain('Patterns')
            ->toContain('App shell (sidebar)')
            ->toContain('App shell (top nav)')
            ->toContain('/atom/docs/patterns/app-shell-header');
    });
});
