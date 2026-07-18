# `<atom:chart>` — Standalone chart component

## Context

Charts already exist in atom but only as branches **inside** `components/card.blade.php`
(`variant="chart"` → bar/area; `variant="stats"` → trend sparkline). They render via three Alpine
factories — `chartBar` / `chartArea` / `chartTrend` (`resources/js/alpinejs/chart/*.js`) — that
dynamically `import('apexcharts')` (already a dependency). There is no reusable `<atom:chart>`; a
consumer who wants a chart outside a card must hand-write `x-data="chartBar({...})"`.

This extracts a standalone `<atom:chart type=bar|area|trend>` wrapping the existing factories 1:1
(same capability, now usable anywhere), fixes two latent factory bugs found while reading them, and
refactors the card's `variant=chart` branch to delegate to the new component (single source of
truth). Mirrors the `<atom:pagination>` extraction. Branch `worktree-chart` (off `main`).

## Approach (settled)

- **Extract, match current** — no new chart types, no multi-series, no legends/axis toggles. Just the
  3 existing factories exposed as one component. (Expansion is explicitly out of scope, below.)
- **Card delegates** its `variant=chart` branch to `<atom:chart>`; the `stats` variant and its inline
  trend sparkline are **left untouched**.
- **Default height, overridable** — component applies a sensible default height only when the caller
  passed no `h-*` class, using the established `icon/_wrapper.blade.php` convention
  (`str($attributes->get('class'))->is('*h-*') ? '' : $default`).

## Data shapes (by type — documented, caller's responsibility)

- `bar` / `area`: `[['label' => 'Mon', 'value' => 10, 'tooltip' => '10 sales'], …]`
- `trend` (sparkline): plain numeric array `[8, 12, 9, 14, 18, 22]`

## Files

1. **`components/chart.blade.php`** — new.
   ```blade
   @props([
       'type' => 'bar',    // bar | area | trend
       'data' => [],
       'color' => null,    // named (red/green/orange/gray) or #hex
       'max' => null,      // ['value' => .., 'label' => ..]  (bar/area annotation line)
       'min' => null,      // ['value' => ..]                 (area y-axis floor)
   ])
   ```
   - `@php $factory = match ($type) { 'area' => 'chartArea', 'trend' => 'chartTrend', default =>
     'chartBar' }; @endphp` — single mapping, no `@if` inside the tag.
   - Default height: `$type === 'trend' ? 'h-16' : 'h-64'`, applied only when caller passed no `h-*`
     (icon `_wrapper` convention).
   - Renders one div carrying `$attributes` + the default height + a single
     `x-data="{{ $factory }}({ data: @js($data), color: @js($color), max: @js($max), min: @js($min) })"`.
     ApexCharts renders into this `$el`. Passing `max`/`min` to `chartTrend` is harmless (it ignores
     them), so one x-data string covers all three types.
   - `data-atom-chart` + `data-atom-chart-type="{{ $type }}"` hooks for tests.

2. **`resources/js/alpinejs/chart/area.js`** — bug fixes:
   - `chart.render()` → `this.chart.render()` (undefined-var ReferenceError — area charts currently
     never render).
   - Add a `document.addEventListener('darkmode-changed', () => this.setColors())` listener to match
     `bar.js` (area defines `setColors` but never re-invokes it on theme flip).
   - `trend.js` left as-is (sparkline, single static color — no dark inversion needed).

3. **`components/card.blade.php`** — refactor `variant === 'chart'` branch (lines ~72–86): replace the
   two inline `x-data` blocks (the `@if ($type === 'area') … @else … @endif`) with a single
   `<atom:chart :type="$type" :data="$data" :color="$color" :max="$max" :min="$min" class="h-full" />`.
   `card` still renders its own `<atom:subheading>` heading and the `grow` wrapper. `variant=stats`
   (incl. its `chartTrend`) untouched. `card` passes `type` = `'area'` or `null`; `<atom:chart>`
   default `type=bar` handles the null.

4. **Docs** — `resources/views/docs/demos/chart.blade.php` + `demos/chart/` partials:
   `bar.blade.php` (with `color` + `max` goal line), `area.blade.php`, `trend.blade.php`. Register the
   page. Charts have no dedicated demo today (card demo only shows basic + stats), so this is their
   first showcase.

5. **`README.md`** — add `<atom:chart>` near the display/data components.

6. **`dist/`** — rebuild (`npm run build`) since `area.js` changed; commit the output.

## Verification

- **Pest** `tests/Feature/ChartTest.php` (renderBlade):
  - `type=bar` → div with `data-atom-chart-type="bar"` and `x-data` containing `chartBar(` + the JSON
    data; default class contains `h-64`.
  - `type=area` → `chartArea(`; `type=trend` → `chartTrend(` + `h-16` default height.
  - caller `class="h-96"` → **no** `h-64`/`h-16` in output (override wins).
  - `color`, `max`, `min` serialize into the `x-data` string.
  - card `variant=chart` renders a nested `data-atom-chart` (delegation wired).
- **E2E** `tests/e2e/chart.spec.js` (testbench-serve): mount `type=bar` demo → assert an
  `apexcharts-canvas`/`.apexcharts-svg` appears inside `[data-atom-chart]` (proves the factory booted
  and ApexCharts rendered — the real regression guard for the area `this.chart` fix). Toggle dark mode
  → chart still present. Keep it to render-smoke; don't assert pixel colors.
- Commands: `./vendor/bin/pest --filter Chart`; `npx playwright test chart`. Worktree deps: real
  `composer install` **and** `npm ci` + `npm run build` in the worktree (symlinked vendor resolves to
  main's dist — see atom-testing gotchas).

## Ship

Squash-per-task on `worktree-chart` → `npm run build` + commit dist → `gh pr create --draft`. Release
**v3.15.0** (new component). Merge via `gh pr merge --squash --delete-branch`, then sync main + tag +
push tag.

## Out of scope (v1)

New chart types (line/donut/pie/radial), multi-series, legends, axis/grid toggles, exposing arbitrary
ApexCharts config, refactoring the `stats` variant's trend sparkline, a `heading` prop on
`<atom:chart>` (card owns its heading).
