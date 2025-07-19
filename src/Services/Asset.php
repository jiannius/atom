<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Arr;
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
        $hash = str($file)->remove('assets/')->remove('atom-')->remove('.css')->remove('.js')->toString();

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
                $id = request()->query('id');
                $script = str($script)->replaceLast('.js', '-'.$id.'.js')->replaceLast('.css', '-'.$id.'.css')->toString();

                return response()->file(__DIR__.'/../../dist/assets/'.$script, [
                    'Content-Type' => $type,
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            });
        }
    }
}