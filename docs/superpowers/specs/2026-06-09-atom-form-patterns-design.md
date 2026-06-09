# Atom Form Patterns — Reducing Host-App UI Drift

**Date:** 2026-06-09
**Status:** Design (approved in brainstorm, pending spec review)

## Problem

Host-apps that consume `jiannius/atom` drift in form/modal UI even though they share the same component library. The root cause is **not** a missing component API — it's that the AI (and humans) generating host-app markup pick **bad values among legal ones**. A judgment/taste gap, not an API gap.

Two distinct bug classes:

- **Correlated-decision drift** — modal width and column count chosen independently and mispaired: 2-col in a `max-w-lg` modal (suffocated), or 1-col in a `max-w-4xl` modal (stretched inputs).
- **Taste/knowledge drift** — 2 columns for a 3-field form (overkill), multiple checkboxes left as loose inline `<atom:checkbox>` stuck together instead of `<atom:checkbox.group>`, reaching for `variant="card"` when plain would do, gray delete buttons, ad-hoc footer layouts.

### Audit evidence (host-apps: humblebear, sudo, apikan, webu)

- **Modal width** spread across **9 distinct `max-w-*`** values (lg/xl/2xl dominate = 82%; md/3xl/4xl/5xl/6xl/7xl = freehand tail).
- **`md:grid-cols-2`** over-applied (41×), plus bare `grid-cols-2` (13×) that does not collapse on mobile.
- **2-col never appears below `max-w-2xl`** in humblebear — empirical floor.
- **Checkbox**: 67 loose `<atom:checkbox>` vs 28 `<atom:checkbox.group>`; `variant="card"` only 2 of 28 (rare/special).
- **Delete buttons** written as `type="delete" variant="ghost"` — the `ghost` override *suppresses* the component's built-in de-emphasized-red styling, forcing gray.

**humblebear is the reference "appropriate" UI** — all numeric rules below are derived from it.

### Existing root cause in the guidance channel

`resources/boost/guidelines/core.blade.php` already ships a conventions block to consumers (via Boost `boost:install`) that says verbatim: *"Dense business forms. Two-column by default: `grid gap-6 md:grid-cols-2`."* That blunt rule **generates** the over-columned forms, and there is **zero** guidance on modal width↔columns, checkbox grouping, or footers.

## Approach

**Hybrid** — split each decision by whether it can be made mechanical:

- **Bake the structural/mechanical decisions into components** (so they can't be gotten wrong, regardless of AI compliance): the width-gate (via CSS container queries), the footer layout, and a color-aware ghost button.
- **Document the judgment decisions in the guidance channel** (`core.blade.php`) + a canonical `/atom/docs` demo (since every atom-consuming app has Boost installed, this reaches the host-app AI): the modal-width table, the column length heuristic, checkbox/dd conventions, footer convention.

All component changes are **additive and backward-compatible** — existing host-app markup keeps working unchanged.

## Component Changes (atom)

### 1. `<atom:form.grid>` (new — `components/form/grid.blade.php`) + `cols` convenience on `<atom:form>`

**Why a per-group grid, not a whole-form grid:** humblebear forms apply a grid to a *field group* (e.g. journal/form: a grid around one field-pair, then a `<atom:textarea>` outside the grid, then the footer), interleaved with `<atom:separator>` and section headings. Wrapping the entire form slot in one grid would force separators/headings into grid cells. So the grid primitive operates on a **group of fields**, used as many times as needed within a form.

- **`<atom:form.grid cols="auto|2|3">`** — the container-query grid primitive. Wraps a field group:
  - `cols="auto"` → `<div class="@container"><div class="grid gap-6 @2xl:grid-cols-2">{{ $slot }}</div></div>`. Columns respond to the **container's** width, not the viewport — narrow parent (card/panel) → 1 col automatically; wide parent (full modal/page) → 2 col. Width-gate becomes mechanical: "never 2-col in a too-narrow box" enforced by CSS.
  - `cols="2"` / `cols="3"` → forced grid, no container query: `grid gap-6 grid-cols-1 md:grid-cols-2` (or `md:grid-cols-3`). Explicit "I know I want N columns."
  - Default `cols` → `auto`.
- **`cols` prop on `<atom:form>`** — convenience for **single-group forms** (the whole body is one uniform grid, e.g. contact/product forms). Internally wraps the slot in one `form.grid`. Default `null` → current behavior (`flex flex-col gap-6`, no grid). **Back-compat.** Documented as single-group only; multi-section forms compose `<atom:form>` + multiple `<atom:form.grid>` blocks separated by `<atom:separator>`.

**Implementation note — container breakpoint:** `@2xl` ≈ 42rem matches the empirical `max-w-2xl` floor for 2 columns. Tune against `/atom/docs` (`@xl`/`@2xl`) during build; document the chosen breakpoint.

### 2. `<atom:form.modal>` (new — `components/form/modal.blade.php`)

Pattern component composing modal + form + footer, following atom's dot-path convention (like `button.group`, `checkbox.group`).

- Renders: `<atom:modal {name,dismissible,closeable,width}>` → `<atom:form {cols}>` → `{{ $slot }}` (fields) → `<atom:form.actions>`.
- Props (passthrough): `name`, `:dismissible`, `:closeable`, `cols` (single-group convenience; multi-section forms compose `<atom:form.grid>` blocks inside the slot), plus a width override and a `submit` label.
- **Default width derived from `cols`**: 1-col → `max-w-xl`; `auto`/`2` → `max-w-2xl`; consumer can override via class/prop. (The width table guides manual cases.)
- Footer (`form.actions`) sticky to the modal bottom.

### 3. `<atom:form.actions>` (new — `components/form/actions.blade.php`)

Standardized form footer.

- Layout: `flex items-center gap-3 justify-between`.
- **Primary slot (left)** — defaults to `<atom:button type="submit">{{ t('Save') }}</atom:button>` when not overridden (or via a `submit` prop/label).
- **Destructive slot (right)** — for the delete button.
- Sticky-bottom treatment when rendered inside a modal.
- **No Cancel button** — modal `×`/backdrop dismiss handles it (humblebear convention).

### 4. `color` prop on `<atom:button>` (`components/button/index.blade.php`, ghost branch)

- New `color` prop. Default `null` → current gray ghost. **Back-compat.**
- When `variant="ghost"` and `color` set → transparent rest + colored text + colored-fill hover, via a `match($color)` over atom's intent palette (same hues as the existing inverted variants):

| `color` | rest | hover |
|---|---|---|
| `danger` | `text-red-500` | `bg-red-500 text-red-100` |
| `primary` | `text-primary` | `bg-primary text-primary-foreground` |
| `warning` | `text-yellow-500` | `bg-yellow-500 text-yellow-800` |
| `accent` | `text-accent` | `bg-accent text-accent-foreground` |
| `success` | `text-green-600` | `bg-green-600 text-green-100` |
| (none) | `text-zinc-600` … | current gray (unchanged) |

- Token vocabulary aligns to `variant` (`danger`, not `red`) — one vocabulary across the component. `color` only affects the `ghost` branch (and is ignored elsewhere, where `variant` already carries the hue).
- **Standard footer delete** becomes `<atom:button type="delete" variant="ghost" color="danger">` — `type="delete"` keeps the auto-confirm wiring; `variant="ghost" color="danger"` gives transparent de-emphasized red that inverts to solid red on hover.

## Guidance Changes (`resources/boost/guidelines/core.blade.php`)

Rewrite the **"Recommended project conventions"** block into explicit **form & layout decision rules**:

- **Columns** — replace "two-column by default" with: *principle* (choose columns to avoid a long scroll) + *container gate* (wrap field groups in `<atom:form.grid cols="auto">` — or `<atom:form cols="auto">` for single-group forms; never 2-col in a container narrower than `max-w-2xl`, which the container query enforces automatically) + *length proxy* (~≤5 fields → 1 column; longer/scrolly → 2 columns, pairing related fields). Always responsive — never bare `grid-cols-2`.
- **Modal width table:**

  | Form shape | Width |
  |---|---|
  | 1-col, ≤4 simple fields | `max-w-lg` |
  | 1-col, more/wider fields | `max-w-xl` |
  | 1-col, dense/settings | `max-w-2xl`–`max-w-3xl` |
  | 2-col (`md:grid-cols-2`) | min `max-w-2xl` → `4xl` (~10 fields) → `5xl` (15+) |
  | builder/full-tool screens only | `max-w-6xl`/`7xl` |

- **Checkbox** — multiple related boxes → always `<atom:checkbox.group>`, default variant; `variant="card"` only when each option needs a description/icon.
- **dd** — group label/value pairs in `<atom:dd.group>`; `cols="2"` only for many fields on wide show pages (same density logic as forms).
- **Form footer** — use `<atom:form.actions>`. Save left (`type="submit"`, label "Save"); Delete right (`type="delete" variant="ghost" color="danger"`); no Cancel.
- Point the AI at the new canonical demo.

## Canonical Demo (`/atom/docs`)

New demo partial(s) under `resources/views/docs/demos/` (the file text IS the documentation; rendered live + shown as source), demonstrating: 1-col vs 2-col form via `cols="auto"`, `<atom:form.modal>`, `<atom:form.actions>` footer, and the ghost-red delete button. Self-verifying because `/atom/docs` renders them.

## Release

- All changes are Blade-only (component markup + class strings + guidelines + demos) — **no `resources/js|css` source change**, so **no atom `dist/` rebuild** is required (per the package's build rule). New utility classes used by `color`-on-ghost already exist in the inverted-danger variants, so they are already in consumers' scanned class sets; host-apps pick up any net-new classes on their normal `npm run build`.
- Version bump **v3.2.6 → v3.3.0** (new components/props, additive = minor `feat`). Push + tag per release flow.
- Consumers absorb the guidelines on next `boost:install` and the components on `composer update`.

## Non-Goals

- No change to existing `<atom:modal>` / `<atom:form>` default behavior (all additive).
- No retro-fix of existing host-app markup (the lint/codemod option — "Approach 3" — is deferred).
- No semantic modal `size` enum; width stays class-based, steered by the table + `form.modal` defaults.

## Verification

atom has no automated test suite. Verification = render the new demos at `/atom/docs` via the humblebear live-verification rig (symlink vendor → worktree), visually confirm: container-query column behavior at varying container widths, the modal/form/footer composition, and the ghost-red delete hover-invert.
