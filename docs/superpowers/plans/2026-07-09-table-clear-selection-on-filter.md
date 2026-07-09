# Table Clear-Selection-on-Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `<atom:table>` clears its row-checkbox selection whenever the visible result set narrows — a filter-control change, a search, or a trashed-view toggle — so a hidden checked row can't linger in `$_table.checkboxes`.

**Architecture:** Split by state ownership. The trashed toggle mutates atom's own `_table.show_trashed`, so it's cleared **server-side** in the existing `updatedAtomComponent` trait hook. Filter/search values live on **consumer** properties atom can't name, so those emit a new front-end `table-filter:changed` window event that the `<atom:table>` root listens for and turns into a `$wire.resetTableCheckboxes()` call.

**Tech Stack:** Laravel Blade (`<atom:...>` tag components), Alpine.js (inline in blade), Livewire 4, Pest 4 + Orchestra Testbench 11, Playwright (e2e).

## Global Constraints

- No `resources/js` / `resources/css` source changes → **`dist/` must NOT change**; do not rebuild-and-commit dist (Vite doesn't build blade). A build is run only to *confirm* dist is unchanged.
- No new Tailwind utility classes → consumers do **not** rebuild their CSS.
- "Clear" always means the existing `resetTableCheckboxes()` (empties `checkboxes` **and** sets `select_all = false`).
- Front-end event name is exactly `table-filter:changed` (namespaced under the existing `table-filter:` events).
- The event is dispatched only on a real value **change**, never from the init/hydration `emit()` that populates chips — so page load / `wire:navigate` never wipes a restored selection.
- PHP style: curly braces on all control structures; one-line PHPDoc on the modified method (match existing trait style).
- Curly-brace, terse, follow sibling patterns. `t()` for any user-facing string (none added here).

---

### Task 1: Trashed toggle clears the selection (server-side)

Handle the atom-owned `_table.show_trashed` update in the trait's existing
`updatedAtomComponent` hook. Real Pest coverage (no browser needed).

**Files:**
- Modify: `src/Traits/AtomComponent.php:42-58` (the `updatedAtomComponent` method)
- Test: `tests/Feature/TableSelectionTest.php` (add a `describe('clear on trashed toggle', ...)` block)

**Interfaces:**
- Consumes: existing `resetTableCheckboxes()` (trait, `src/Traits/AtomComponent.php:71`) — sets `_table.checkboxes = []` and `_table.select_all = false`.
- Produces: nothing new; behavior only.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/TableSelectionTest.php` (after the existing `describe('checkbox markup', ...)` block):

```php
describe('clear on trashed toggle', function () {
    it('clears both selection modes when the trashed view is toggled', function () {
        $test = Livewire::test(TableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->set('_table.select_all', true)
            ->set('_table.show_trashed', true);

        expect($test->get('_table.checkboxes'))->toBe([])
            ->and($test->get('_table.select_all'))->toBeFalse();
    });

    it('leaves the selection alone on an unrelated property update', function () {
        $test = Livewire::test(TableFixture::class)
            ->set('_table.checkboxes', [1, 2])
            ->set('_table.sort.column', 'name');

        expect($test->get('_table.checkboxes'))->toBe([1, 2]);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter="clear on trashed toggle"`
Expected: FAIL — first test: `checkboxes` is still `[1, 2]` (hook doesn't clear yet). Second test already passes.

- [ ] **Step 3: Write minimal implementation**

In `src/Traits/AtomComponent.php`, inside `updatedAtomComponent($property, $value)`, after the existing `if ($property === '_editor.images') { ... }` block, add:

```php
        // A trashed-view toggle changes which rows are listed, so a lingering
        // checkbox selection would point at rows no longer shown. Clear it.
        if ($property === '_table.show_trashed') {
            $this->resetTableCheckboxes();
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter="clear on trashed toggle"`
Expected: PASS (2 passed).

- [ ] **Step 5: Run the full selection suite (no regression)**

Run: `vendor/bin/pest tests/Feature/TableSelectionTest.php`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Traits/AtomComponent.php tests/Feature/TableSelectionTest.php
git commit -m "feat(table): trashed-view toggle clears the checkbox selection"
```

---

### Task 2: Filters + search emit `table-filter:changed`; table clears on it

Front-end wiring. Atom's rig isn't Livewire-backed, so the runtime clear is
verify-on-humblebear; Pest asserts the wiring is present in the rendered markup.

**Files:**
- Modify: `components/table/index.blade.php:27` (root `<div data-atom-table>`)
- Modify: `components/select/filter.blade.php:65` (the `$watch('selectValue', ...)` line in `x-init`)
- Modify: `components/date-picker/range.blade.php:31` (the `$watch('dateRangeValue', ...)` line in `x-init`)
- Modify: `components/table/search.blade.php` (the `<atom:input ... x-on:keyup.enter.prevent>` line)
- Test: `tests/Feature/TableSelectionTest.php` (add a `describe('clear on filter change (wiring)', ...)` block)

**Interfaces:**
- Consumes: `resetTableCheckboxes()` (trait), called from the front-end via `$wire.resetTableCheckboxes()`.
- Produces: window event `table-filter:changed` (no payload). Producers: `select.filter`, `date-picker.range`, `table.search`. Consumer: `<atom:table>` root listener.

- [ ] **Step 1: Write the failing markup tests**

Append to `tests/Feature/TableSelectionTest.php`:

```php
describe('clear on filter change (wiring)', function () {
    it('table root listens for table-filter:changed and clears when a selection exists', function () {
        $html = renderBlade('<atom:table><x-slot:columns><atom:table.column>A</atom:table.column></x-slot:columns></atom:table>');

        expect($html)
            ->toContain('table-filter:changed.window')
            ->toContain('resetTableCheckboxes');
    });

    it('select filter dispatches table-filter:changed on value change', function () {
        $html = renderBlade('<atom:select.filter wire:model.live="status" label="Status" :options="[]" />');

        expect($html)->toContain('table-filter:changed');
    });

    it('date range filter dispatches table-filter:changed on value change', function () {
        $html = renderBlade('<atom:date-picker.range wire:model.live="range" />');

        expect($html)->toContain('table-filter:changed');
    });

    it('search dispatches table-filter:changed on submit', function () {
        $html = renderBlade('<atom:table.search wire:model="q" />');

        expect($html)->toContain('table-filter:changed');
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest --filter="clear on filter change"`
Expected: FAIL — all four assertions miss `table-filter:changed` (not wired yet).

- [ ] **Step 3: Wire the table root listener**

In `components/table/index.blade.php`, change the opening root div (line 27) from:

```blade
<div class="group/table space-y-4" data-atom-table>
```

to:

```blade
<div
x-data="{}"
x-on:table-filter:changed.window="if ($wire._table.checkboxes.length || $wire._table.select_all) $wire.resetTableCheckboxes()"
class="group/table space-y-4" data-atom-table>
```

- [ ] **Step 4: Wire the select filter dispatch**

In `components/select/filter.blade.php`, change the `$watch` line (line 65) from:

```js
    $watch('selectValue', () => $nextTick(emit));
```

to:

```js
    $watch('selectValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
```

- [ ] **Step 5: Wire the date-range dispatch**

In `components/date-picker/range.blade.php`, change the `$watch` line (line 31) from:

```js
    $watch('dateRangeValue', () => $nextTick(emit));
```

to:

```js
    $watch('dateRangeValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
```

- [ ] **Step 6: Wire the search dispatch**

In `components/table/search.blade.php`, change the search input's enter handler from:

```blade
        x-on:keyup.enter.prevent="$wire.$refresh()" />
```

to:

```blade
        x-on:keyup.enter.prevent="$dispatch('table-filter:changed'); $wire.$refresh()" />
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest --filter="clear on filter change"`
Expected: PASS (4 passed).

- [ ] **Step 8: Commit**

```bash
git add components/table/index.blade.php components/select/filter.blade.php components/date-picker/range.blade.php components/table/search.blade.php tests/Feature/TableSelectionTest.php
git commit -m "feat(table): filter/search change clears the checkbox selection"
```

---

### Task 3: Boost docs + full-suite verification

Document the new behavior + the escape hatch, confirm `dist/` is untouched, and
run the full Pest + Playwright suites to prove no regression.

**Files:**
- Modify: `resources/boost/guidelines/core.blade.php:118` (the "Selection & bulk actions" bullet, inside the `@verbatim` block)

**Interfaces:**
- Consumes: nothing. Documentation + verification only.
- Produces: nothing.

- [ ] **Step 1: Update the Boost guideline**

In `resources/boost/guidelines/core.blade.php`, the "Selection & bulk actions" bullet (line 118) ends with "... Individual/page toggles exit select-all mode." Append this sentence to that same bullet, before the trailing content:

```
Changing a filter, running a search, or toggling the trashed view **clears the selection** (the checked rows may no longer be visible) — so build bulk actions against the currently-visible result set. A custom (non-atom) filter control can opt into the same clear by dispatching the event itself: `x-on:input="$dispatch('table-filter:changed')"`.
```

The bullet must stay inside the existing `@verbatim ... @endverbatim` wrapper (line 119 is `@endverbatim`), so the `$dispatch` example is not compiled by Blade.

- [ ] **Step 2: Confirm dist is unchanged (no JS/CSS source touched)**

Run: `npm run build && git status --porcelain dist/`
Expected: build succeeds and `git status` for `dist/` prints **nothing** (identical output → no dist churn to commit). If anything under `dist/` shows as modified, STOP — a source file was changed unexpectedly; investigate before continuing.

- [ ] **Step 3: Run the full Pest suite**

Run: `vendor/bin/pest`
Expected: all green (existing count + 6 new selection tests).

- [ ] **Step 4: Run the full Playwright suite (table markup regression)**

Run: `npx playwright test`
Expected: all pass (the added `x-data="{}"` + listener on the table root must not break the existing `table-filters` / `table-overflow` / other specs).

> Worktree note: Playwright + testbench need the worktree's own deps — a real `composer install` in the worktree (a symlinked `vendor` makes `testbench serve` resolve the package to the main checkout and serve the wrong code), and `node_modules` (symlink from the main checkout is fine for the build; if `npx playwright` mis-resolves, run a real `npm ci`). See the [[atom-testing]] memory.

- [ ] **Step 5: Commit**

```bash
git add resources/boost/guidelines/core.blade.php
git commit -m "docs(boost): note table selection clears on filter/search/trashed"
```

---

## Verify-on-humblebear (post-merge, atom's rig can't test)

`/atom/docs` is not Livewire-backed, so the runtime clear is unverifiable here.
On humblebear, confirm:

- Check rows → change a filter control → selection clears (bar disappears / count resets).
- Check rows → run a search → clears.
- Check rows → remove a filter chip / "Clear all" → clears.
- Check rows → toggle trashed → clears.
- **Nothing selected** → change a filter → no spurious Livewire request (guard holds).
- Page load / `wire:navigate` with a persisted selection → selection is **not** wiped (init emit doesn't dispatch the event).
- Paginate / sort → selection **persists** (not wired).

## Self-Review

- **Spec coverage:** Trigger 1 (trashed, server-side) → Task 1. Trigger 2 (filters + search, front-end event) → Task 2 (all three producers + the root listener). Escape hatch + behavior note → Task 3. Build/consumer-impact (dist unchanged) → Task 3 Step 2. Testing (Pest real + markup, hb runtime) → Tasks 1–3 + the verify list. All spec sections covered.
- **Placeholder scan:** none — every code/edit step shows exact before/after and exact commands.
- **Type/name consistency:** event name `table-filter:changed` and method `resetTableCheckboxes` used identically in every producer, the listener, the trait, and the tests.
