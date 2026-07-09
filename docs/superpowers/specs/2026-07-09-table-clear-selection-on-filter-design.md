# Table: clear checkbox selection when the result set narrows

**Date:** 2026-07-09
**Status:** Approved — ready for implementation plan

## Problem

`<atom:table>` row-checkbox selection (`$_table['checkboxes']`, plus the
cross-page `select_all` intent flag) is Livewire server state. Pagination never
resets it — which is correct, and lets a selection span pages. But **filtering,
searching, and toggling the trashed view** also leave it untouched, so a checked
row can disappear from the visible result set while its id stays in
`checkboxes`. Consequences:

1. The bulk-action bar keeps counting the now-hidden row → "N selected"
   overstates what the user can see.
2. A bulk action routed through the blessed path
   `tableSelection()` (= `tableQuery()->whereKey($checkboxes)`) silently drops
   the hidden id (it fails `tableQuery()`'s filter WHERE), so the action affects
   fewer rows than the count implies — confusing, though not destructive.
3. A consumer that bulk-acts on the **raw** `getTableCheckboxes()` ids (bypassing
   `tableSelection()`) *would* act on the invisible row — a data footgun.
4. In `select_all` mode, changing the filter silently re-scopes "all matching"
   to the new filtered set.

**Decision:** clearing the selection whenever the result set narrows is the
expected UX and removes the footgun. On by default, no opt-out prop.

## Scope

Clear the selection on **all three** result-set-narrowing actions:

- any atom filter-control change (`select.filter`, `date-picker.range`),
  including chip removal;
- submitting a search (`<atom:table.search>`);
- toggling the trashed view (`<atom:table.trashed>`).

"Clear" means `resetTableCheckboxes()` — empties `checkboxes` **and** resets
`select_all` to `false` (both modes).

Out of scope: pagination and sorting never clear the selection (a sorted or
paged view still contains the same rows). Ad-hoc consumer filter inputs that are
not atom filter controls are not auto-covered — see Escape hatch.

## Architecture

The trigger sources split by who owns the state, so the mechanism splits too.

### Trigger 1 — trashed toggle (atom-owned server state) → server-side

`_table.show_trashed` is atom's own property, toggled by
`components/table/trashed.blade.php` via `wire:click="$toggle('_table.show_trashed')"`.

Handle it in the trait's existing `updatedAtomComponent($property, $value)` hook
(`src/Traits/AtomComponent.php`) — Livewire calls this on every property update:

```php
public function updatedAtomComponent($property, $value)
{
    // ... existing _editor.images handling ...

    // A trashed-view toggle changes which rows are listed; a lingering
    // selection would point at rows no longer shown. Clear it.
    if ($property === '_table.show_trashed') {
        $this->resetTableCheckboxes();
    }
}
```

- Piggybacks the same request as the `$toggle`, so no extra round-trip.
- No recursion: `resetTableCheckboxes()` sets `_table.checkboxes` /
  `_table.select_all` by direct PHP assignment, which does not re-enter the
  Livewire `updated` pipeline (that only iterates the request's *incoming*
  updates) — same proven pattern as the existing `_editor.images` handler.
- **Pest-testable** via `Livewire::test` (real assertion, not just markup).

### Trigger 2 — filters + search (consumer / front-end state) → front-end event

Filter values bind `wire:model` to **consumer** properties; atom does not know
their names, so it cannot detect the change server-side. Use the front-end
signal atom already centralizes around `table-filter:*` events.

Introduce one new window event: **`table-filter:changed`**, dispatched only when
a filter's value actually changes (not on the init/hydration emit that populates
chips), and once on search submit.

**`components/select/filter.blade.php`** — the `x-init` block already runs
`$nextTick(emit)` once (chip hydration) and `$watch('selectValue', () => $nextTick(emit))`
on change. Add the new event to the **watch path only**:

```js
$nextTick(emit);
$watch('selectValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
```

**`components/date-picker/range.blade.php`** — identical `x-init` shape
(`$nextTick(emit)` on init, `$watch('dateRangeValue', () => $nextTick(emit))` on
change). Add the event to the watch path only:

```js
$nextTick(emit);
$watch('dateRangeValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
```

**`components/table/search.blade.php`** — the input already does
`x-on:keyup.enter.prevent="$wire.$refresh()"`; add the dispatch:

```html
x-on:keyup.enter.prevent="$dispatch('table-filter:changed'); $wire.$refresh()"
```

**`components/table/index.blade.php`** — the root `<div data-atom-table>` has no
Alpine scope today. Add `x-data="{}"` and the listener:

```html
<div x-data="{}"
     x-on:table-filter:changed.window="if ($wire._table.checkboxes.length || $wire._table.select_all) $wire.resetTableCheckboxes()"
     class="group/table space-y-4" data-atom-table>
```

- Guarded on an existing selection → no round-trip on filter changes when
  nothing is selected.
- `$wire` resolves inside the added `x-data` scope (Livewire injects it into all
  Alpine scopes within the component); the nested `<template x-if="$wire...">`
  blocks are unaffected.

Chip removal needs no extra wiring: `table-filter:do-clear` / `clearAll()` drives
each control's `clear()` → `selectValue` changes → the `$watch` fires →
`table-filter:changed` dispatched.

## Data flow

```
filter control value change ─┐
search submit ───────────────┤─▶ dispatch table-filter:changed (window)
                             │        │
chip remove / clear-all ─────┘        ▼
                          <atom:table> x-on:table-filter:changed.window
                                      │  (guard: selection non-empty)
                                      ▼
                             $wire.resetTableCheckboxes()  ──▶ checkboxes=[], select_all=false

trashed toggle ─▶ $toggle('_table.show_trashed') ─▶ updatedAtomComponent()
                                      │ ($property === '_table.show_trashed')
                                      ▼
                             $this->resetTableCheckboxes()
```

## Escape hatch

A consumer whose filter is an ad-hoc control (not `select.filter` /
`date-picker.range` / `table.search`) can opt that control into the same
behavior by dispatching the event itself:

```blade
<atom:input wire:model.live="myFilter" x-on:input="$dispatch('table-filter:changed')" />
```

Documented in the Boost table section.

## Error handling / edge cases

- **Init / `wire:navigate` hydration:** `table-filter:changed` fires only from the
  `$watch` change path, never the init `$nextTick(emit)`, so a page load or SPA
  navigate that restores a persisted selection does not wipe it.
- **Nothing selected:** listener guard skips the `$wire` call — filtering stays a
  single request.
- **`select_all` active + filter change:** `resetTableCheckboxes()` clears the
  flag too, so "all matching" does not silently re-scope.
- **Sort / paginate:** not wired → selection persists (intended).

## Testing

- **Pest (real):** `Livewire::test` a fixture with `AtomComponent` →
  set `checkboxes` + `select_all`, toggle `_table.show_trashed`, assert both
  cleared. Assert a non-`_table.show_trashed` update does **not** clear.
- **Pest (markup):** assert `components/table/index.blade.php` renders the
  `x-on:table-filter:changed.window` listener; assert `select.filter`,
  `date-picker.range`, and `table.search` markup dispatch `table-filter:changed`.
- **Verify-on-humblebear (runtime, unavailable in atom's rig — `/atom/docs` is
  not Livewire-backed):** change a filter with rows checked → selection clears;
  search → clears; chip remove → clears; nothing-selected filter change → no
  spurious request; page/sort → selection persists.

## Build / consumer impact

- No JS/CSS **source** change (all inline Alpine in blade), so `dist/` is
  **unchanged** — nothing to rebuild or commit (Vite doesn't build blade). A
  build is run only to confirm `dist/` stays clean.
- No new Tailwind utility classes → consumers do **not** need to rebuild their CSS.
- Behavior change: existing consumers who relied on selection surviving a filter
  change lose that. Deemed a bugfix, not a regression (the surviving selection
  was the footgun). Note it in the release/upgrade summary.

## Files touched

- `src/Traits/AtomComponent.php` — trashed-toggle clear in `updatedAtomComponent`.
- `components/table/index.blade.php` — root `x-data` + listener.
- `components/select/filter.blade.php` — dispatch on change.
- `components/date-picker/range.blade.php` — dispatch on change.
- `components/table/search.blade.php` — dispatch on enter.
- Boost table docs — behavior note + escape-hatch snippet.
- `dist/` — unchanged (no JS/CSS source touched).
- Tests — `TableSelectionTest` (or a new `TableClearSelectionTest`) for the
  trait clear + markup assertions.
