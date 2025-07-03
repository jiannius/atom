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
        $hash = $manifest[$name] ?? '';

        return $hash;
    }

    protected function registerRoutes(): void
    {
        foreach ([
            'atom.css' => 'text/css',
            'atom.js' => 'text/javascript',
            'editor.js' => 'text/javascript',
        ] as $script => $type) {
            Route::get("/atom/{$script}", function () use ($script, $type) {
                if (!config('app.debug')) {
                    $script = str($script)->replaceLast('.js', '.min.js')->toString();
                    $script = str($script)->replaceLast('.css', '.min.css')->toString();
                }

                return response()->file(__DIR__.'/../../dist/'.$script, [
                    'Content-Type' => $type,
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            });
        }
    }
}