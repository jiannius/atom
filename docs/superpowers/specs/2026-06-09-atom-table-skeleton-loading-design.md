# Atom Table Skeleton + Scoped Loaders — Design

**Date:** 2026-06-09
**Status:** Design (approved in brainstorm, pending spec review)

## Problem

`<atom:table>` (v3.4.0) ships only one loading affordance: a dim overlay on pagination/sort (rows persist). The originally-designed loading model also wanted **skeleton rows on first load** and a loading cue on **filter/search**. Those were descoped because the table can't generically know the consumer's filter property to target a `wire:loading` (humblebear uses `$filters`, but that's convention, not guaranteed), and first-load skeleton naively triggered on null data would clobber **static/standalone tables** (which also have null `$paginate`).

## Approach

Three **independent, locally-triggered** loaders — none requires the table to know the consumer's filter property:

1. **First-load skeleton** — opt-in via a `skeleton` prop; renders placeholder rows while data is not yet loaded (lazy tables). Gated behind the flag so static/synchronous tables are untouched.
2. **Filter/search** — a spinner inside the `table.search` control itself (the control owns its loader); rows stay visible.
3. **Pagination/sort** — the existing overlay, unchanged.

This sidesteps the targeting/`$filters`-convention coupling that blocked the original design.

## Components

### 1. First-load skeleton — `components/table/index.blade.php` (+ optional `components/table/skeleton.blade.php`)

- New prop on `<atom:table>`: `skeleton` — `false` (default, off) | `true` (default row count) | integer (explicit row count, e.g. `:skeleton="10"`).
- Render skeleton **only when** `skeleton` is truthy **and** `$paginate === null` (not yet loaded). A lazy consumer passes `:paginate="$this->rows"` where `$this->rows` is null on first render and is populated via `wire:init`/deferred load; once loaded, `$paginate` is a paginator → real rows render. A *loaded-but-empty* table has a paginator with `total() === 0` (not null) → the existing empty state shows, **not** the skeleton. So the signal is purely "paginator absent + opted in".
- Skeleton markup: N `<tr>` rows of `<atom:placeholder-bar>` cells inside the same bordered table chrome (so layout doesn't jump). Default N = 5 when `skeleton` is `true`; N = the integer when given.
- **Safety (the static-table guard):** the entire skeleton branch is reachable only when `skeleton` is truthy. A standalone/static `<atom:table>` (rows slot, no `skeleton` prop) takes the existing code path with **zero behavioral change** — no skeleton, no crash. This is the explicit resolution of the "null `$paginate` is ambiguous" problem: opt-in disambiguates.
- The skeleton is naturally **first-load-only**: after the initial load `$paginate` is non-null, so later filter/sort re-queries (old data still present during the request) never re-trigger it.

### 2. Filter/search scoped spinner — `components/table/search.blade.php`

- `table.search` currently renders `<atom:input icon="search" … x-on:keyup.enter.prevent="$wire.$refresh()">`.
- Add a `wire:loading` spinner that replaces/overlays the search icon **while the search request is in flight**, **scoped** so it does NOT spin on unrelated requests (pagination/sort).
- **Scoping (SPIKE — must verify):** the search triggers via `$wire.$refresh()`. Verify `wire:loading wire:target="$refresh"` scopes to that refresh in the installed Livewire 4. If it works → scoped spinner. **Fallback (if `$refresh` is not a valid `wire:target`):** scope to the search's bound model instead (the consumer's `wire:model` key is on the element, so a self-targeted `wire:loading` works), or as a last resort a generic `wire:loading` on the input (spins on any table activity) — documented. The spike picks the working option; the contract is "spinner shows during search, rows stay visible."
- Rows are untouched (no skeleton swap) — this is the "keep rows, show a control cue" behavior.

### 3. Pagination/sort overlay — unchanged

The existing `wire:loading.flex wire:target="gotoPage,nextPage,previousPage,_table.sort.column,_table.sort.direction"` overlay stays as-is.

## Non-Goals

- No `loading`/filter-target prop, no `$filters` convention dependency (the original blocker — avoided).
- First-load skeleton does nothing for synchronous (non-lazy) tables — by design (they paint instantly). Not a gap.
- No skeleton on filter/search (rows persist + control spinner instead).

## Testing

- **Render (Pest, Blade::render via `renderBlade`):**
  - `<atom:table skeleton :empty="false">` with **no rows slot / null data** → output contains placeholder-bar skeleton rows.
  - `<atom:table :empty="false">` **without** `skeleton`, with a static `<x-slot:rows>` → renders the slot rows, **no** skeleton markup (static-table safety regression test).
  - `:skeleton="3"` → 3 skeleton rows.
  - `table.search` → emits the scoped loading spinner markup (asserts the chosen `wire:target`/`wire:loading` hook present).
- **Spike:** in the worktree, confirm whether `wire:loading wire:target="$refresh"` scopes correctly (render a fixture + inspect, or test against the Livewire fixture). Record the chosen scoping in the search component + a comment.
- **E2E (optional, if cheap on the existing Playwright harness):** lazy fixture shows skeleton → rows; searching spins the control while `[data-atom-table-rows]` stays present.

## Release

Blade + inline Alpine/`wire:loading` only → no `dist/` rebuild. Additive (`skeleton` prop default off; search spinner is internal). Minor bump **v3.5.0** (new `skeleton` prop = feature). Push + tag.
