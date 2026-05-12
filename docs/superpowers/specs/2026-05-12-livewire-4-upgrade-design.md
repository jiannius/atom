# Livewire 4 Upgrade — Design

**Date:** 2026-05-12
**Status:** Approved, pending implementation plan
**Owner:** TJ

## Goal

Bump `jiannius/atom` from Livewire 3 to Livewire 4. Hard cut — Atom v3.0.0 requires Livewire 4 and no longer ships or recommends Volt (Livewire 4 folds single-file component syntax into core). Laravel 13 remains the target framework (L4 supports it).

This is **Phase 1 (PHP / composer / docs)**. JavaScript verification is a deferred Phase 2 — see "Deferred follow-up" below.

## Current state (as of 2026-05-12, post v2.0.0)

- `livewire/livewire` 3.8.0, `livewire/volt` 1.10.5 in `composer.lock`.
- `resources/views/livewire/` is empty — the `Volt::mount(...)` call in the service provider has never had content to mount.
- Confirmed L4 stable releases on Packagist (`v4.3.0` released 2026-05-01). L4 declares `php: ^8.1`, `illuminate/*: ^10|^11|^12|^13` — fully compatible with our L13 + PHP 8.3 floor.

## Audit results

### Surfaces touched by L4 breaking changes

- **Editor URL regex** (`src/Casts/AsEditorContent.php:40`) — matches `/livewire/preview-file/...`. L4 changes the prefix to `/livewire-{hash}/preview-file/...` where the hash is APP_KEY-derived. The regex stops matching new uploads and must be updated.
- **Volt service-provider mount** (`src/AtomServiceProvider.php:12, 83`) — `Livewire\Volt\Volt` import and `Volt::mount(...)` call. The `livewire/volt` package is removed in L4; both lines must go.
- **Boost guideline doc** (`resources/boost/guidelines/core.blade.php`) — five sections present Volt as the default authoring style; needs rewriting to L4 single-file component syntax.
- **CLAUDE.md** — two specific lines reference the Volt mount and the old `/livewire/preview-file/` URL form.

### Verified unchanged in L4 (no work needed)

- `livewire.temporary_file_upload.directory` config key — same path in L4 default config.
- `Livewire\WithFileUploads` and `Livewire\WithPagination` — still the documented public-API import paths in L4 (verified against L4 main branch). Stay as-is.
- All `wire:*` directives this package uses (`click`, `navigate`, `navigated`, `remove`, `loading.class`, `loading.flex`, `target`, `ignore[.self]`, `model[.live]`, `cancel`, `close`, `submit`) — none renamed in L4. Only `wire:scroll` was renamed, and it's not used here.

### Assumed stable, will be smoke-tested

- `app('livewire')->current()` and `->dispatch(...)` patterns in `src/Atom.php`.
- `app('livewire')->isLivewireRequest()` / `->originalPath()` in `components/navlist/item.blade.php`.
- `Livewire\LivewireManager` class in `src/Macros/Request.php`.
- These have been stable across Livewire majors historically; we'll catch regressions in the Testbench smoke.

## Scope of changes

### 1. `composer.json`

| Action | Package | Constraint |
|--------|---------|------------|
| Drop | `livewire/volt` | (was `^1.0`) |
| Bump | `livewire/livewire` | `^3.0` → `^4.0` |
| Keep | `php` | `^8.3` |
| Keep | `illuminate/support` | `^13.0` |
| Keep | `intervention/image` | `^3.0` |
| Keep (dev) | `orchestra/testbench` | `^11.0` |
| Keep | `config.platform.php` | `8.3.0` |

### 2. `src/AtomServiceProvider.php`

Remove these two lines:
- The `use Livewire\Volt\Volt;` import at the top of the file.
- The `Volt::mount(__DIR__.'/../resources/views/livewire');` call inside `boot()`.

The mounted directory has been empty for the package's whole life — this is pure dead wiring.

### 3. `src/Casts/AsEditorContent.php`

In the regex at line ~40, replace the literal `\/livewire\/preview-file\/` with `\/livewire-[A-Za-z0-9_-]+\/preview-file\/` so it matches L4's hash-prefixed URL form.

The cast runs on save against editor HTML that contains *temporary* upload URLs. Under L4, only the new URL form is ever emitted — strict-only is correct (no need to also match the old form).

### 4. `resources/boost/guidelines/core.blade.php`

Five targeted edits:

| Line | Current | New |
|------|---------|-----|
| ~23 | "Volt is the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every Volt class component." | "Livewire 4 single-file components are the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every single-file Livewire component class." |
| ~33 | code-snippet title `"Volt component with Atom"` | `"Single-file Livewire component with Atom"` |
| ~35 | `use Livewire\Volt\Component;` | `use Livewire\Component;` |
| ~73 | "When `<atom:modal>` is the **root** of a Volt component" | "When `<atom:modal>` is the **root** of a single-file Livewire component" |
| ~241 | "**Volt method order**" | "**Component method order**" |

No other prose in this file references Volt or L3-specific behavior.

### 5. `CLAUDE.md`

Two targeted edits:

- Around line 36: remove the bullet "Mounts every Volt component in `resources/views/livewire/` automatically." (no longer true after dropping the `Volt::mount(...)` call).
- Around lines 70–71: update the editor-URL description from `/livewire/preview-file/...` to `/livewire-{hash}/preview-file/...` and add a one-clause note that the hash is APP_KEY-derived.

### 6. `composer.lock`

Regenerate via `composer update`. Expected outcome: `livewire/livewire` resolves to `^4.x`, `livewire/volt` drops entirely (not even transitive), everything else stays put.

## Out of scope (Phase 1)

- **JavaScript verification.** See "Deferred follow-up" below.
- **L4 API call audit beyond manual smoke.** `app('livewire')->current()`, `dispatch()`, `isLivewireRequest()`, `originalPath()`, `Livewire\LivewireManager` are assumed stable; smoke test will catch regressions.
- **New L4 features.** No demo single-file components, no opportunistic adoption of L4-only syntax. Just "make the existing code work on L4".
- **JS / Vite / Tiptap / Alpine bumps.** No frontend asset rebuild. `dist/` is untouched.
- **Laravel 14 support.** Out of scope; would be a future v4.0.0.

## Verification (Phase 1)

Same shape as the L13 upgrade verification:

1. Scaffold a fresh Testbench 11 host with `laravel/framework: ^13.0`.
2. Add Atom as a path repository (`composer require jiannius/atom:@dev` against the local branch).
3. Confirm `package:discover` picks up Atom.
4. Confirm `app("atom") instanceof \Jiannius\Atom\Atom` and the two `atom/*` routes register.
5. Hit `POST /atom/action/GetOptions` (via tinker bypass to skip the bare-scaffold CSRF wall, as in the L13 verification) and confirm the countries JSON comes back.
6. **New editor-regex smoke:** Build a synthetic HTML string with `<img src="/livewire-DEADBEEF/preview-file/synthetic.png">` and run the same regex from the cast against it (without actually fetching/storing the file). Confirm the regex captures the URL. This catches the regex update specifically — the only behavior-touching code change.

If anything throws or fails to match, capture the failure and stop. Otherwise document the verification steps and outputs in the PR description.

## Deferred follow-up (Phase 2 — JS verification)

After tagging v3.0.0, the package should be exercised end-to-end inside a real consuming application (browser, Livewire requests, all `$wire.*` and browser-event paths). Specific surfaces to re-verify in Phase 2:

- `$wire.entangle(...)` in `components/input/color.blade.php` — entangle semantics in L4.
- `$wire.delete()` confirmation flow in `components/button/index.blade.php`.
- `$wire._table.checkboxes.*` and table sorting in `components/table/*`.
- `$wire.cancelUpload(...)` in `components/uploader/index.blade.php` — file upload cancellation.
- `livewire-upload-start` / `livewire-upload-finish` / `livewire-upload-cancel` / `livewire-upload-error` / `livewire-upload-progress` browser events on the uploader.
- `livewire:navigate` / `livewire:navigated` events in `components/breadcrumbs.blade.php` and `components/html.blade.php`.
- `wire:model.live` and `wire:model` behaviors — L4 changed child event handling; double-check inputs.
- `$__livewire->getId()` reference in `components/modal/index.blade.php:92`.

Any breakage found in Phase 2 will be patched and released as a `v3.0.x` patch.

## Risk

**Low to medium for Phase 1.** The PHP/regex/docs changes are surgical. The largest behavior change is the regex update, which is end-to-end testable. The Volt drop is a no-op functionally (empty mount).

**Medium for Phase 2 (JS).** Real risk lives in the `$wire.*` JS API and browser events; can only be exercised in a real consuming app.

## Release notes (when tagged v3.0.0)

- Requires Livewire `^4.0`. Consumers still on Livewire 3 must stay on Atom `^2.0`.
- Drops `livewire/volt` from the package's dependencies. Consumers on Volt must follow Livewire's official upgrade guide (migrate `Livewire\Volt\Component` to `Livewire\Component`, remove `livewire/volt` from their own composer) before bumping Atom to v3.0.0.
- Editor image upload regex updated for L4's new `/livewire-{hash}/preview-file/` URL prefix. No data migration needed — only affects in-flight uploads being persisted by `AsEditorContent`.
- No Laravel version change. Still requires Laravel 13 / PHP 8.3+.
- **Phase 2 caveat for consumers:** JS verification in the browser is deferred. If you hit a `$wire.*`, browser-event, or `wire:model` regression after upgrading, please report it — patches will ship as `v3.0.x`.
