# Laravel 13 Upgrade — Design

**Date:** 2026-05-12
**Status:** Approved, pending implementation plan
**Owner:** TJ

## Goal

Make `jiannius/atom` install and run cleanly on Laravel 13. Hard-cut from L12 — consumers still on L12 must stay on the prior tagged release. Livewire stays on 3.x; a future Livewire 4 upgrade is a separate effort.

## Current state (as of 2026-05-12)

- No direct `laravel/framework` constraint in `composer.json`; framework enters transitively via `livewire/livewire` and `livewire/volt`.
- `composer.lock` resolves to:
  - `laravel/framework` 12.55.1
  - `livewire/livewire` 3.7.11
  - `livewire/volt` 1.10.4
  - `orchestra/testbench` 10.11.0 (gates floor to L12)
- Livewire 3.x and Volt main both declare `illuminate/*: ^10|^11|^12|^13` — L13 is supported on Livewire 3.
- Testbench 11.x requires `laravel/framework: ^13.7` and `php: ^8.3` — that's the floor we adopt.

## Scope of changes

### 1. `composer.json`

| Action | Package | Constraint |
|--------|---------|------------|
| Add | `php` | `^8.3` |
| Add | `illuminate/support` | `^13.0` (explicit L13 floor — otherwise Livewire's wide constraint lets a consumer resolve back to L12) |
| Remove | `doctrine/dbal` | unused in `src/`; legacy pre-L11 artifact |
| Remove | `laravel/pail` | unused in `src/`; belongs in consuming apps if wanted |
| Bump | `orchestra/testbench` (require-dev) | `^10.0` → `^11.0` |
| Keep | `livewire/livewire` | `^3.0` |
| Keep | `livewire/volt` | `^1.0` |
| Keep | `intervention/image` | `^3.0` |

### 2. `src/Macros/Builder.php` — `tableColumns` macro

L13's new default `serializable_classes: false` rejects deserializing arbitrary classes from cache. The current implementation caches `DB::select("show columns from ...")` rows, which are `stdClass`.

Fix: cast each row to an array before caching. Plain arrays bypass the new default and the existing `data_get($val, 'Field')` / `data_get($val, 'Type')` calls work unchanged on arrays.

### 3. Lockfile

Run `composer update` to regenerate `composer.lock` against L13.

## Out of scope (explicit)

- Livewire 4 / Volt 2.
- Tiptap, Vite, Alpine, or any other JS dep bumps.
- Frontend asset rebuild — `resources/js/` and `resources/css/` are not touched, so `dist/` stays as-is.
- L12 compatibility — this is a hard cut.

## Audit results (already performed)

The following L13 breaking changes were checked and confirmed not present in the codebase:

- `VerifyCsrfToken` → `PreventRequestForgery` rename: no references.
- DB `upsert()` calls: none.
- `pagination::default` view: not referenced.
- `JobAttempted` / `QueueBusy` event property renames: not consumed.
- `Route::domain()` priority change: no domain routes.
- `Container::call` nullable defaults: no direct usage.
- Model `boot()` nested instantiation: package has no Eloquent models.
- Eager-loaded relation serialization: no `toArray()` / Resource usage.

Only the cache-deserialization change in `Macros/Builder.php` requires a code fix.

## Verification

The repo has no test suite (Testbench is in dev deps but unused). Verification will be manual:

1. After `composer update`, scaffold a fresh L13 app with Testbench.
2. `composer require jiannius/atom` against this repo as a path repository.
3. Boot the service provider.
4. Exercise these surfaces:
   - `atom()->modal($name)->show()` / `close()` — sanity-check Livewire event dispatch.
   - `atom()->toast(...)` — translation helper round-trip.
   - `POST /atom/action/GetOptions` — public action endpoint + JSON merge from package vs. consuming app.
   - A test model with `tableColumns()` — exercises the cache fix on the new serialization default.
5. Document what was checked in the PR description.

## Risk

Low. The only code change is one line; everything else is `composer.json` housekeeping. The largest carry-over risk is the `serializable_classes` default biting a consumer with their own cache writes — out of this package's control, but called out for the release notes.

## Release notes (when tagged)

- Drops Laravel 12 support; consumers on L12 should pin to the prior Atom tag.
- Drops `doctrine/dbal` and `laravel/pail` from the package's own dependencies — consumers that relied on either being transitively available must add them to their own app's `composer.json`.
- PHP 8.3+ required.
- Note that L13's default `serializable_classes: false` may require consumers to whitelist their own cached classes in `config/cache.php`.
