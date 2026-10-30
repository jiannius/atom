<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// The default test environment is `testing`, not local: nothing of dev login may exist here.
it('does not register the sign-in route outside local', function () {
    expect(Route::has('atom.dev.login'))->toBeFalse();

    $this->post('/atom/dev/login/1')->assertNotFound();
    $this->post('/atom/dev/login')->assertNotFound();
    $this->get('/atom/dev/login/1')->assertNotFound();

    expect(Auth::check())->toBeFalse();
});

it('renders nothing outside local and runs no query', function () {
    DB::enableQueryLog();

    expect(trim(renderBlade('<atom:dev-login />')))->toBe('')
        ->and(DB::getQueryLog())->toBe([]);
});
