# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jiannius/atom` is a Laravel package (composer name `jiannius/atom`, PSR-4 namespace `Jiannius\Atom\`) that ships a Tailwind + Alpine + Livewire UI component library. It is consumed by other Laravel apps via `composer require`; this repo is the library itself, not a host app. There is no `.env`, no app boot, no test suite wired up — Orchestra Testbench is in dev deps but unused.

## Common commands

```bash
# Build front-end assets (Vite) → dist/assets + dist/manifest.json
npm run build

# PHP deps
composer install
```

There are no lint, test, or dev-server scripts defined. `vite.config.js` uses `laravel-vite-plugin`, but the package has no host Laravel app to serve from — `npm run build` produces a static `dist/` that is committed and served at runtime via the package's own `/atom/{file}` route.

The package's own artisan command (available in any consuming app):

```bash
php artisan atom:purge-editor-images        # dry-cleans unreferenced editor images
php artisan atom:purge-editor-images --force # empties the editor-purged backup folder
```

## Architecture

### Service-provider wiring (`src/AtomServiceProvider.php`)

`boot()` registers everything; read it first when something seems to come from nowhere:

- Loads `routes/web.php`, `database/migrations` (no directory exists yet — referenced for future use), `lang/`, and `resources/views/` under the `atom` view namespace.
- Registers anonymous Blade components in `components/` under the `atom` namespace, so `components/button/index.blade.php` is reachable as `<x-atom::button>` **or** as `<atom:button>` (see Tag compiler below).
- Swaps Laravel's `Date` facade to use `Jiannius\Atom\Services\Carbon`.
- Mixes in macros onto Eloquent `Builder`, Query `Builder`, `ComponentAttributeBag`, `Request`, `Str`, `Stringable`, `Arr` (`src/Macros/*`). These macros are how component blade files get methods like `$attributes->modifier()`, `$attributes->size()`, etc. — if you see an unfamiliar method on an attribute bag in a component, check `src/Macros/ComponentAttributeBag.php` before assuming it's framework.
- Boots `Services\Asset`, which exposes the public route `GET /atom/{file}` serving files from `dist/assets/` with `Cache-Control: immutable`. `atom()->asset()->version($name)` looks up the hashed filename in `dist/manifest.json`. Consuming apps reference assets by name, not path.
- Mounts a public `POST /atom/action/{name}` endpoint that the JS uses to invoke actions remotely (see "Actions" below).

### The `<atom:...>` tag syntax (`src/Services/TagCompiler.php`)

Custom Blade precompiler (inspired by livewire/flux) translates `<atom:button>`, `<atom:icon.add/>`, `<atom:button.group>` into the corresponding `<x-atom::...>` component invocations before the normal Blade compile runs. Dot-paths map to subdirectories: `<atom:button.group>` → `components/button/group.blade.php`. This syntax is preferred throughout `components/` over `<x-atom::...>`.

### The `Atom` singleton (`src/Atom.php`, aliased as `app('atom')` / via the `atom()` helper indirectly)

Single entry point for cross-cutting UI operations that need to dispatch Livewire events from PHP:

- `atom()->modal($name)->show()/slide()/close()` — dispatches `atom-modal-show` / `atom-modal-close` on the *current* Livewire component.
- `atom()->toast(...)`, `atom()->alert(...)`, `atom()->confirm(...)` — dispatch the matching `atom-toast-show` / `atom-alert-show` / `atom-confirm-show` events. All `heading`, `subheading`, `message` strings are passed through `t()` (translation helper).
- `atom()->action($name, $params)` — resolves an Action class from `App\Actions\{Name}` *or* `Jiannius\Atom\Actions\{Name}` and invokes `handle($params)` (or `$params['method']`). Powers the public `/atom/action/{name}` endpoint.
- `atom()->mail(...)`, `atom()->breadcrumbs()`, `atom()->broadcast()`, `atom()->sitemap()`, `atom()->asset()`.

The `Atom` class is registered both by FQN and by the `'atom'` container alias — both work.

### Livewire integration: `Traits\AtomComponent`

Consuming Livewire components mix this in to get `WithPagination + WithFileUploads`, plus reserved state buckets:
- `$_breadcrumbs` — populated by an optional `breadcrumbs()` method during `mountAtomComponent()`.
- `$_table` — sort, checkboxes, max_rows, show_trashed (consumed by `<atom:table>`).
- `$_editor.images` — buffered temporary upload URLs from Tiptap editor (see Editor flow below).

Helper methods on the trait (`modal()`, `toast()`, `alert()`, `confirm()`, `action()`) all delegate to `app('atom')`.

### Editor content lifecycle (Tiptap + Livewire uploads)

A subtle, two-phase flow worth understanding before touching the editor:

1. While the user types, image uploads land in Livewire's temporary disk and are echoed back into `_editor.images` as `temporaryUrl()` strings (handled in `updatedAtomComponent`). The editor HTML carries `<img src="/livewire-{hash}/preview-file/...">` URLs (Livewire 4 prefixes internal URLs with an APP_KEY-derived hash).
2. Only when the editor's HTML column is *saved* through Eloquent does `Casts\AsEditorContent::set()` regex out each `/livewire-{hash}/preview-file/` URL, resize via Intervention Image (max width 1000, q=80), persist to `Storage::disk(env('FILESYSTEM_DISK'))` under `<configured folder>/editor/`, rewrite the URL in the HTML, and serialize the result.
3. Stored values are `serialize()`d strings; `get()` unserializes lazily, falling back to the raw value if it isn't serialized.
4. `atom:purge-editor-images` walks `App\Models\*`, finds columns cast as `AsEditorContent`, extracts every `<img src>`, and moves anything in the editor folder that isn't referenced to `editor-purged/` on the local disk before deleting from the configured disk.

If you change the cast, also update the purge command's scanning logic — they are coupled.

### Actions pattern

JS calls `atom.action('Foo.Bar', params)` (= `POST /atom/action/Foo.Bar`), which hits `Atom::action()`, which:
- Converts the dotted name to a namespace via the `str()->namespace()` macro (`Foo.Bar` → `Foo\Bar`).
- Tries `App\Actions\Foo\Bar` first, then `Jiannius\Atom\Actions\Foo\Bar`. App-level overrides take precedence.
- Pulls `method` from params (default `handle`).

`Actions\GetOptions` is the in-package example. It also demonstrates the JSON lookup convention: `getFromJson($name)` reads `resource_path('json/'.$name.'.json')` from the consuming app and merges it (recursively) over `json/{$name}.json` from this package. Results are cached under `_options` in the default cache store. Adding a new option set means adding a JSON file in both places (or just one).

### Component directory (`/atom/docs`)

Local-env-only routes (registered in `routes/web.php`) serve a browsable component directory. `Services\Docs` scans `components/` (excluding `docs/`), parses `@props` blocks for prop tables, and lists icon/logo glyphs. Docs chrome lives in `components/docs/` (layout, example, props); pages and demo partials live in `resources/views/docs/`. Each example partial is BOTH rendered live AND displayed as its own source — when editing a demo, remember the file text is the documentation. Undocumented components automatically get a fallback page, so new components need no docs work to appear.

### Front-end (`resources/js/atom.js`)

Entry point bundled by Vite. It:
- Extends `Array`, `Number`, `String` prototypes (`prototypes/*`) — `window.atom`, `window.dd`, `window.empty` are also set.
- Registers Alpine data factories (`modal`, `editor`, `select`, `tooltip`, `dropdown`, `lightbox`, `telInput`, `emailInput`, `breadcrumbs`, `datePicker`, `timePicker`, `dateRange`, `calendar`, chart variants) and the `$clipboard` magic.
- Loads `@alpinejs/intersect` and `@marcreichel/alpine-autosize` plugins.

The Vite config builds `resources/css/atom.css`, `resources/css/editor.css`, `resources/css/calendar.css`, `resources/js/atom.js` to `dist/`, with the calendar package split into its own chunk. The build output is committed and served by the package itself (not by the consuming app), so **`npm run build` must be run and the resulting `dist/` committed whenever JS/CSS sources change**.

## Conventions worth knowing

- `t('Some string', $count, $params)` is the package's translation shim — accepts a plain string, number, or array; routes through `trans_choice` or `__()` appropriately. Almost every UI string in components passes through it.
- Date handling everywhere goes through `Jiannius\Atom\Services\Carbon` because of the `Date::use()` swap.
- Components prefer `Arr::toCssClasses([...])` over conditional class strings; conditional/utility classes are grouped by variant in plain `match` expressions (see `components/button/index.blade.php` for the canonical pattern).
- Many components dispatch and listen for window-level Livewire events prefixed `atom-` (`atom-modal-show`, `atom-toast-show`, `atom-confirm-show`, `atom-alert-show`). Search by this prefix when tracing UI state changes.
- The `confirm` flow for `<atom:button type="delete">` is auto-wired in the button component: it dispatches `confirmed` on accept and that translates to `$wire.delete()` unless the caller overrides `wire:click` or `x-on:click`.
- **Icons come from [heroicons.com](https://heroicons.com/) or [lucide.dev/icons](https://lucide.dev/icons/)** — never hand-draw a glyph or invent path data. One file per glyph at `components/icon/<name>.blade.php`: `<atom:icon._wrapper :attributes="$attributes">` wrapping a single-line 24×24 SVG. Normalise lucide's `stroke-width` from `2` to the set's `1.5`; keep its `class="lucide lucide-x-icon lucide-x"` (recent additions do). `Services\Docs::glyphs()` globs the directory and the Boost guidelines point at it rather than listing names, so **adding an icon needs no docs work** — it lists itself in `/atom/docs`.

## Development guidelines

Curated from the jiannius package skeleton (`skeleton-package`) — the subset that applies to atom. The skeleton's Pest/Testbench, Models & data, and Pint guidelines are intentionally omitted: atom has no test suite or Pint config (Orchestra Testbench is a dev dep but unused) and ships no models. Front-end changes still require `npm run build` + committing `dist/` (see Front-end above).

### Conventions

- Follow the existing code conventions; when creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods (`isRegisteredForDiscounts`, not `discount()`).
- Stick to the existing directory structure — don't create new base folders without approval.
- Don't change the package's dependencies without approval.
- Only create documentation files if explicitly requested.
- Be concise in explanations — focus on what's important rather than obvious details.

### PHP style

- Always use curly braces for control structures, even single-line bodies.
- Use PHP 8 constructor property promotion (`public function __construct(public GitHub $github) {}`); no empty zero-parameter constructors unless private.
- Explicit return types and type hints on all parameters: `function isAccessible(User $user, ?string $path = null): bool`.
- Prefer PHPDoc over inline comments; every public/private method gets a one-line PHPDoc. Use array-shape definitions in PHPDoc where useful.
- Backed enums mix in `Jiannius\Atom\Traits\Enum`; cases are `FULL_UPPERCASE`. The trait provides `all()`, `option()`, `label()`, `get()`, `is()`/`isNot()`, plus `color()`, `str()`, `snake()`, `slug()`.

### Workflow

- Always squash-merge when exiting a worktree, then remove the worktree.
- Plan mode: no need to use the superpowers skills.
- Session close: when closing or clearing the session, save important gotchas/findings to memory and clear any stale data pieces from it.

## Working Guidelines

Behavioral guidelines to reduce common LLM coding mistakes. For trivial tasks, use judgment.

### 1. Think Before Coding

Don't assume. Don't hide confusion. Surface tradeoffs. State assumptions explicitly; if multiple interpretations exist, present them rather than picking silently. If something is unclear, stop, name what's confusing, and ask.

### 2. Simplicity First

Minimum code that solves the problem. No features beyond what was asked, no abstractions for single-use code, no "configurability" that wasn't requested, no error handling for impossible scenarios.

### 3. Surgical Changes

Touch only what you must. Don't "improve" adjacent code or refactor things that aren't broken. Match existing style. Remove imports/variables your change orphaned; leave pre-existing dead code unless asked.

### 4. Goal-Driven Execution

Transform the task into a verifiable goal ("write a test that reproduces the bug, then make it pass") and loop until verified.

### 5. Caveman

Talk normally in discussion; talk like a caveman (caveman skill) during coding work.
