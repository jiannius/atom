<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Route;

class Asset
{
    public static function boot(): void
    {
        $instance = new static;
        $instance->registerRoutes();
    }

    /**
     * Get the version hash of the asset
     */
    public function version($name): string
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../dist/manifest.json'), true);
        $data = collect($manifest)->where(fn ($value, $key) => str($key)->is('*/'.$name))->values()->first();
        $file = data_get($data, 'file');

        return str($file)->remove('assets/')->start('/atom/')->toString();
    }

    /**
     * Register the routes for the assets
     */
    protected function registerRoutes(): void
    {
        Route::get('/atom/{file}', function ($file) {
            $ext = str($file)->afterLast('.')->toString();

            $type = match ($ext) {
                'css' => 'text/css',
                'js' => 'text/javascript',
                default => 'text/plain',
            };

            $path = __DIR__.'/../../dist/assets/'.$file;

            if (!file_exists($path)) abort(404);

            return response()->file($path, [
                'Content-Type' => $type,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        });
    }
}