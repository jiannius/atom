# Atom Table Listing Scaffolding — Design

**Date:** 2026-06-09
**Status:** Design (approved in brainstorm, pending spec review)

## Problem

`<atom:table>` standardizes table *rendering* (columns/rows/sort/checkboxes/pagination via the `$_table` state + `toTable()`/`filter()` Builder macros — all solid). But the surrounding **listing-page chrome** is hand-rolled on every admin page, causing the same UI drift the form-patterns work (v3.3.0) addressed. Evidence from humblebear (16 listing pages): **453** ad-hoc `filter` usages, search markup rewritten on every page, **no** filter-UI/active-filter/trashed/row-action/loading components.

Key scoping finding: the filter **controls** already exist — `<atom:select variant="filter">` (`components/select/filter.blade.php`) and `<atom:date-picker variant="range">`. So this feature does NOT rebuild controls. It adds the **scaffolding** around them.

## Approach

Five additive, opt-in pieces around the existing `<atom:table>`. Consistent with the form-patterns philosophy: bake the repeated listing-page structure into components so host-apps stop hand-rolling it. All Blade + inline Alpine → no `resources/js|css` change → no `dist/` rebuild.

## Components

### 1. `<atom:table.search>` (new — `components/table/search.blade.php`)

Standardized search input. Wraps `<atom:input icon="search">`, **Enter-to-search** (`x-on:keyup.enter.prevent="$wire.$refresh()"`), clearable ×. Removes the per-page boilerplate.

```blade
<atom:table.search wire:model="filters.search" placeholder="Search invoices" />
```
- Forwards `wire:model` to the inner input; default `placeholder="Search"`.
- On clear (×): empties the bound value and `$wire.$refresh()`.

### 2. `<atom:table.filters>` (new — `components/table/filters.blade.php`) — core piece

A filter bar that wraps filter controls and renders active-filter chips + Clear all.

- **Default slot** = always-visible controls (`<atom:select variant="filter">`, `<atom:date-picker variant="range">`, custom `<x-select.*>` wrappers, and `<atom:table.search>` / `<atom:table.trashed>`).
- **`<x-slot:more>`** = overflow controls. Default presentation: a **"Filters (n) ▾" popover** (`<atom:dropdown>` + `<atom:menu popover>`). With `more="card"`: an expandable **card row** beneath the bar (toggle reveals an inline panel).
- **Active chips + Clear all** render in a row below the controls: `(Status: Paid ✕) (Contact: Acme ✕) … Clear all`. ✕ clears that one filter; Clear all resets every registered filter. Chips appear for active filters whether inline or in the overflow group.
- Layout: `grow flex flex-wrap items-center gap-3` (matches the hand-rolled humblebear pattern).

**Auto-register contract (the high-risk piece — validate first).**
The bar is an Alpine component holding a reactive registry of active filters. Each filter control inside registers itself and keeps its entry current:

- The bar root carries `data-atom-table-filters` and an Alpine scope exposing a reactive `chips` list, plus handlers for `table-filter:set` / `table-filter:clear` events.
- `<atom:select variant="filter">` and `<atom:date-picker variant="range">` are enhanced (blade-level Alpine only — they already expose `selectValue`, `selectedOptions[].label`, `isEmpty`, `clear()`): on init and whenever the bound value changes, if inside a `[data-atom-table-filters]`, `$dispatch('table-filter:set', { key, label, display })` (or `table-filter:clear` when emptied). `key` derives from the `wire:model` target; `label` from the control's `label` prop; `display` from `selectedOptions` text (joined/“n selected” for multiple).
- A chip's ✕ and Clear all `$dispatch('table-filter:do-clear', { key })` (or all keys); each control listens and runs its existing `clear()`.
- **Graceful degradation:** a control that does not register simply produces no chip. An optional `chip-label` / no-chip escape hatch can force/suppress a chip for bespoke controls. The bar must work even if zero controls register (chips row just stays empty).

This contract is blade/Alpine-only (no `atom.js` edit). It is the riskiest part; the implementation plan must validate it on a real `select.filter` before building chips/overflow on top.

### 3. `<atom:table.trashed>` (new — `components/table/trashed.blade.php`)

Toggle bound to `$_table.show_trashed` (query side already handled by `toTable()` → `onlyTrashed()`). Renders a small toggle/button ("Show archived"). Lives inside `table.filters` (registers a chip like any filter when on). Toggling `$wire.$refresh()`s.

### 4. `<atom:table.actions>` (new — `components/table/actions.blade.php`)

Trailing row-actions cell. Renders the last `<td>` (right-aligned, narrow) containing a ⋯ icon button that opens `<atom:menu popover>`; slot = `<atom:menu.item>`s.

```blade
<atom:table.actions>
  <atom:menu.item wire:click="edit({{ js($row->id) }})">Edit</atom:menu.item>
  <atom:menu.item wire:click="delete({{ js($row->id) }})" class="text-red-600">Delete</atom:menu.item>
</atom:table.actions>
```
- Stops row-click propagation (`x-on:click.stop`) so it works inside a clickable `<atom:table.row href>`.
- Delete items use the existing confirm pattern (`<atom:confirm.trigger>` / `type="delete"` convention) — documented, not auto-magic.

### 5. Loading states (modify `components/table/index.blade.php`)

- **Skeleton rows** — shown (a) on first load when rows are not yet available (lazy/deferred/`#[Computed]` data) and (b) during filter/search re-queries. Built from the existing `<atom:skeleton>` / `<atom:placeholder-bar>`; render ~`min(maxRows, 8)` placeholder rows matching column count.
- **Dim overlay (rows persist)** — during pagination and sort. `wire:loading.flex wire:target="gotoPage,nextPage,previousPage,_table.sort.column,_table.sort.direction"` over the table body with a centered spinner; existing rows stay put.
- Rule encoded: skeleton is the default loading affordance; pagination + sort explicitly opt into the overlay (their `wire:target`s are known to the table). Sort is grouped with pagination (navigating/reordering the same set) per the approved design.

## Guidance + Demos

- **`resources/boost/guidelines/core.blade.php`** — add/expand the table section with the listing-page recipe: `<atom:table.search>` + `<atom:table.filters>` (with `<x-slot:more>` + `more="card"`) + `<atom:table.trashed>` + `<atom:table.actions>` + the loading behavior. Note Enter-to-search, the auto-chip behavior, and that delete actions use the confirm pattern. Point at the new demos.
- **`/atom/docs` demos** under `resources/views/docs/demos/table/` — one partial per piece (search, filters with chips + overflow, trashed, actions) registered on `resources/views/docs/demos/table.blade.php`. (Demos are static/non-Livewire — chips/overflow shown with seeded values; loading demonstrated descriptively.)

## Non-Goals (deferred — lower-tier review items)

Cross-page "select all N matching"; column show/hide & density; responsive stacked-card mode; the a11y overhaul (div→button sort headers, real checkbox inputs, `aria-sort`); sort-arrow semantics and per-column width control. These are a separate follow-up, not this feature.

## Release

Feature code is Blade + inline Alpine only → **no `dist/` rebuild**. New utility classes (shimmer, chip styles) are picked up by consumers' normal `npm run build`. The test harness adds **dev-only** deps (`pestphp/pest`, `pest-plugin-laravel`) + `tests/`, `testbench.yaml`, `phpunit.xml`, Playwright config — none of which ship to consumers (dev deps + non-autoloaded paths). Additive/back-compat (existing `<atom:table>` usage unchanged). Minor bump **v3.4.0**; push + tag per release flow.

## Testing

atom currently has **no test suite** (Testbench is a dev dep but unused; no Pest, no `tests/`). This feature introduces one. Three tiers, built on a foundational harness done first.

### Phase 0 — Test harness (foundational; do first, with a de-risking spike)

One-time setup mirroring the org pattern in `skeleton-package`:
- **Dev deps:** add `pestphp/pest ^4`, `pestphp/pest-plugin-laravel ^4` (keep `orchestra/testbench ^11`).
- **Config:** `testbench.yaml` (register `Jiannius\Atom\AtomServiceProvider`; set `env: APP_ENV=local` so the `/atom/docs` routes load), `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php` (extends Testbench `TestCase`, in-memory sqlite). `composer test` + `composer test:e2e` scripts.
- **Livewire fixture:** `tests/Fixtures/` — a Livewire component using `AtomComponent`, a test model + migration + factory (sqlite), to exercise `toTable()`/`filter()`/sort/checkboxes against real data. Also a fixture **route** (registered in the Testbench app) rendering a real Livewire-backed table page — the E2E target for server-round-trip behavior (sort/paginate/filter/loading).
- **Playwright:** config with `baseURL` pointing at a `vendor/bin/testbench serve` instance; a setup step that boots it. E2E runs against the self-contained `/atom/docs` pages (pure-Alpine behavior) and the fixture route (Livewire behavior).
- **SPIKE (resolve before building on it):** confirm `testbench serve` renders `/atom/docs` with Alpine + atom.js working. Known risk: `@vite($vite)` in `components/html.blade.php:122` may fatal under Testbench (no Vite manifest) — the spike must make the page render (e.g. provide a Testbench Vite shim / ensure `$vite` is empty / publish a hot-file stub). atom.css/atom.js themselves load via the package's own `/atom/{file}` route from the committed `dist/`, independent of Vite.
- **Smoke tests proving each tier works** before feature tests: (A) `Livewire::test(fixture)` asserts `$_table` defaults; (B) render `<atom:button>` and assert markup; (C) Playwright loads `/atom/docs/button`, clicks a dropdown, asserts it opens.

### Tier A — PHP / Livewire logic (highest ROI; currently untested)

- **`toTable()` macro:** sort asc/desc/none, `raw:` prefix → `orderByRaw`, default `latest('id')` when unsorted, `onlyTrashed()` when `show_trashed`, `paginate(maxRows)`, `filter($filters)` integration.
- **`filter()` macro:** `search` named scope, other named scopes, date range `"a to b"`, enum-cast `whereIn`/`whereNotIn`, json `whereJsonContains`, `like` (with `%*`/`*%`), `key:operator` syntax.
- **`$_table` state:** sort 3-state toggle, `resetTableCheckboxes()`, `isTableShowTrashed()`, `max_rows`.

### Tier B — Blade render assertions (new components)

- `table.search`: input + forwarded `wire:model` + Enter handler + clear.
- `table.filters`: chips container present; `more="card"` branch vs popover branch; slot rendering; `data-atom-table-filters` hook present.
- `table.trashed`: bound to `_table.show_trashed`.
- `table.actions`: trailing `<td>` + ⋯ `<atom:menu>`; `x-on:click.stop`.
- Loading: skeleton block markup; overlay `wire:loading wire:target="gotoPage,nextPage,previousPage,_table.sort.column,..."` present.

### Tier C — Playwright E2E (Alpine behavior; the only true test of the risky chips)

Against `testbench serve`:
- **`/atom/docs` (pure Alpine):** set a filter in the demo → chip appears; click chip ✕ → filter clears; Clear all; `more="card"` toggles the card panel; overflow popover opens; row ⋯ menu opens.
- **Fixture route (Livewire round-trips):** filter/search → skeleton rows; paginate → overlay with rows persisting; sort → overlay + reorder.

The **auto-register chip** contract must be validated by a Tier-C test early — it's the feature's main risk.

## Verification

Automated: `composer test` (Tier A+B) and the Playwright suite (Tier C) all green. Manual spot-check via `testbench serve` / the humblebear rig as a sanity pass before release.
