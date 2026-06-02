# Component Directory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A browsable component directory at `/atom/docs` (local env only) with live previews, code snippets, auto-generated prop tables, and icon/logo galleries — per the approved spec at `docs/superpowers/specs/2026-06-02-component-directory-design.md`.

**Architecture:** A `Services\Docs` class scans `components/` and parses `@props`. Routes live in `routes/web.php` behind an `app()->environment('local')` check. Reusable docs chrome (layout/example/prop-table) are anonymous Blade components under `components/docs/` (excluded from the scanner); routable pages are views under `resources/views/docs/`. Each example is a tiny Blade partial that is BOTH `@include`d (live render) AND read as text (code snippet) — code shown is code run.

**Tech Stack:** Pure PHP + Blade + Alpine (already bundled). **No JS/CSS source changes → no `npm run build`, no `dist/` commit.**

**Testing note:** This package has no test suite (per CLAUDE.md and the approved spec). Verification = `php -l` on PHP files, a `php -r` smoke harness against `vendor/autoload.php` for the service, and a final end-to-end Playwright walk in a host app (Task 30). Blade files cannot be executed in this repo — they are verified in Task 30.

**Execution context:** Work in a worktree (EnterWorktree) on a feature branch, e.g. `component-directory`. Commit after every task.

**Critical background for the engineer (read before Task 1):**

- `<atom:foo.bar>` tag syntax compiles to `<x-atom::foo.bar>` → file `components/foo/bar.blade.php`. Components are anonymous; `components/` is registered via `Blade::anonymousComponentPath(__DIR__.'/../components', 'atom')` in `src/AtomServiceProvider.php:33`.
- `resources/views/` is registered under the `atom` view namespace → `view('atom::docs.index')` = `resources/views/docs/index.blade.php`.
- `routes/web.php` is loaded by the provider at `src/AtomServiceProvider.php:28` — BEFORE `Asset::boot()` registers `GET /atom/{file}` (line 40), so `/atom/docs` wins route matching (first registered wins).
- The docs pages are plain Blade routes, NOT Livewire. `wire:model` in demo snippets renders inert (intended; snippets show real-world usage). Alpine directives only activate inside an `x-data` scope — interactive demo partials MUST wrap content in `<div x-data ...>`.
- `window.atom` JS helpers (from `resources/js/helpers/`): `atom.toast(config)`, `atom.alert(config)` (Promise), `atom.confirm(config)` (Promise), `atom.modal(name).show()/.slide()/.close()`. All dispatch window `CustomEvent`s (`atom-toast-show` etc.) that the `<atom:toast/>`, `<atom:alert/>`, `<atom:confirm/>` components listen for. `<atom:layouts.sidebar>` already mounts all three (components/layouts/sidebar.blade.php:190-192) — demo partials must NOT mount them again.
- `<atom:copy :value="...">` (components/copy.blade.php) is an existing click-to-copy component using the `$clipboard` Alpine magic. Slot optional (defaults to a copy icon).
- `<atom:layouts.sidebar>` props: `title`, `dark`, `editor`, `noindex`, `scripts`, `styles`; slots: `brand`, `nav`, `navfoot`, `dropdown`, `profile`, `footer`. Without `dropdown`/`profile` slots it never touches `auth()` — safe on guest routes.
- NEVER name a Blade view/loop variable $component — inside any component-tag slot, Blade's compiled code shadows it with the AnonymousComponent instance. Docs views use $entry/$item instead. Also: docs pages render no Livewire component, so the docs layout includes @livewireScripts explicitly (Alpine ships with Livewire).

---

## Phase 1 — Framework

### Task 1: `Services\Docs`

**Files:**
- Create: `src/Services/Docs.php`

- [ ] **Step 1: Write the class**

```php
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
```

- [ ] **Step 2: Lint**

Run: `php -l src/Services/Docs.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test with the composer autoloader** (no Laravel app needed — `components()`, `props()`, `icons()` use only `illuminate/support` helpers; `source()` is the only method needing a booted app and is exercised in Task 30)

Run:

```bash
php -r "
require 'vendor/autoload.php';
\$d = new Jiannius\Atom\Services\Docs;
echo 'count: '.\$d->components()->count().PHP_EOL;
echo 'button category: '.\$d->category('button').PHP_EOL;
echo 'button props: '.count(\$d->props('button')).PHP_EOL;
echo 'toast props: '.count(\$d->props('toast')).PHP_EOL;
echo 'icons: '.\$d->icons()->count().PHP_EOL;
echo 'logos: '.\$d->logos()->count().PHP_EOL;
echo 'unknown: '.var_export(\$d->component('nope'), true).PHP_EOL;
"
```

Expected output (counts may drift ±1 if components were added since):
```
count: 51
button category: Buttons & links
button props: 13
toast props: 0
icons: 204
logos: 10
unknown: NULL
```

If `vendor/` is missing, run `composer install` first.

- [ ] **Step 4: Commit**

```bash
git add src/Services/Docs.php
git commit -m "feat(docs): add Docs service - component scanner, props parser, glyph lists"
```

---

### Task 2: Docs chrome components (layout, example, prop table)

**Files:**
- Create: `components/docs/layout.blade.php`
- Create: `components/docs/example.blade.php`
- Create: `components/docs/props.blade.php`

These live in `components/` so the `<atom:docs.*>` tag syntax works; the scanner already excludes the `docs` entry (Task 1).

- [ ] **Step 1: Create `components/docs/layout.blade.php`**

```blade
@props([
    'title' => null,
    'editor' => false,
])

<atom:layouts.sidebar :title="trim(($title ? $title.' — ' : '').'Atom Docs')" :editor="$editor">
    <x-slot:brand>
        <a href="{{ route('atom.docs') }}" class="me-5 flex items-center gap-2 px-1">
            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                <atom:icon.blocks class="size-5"/>
            </div>
            <span class="text-lg font-medium">Atom Docs</span>
        </a>
    </x-slot:brand>

    <x-slot:nav>
        <div x-data="{ q: '' }" class="flex flex-col gap-4">
            <atom:input placeholder="Search components..." x-model="q"/>

            <atom:navlist>
                @foreach (app(\Jiannius\Atom\Services\Docs::class)->grouped() as $category => $items)
                    <atom:navlist.group :heading="$category">
                        @foreach ($items as $item)
                            <atom:navlist.item
                            :href="route('atom.docs.show', $item['name'])"
                            :x-show="'!q || '.js($item['name']).'.includes(q.toLowerCase())'">
                                {{ $item['name'] }}
                            </atom:navlist.item>
                        @endforeach
                    </atom:navlist.group>
                @endforeach
            </atom:navlist>
        </div>
    </x-slot:nav>

    <div class="max-w-3xl p-6 lg:p-8">
        {{ $slot }}
    </div>

    {{-- docs pages render no Livewire component, so Livewire never auto-injects its
         assets — but atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
    @livewireScripts
</atom:layouts.sidebar>
```

- [ ] **Step 2: Create `components/docs/example.blade.php`**

```blade
@props([
    'title' => null,
    'description' => null,
    'view' => null,
])

@php
$source = app(\Jiannius\Atom\Services\Docs::class)->source($view);
@endphp

<section class="mb-12">
    <atom:heading>{{ $title }}</atom:heading>

    @if ($description)
        <atom:caption class="mt-1">{{ $description }}</atom:caption>
    @endif

    <div class="mt-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        @include($view)
    </div>

    <div class="relative mt-2 rounded-xl bg-zinc-900 dark:border dark:border-zinc-700">
        <div class="absolute end-3 top-3 text-zinc-400 hover:text-white">
            <atom:copy :value="$source"/>
        </div>

        <pre class="overflow-x-auto p-4 text-sm text-zinc-100"><code>{{ $source }}</code></pre>
    </div>
</section>
```

- [ ] **Step 3: Create `components/docs/props.blade.php`**

```blade
@props([
    'props' => [],
])

@if (count($props))
    <atom:table :empty="false" class="mt-3">
        <x-slot:columns>
            <atom:table.column>Prop</atom:table.column>
            <atom:table.column>Default</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($props as $prop)
                <atom:table.row>
                    <atom:table.cell><code class="text-sm">{{ $prop['name'] }}</code></atom:table.cell>
                    <atom:table.cell muted><code class="text-sm">{{ var_export($prop['default'], true) }}</code></atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
@else
    <atom:caption class="mt-3">No props declared — attributes pass through to the root element.</atom:caption>
@endif
```

- [ ] **Step 4: Cross-check `<atom:table>` slot names against `components/table/index.blade.php`** — expected named slots `columns` and `rows`, children `<atom:table.column>`, `<atom:table.row>`, `<atom:table.cell>` (cell has a `muted` prop). Adjust if they differ.

- [ ] **Step 5: Commit**

```bash
git add components/docs/
git commit -m "feat(docs): add docs chrome components - layout, example, prop table"
```

---

### Task 3: Routable pages (index, show, fallback)

**Files:**
- Create: `resources/views/docs/index.blade.php`
- Create: `resources/views/docs/show.blade.php`
- Create: `resources/views/docs/fallback.blade.php`

- [ ] **Step 1: Create `resources/views/docs/index.blade.php`** (receives `$docs` = the Docs service, from the route)

```blade
<atom:docs.layout>
    <atom:heading size="xl">Atom Components</atom:heading>

    <atom:caption class="mt-2">
        {{ $docs->components()->count() }} components. Pick one from the sidebar, or browse by category below.
        Pages with authored examples show live previews; the rest show an auto-generated prop reference.
    </atom:caption>

    @foreach ($docs->grouped() as $category => $items)
        <div class="mt-10">
            <atom:heading>{{ $category }}</atom:heading>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($items as $item)
                    <a
                    href="{{ route('atom.docs.show', $item['name']) }}"
                    class="rounded-lg border border-zinc-200 px-4 py-3 font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</atom:docs.layout>
```

- [ ] **Step 2: Create `resources/views/docs/show.blade.php`** (receives `$entry` array from the route)

```blade
<atom:docs.layout :title="str($entry['name'])->headline()" :editor="$entry['name'] === 'editor'">
    <atom:heading size="xl">{{ str($entry['name'])->headline() }}</atom:heading>

    <div class="mt-2 flex items-center gap-2">
        <code class="rounded bg-zinc-100 px-2 py-1 text-sm dark:bg-zinc-800">{{ $entry['tag'] }}</code>
        <atom:copy :value="$entry['tag']"/>
    </div>

    <div class="mt-10">
        @if ($entry['isGallery'])
            @include('atom::docs.gallery.'.$entry['name'])
        @elseif (view()->exists('atom::docs.demos.'.$entry['name']))
            @include('atom::docs.demos.'.$entry['name'])
        @else
            @include('atom::docs.fallback')
        @endif
    </div>

    @unless ($entry['isGallery'])
        <div class="mt-12">
            <atom:heading>Props</atom:heading>
            <atom:docs.props :props="$entry['props']"/>
        </div>

        <div class="mt-6">
            <atom:caption>Source: {{ $entry['path'] }}</atom:caption>
        </div>
    @endunless
</atom:docs.layout>
```

- [ ] **Step 3: Create `resources/views/docs/fallback.blade.php`**

```blade
<atom:callout
icon="info"
heading="Examples pending"
content="This component doesn't have authored examples yet. The prop reference below is generated from its props declaration."/>
```

- [ ] **Step 4: Cross-check `<atom:callout>` props against `components/callout.blade.php`** — expected `icon`, `heading`, `content`. Adjust if they differ.

- [ ] **Step 5: Commit**

```bash
git add resources/views/docs/
git commit -m "feat(docs): add docs pages - index, show shell, fallback"
```

---

### Task 4: Routes

**Files:**
- Modify: `routes/web.php` (currently only imports the Route facade)

- [ ] **Step 1: Replace the file content**

```php
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
```

- [ ] **Step 2: Lint**

Run: `php -l routes/web.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(docs): mount /atom/docs routes, local environment only"
```

---

### Task 5: Icon gallery

**Files:**
- Create: `resources/views/docs/gallery/icon.blade.php`

- [ ] **Step 1: Create the gallery view**

```blade
@php
$icons = app(\Jiannius\Atom\Services\Docs::class)->icons();
@endphp

<div x-data="{ q: '' }">
    <atom:input placeholder="Search {{ $icons->count() }} icons..." x-model="q"/>

    <atom:caption class="mt-2">Click an icon to copy its tag.</atom:caption>

    <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-5">
        @foreach ($icons as $icon)
            <div x-show="!q || @js($icon).includes(q.toLowerCase())">
                <atom:copy :value="'<atom:icon.'.$icon.'/>'">
                    <div class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5"/>
                        <span class="w-full truncate text-center text-xs">{{ $icon }}</span>
                    </div>
                </atom:copy>
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 2: Note on the wrapper div** — do NOT move `x-show` onto `<atom:copy>` itself: copy renders a `class="contents"` div, and `display: contents` beats the `display: none` that `x-show` applies. The plain wrapper div is required for filtering to work.

- [ ] **Step 3: Commit**

```bash
git add resources/views/docs/gallery/icon.blade.php
git commit -m "feat(docs): add icon gallery with search and click-to-copy"
```

---

### Task 6: Logo gallery

**Files:**
- Create: `resources/views/docs/gallery/logo.blade.php`

- [ ] **Step 1: Create the gallery view** (same shape as icons; logos are few, no search needed)

```blade
@php
$logos = app(\Jiannius\Atom\Services\Docs::class)->logos();
@endphp

<div>
    <atom:caption>Payment and brand marks. Click a logo to copy its tag.</atom:caption>

    <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-5">
        @foreach ($logos as $logo)
            <atom:copy :value="'<atom:logo.'.$logo.'/>'">
                <div class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <x-dynamic-component :component="'atom::logo.'.$logo" class="h-6"/>
                    <span class="w-full truncate text-center text-xs">{{ $logo }}</span>
                </div>
            </atom:copy>
        @endforeach
    </div>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/docs/gallery/logo.blade.php
git commit -m "feat(docs): add logo gallery with click-to-copy"
```

---

## Phase 2 — Demo pages (22 components)

**Pattern for every task in this phase.** A demo page is `resources/views/docs/demos/<name>.blade.php` containing only `<atom:docs.example>` calls; each example's markup lives in `resources/views/docs/demos/<name>/<example>.blade.php`. Rules:

1. Interactive partials (anything with `x-on`, `x-model`, or JS helpers) wrap content in `<div x-data ...>` — docs pages have no Livewire, and Alpine directives need an `x-data` scope.
2. Never mount `<atom:toast/>`, `<atom:alert/>`, `<atom:confirm/>` in a partial — the layout already does.
3. Before committing, cross-check every prop you used against the component's `@props` block: `sed -n '/@props(/,/^\])/p' components/<name>/index.blade.php` (or `components/<name>.blade.php` for flat components). The snippets below were written against the actual `@props` blocks on 2026-06-02, but components evolve — trust the source over this plan.
4. Commit message: `feat(docs): add <name> demo page`.

### Task 7: `button` demo page

**Files:**
- Create: `resources/views/docs/demos/button.blade.php`
- Create: `resources/views/docs/demos/button/variants.blade.php`
- Create: `resources/views/docs/demos/button/sizes.blade.php`
- Create: `resources/views/docs/demos/button/icons.blade.php`
- Create: `resources/views/docs/demos/button/delete.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/button/variants.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:button>Default</atom:button>
    <atom:button variant="primary">Primary</atom:button>
    <atom:button variant="accent">Accent</atom:button>
    <atom:button variant="warning">Warning</atom:button>
    <atom:button variant="danger">Danger</atom:button>
    <atom:button variant="ghost">Ghost</atom:button>
    <atom:button variant="link">Link</atom:button>
</div>
```

`resources/views/docs/demos/button/sizes.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:button size="xs">Extra small</atom:button>
    <atom:button size="sm">Small</atom:button>
    <atom:button>Default</atom:button>
    <atom:button size="md">Medium</atom:button>
    <atom:button size="lg">Large</atom:button>
</div>
```

`resources/views/docs/demos/button/icons.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:button icon="add">New record</atom:button>
    <atom:button iconSuffix="arrow-right">Continue</atom:button>
    <atom:button icon="settings"/>
</div>
```

`resources/views/docs/demos/button/delete.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button type="delete">Delete record</atom:button>
    <atom:button type="submit">Submit</atom:button>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/button.blade.php`:

```blade
<atom:docs.example
title="Variants"
description="Color and emphasis via the variant prop. Social variants (facebook, google, linkedin, whatsapp, telegram) also exist."
view="atom::docs.demos.button.variants"/>

<atom:docs.example
title="Sizes"
description="xs, sm, default, md, lg."
view="atom::docs.demos.button.sizes"/>

<atom:docs.example
title="Icons"
description="icon prefixes, iconSuffix appends, and a slotless button renders icon-only square."
view="atom::docs.demos.button.icons"/>

<atom:docs.example
title="Submit & delete types"
description="type=submit styles as primary with loading state on wire submit. type=delete auto-wires the confirm dialog and dispatches confirmed → $wire.delete() unless you override wire:click or x-on:click."
view="atom::docs.demos.button.delete"/>
```

- [ ] **Step 3: Cross-check props** per the phase pattern (file: `components/button/index.blade.php`)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/button.blade.php resources/views/docs/demos/button/
git commit -m "feat(docs): add button demo page"
```

### Task 8: `input` demo page

**Files:**
- Create: `resources/views/docs/demos/input.blade.php`
- Create: `resources/views/docs/demos/input/basic.blade.php`
- Create: `resources/views/docs/demos/input/types.blade.php`
- Create: `resources/views/docs/demos/input/affixes.blade.php`
- Create: `resources/views/docs/demos/input/states.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/input/basic.blade.php`:

```blade
<atom:input label="Name" caption="As shown on your identity card." placeholder="Jane Cooper"/>
```

`resources/views/docs/demos/input/types.blade.php`:

```blade
<div class="space-y-4">
    <atom:input type="email" label="Email" placeholder="jane@example.com"/>
    <atom:input type="password" label="Password"/>
    <atom:input type="number" label="Quantity"/>
    <atom:input type="tel" label="Phone"/>
    <atom:input type="color" label="Brand color"/>
</div>
```

`resources/views/docs/demos/input/affixes.blade.php`:

```blade
<div class="space-y-4">
    <atom:input label="Website" prefix="https://"/>
    <atom:input label="Username" suffix="@jiannius.com"/>
</div>
```

`resources/views/docs/demos/input/states.blade.php`:

```blade
<div class="space-y-4">
    <atom:input label="Company" required/>
    <atom:input label="Email" error="This email is already taken."/>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/input.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Label and caption render through the shared input field wrapper. Bind with wire:model in Livewire components."
view="atom::docs.demos.input.basic"/>

<atom:docs.example
title="Types"
description="text (default), email, password, number, tel, color. tel and email get dedicated Alpine behaviors."
view="atom::docs.demos.input.types"/>

<atom:docs.example
title="Prefix & suffix"
view="atom::docs.demos.input.affixes"/>

<atom:docs.example
title="Required & error"
description="error accepts a message string; in Livewire it defaults to the validation error for the bound field."
view="atom::docs.demos.input.states"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/input/index.blade.php` — expected: name, type, label, caption, prefix, suffix, required, error)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/input.blade.php resources/views/docs/demos/input/
git commit -m "feat(docs): add input demo page"
```

### Task 9: `textarea` demo page

**Files:**
- Create: `resources/views/docs/demos/textarea.blade.php`
- Create: `resources/views/docs/demos/textarea/basic.blade.php`
- Create: `resources/views/docs/demos/textarea/autoresize.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/textarea/basic.blade.php`:

```blade
<atom:textarea label="Bio" caption="A few sentences about yourself." rows="4" placeholder="Write something..."/>
```

`resources/views/docs/demos/textarea/autoresize.blade.php`:

```blade
<atom:textarea label="Notes" autoresize placeholder="Grows as you type..."/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/textarea.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="rows defaults to 3."
view="atom::docs.demos.textarea.basic"/>

<atom:docs.example
title="Autoresize"
description="Uses the alpine-autosize plugin bundled with atom."
view="atom::docs.demos.textarea.autoresize"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/textarea.blade.php` — expected: name, label, caption, invalid, autoresize, placeholder, required, error, variant, rows)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/textarea.blade.php resources/views/docs/demos/textarea/
git commit -m "feat(docs): add textarea demo page"
```

### Task 10: `select` demo page

**Files:**
- Create: `resources/views/docs/demos/select.blade.php`
- Create: `resources/views/docs/demos/select/native.blade.php`
- Create: `resources/views/docs/demos/select/listbox.blade.php`
- Create: `resources/views/docs/demos/select/filter.blade.php`
- Create: `resources/views/docs/demos/select/groups.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/select/native.blade.php`:

```blade
<atom:select label="Country">
    <atom:select.option value="my" label="Malaysia"/>
    <atom:select.option value="sg" label="Singapore"/>
    <atom:select.option value="id" label="Indonesia"/>
</atom:select>
```

`resources/views/docs/demos/select/listbox.blade.php`:

```blade
<atom:select
variant="listbox"
label="Assignee"
:options="[
    ['value' => 1, 'label' => 'Jane Cooper'],
    ['value' => 2, 'label' => 'Wade Warren'],
    ['value' => 3, 'label' => 'Esther Howard'],
]"/>
```

`resources/views/docs/demos/select/filter.blade.php`:

```blade
<atom:select
variant="filter"
label="Customer"
:options="[
    ['value' => 1, 'label' => 'Acme Sdn Bhd'],
    ['value' => 2, 'label' => 'Globex Pte Ltd'],
    ['value' => 3, 'label' => 'Initech Bhd'],
]"/>
```

`resources/views/docs/demos/select/groups.blade.php`:

```blade
<atom:select label="Team">
    <atom:select.group label="Engineering">
        <atom:select.option value="fe" label="Frontend"/>
        <atom:select.option value="be" label="Backend"/>
    </atom:select.group>

    <atom:select.group label="Design">
        <atom:select.option value="ux" label="UX"/>
        <atom:select.option value="ui" label="UI"/>
    </atom:select.group>
</atom:select>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/select.blade.php`:

```blade
<atom:docs.example
title="Native"
description="The default variant renders a native select element."
view="atom::docs.demos.select.native"/>

<atom:docs.example
title="Listbox"
description="Custom-rendered dropdown with keyboard navigation. Options are passed as an array of value/label pairs, not slot children."
view="atom::docs.demos.select.listbox"/>

<atom:docs.example
title="Filter"
description="Listbox with a search input for long option lists. Takes the same options array as listbox."
view="atom::docs.demos.select.filter"/>

<atom:docs.example
title="Grouped options"
view="atom::docs.demos.select.groups"/>
```

- [ ] **Step 3: Cross-check props** — also check children: `components/select/option.blade.php` (expected prop: label; value passes through attributes) and `components/select/group.blade.php` (verify its heading prop name — the plan assumes `label`; if it's `heading`, fix the groups partial).

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/select.blade.php resources/views/docs/demos/select/
git commit -m "feat(docs): add select demo page"
```

### Task 11: `checkbox` demo page

**Files:**
- Create: `resources/views/docs/demos/checkbox.blade.php`
- Create: `resources/views/docs/demos/checkbox/basic.blade.php`
- Create: `resources/views/docs/demos/checkbox/group.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/checkbox/basic.blade.php`:

```blade
<atom:checkbox label="Email me about product updates" caption="You can unsubscribe at any time."/>
```

`resources/views/docs/demos/checkbox/group.blade.php`:

```blade
<atom:checkbox.group>
    <atom:checkbox label="Email"/>
    <atom:checkbox label="SMS"/>
    <atom:checkbox label="WhatsApp"/>
</atom:checkbox.group>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/checkbox.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="align (start, center, end) controls label alignment against the box."
view="atom::docs.demos.checkbox.basic"/>

<atom:docs.example
title="Group"
view="atom::docs.demos.checkbox.group"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/checkbox/index.blade.php`, `components/checkbox/group.blade.php`)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/checkbox.blade.php resources/views/docs/demos/checkbox/
git commit -m "feat(docs): add checkbox demo page"
```

### Task 12: `radio` demo page

**Files:**
- Create: `resources/views/docs/demos/radio.blade.php`
- Create: `resources/views/docs/demos/radio/group.blade.php`

- [ ] **Step 1: Create the example partial**

`resources/views/docs/demos/radio/group.blade.php`:

```blade
<atom:radio.group label="Plan" caption="Switch anytime.">
    <atom:radio name="plan" label="Starter" value="starter"/>
    <atom:radio name="plan" label="Growth" value="growth"/>
    <atom:radio name="plan" label="Enterprise" value="enterprise"/>
</atom:radio.group>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/radio.blade.php`:

```blade
<atom:docs.example
title="Group"
description="Each radio carries the shared name (or a wire:model binding); the group provides the label, caption and error."
view="atom::docs.demos.radio.group"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/radio/index.blade.php` — label, caption, align; `components/radio/group.blade.php` — name, label, caption, inline, required, error)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/radio.blade.php resources/views/docs/demos/radio/
git commit -m "feat(docs): add radio demo page"
```

### Task 13: `toggle` demo page

**Files:**
- Create: `resources/views/docs/demos/toggle.blade.php`
- Create: `resources/views/docs/demos/toggle/basic.blade.php`
- Create: `resources/views/docs/demos/toggle/group.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/toggle/basic.blade.php`:

```blade
<atom:toggle label="Enable notifications" caption="Receive a push when something happens."/>
```

`resources/views/docs/demos/toggle/group.blade.php`:

```blade
<atom:toggle.group>
    <atom:toggle label="Email digest"/>
    <atom:toggle label="Weekly report"/>
    <atom:toggle label="Marketing"/>
</atom:toggle.group>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/toggle.blade.php`:

```blade
<atom:docs.example
title="Basic"
view="atom::docs.demos.toggle.basic"/>

<atom:docs.example
title="Group"
view="atom::docs.demos.toggle.group"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/toggle/index.blade.php`, `components/toggle/group.blade.php` — the group declares no `@props`; attributes pass through)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/toggle.blade.php resources/views/docs/demos/toggle/
git commit -m "feat(docs): add toggle demo page"
```

### Task 14: `date-picker` demo page

**Files:**
- Create: `resources/views/docs/demos/date-picker.blade.php`
- Create: `resources/views/docs/demos/date-picker/date.blade.php`
- Create: `resources/views/docs/demos/date-picker/range.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/date-picker/date.blade.php`:

```blade
<atom:date-picker label="Issue date"/>
```

`resources/views/docs/demos/date-picker/range.blade.php`:

```blade
<atom:date-picker variant="range" label="Reporting period"/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/date-picker.blade.php`:

```blade
<atom:docs.example
title="Date"
description="The default variant: a single date with a popover calendar."
view="atom::docs.demos.date-picker.date"/>

<atom:docs.example
title="Range"
description="Start and end date in one control. Pairs with the whereDateBetween builder macro."
view="atom::docs.demos.date-picker.range"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/date-picker/index.blade.php` — name, variant, label, caption, inline, required, error, prefix, suffix)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/date-picker.blade.php resources/views/docs/demos/date-picker/
git commit -m "feat(docs): add date-picker demo page"
```

### Task 15: `time-picker` demo page

**Files:**
- Create: `resources/views/docs/demos/time-picker.blade.php`
- Create: `resources/views/docs/demos/time-picker/basic.blade.php`
- Create: `resources/views/docs/demos/time-picker/inline.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/time-picker/basic.blade.php`:

```blade
<atom:time-picker label="Opens at"/>
```

`resources/views/docs/demos/time-picker/inline.blade.php`:

```blade
<atom:time-picker label="Closes at" inline/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/time-picker.blade.php`:

```blade
<atom:docs.example
title="Basic"
view="atom::docs.demos.time-picker.basic"/>

<atom:docs.example
title="Inline label"
view="atom::docs.demos.time-picker.inline"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/time-picker.blade.php` — name, label, caption, inline, required, invalid, error)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/time-picker.blade.php resources/views/docs/demos/time-picker/
git commit -m "feat(docs): add time-picker demo page"
```

### Task 16: `uploader` demo page

**Files:**
- Create: `resources/views/docs/demos/uploader.blade.php`
- Create: `resources/views/docs/demos/uploader/basic.blade.php`

- [ ] **Step 1: Create the example partial**

`resources/views/docs/demos/uploader/basic.blade.php`:

```blade
<atom:uploader label="Upload attachment"/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/uploader.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Actual uploads go through Livewire's WithFileUploads (included in AtomComponent) — bind with wire:model. This preview renders the trigger UI only."
view="atom::docs.demos.uploader.basic"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/uploader/index.blade.php` — label, variant, size)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/uploader.blade.php resources/views/docs/demos/uploader/
git commit -m "feat(docs): add uploader demo page"
```

### Task 17: `editor` demo page

**Files:**
- Create: `resources/views/docs/demos/editor.blade.php`
- Create: `resources/views/docs/demos/editor/basic.blade.php`

- [ ] **Step 1: Create the example partial**

`resources/views/docs/demos/editor/basic.blade.php`:

```blade
<atom:editor label="Article body" placeholder="Write something..."/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/editor.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Tiptap rich text. Requires editor CSS on the page — pass editor to atom:html (this docs page does it for you). Image uploads and persistence need a Livewire component plus the AsEditorContent cast; see the README's editor lifecycle section."
view="atom::docs.demos.editor.basic"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/editor/index.blade.php` — name, label, caption, required, error, readonly, autofocus, variant, mention, placeholder, toolbar). Reminder: `resources/views/docs/show.blade.php` already passes `:editor="$component['name'] === 'editor'"` to the layout — confirm that wiring survived Task 3.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/editor.blade.php resources/views/docs/demos/editor/
git commit -m "feat(docs): add editor demo page"
```

### Task 18: `form` demo page

**Files:**
- Create: `resources/views/docs/demos/form.blade.php`
- Create: `resources/views/docs/demos/form/basic.blade.php`

- [ ] **Step 1: Create the example partial**

`resources/views/docs/demos/form/basic.blade.php`:

```blade
<div x-data>
    <atom:form x-on:submit.prevent>
        <atom:input label="Name" required/>
        <atom:input type="email" label="Email" required/>
        <atom:textarea label="Message" rows="3"/>

        <atom:button.group>
            <atom:button>Cancel</atom:button>
            <atom:button type="submit">Send</atom:button>
        </atom:button.group>
    </atom:form>
</div>
```

Note: `x-on:submit.prevent` prevents the native GET reload on docs pages (no Livewire). The `<div x-data>` wrapper provides the Alpine scope required for the prevent modifier.

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/form.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Wraps a form and auto-wires the submit button's loading state. In Livewire, use wire:submit on the form."
view="atom::docs.demos.form.basic"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/form.blade.php` — inset)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/form.blade.php resources/views/docs/demos/form/
git commit -m "feat(docs): add form demo page"
```

### Task 19: `link` demo page

**Files:**
- Create: `resources/views/docs/demos/link.blade.php`
- Create: `resources/views/docs/demos/link/basic.blade.php`
- Create: `resources/views/docs/demos/link/icons.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/link/basic.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-6">
    <atom:link href="#">Default link</atom:link>
    <atom:link href="#" variant="accent">Accent link</atom:link>
    <atom:link href="https://github.com/jiannius/atom" newtab>New tab</atom:link>
</div>
```

`resources/views/docs/demos/link/icons.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-6">
    <atom:link href="#" icon="book">Documentation</atom:link>
    <atom:link href="#" iconSuffix="arrow-right">Next page</atom:link>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/link.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="newtab adds target=_blank with the rel defaults."
view="atom::docs.demos.link.basic"/>

<atom:docs.example
title="With icons"
view="atom::docs.demos.link.icons"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/link.blade.php` — href, icon, iconSuffix, variant, rel, newtab)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/link.blade.php resources/views/docs/demos/link/
git commit -m "feat(docs): add link demo page"
```

### Task 20: `modal` demo page

**Files:**
- Create: `resources/views/docs/demos/modal.blade.php`
- Create: `resources/views/docs/demos/modal/basic.blade.php`
- Create: `resources/views/docs/demos/modal/slide.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/modal/basic.blade.php`:

```blade
<div x-data>
    <atom:button x-on:click="atom.modal('demo-basic').show()">Open modal</atom:button>

    <atom:modal name="demo-basic">
        <div class="space-y-4 p-6">
            <atom:heading>Modal heading</atom:heading>
            <p>Any content can live here. Dismiss with the close button, ESC, or a backdrop click.</p>
            <atom:button x-on:click="atom.modal('demo-basic').close()">Close</atom:button>
        </div>
    </atom:modal>
</div>
```

`resources/views/docs/demos/modal/slide.blade.php`:

```blade
<div x-data>
    <atom:button x-on:click="atom.modal('demo-slide').slide()">Open slide-over</atom:button>

    <atom:modal name="demo-slide">
        <div class="space-y-4 p-6">
            <atom:heading>Slide-over</atom:heading>
            <p>Same modal component, slide variant. From PHP: atom()->modal('demo-slide')->slide().</p>
        </div>
    </atom:modal>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/modal.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="name is required so triggers can find the modal. Open from JS (atom.modal(name).show()), from a trigger component (atom:modal.trigger), or from PHP (atom()->modal(name)->show())."
view="atom::docs.demos.modal.basic"/>

<atom:docs.example
title="Slide-over"
view="atom::docs.demos.modal.slide"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/modal/index.blade.php` — name, inset, dismissible, closeable; `components/modal/trigger.blade.php` — name, slide, shortcut)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/modal.blade.php resources/views/docs/demos/modal/
git commit -m "feat(docs): add modal demo page"
```

### Task 21: `toast` demo page

**Files:**
- Create: `resources/views/docs/demos/toast.blade.php`
- Create: `resources/views/docs/demos/toast/variants.blade.php`
- Create: `resources/views/docs/demos/toast/positions.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/toast/variants.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button x-on:click="atom.toast({ message: 'Saved successfully.', variant: 'success' })">Success</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Check your input.', variant: 'warning' })">Warning</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Something went wrong.', variant: 'danger' })">Danger</atom:button>
</div>
```

`resources/views/docs/demos/toast/positions.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button x-on:click="atom.toast({ message: 'Bottom (default).' })">Bottom</atom:button>
    <atom:button x-on:click="atom.toast({ message: 'Center.', position: 'center' })">Center</atom:button>
</div>
```

Note: The component default position is `bottom`; there is no `top` position mapping.

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/toast.blade.php`:

```blade
<atom:docs.example
title="Variants"
description="Drop the toast component once in your root layout (atom:layouts.sidebar already includes it), then fire from JS with atom.toast(config) or from PHP with atom()->toast(...). Config keys: message, variant, delay (default 3000), position, align."
view="atom::docs.demos.toast.variants"/>

<atom:docs.example
title="Positions"
description="position defaults to bottom; center is also supported."
view="atom::docs.demos.toast.positions"/>
```

- [ ] **Step 3: Note** — `components/toast/index.blade.php` declares no `@props` (configured via the event payload); the props table will correctly show the "No props declared" caption.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/toast.blade.php resources/views/docs/demos/toast/
git commit -m "feat(docs): add toast demo page"
```

### Task 22: `alert` demo page

**Files:**
- Create: `resources/views/docs/demos/alert.blade.php`
- Create: `resources/views/docs/demos/alert/basic.blade.php`

- [ ] **Step 1: Create the example partial**

`resources/views/docs/demos/alert/basic.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    x-on:click="atom.alert({
        heading: 'Heads up',
        message: 'Your subscription expires tomorrow.',
        variant: 'warning',
        button: 'Got it',
    }).then(() => atom.toast({ message: 'Dismissed.' }))">
        Show alert
    </atom:button>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/alert.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="atom.alert(config) returns a Promise that resolves when dismissed. Config keys: heading, subheading, message, variant, button, onDismissed. From PHP: atom()->alert(...)."
view="atom::docs.demos.alert.basic"/>
```

- [ ] **Step 3: Note** — like toast, `components/alert/index.blade.php` declares no `@props`; the empty prop table is correct.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/alert.blade.php resources/views/docs/demos/alert/
git commit -m "feat(docs): add alert demo page"
```

### Task 23: `confirm` demo page

**Files:**
- Create: `resources/views/docs/demos/confirm.blade.php`
- Create: `resources/views/docs/demos/confirm/basic.blade.php`
- Create: `resources/views/docs/demos/confirm/password.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/confirm/basic.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    variant="danger"
    x-on:click="atom.confirm({
        variant: 'danger',
        heading: 'Delete customer?',
        message: 'This cannot be undone.',
    }).then(() => atom.toast({ message: 'Confirmed.', variant: 'success' })).catch(() => {})">
        Delete
    </atom:button>
</div>
```

`resources/views/docs/demos/confirm/password.blade.php`:

```blade
<div x-data class="flex flex-wrap items-center gap-3">
    <atom:button
    x-on:click="atom.confirm({
        heading: 'Transfer ownership?',
        message: 'Re-enter your password to continue.',
        password: true,
    }).then(({ password }) => atom.toast({ message: 'Confirmed.', variant: 'success' })).catch(() => {})">
        Transfer
    </atom:button>
</div>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/confirm.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="atom.confirm(config) returns a Promise — resolves on accept (with { password, passphrase, reason }), rejects on cancel; always chain .catch. From PHP: atom()->confirm(..., onAccepted: 'method'). Buttons with type=delete wire this automatically."
view="atom::docs.demos.confirm.basic"/>

<atom:docs.example
title="Password re-entry"
description="password: true requires the user's password; passphrase and a reason field are also supported."
view="atom::docs.demos.confirm.password"/>
```

- [ ] **Step 3: Note** — `components/confirm/index.blade.php` declares no `@props`; the empty prop table is correct.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/confirm.blade.php resources/views/docs/demos/confirm/
git commit -m "feat(docs): add confirm demo page"
```

### Task 24: `tooltip` demo page

**Files:**
- Create: `resources/views/docs/demos/tooltip.blade.php`
- Create: `resources/views/docs/demos/tooltip/positions.blade.php`
- Create: `resources/views/docs/demos/tooltip/kbd.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/tooltip/positions.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:tooltip content="Top (default)"><atom:button>Top</atom:button></atom:tooltip>
    <atom:tooltip content="Bottom" position="bottom"><atom:button>Bottom</atom:button></atom:tooltip>
    <atom:tooltip content="Left" position="left"><atom:button>Left</atom:button></atom:tooltip>
    <atom:tooltip content="Right" position="right"><atom:button>Right</atom:button></atom:tooltip>
</div>
```

`resources/views/docs/demos/tooltip/kbd.blade.php`:

```blade
<atom:tooltip content="Open the command palette" kbd="⌘K">
    <atom:button>Hover for shortcut</atom:button>
</atom:tooltip>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/tooltip.blade.php`:

```blade
<atom:docs.example
title="Positions"
description="top, bottom, left, right with start/center/end alignment."
view="atom::docs.demos.tooltip.positions"/>

<atom:docs.example
title="Keyboard hint"
view="atom::docs.demos.tooltip.kbd"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/tooltip/index.blade.php` — interactive, position, align, content, kbd, toggleable)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/tooltip.blade.php resources/views/docs/demos/tooltip/
git commit -m "feat(docs): add tooltip demo page"
```

### Task 25: `table` demo page

**Files:**
- Create: `resources/views/docs/demos/table.blade.php`
- Create: `resources/views/docs/demos/table/basic.blade.php`
- Create: `resources/views/docs/demos/table/empty.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/table/basic.blade.php`:

```blade
<atom:table :empty="false">
    <x-slot:columns>
        <atom:table.column>Customer</atom:table.column>
        <atom:table.column>Email</atom:table.column>
        <atom:table.column align="right">Amount</atom:table.column>
    </x-slot:columns>

    <x-slot:rows>
        <atom:table.row>
            <atom:table.cell>Jane Cooper</atom:table.cell>
            <atom:table.cell muted>jane@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 1,250.00</atom:table.cell>
        </atom:table.row>

        <atom:table.row>
            <atom:table.cell>Wade Warren</atom:table.cell>
            <atom:table.cell muted>wade@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 890.00</atom:table.cell>
        </atom:table.row>

        <atom:table.row>
            <atom:table.cell>Esther Howard</atom:table.cell>
            <atom:table.cell muted>esther@example.com</atom:table.cell>
            <atom:table.cell align="right">RM 2,400.00</atom:table.cell>
        </atom:table.row>
    </x-slot:rows>
</atom:table>
```

`resources/views/docs/demos/table/empty.blade.php`:

```blade
<atom:table :empty="true"/>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/table.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Static rows shown here. In Livewire, sorting, checkboxes, max rows and pagination are driven by the $_table state from AtomComponent plus the toTable() builder macro."
view="atom::docs.demos.table.basic"/>

<atom:docs.example
title="Empty state"
description="empty=true renders the empty-state component; with a paginator it is derived automatically."
view="atom::docs.demos.table.empty"/>
```

- [ ] **Step 3: Cross-check** — slots `columns`/`rows` and the `sort`/`checkbox` props on `components/table/column.blade.php` (sort/checkbox need Livewire; do not demo them).

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/table.blade.php resources/views/docs/demos/table/
git commit -m "feat(docs): add table demo page"
```

### Task 26: `tabs` demo page

**Files:**
- Create: `resources/views/docs/demos/tabs.blade.php`
- Create: `resources/views/docs/demos/tabs/basic.blade.php`
- Create: `resources/views/docs/demos/tabs/button.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/tabs/basic.blade.php`:

```blade
<atom:tabs>
    <atom:tabs.item label="Profile" current/>
    <atom:tabs.item label="Billing"/>
    <atom:tabs.item label="Team"/>
</atom:tabs>
```

`resources/views/docs/demos/tabs/button.blade.php`:

```blade
<atom:tabs variant="button" size="sm">
    <atom:tabs.item label="Day" current/>
    <atom:tabs.item label="Week"/>
    <atom:tabs.item label="Month"/>
</atom:tabs>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/tabs.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Static items shown with current. In Livewire, pass a tabs array plus wire:model and the active tab binds automatically."
view="atom::docs.demos.tabs.basic"/>

<atom:docs.example
title="Button variant"
view="atom::docs.demos.tabs.button"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/tabs/index.blade.php` — tabs, size, variant; `components/tabs/item.blade.php` — tab, label, value, icon, count, current, href, rel, newtab)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/tabs.blade.php resources/views/docs/demos/tabs/
git commit -m "feat(docs): add tabs demo page"
```

### Task 27: `card` demo page

**Files:**
- Create: `resources/views/docs/demos/card.blade.php`
- Create: `resources/views/docs/demos/card/basic.blade.php`
- Create: `resources/views/docs/demos/card/stats.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/card/basic.blade.php`:

```blade
<div class="grid gap-4 sm:grid-cols-2">
    <atom:card>
        <p>Default card. Content goes in the default slot.</p>
    </atom:card>

    <atom:card subtle>
        <p>Subtle card with muted chrome.</p>
    </atom:card>
</div>
```

`resources/views/docs/demos/card/stats.blade.php`:

```blade
<div class="grid gap-4 sm:grid-cols-2">
    <atom:card variant="stats" heading="Revenue" data="RM 48,200" :indicator="12.4" :trend="[8, 12, 9, 14, 18, 22]"/>
    <atom:card variant="stats" heading="Churn" data="2.1%" :indicator="-0.4"/>
</div>
```

(`indicator` is numeric — the component renders `abs($indicator)` with a `%` and colors by sign. `trend` is a numeric data array rendered as a mini chart, colored by the indicator's sign.)

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/card.blade.php`:

```blade
<atom:docs.example
title="Basic"
description="Content renders in the default slot. subtle, inset and divided modify the padding and chrome. heading renders in the stats and chart variants."
view="atom::docs.demos.card.basic"/>

<atom:docs.example
title="Stats variant"
view="atom::docs.demos.card.stats"/>
```

- [ ] **Step 3: Cross-check props** (file: `components/card.blade.php` — inset, subtle, divided, variant, heading, data, indicator, trend, type, color, max, min). Verified 2026-06-02: `indicator` numeric, `trend` numeric array.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/card.blade.php resources/views/docs/demos/card/
git commit -m "feat(docs): add card demo page"
```

### Task 28: `badge` demo page

**Files:**
- Create: `resources/views/docs/demos/badge.blade.php`
- Create: `resources/views/docs/demos/badge/colors.blade.php`
- Create: `resources/views/docs/demos/badge/sizes.blade.php`
- Create: `resources/views/docs/demos/badge/group.blade.php`

- [ ] **Step 1: Create the example partials**

`resources/views/docs/demos/badge/colors.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:badge label="Active" color="green"/>
    <atom:badge label="Pending" color="yellow"/>
    <atom:badge label="Cancelled" color="red"/>
    <atom:badge label="Draft"/>
    <atom:badge label="Custom" color="#8b5cf6"/>
</div>
```

`resources/views/docs/demos/badge/sizes.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:badge label="Extra small" size="xs"/>
    <atom:badge label="Default"/>
    <atom:badge label="Large" size="lg"/>
</div>
```

`resources/views/docs/demos/badge/group.blade.php`:

```blade
<atom:badge.group :max="3">
    <atom:badge label="Laravel"/>
    <atom:badge label="Livewire"/>
    <atom:badge label="Alpine"/>
    <atom:badge label="Tailwind"/>
    <atom:badge label="Vite"/>
</atom:badge.group>
```

- [ ] **Step 2: Create the demo page**

`resources/views/docs/demos/badge.blade.php`:

```blade
<atom:docs.example
title="Colors"
description="Named colors (red, blue, yellow, orange, green, purple, black) plus any hex value via the color prop."
view="atom::docs.demos.badge.colors"/>

<atom:docs.example
title="Sizes"
view="atom::docs.demos.badge.sizes"/>

<atom:docs.example
title="Group"
description="max collapses the overflow into a +N badge."
view="atom::docs.demos.badge.group"/>
```

- [ ] **Step 3: Cross-check props** (files: `components/badge/index.blade.php` — status, size, icon, color, label; `components/badge/group.blade.php` — max). Verified 2026-06-02: named colors are red, blue, yellow, orange, green, purple, black (anything else falls back to zinc); hex values like `#8b5cf6` get computed shades via `Services\Color`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/badge.blade.php resources/views/docs/demos/badge/
git commit -m "feat(docs): add badge demo page"
```

---

## Phase 3 — Documentation & verification

### Task 29: Update README and CLAUDE.md

**Files:**
- Modify: `README.md` (add a section after "Installation")
- Modify: `CLAUDE.md` (add to Architecture)

- [ ] **Step 1: Add a "Component directory" section to README.md** — insert after the "Page boilerplate" subsection:

```markdown
### Component directory

With the package installed and `APP_ENV=local`, visit **`/atom/docs`** in your app for a browsable directory of every component: live previews, copyable code snippets, auto-generated prop tables, and searchable icon/logo galleries. The routes are not registered outside the local environment.
```

- [ ] **Step 2: Add a short subsection to CLAUDE.md** under "Architecture" (after the Actions pattern section):

```markdown
### Component directory (`/atom/docs`)

Local-env-only routes (registered in `routes/web.php`) serve a browsable component directory. `Services\Docs` scans `components/` (excluding `docs/`), parses `@props` blocks for prop tables, and lists icon/logo glyphs. Docs chrome lives in `components/docs/` (layout, example, props); pages and demo partials live in `resources/views/docs/`. Each example partial is BOTH rendered live AND displayed as its own source — when editing a demo, remember the file text is the documentation. Undocumented components automatically get a fallback page, so new components need no docs work to appear.
```

- [ ] **Step 3: Commit**

```bash
git add README.md CLAUDE.md
git commit -m "docs: document the /atom/docs component directory"
```

### Task 30: End-to-end verification in a host app

**Files:** none (verification only)

- [ ] **Step 1: Point a local host app at the branch.** In a consuming app (e.g. `toocrm-l13`), add a path repository to its `composer.json` (or update the existing one) and require the feature branch:

```bash
# in the host app
composer config repositories.atom path /Users/tj/Projects/jiannius/atom-worktree-path
composer require jiannius/atom:@dev
php artisan view:clear && php artisan route:clear
```

(Use the actual worktree path. If the host app already has a path repo for atom, just clear caches.)

- [ ] **Step 2: Confirm the routes exist**

Run (in the host app): `php artisan route:list | grep atom`
Expected: `GET /atom/docs` and `GET /atom/docs/{component}` listed alongside the existing `/atom/{file}` and `POST /atom/action/{name}`.

- [ ] **Step 3: Playwright walk.** With the host app serving locally, verify each of these and screenshot as evidence:

1. `/atom/docs` renders: sidebar with 6 category groups, all ~51 components listed, landing grid.
2. Sidebar search filters the nav (type "but" → button remains, input disappears).
3. `/atom/docs/button`: 4 example sections render live; each code block matches its rendered example; copy button works (clipboard contains the snippet).
4. The delete-confirm demo opens the confirm dialog (click Delete → dialog appears → cancel).
5. `/atom/docs/toast`: clicking variant buttons fires visible toasts.
6. `/atom/docs/modal`: open + close modal, open slide-over.
7. `/atom/docs/editor`: Tiptap editor mounts (editor CSS loaded via the layout's editor flag).
8. A fallback page (e.g. `/atom/docs/avatar` — not in the priority set): callout + populated prop table + source path.
9. `/atom/docs/icon`: grid renders ~204 icons; search filters; clicking copies the tag.
10. `/atom/docs/logo`: grid renders ~10 logos.
11. `/atom/docs/nope` → 404.
12. Props table on `/atom/docs/button` lists 13 props with defaults.

- [ ] **Step 4: Gating check.** In the host app set `APP_ENV=production` temporarily (`php artisan config:clear` after), confirm `/atom/docs` returns 404, then revert to `local`.

- [ ] **Step 5: Fix anything found, commit fixes** with messages like `fix(docs): <issue>`.

---

## Completion

After Task 30 passes, use the superpowers:finishing-a-development-branch skill. Per the user's global rules: squash-merge to `main` with a single summary commit (e.g. `feat: add /atom/docs component directory with live previews and prop tables`), push, remove the worktree, delete the feature branch. Then tag a minor release (this is a `feat`): bump from the current `v3.1.0` → `v3.2.0` and push the tag.
