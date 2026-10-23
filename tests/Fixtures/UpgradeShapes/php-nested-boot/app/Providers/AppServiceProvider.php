<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('x', fn () => new class
        {
            public function boot()
            {
                return 1;
            }
        });
    }

    public function boot(): void
    {
        //
    }
}
