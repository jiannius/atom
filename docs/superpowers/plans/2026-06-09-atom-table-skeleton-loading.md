# Atom Table Skeleton + Scoped Loaders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an opt-in first-load skeleton to `<atom:table>` and a scoped loading spinner to `<atom:table.search>`, without any filter-property targeting and without affecting static/synchronous tables.

**Architecture:** Three independent local loaders: (1) `skeleton` prop renders placeholder rows when a paginator hasn't loaded yet (lazy tables), gated so static tables are untouched; (2) `table.search` shows a `wire:loading` spinner scoped to its own search request (rows stay visible); (3) the existing pagination/sort overlay is unchanged.

**Tech Stack:** Blade anonymous components, Livewire 4 `wire:loading`, Tailwind v4. No JS/CSS source → no `dist/` rebuild.

**Spec:** `docs/superpowers/specs/2026-06-09-atom-table-skeleton-loading-design.md`

## Execution notes
- Work in this worktree: `/Users/tj/Projects/jiannius/atom/.claude/worktrees/table-skeleton`. Run `composer install` first (gets the Pest harness).
- `renderBlade(string, array)` helper exists in `tests/Pest.php` (unwinds slot output buffers) — use it for Blade render assertions.

---

## Task 1: SPIKE — determine the search spinner's `wire:target`

**Goal:** Decide whether `wire:loading wire:target="$refresh"` scopes to `$wire.$refresh()` in the installed Livewire 4. This decides Task 3's exact attribute.

**Files:** none (investigation only).

- [ ] **Step 1: Inspect Livewire's loading-target resolution for `$refresh`**

Run:
```bash
cd /Users/tj/Projects/jiannius/atom/.claude/worktrees/table-skeleton
grep -rn "refresh" vendor/livewire/livewire/js/features/supportLoadingStates 2>/dev/null | head
grep -rn "\$refresh\|'refresh'\|\"refresh\"\|callTarget\|targets" vendor/livewire/livewire/js/features/supportLoadingStates/index.js 2>/dev/null | head -30
```
Look for whether the loading-states feature treats `$refresh` / `refresh` as a callable target (Livewire commits a `$refresh` as a method call named `__lazyLoad`? or `$refresh`). The question to answer: when `$wire.$refresh()` fires, does a `wire:loading wire:target="$refresh"` element activate?

- [ ] **Step 2: Confirm empirically via the docs server (cheap)**

Add a throwaway probe view is overkill — instead reason from Step 1 + the Livewire docs convention. Livewire 3/4 documents `wire:target="$refresh"` as valid for `$refresh` calls. If Step 1 confirms a `refresh`/`$refresh` branch in the target matcher, **decision = use `wire:target="$refresh"`**.

- [ ] **Step 3: Record the decision**

Write the chosen target into a note for Task 3:
- **If `$refresh` is targetable (expected):** Task 3 uses `wire:loading wire:target="$refresh"`.
- **Fallback (if NOT targetable):** Task 3 scopes to the bound model — the consumer passes `wire:model="filters.search"`, so the spinner element can use `wire:loading wire:target="filters.search"`; but since the key is consumer-defined, the robust fallback is a **generic** `wire:loading` on the spinner (spins on any table request) with a code comment explaining why. Pick generic over guessing the model key.

No commit (investigation). Proceed to Task 2.

---

## Task 2: `skeleton` prop + first-load skeleton rows

**Files:**
- Modify: `components/table/index.blade.php`
- Test: `tests/Feature/TableSkeletonTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/TableSkeletonTest.php`:
```php
<?php

it('renders skeleton rows when skeleton is set and no paginator is loaded', function () {
    $html = renderBlade('<atom:table skeleton></atom:table>');

    expect($html)->toContain('data-atom-table-skeleton');
});

it('renders the given number of skeleton rows', function () {
    $html = renderBlade('<atom:table :skeleton="3"></atom:table>');

    expect(substr_count($html, 'data-atom-table-skeleton-row'))->toBe(3);
});

it('does NOT render skeleton for a static table without the flag', function () {
    $html = renderBlade(<<<'BLADE'
        <atom:table :empty="false">
            <x-slot:rows>
                <atom:table.row><atom:table.cell>Jane</atom:table.cell></atom:table.row>
            </x-slot:rows>
        </atom:table>
    BLADE);

    expect($html)->not->toContain('data-atom-table-skeleton')
        ->and($html)->toContain('Jane');
});
```

- [ ] **Step 2: Run — verify they fail**

Run: `vendor/bin/pest tests/Feature/TableSkeletonTest.php`
Expected: FAIL (no `data-atom-table-skeleton` markup yet; the static test may already pass).

- [ ] **Step 3: Add the `skeleton` prop + short-circuit in `components/table/index.blade.php`**

Change the `@props` block (lines 1-5) to add `skeleton`:
```blade
@props([
    'empty' => null,
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'skeleton' => false,
])
```

Replace the `@php ... @endphp` block (lines 7-12) with one that short-circuits the empty derivation when showing skeleton (the derivation calls `$rows->toHtml()`, which would error on a lazy null-rows slot):
```blade
@php
// First-load skeleton: opt-in, and only while a paginator hasn't loaded yet.
// Gated behind $skeleton so static/synchronous tables are completely unaffected.
$showSkeleton = $skeleton && is_null($paginate);
$skeletonRows = $skeleton === true ? 5 : (int) $skeleton;

if (!$showSkeleton && !is_bool($empty)) {
    if ($paginate) $empty = !$paginate->total();
    else $empty = isset($rows) && !strip_tags($rows->toHtml());
}
@endphp
```

- [ ] **Step 4: Render the skeleton branch**

In the scroll container, replace the `@if ($empty) ... @else <table> ... </table> @endif` block (lines 49-73) so the skeleton takes precedence:
```blade
            @if ($showSkeleton)
                <div class="animate-pulse divide-y divide-zinc-150 dark:divide-zinc-700" data-atom-table-skeleton>
                    @for ($i = 0; $i < $skeletonRows; $i++)
                        <div class="py-4 px-4" data-atom-table-skeleton-row>
                            <atom:placeholder-bar size="{{ [45, 70, 55, 80, 50][$i % 5] }}%/x/10" />
                        </div>
                    @endfor
                </div>
            @elseif ($empty)
                <atom:empty />
            @else
                <table class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-150 dark:divide-zinc-700">
                    @if (isset($columns) && $columns->isNotEmpty())
                        <thead data-atom-table-columns>
                            <tr {{ $columns->attributes }}>
                                {{ $columns }}
                            </tr>
                        </thead>
                    @endif

                    @if (isset($rows) && $rows->isNotEmpty())
                        <tbody {{ $rows->attributes->class(['divide-y divide-zinc-150 dark:divide-zinc-700']) }} data-atom-table-rows>
                            {{ $rows }}
                        </tbody>
                    @endif

                    @if (isset($footer) && $footer->isNotEmpty())
                        <tfoot data-atom-table-footer>
                            {{ $footer }}
                        </tfoot>
                    @endif
                </table>
            @endif
```
(The pagination/sort overlay div above it and the `@if ($paginate?->hasPages())` block below it are unchanged.)

- [ ] **Step 5: Run — verify pass**

Run: `vendor/bin/pest tests/Feature/TableSkeletonTest.php`
Expected: 3 passing. If the `:skeleton="3"` count assertion fails, check the `data-atom-table-skeleton-row` attribute appears once per row.

- [ ] **Step 6: Run the full suite (no regressions)**

Run: `composer test`
Expected: all green (existing + 3 new).

- [ ] **Step 7: Commit**

```bash
git add components/table/index.blade.php tests/Feature/TableSkeletonTest.php
git commit -m "feat(table): opt-in first-load skeleton rows"
```

---

## Task 3: scoped loading spinner in `table.search`

**Files:**
- Modify: `components/table/search.blade.php`
- Test: `tests/Feature/TableSearchTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/TableSearchTest.php`:
```php
it('renders a scoped loading spinner that keeps rows visible', function () {
    $html = renderBlade('<atom:table.search wire:model="filters.search" />');

    expect($html)->toContain('wire:loading')
        ->and($html)->toContain('data-atom-table-search');
});
```

- [ ] **Step 2: Run — verify it fails**

Run: `vendor/bin/pest tests/Feature/TableSearchTest.php`
Expected: FAIL (no `wire:loading` in the search markup yet).

- [ ] **Step 3: Add the spinner to `components/table/search.blade.php`**

Replace the whole file with (wrap the input in a relative container + a right-aligned spinner; the search icon is on the LEFT so the right side is free):
```blade
@props([
    'placeholder' => 'Search',
])

<div class="relative" data-atom-table-search>
    <atom:input
        icon="search"
        {{ $attributes->merge(['placeholder' => $placeholder]) }}
        x-on:keyup.enter.prevent="$wire.$refresh()" />

    <div
    wire:loading
    wire:target="$refresh"
    class="absolute inset-y-0 right-0 z-1 flex items-center pr-3 text-zinc-400">
        <atom:icon.loading class="size-4" />
    </div>
</div>
```
**Use the Task 1 spike decision for `wire:target`:** if `$refresh` is targetable, keep `wire:target="$refresh"`. If the spike found it is NOT targetable, REMOVE the `wire:target` line (generic `wire:loading` — spins on any table request) and add a comment: `{{-- generic: Livewire $refresh isn't a targetable action in this version --}}`.

Note: `data-atom-table-search` moved from the input to the wrapper. Confirm the existing TableSearchTest assertions (`data-atom-table-search`, `keyup.enter`, the wire:model substring) still pass — they check substrings present anywhere in the output, so they hold.

- [ ] **Step 4: Run — verify pass**

Run: `vendor/bin/pest tests/Feature/TableSearchTest.php`
Expected: all passing (the original search tests + the new spinner test).

- [ ] **Step 5: Run full suite**

Run: `composer test`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add components/table/search.blade.php tests/Feature/TableSearchTest.php
git commit -m "feat(table): scoped loading spinner in table.search"
```

---

## Task 4: demo, guidance, release

**Files:**
- Create: `resources/views/docs/demos/table/skeleton.blade.php`
- Modify: `resources/views/docs/demos/table.blade.php`, `resources/boost/guidelines/core.blade.php`

- [ ] **Step 1: Skeleton demo partial**

Create `resources/views/docs/demos/table/skeleton.blade.php`:
```blade
<atom:table skeleton :skeleton="4" />
```
(With no paginator passed, `$paginate` is null → the demo shows the skeleton state statically.)

- [ ] **Step 2: Register the demo**

Append to `resources/views/docs/demos/table.blade.php`:
```blade
<atom:docs.example
title="Loading skeleton"
description="Pass skeleton (or :skeleton=N for a row count) to show placeholder rows on first load while a lazy/deferred table fetches its data. Opt-in: a table without the flag is unaffected. Filter/search shows a spinner in the search box (rows stay); pagination/sort use a dim overlay."
view="atom::docs.demos.table.skeleton"/>
```

- [ ] **Step 3: Update the Boost guidance loading line**

In `resources/boost/guidelines/core.blade.php`, the Tables section currently has (inside `@verbatim`):
```
- **Loading:** built in — a dim overlay keeps the rows visible (no jarring wipe) during pagination and sort. No extra markup needed.
```
Replace it with:
```
- **Loading:** built in — pagination/sort show a dim overlay (rows stay put); search shows a spinner in the search box (rows stay). For a lazy/deferred table, add the `skeleton` prop (or `:skeleton="N"`) to `<atom:table>` to show placeholder rows on first load until the data resolves.
```
Verify `@verbatim`/`@endverbatim` counts stay equal: `grep -c '@verbatim' resources/boost/guidelines/core.blade.php; grep -c '@endverbatim' resources/boost/guidelines/core.blade.php`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/table.blade.php resources/views/docs/demos/table/skeleton.blade.php resources/boost/guidelines/core.blade.php
git commit -m "docs(table): skeleton demo + guidance"
```

- [ ] **Step 5: Final verification + release (finishing-branch step)**

```bash
composer test                 # all green
# confirm no js/css source touched:
git diff --name-only main...worktree-table-skeleton | grep -E '^resources/(js|css)/' || echo "no js/css source changes"
```
Optionally smoke `/atom/docs/table` under `vendor/bin/testbench serve` and confirm the "Loading skeleton" example renders shimmer rows.

Then squash-merge to main, tag **v3.5.0**, push, remove the worktree:
```bash
git checkout main && git pull
git merge --squash worktree-table-skeleton
git commit -m "feat: table first-load skeleton + scoped search loading"
git push origin main
git tag v3.5.0 && git push origin v3.5.0
```

- [ ] **Step 6: Update memory** — record v3.5.0 + that the skeleton/loading backlog item is closed.

---

## Self-Review

**Spec coverage:**
- Opt-in `skeleton` prop + first-load skeleton, gated for static-table safety → Task 2 ✓ (incl. the static-table no-skeleton regression test).
- Skeleton short-circuits the `$empty` derivation (avoids `$rows->toHtml()` on lazy null rows) → Task 2 Step 3 ✓.
- Scoped search spinner, `$refresh` targeting with documented fallback → Task 1 (spike) + Task 3 ✓.
- Pagination/sort overlay unchanged → not modified (verified by leaving lines 42-47 + 76-78 intact).
- Demo + guidance → Task 4 ✓. Release v3.5.0 → Task 4 Step 5 ✓.

**Placeholder scan:** No TBD/TODO. The spike (Task 1) is a real investigation with a concrete decision + fallback, not a placeholder. All Blade is shown in full.

**Type/name consistency:** `skeleton` prop (`false`|`true`|int) and `$skeletonRows`/`$showSkeleton` used consistently in Task 2. Data hooks `data-atom-table-skeleton` (container) + `data-atom-table-skeleton-row` (per row) match between the component and the Task 2 tests. `renderBlade` helper used consistently. Demo `view=` path matches the created partial.
