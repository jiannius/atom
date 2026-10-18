<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Jiannius\Atom\Services\Docs;

if (app()->environment('local')) {
    Route::middleware('web')->group(function () {
        // Minimal fixture page for E2E testing — renders just the filters bar near the
        // top of the page so FloatingUI dropdown menus stay within the viewport.
        Route::get('/atom/e2e/table-filters', function () {
            return view('atom::e2e.table-filters');
        })->name('atom.e2e.table-filters');

        Route::get('/atom/docs', function () {
            return view('atom::docs.index', ['docs' => app(Docs::class)]);
        })->name('atom.docs');

        // Pattern pages: a whole screen in each app shell, from resources/views/docs/patterns/. They sign in a
        // stand-in user so the account menu has a name to show.
        Route::get('/atom/docs/patterns/{pattern}', function ($pattern) {
            abort_unless(array_key_exists($pattern, Docs::PATTERNS), 404);

            Auth::setUser(new class(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]) extends GenericUser {
                public function initials(): string
                {
                    return 'AD';
                }
            });

            return view('atom::docs.patterns.'.$pattern);
        })->name('atom.docs.pattern');

        Route::get('/atom/docs/{component}', function ($component) {
            $data = app(Docs::class)->component($component);

            abort_unless((bool) $data, 404);

            return view('atom::docs.show', ['entry' => $data]);
        })->where('component', '[a-z0-9-]+')->name('atom.docs.show');
    });
}
