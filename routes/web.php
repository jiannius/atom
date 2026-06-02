<?php

use Illuminate\Support\Facades\Route;
use Jiannius\Atom\Services\Docs;

if (app()->environment('local')) {
    Route::middleware('web')->group(function () {
        Route::get('/atom/docs', function () {
            return view('atom::docs.index', ['docs' => app(Docs::class)]);
        })->name('atom.docs');

        Route::get('/atom/docs/{component}', function ($component) {
            $data = app(Docs::class)->component($component);

            abort_unless((bool) $data, 404);

            return view('atom::docs.show', ['entry' => $data]);
        })->where('component', '[a-z0-9-]+')->name('atom.docs.show');
    });
}
