<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Collection;

class Docs
{
    const CATEGORIES = [
        'Form inputs' => ['input', 'textarea', 'select', 'checkbox', 'radio', 'toggle', 'date-picker', 'time-picker', 'uploader', 'tiptap'],
        'Buttons & links' => ['button', 'link'],
        'Display & typography' => ['heading', 'subheading', 'caption', 'label', 'avatar', 'badge', 'status', 'stat', 'timeline', 'card', 'callout', 'skeleton', 'placeholder-bar', 'empty', 'profile', 'icon', 'logo'],
        'Feedback & overlays' => ['modal', 'alert', 'toast', 'confirm', 'tooltip', 'dropdown', 'lightbox'],
        'Layout & navigation' => ['form', 'table', 'tabs', 'list', 'menu', 'navlist', 'breadcrumbs', 'page', 'calendar', 'separator', 'layouts'],
    ];

    const GALLERIES = ['icon', 'logo'];

    /**
     * Pattern pages: whole screens built from atom's components, under resources/views/docs/patterns/. Name => title.
     */
    const PATTERNS = [
        'app-shell-sidebar' => 'App shell (sidebar)',
        'app-shell-header' => 'App shell (top nav)',
        'record-page' => 'Record page',
        'list-page' => 'List page',
        'document-page' => 'Document page',
        'form-page' => 'Form page',
        'slide-over-form' => 'Slide-over form',
    ];

    /**
     * When to reach for each pattern page (the patterns overview prints it), and the query strings that change what
     * the page shows. Name => [use, try].
     */
    const PATTERN_NOTES = [
        'app-shell-sidebar' => ['use' => 'The shell for an app with many sections (about seven or more): every slot filled, one nav partial shared with the top-nav shell.', 'try' => '?page=customers'],
        'app-shell-header' => ['use' => 'The same slots and the same nav in the top-nav shell, for about six sections or fewer, or a role that can open only one or two.', 'try' => '?page=customers'],
        'record-page' => ['use' => 'One record (a work order): crumbs, title and status, facts, the one action for its current status, stats, tabs and the three empty states.', 'try' => '?status=blocked&tab=parts'],
        'list-page' => ['use' => 'A listing people filter and come back to: header, totals strip, then one panel of saved views, a toolbar and a frameless table.', 'try' => '?view=overdue'],
        'document-page' => ['use' => 'A printable document (an invoice) as paper, with its balance, details and history in a rail beside it.', 'try' => '?status=paid'],
        'form-page' => ['use' => 'A long form of sections, ending in the save bar, in a narrow page.', 'try' => '?layout=stacked'],
        'slide-over-form' => ['use' => 'A short create or edit form that opens over a list, so the list stays in view. A whole record is a page, never a slide-over.', 'try' => '?open=1'],
    ];

    /**
     * Files in the patterns directory that are not pages: the slots both app shell patterns share
     */
    const PATTERN_PARTIALS = [
        'nav' => 'The nav slot both shells include: plain navlist markup that knows nothing of the shell around it.',
        'crumbs' => 'The crumbs slot both shells include.',
        'page' => 'Placeholder page content for the two shell patterns.',
    ];

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
