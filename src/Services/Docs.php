<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Collection;

class Docs
{
    const CATEGORIES = [
        'Form inputs' => ['input', 'textarea', 'select', 'checkbox', 'radio', 'toggle', 'date-picker', 'time-picker', 'uploader', 'editor'],
        'Buttons & links' => ['button', 'link'],
        'Display & typography' => ['heading', 'subheading', 'caption', 'label', 'avatar', 'badge', 'card', 'callout', 'skeleton', 'placeholder-bar', 'empty', 'profile', 'icon', 'logo'],
        'Feedback & overlays' => ['modal', 'alert', 'toast', 'confirm', 'tooltip', 'dropdown', 'lightbox'],
        'Layout & navigation' => ['form', 'table', 'tabs', 'list', 'menu', 'navlist', 'breadcrumbs', 'calendar', 'separator', 'layouts'],
    ];

    const GALLERIES = ['icon', 'logo'];

    /**
     * All top-level components, sorted by name
     */
    public function components() : Collection
    {
        return collect(scandir($this->path()))
            ->reject(fn ($entry) => in_array($entry, ['.', '..', 'docs']))
            ->reject(fn ($entry) => str($entry)->startsWith('_'))
            ->map(fn ($entry) => str($entry)->before('.blade.php')->toString())
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($name) => [
                'name' => $name,
                'tag' => '<atom:'.$name.'>',
                'category' => $this->category($name),
                'isGallery' => in_array($name, static::GALLERIES),
            ]);
    }

    /**
     * Components grouped by category, in CATEGORIES order with Miscellaneous last
     */
    public function grouped() : Collection
    {
        $order = [...array_keys(static::CATEGORIES), 'Miscellaneous'];

        return $this->components()
            ->groupBy('category')
            ->sortBy(fn ($components, $category) => array_search($category, $order));
    }

    /**
     * A single component with parsed props, or null if unknown
     */
    public function component($name) : ?array
    {
        $component = $this->components()->firstWhere('name', $name);

        if (!$component) return null;

        return [
            ...$component,
            'props' => $this->props($name),
            'path' => $this->relativePath($name),
        ];
    }

    /**
     * The category of a component
     */
    public function category($name) : string
    {
        foreach (static::CATEGORIES as $category => $names) {
            if (in_array($name, $names)) return $category;
        }

        return 'Miscellaneous';
    }

    /**
     * Parse the @props([...]) declaration of a component into [['name' => ..., 'default' => ...]]
     */
    public function props($name) : array
    {
        if (!$file = $this->file($name)) return [];

        $content = file_get_contents($file);

        if (!preg_match('/@props\(\[(.*?)\]\)/s', $content, $matches)) return [];

        try {
            // evaluates the package's own committed @props source (not user input); docs routes are local-env only
            $props = eval('return ['.$matches[1].'];');
        } catch (\Throwable $e) {
            // fall back to prop names only
            preg_match_all('/[\'"]([a-zA-Z0-9\-_:.]+)[\'"]\s*=>/', $matches[1], $keys);

            return collect($keys[1])->map(fn ($key) => ['name' => $key, 'default' => null])->all();
        }

        return collect($props)->map(fn ($default, $key) => is_int($key)
            ? ['name' => $default, 'default' => null]
            : ['name' => $key, 'default' => $default]
        )->values()->all();
    }

    /**
     * Absolute path to a component's main blade file, or null
     */
    public function file($name) : ?string
    {
        return collect([
            $this->path($name.'/index.blade.php'),
            $this->path($name.'.blade.php'),
        ])->first(fn ($path) => file_exists($path));
    }

    /**
     * Repo-relative path to a component's main blade file, for display
     */
    public function relativePath($name) : string
    {
        if ($file = $this->file($name)) {
            return 'components/'.str($file)->after($this->path().'/')->toString();
        }

        return 'components/'.$name.'/';
    }

    /**
     * The raw blade source of a view, for code display
     */
    public function source($view) : string
    {
        return trim(file_get_contents(view($view)->getPath()));
    }

    /**
     * All icon names (excludes _wrapper and other underscore-prefixed partials)
     */
    public function icons() : Collection
    {
        return $this->glyphs('icon');
    }

    /**
     * All logo names
     */
    public function logos() : Collection
    {
        return $this->glyphs('logo');
    }

    /**
     * Scan a glyph directory (icon/logo) for component names
     */
    protected function glyphs($dir) : Collection
    {
        return collect(glob($this->path($dir.'/*.blade.php')))
            ->map(fn ($path) => basename($path, '.blade.php'))
            ->reject(fn ($name) => $name === 'index' || str($name)->startsWith('_'))
            ->sort()
            ->values();
    }

    /**
     * Absolute path into the components directory
     */
    public function path($append = '') : string
    {
        return realpath(__DIR__.'/../../components').($append ? '/'.$append : '');
    }
}
