# Atom Form Patterns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce host-app form/modal UI drift by baking structural layout decisions into atom components and documenting judgment decisions in the Boost guidance channel.

**Architecture:** Additive, backward-compatible component changes — a color-aware ghost button, a container-query grid primitive (`form.grid`), a standardized footer (`form.actions`), and a composed modal-form (`form.modal`) — plus a rewrite of the `core.blade.php` conventions and a canonical `/atom/docs` demo. Spec: `docs/superpowers/specs/2026-06-09-atom-form-patterns-design.md`.

**Tech Stack:** Blade anonymous components, Tailwind v4 (container queries), Alpine. No JS/CSS source changes → no `dist/` rebuild.

---

## Testing note — atom has no automated test suite

atom ships no Pest/PHPUnit suite (Testbench is an unused dev dep). Per-task verification is **visual via `/atom/docs`**, using the humblebear live-verification rig (see `[[atom-release-flow]]` memory): back up + symlink `humblebear/vendor/jiannius/atom` → this worktree, then visit `https://humblebear.test/atom/docs/<component>`; restore afterward. Every component change ships with a demo partial that is rendered (and thus verified) on its docs page. There are no red/green test commands; the "verify" step means *render the demo and confirm the described behavior visually*.

All component edits are Blade-only. Each task commits independently.

---

## File Structure

- `components/button/index.blade.php` — **modify**: add `color` prop, make ghost branch color-aware.
- `components/form.blade.php` → `components/form/index.blade.php` — **move** (enables `form.*` subcomponents), then add `cols` convenience.
- `components/form/grid.blade.php` — **create**: container-query grid primitive.
- `components/form/actions.blade.php` — **create**: standardized footer.
- `components/form/modal.blade.php` — **create**: composed modal + form + footer.
- `resources/views/docs/demos/button.blade.php` + `button/ghost-colors.blade.php` — **modify/create**: ghost-color demo.
- `resources/views/docs/demos/form.blade.php` + `form/{grid,actions,modal}.blade.php` — **modify/create**: form-pattern demos.
- `resources/boost/guidelines/core.blade.php` — **modify**: rewrite the conventions block into decision rules.

---

## Task 1: `color` prop on `<atom:button>` (color-aware ghost)

**Files:**
- Modify: `components/button/index.blade.php` (props block; ghost branch at lines ~7-13 and ~56-58)
- Create: `resources/views/docs/demos/button/ghost-colors.blade.php`
- Modify: `resources/views/docs/demos/button.blade.php`

- [ ] **Step 1: Add the `color` prop**

In `components/button/index.blade.php`, add `'color' => null,` to the `@props([...])` block (place it right after `'variant' => null,`):

```php
    'variant' => null,
    'color' => null,
    'icon' => null,
```

- [ ] **Step 2: Make the ghost branch color-aware**

Replace the existing ghost branch:

```php
    if ($variant === 'ghost') {
        $classes[] = 'bg-transparent text-zinc-600 dark:text-zinc-400 border border-transparent focus:outline-zinc-200 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-zinc-300';
    }
```

with:

```php
    if ($variant === 'ghost') {
        $classes[] = 'bg-transparent border border-transparent';
        $classes[] = match ($color) {
            'primary' => 'text-primary focus:outline-primary hover:bg-primary hover:text-primary-foreground',
            'accent' => 'text-accent focus:outline-accent hover:bg-accent hover:text-accent-foreground',
            'warning' => 'text-yellow-500 focus:outline-yellow-300 hover:bg-yellow-500 hover:text-yellow-800',
            'danger', 'error' => 'text-red-500 focus:outline-red-300 hover:bg-red-500 hover:text-red-100',
            'success' => 'text-green-600 focus:outline-green-300 hover:bg-green-600 hover:text-green-100',
            default => 'text-zinc-600 dark:text-zinc-400 focus:outline-zinc-200 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-zinc-300',
        };
    }
```

- [ ] **Step 3: Create the ghost-colors demo partial**

Create `resources/views/docs/demos/button/ghost-colors.blade.php`:

```blade
<div class="flex flex-wrap items-center gap-3">
    <atom:button variant="ghost">Default</atom:button>
    <atom:button variant="ghost" color="primary">Primary</atom:button>
    <atom:button variant="ghost" color="danger">Danger</atom:button>
    <atom:button variant="ghost" color="warning">Warning</atom:button>
    <atom:button variant="ghost" color="success">Success</atom:button>
</div>
```

- [ ] **Step 4: Register the demo on the button docs page**

In `resources/views/docs/demos/button.blade.php`, append:

```blade
<atom:docs.example
title="Ghost colors"
description="variant=ghost is de-emphasized (transparent). Add color (primary, danger, warning, success) to tint the text and invert to a solid fill on hover. Used for the standard form delete button: type=delete variant=ghost color=danger."
view="atom::docs.demos.button.ghost-colors"/>
```

- [ ] **Step 5: Verify**

Render `https://humblebear.test/atom/docs/button` (humblebear rig). Confirm: the 4 colored ghost buttons show transparent backgrounds with colored text at rest, and invert to a solid colored fill with light text on hover. The "Default" ghost is unchanged gray.

- [ ] **Step 6: Commit**

```bash
git add components/button/index.blade.php resources/views/docs/demos/button.blade.php resources/views/docs/demos/button/ghost-colors.blade.php
git commit -m "feat(button): color-aware ghost variant"
```

---

## Task 2: Convert `form` to a directory

This enables `<atom:form.grid>`, `<atom:form.actions>`, `<atom:form.modal>` (dot-paths map to `components/form/*.blade.php`). `<atom:form>` resolves to `components/form/index.blade.php` exactly as it did to the flat file.

**Files:**
- Move: `components/form.blade.php` → `components/form/index.blade.php`

- [ ] **Step 1: Move the file**

```bash
mkdir -p components/form
git mv components/form.blade.php components/form/index.blade.php
```

- [ ] **Step 2: Verify the move kept content identical**

Run: `git diff --cached --stat`
Expected: a single rename `components/form.blade.php -> components/form/index.blade.php`, 0 insertions/deletions.

- [ ] **Step 3: Verify `<atom:form>` still resolves**

Render `https://humblebear.test/atom/docs/form`. Confirm the existing "Basic" form example still renders unchanged (inputs stack, submit button present).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(form): convert to directory component for subcomponents"
```

---

## Task 3: `<atom:form.grid>` + `cols` convenience on `<atom:form>`

**Files:**
- Create: `components/form/grid.blade.php`
- Modify: `components/form/index.blade.php`
- Create: `resources/views/docs/demos/form/grid.blade.php`
- Modify: `resources/views/docs/demos/form.blade.php`

- [ ] **Step 1: Create `form/grid.blade.php`**

```blade
@props([
    'cols' => 'auto',
])

@if ($cols === 'auto')
    <div class="@container">
        <div {{ $attributes->class('grid gap-6 @2xl:grid-cols-2') }}>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes->class([
        'grid gap-6 grid-cols-1',
        'md:grid-cols-2' => (string) $cols === '2',
        'md:grid-cols-3' => (string) $cols === '3',
    ]) }}>
        {{ $slot }}
    </div>
@endif
```

`cols="auto"` (default) responds to the **container** width (`@2xl` ≈ 42rem, matching the `max-w-2xl` floor): 1 col in a narrow parent, 2 cols when the parent is wide. `cols="1|2|3"` forces a viewport-responsive grid.

- [ ] **Step 2: Add the `cols` convenience to `form/index.blade.php`**

Add `'cols' => null,` to the props block:

```php
@props([
    'inset' => false,
    'cols' => null,
])
```

Then replace the slot render inside the `<form>`:

```blade
        {{ $slot }}
```

with:

```blade
        @if ($cols)
            <atom:form.grid :cols="$cols">{{ $slot }}</atom:form.grid>
        @else
            {{ $slot }}
        @endif
```

(Single-group convenience only — wraps the *entire* form body in one grid. Multi-section forms use explicit `<atom:form.grid>` blocks instead.)

- [ ] **Step 3: Create the grid demo partial**

Create `resources/views/docs/demos/form/grid.blade.php` — shows the same `auto` grid in a narrow vs wide container so the container-query behavior is visible:

```blade
<div x-data class="space-y-6">
    <div>
        <atom:caption>Narrow container (max-w-sm) → collapses to 1 column</atom:caption>
        <div class="max-w-sm border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <atom:form x-on:submit.prevent>
                <atom:form.grid cols="auto">
                    <atom:input label="First name"/>
                    <atom:input label="Last name"/>
                </atom:form.grid>
            </atom:form>
        </div>
    </div>

    <div>
        <atom:caption>Wide container (max-w-3xl) → 2 columns</atom:caption>
        <div class="max-w-3xl border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <atom:form x-on:submit.prevent>
                <atom:form.grid cols="auto">
                    <atom:input label="First name"/>
                    <atom:input label="Last name"/>
                </atom:form.grid>
            </atom:form>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Register the demo**

In `resources/views/docs/demos/form.blade.php`, append:

```blade
<atom:docs.example
title="Grid columns"
description="atom:form.grid lays out a field group. cols=auto (default) is a container query: it shows 1 column in a narrow parent and 2 columns once the parent is wide enough (~max-w-2xl), regardless of viewport. Use cols=2 or cols=3 to force a viewport-responsive grid. For a single-group form, put cols on atom:form directly."
view="atom::docs.demos.form.grid"/>
```

- [ ] **Step 5: Verify**

Render `https://humblebear.test/atom/docs/form`. Confirm: the narrow-container form shows the two inputs stacked (1 col); the wide-container form shows them side-by-side (2 col). Resize the browser — the columns should NOT change with viewport (they track the container).

- [ ] **Step 6: Commit**

```bash
git add components/form/grid.blade.php components/form/index.blade.php resources/views/docs/demos/form.blade.php resources/views/docs/demos/form/grid.blade.php
git commit -m "feat(form): add form.grid container-query layout + cols convenience"
```

---

## Task 4: `<atom:form.actions>` (standardized footer)

**Files:**
- Create: `components/form/actions.blade.php`
- Create: `resources/views/docs/demos/form/actions.blade.php`
- Modify: `resources/views/docs/demos/form.blade.php`

- [ ] **Step 1: Create `form/actions.blade.php`**

```blade
@props([
    'sticky' => false,
])

<div {{ $attributes->class([
    'flex items-center gap-3 justify-between',
    'sticky bottom-0 z-1 bg-white dark:bg-zinc-900 pt-4 -mb-2' => $sticky,
]) }} data-atom-form-actions>
    @if ($slot->isEmpty())
        <atom:button type="submit">{{ t('Save') }}</atom:button>
    @else
        {{ $slot }}
    @endif
</div>
```

`justify-between` puts the first child (Save) on the left and any second child (Delete) on the right. Empty slot → a default "Save" submit button. `sticky` pins it to the bottom inside a scrolling modal.

- [ ] **Step 2: Create the actions demo partial**

Create `resources/views/docs/demos/form/actions.blade.php`:

```blade
<div x-data>
    <atom:form x-on:submit.prevent>
        <atom:input label="Name"/>

        <atom:form.actions>
            <atom:button type="submit">Save</atom:button>
            <atom:button type="delete" variant="ghost" color="danger" x-on:click.prevent>Delete</atom:button>
        </atom:form.actions>
    </atom:form>
</div>
```

- [ ] **Step 3: Register the demo**

In `resources/views/docs/demos/form.blade.php`, append:

```blade
<atom:docs.example
title="Actions footer"
description="atom:form.actions standardizes the footer: justify-between puts Save on the left and Delete on the right. An empty footer renders a default Save submit button. The standard delete is type=delete variant=ghost color=danger (de-emphasized red, inverts on hover). No Cancel button — modal dismiss handles it. Pass sticky to pin it to a modal's bottom."
view="atom::docs.demos.form.actions"/>
```

- [ ] **Step 4: Verify**

Render `https://humblebear.test/atom/docs/form`. Confirm: Save is left-aligned (primary), Delete is right-aligned, ghost-red, and inverts to solid red on hover.

- [ ] **Step 5: Commit**

```bash
git add components/form/actions.blade.php resources/views/docs/demos/form.blade.php resources/views/docs/demos/form/actions.blade.php
git commit -m "feat(form): add form.actions standardized footer"
```

---

## Task 5: `<atom:form.modal>` (composed modal + form + footer)

**Files:**
- Create: `components/form/modal.blade.php`
- Create: `resources/views/docs/demos/form/modal.blade.php`
- Modify: `resources/views/docs/demos/form.blade.php`

- [ ] **Step 1: Create `form/modal.blade.php`**

```blade
@props([
    'name' => null,
    'cols' => 'auto',
    'submit' => 'Save',
    'dismissible' => true,
    'closeable' => true,
])

@php
$width = match ((string) $cols) {
    'auto', '2' => 'max-w-2xl',
    '3' => 'max-w-4xl',
    default => 'max-w-xl',
};
@endphp

<atom:modal :name="$name" :dismissible="$dismissible" :closeable="$closeable" {{ $attributes->class($width) }}>
    <atom:form>
        <atom:form.grid :cols="$cols">
            {{ $slot }}
        </atom:form.grid>

        <atom:form.actions sticky>
            <atom:button type="submit">{{ t($submit) }}</atom:button>
            @isset($delete)
                {{ $delete }}
            @endisset
        </atom:form.actions>
    </atom:form>
</atom:modal>
```

Fields go in a `form.grid` (so the footer stays outside the grid); width is derived from `cols` and overridable via `class`. The `delete` named slot adds a right-aligned delete button.

- [ ] **Step 2: Create the modal demo partial**

Create `resources/views/docs/demos/form/modal.blade.php`:

```blade
<div x-data>
    <atom:button x-on:click="atom.modal('demo-form-modal').show()">Edit contact</atom:button>

    <atom:form.modal name="demo-form-modal">
        <atom:input label="First name"/>
        <atom:input label="Last name"/>
        <atom:input type="email" label="Email"/>
        <atom:input label="Phone"/>

        <x-slot:delete>
            <atom:button type="delete" variant="ghost" color="danger" x-on:click.prevent>Delete</atom:button>
        </x-slot:delete>
    </atom:form.modal>
</div>
```

- [ ] **Step 3: Register the demo**

In `resources/views/docs/demos/form.blade.php`, append:

```blade
<atom:docs.example
title="Modal form"
description="atom:form.modal composes modal + form + footer. Fields go in the default slot (laid out by cols=auto, default). Width derives from cols (1-col→xl, auto/2→2xl, 3→4xl) and is overridable via class. The footer auto-includes a Save submit; add a right-aligned delete via the delete slot."
view="atom::docs.demos.form.modal"/>
```

- [ ] **Step 4: Verify**

Render `https://humblebear.test/atom/docs/form`. Click "Edit contact". Confirm: a `max-w-2xl` modal opens; the 4 fields lay out 2-up (container is wide enough); a sticky footer shows Save (left) + ghost-red Delete (right).

- [ ] **Step 5: Commit**

```bash
git add components/form/modal.blade.php resources/views/docs/demos/form.blade.php resources/views/docs/demos/form/modal.blade.php
git commit -m "feat(form): add form.modal composed pattern component"
```

---

## Task 6: Rewrite the Boost guidance conventions

**Files:**
- Modify: `resources/boost/guidelines/core.blade.php` (the "Recommended project conventions" section near the end)

- [ ] **Step 1: Replace the form/spacing conventions block**

Find this block:

```blade
@verbatim
- **Dense business forms.** Two-column by default: `grid gap-6 md:grid-cols-2`. Separate logical groups with `<atom:separator>` and short titles (e.g. "Address", "Registration & Tax").
- **Section separation.** Prefer `<atom:separator>` over ad-hoc `<hr>` or border classes.
@endverbatim
```

Replace it with:

```blade
@verbatim
- **Form columns.** Choose columns to keep the form from scrolling much — a single column should roughly fit its container without heavy scrolling. Wrap field groups in `<atom:form.grid cols="auto">` (a container query: 1 column in a narrow container, 2 once it is wide enough — never 2 columns below ~`max-w-2xl`; this is enforced by CSS, not viewport). Operationally: ~≤5 fields → 1 column; longer/scrolly → 2 columns, pairing related fields. For a single-group form, put `cols` on `<atom:form>`. Force a fixed layout with `cols="2"`/`cols="3"`. Never use bare `grid-cols-2` (it will not collapse on mobile).
- **Modal width.** Match width to the form: 1-col & ≤4 simple fields → `max-w-lg`; 1-col with more/wider fields → `max-w-xl`; 1-col dense/settings → `max-w-2xl`–`max-w-3xl`; 2-col → minimum `max-w-2xl`, scaling to `max-w-4xl` (~10 fields) and `max-w-5xl` (15+). Reserve `max-w-6xl`/`7xl` for builder/full-tool screens, not forms. `<atom:form.modal>` sets a sensible width from `cols` automatically.
- **Form footer.** Use `<atom:form.actions>`: Save on the left (`<atom:button type="submit">`, label "Save"), Delete on the right (`<atom:button type="delete" variant="ghost" color="danger">`). No Cancel button — modal dismiss handles it.
- **Checkboxes.** Multiple related checkboxes → always `<atom:checkbox.group>` (never loose stacked `<atom:checkbox>`). Default variant; use `variant="card"` only when each option needs its own description or icon.
- **Description lists (show pages).** Group label/value pairs in `<atom:dd.group>`; use `cols="2"` only for many fields on a wide page — same density logic as forms.
- **Section separation.** Prefer `<atom:separator>` over ad-hoc `<hr>` or border classes; separate logical field groups with a separator and a short title (e.g. "Address", "Registration & Tax").
@endverbatim
```

- [ ] **Step 2: Verify the guidelines render**

Run: `php -r "echo 'ok' . PHP_EOL;"` is not sufficient — instead confirm the Blade has balanced `@verbatim`/`@endverbatim`:
Run: `grep -c '@verbatim' resources/boost/guidelines/core.blade.php; grep -c '@endverbatim' resources/boost/guidelines/core.blade.php`
Expected: the two counts are equal.

- [ ] **Step 3: Commit**

```bash
git add resources/boost/guidelines/core.blade.php
git commit -m "docs(boost): form/modal/checkbox/dd decision rules for consumers"
```

---

## Task 7: Release (finishing-branch step)

This task is the finish/ship step — run it via the global squash-merge worktree flow, not as a code edit.

- [ ] **Step 1: Confirm no JS/CSS source changed (no `dist/` rebuild needed)**

Run: `git diff --name-only main...worktree-form-patterns | grep -E '^resources/(js|css)/' || echo "no js/css source changes"`
Expected: `no js/css source changes`.

- [ ] **Step 2: Final visual pass**

Render `https://humblebear.test/atom/docs/button` and `.../atom/docs/form` once more; confirm all new examples render and behave as described in Tasks 1–5.

- [ ] **Step 3: Squash-merge to main, tag, push, clean up**

```bash
# from the worktree
git checkout main
git pull
git merge --squash worktree-form-patterns
git commit -m "feat: form patterns to reduce host-app UI drift (form.grid/modal/actions, ghost color, guidance)"
git push origin main
git tag v3.3.0
git push origin v3.3.0
```

Then remove the worktree (`ExitWorktree action: remove`, or `git worktree remove` + `git branch -D worktree-form-patterns`).

- [ ] **Step 4: Update memory**

Update `[[atom-release-flow]]` latest version to v3.3.0; note the new `form.*` pattern components + `button color` exist (so future sessions reach for them).

---

## Self-Review

**Spec coverage:**
- `form.grid` + `cols` on form → Task 3 ✓
- `form.modal` → Task 5 ✓
- `form.actions` → Task 4 ✓
- `color` on button (ghost) → Task 1 ✓
- Guidance rewrite (columns, width table, checkbox, dd, footer) → Task 6 ✓
- Canonical `/atom/docs` demos → Tasks 1,3,4,5 (demo per component) ✓
- Release (Blade-only, no rebuild, v3.3.0) → Task 7 ✓
- Form-to-directory prerequisite → Task 2 ✓

**Placeholder scan:** No TBD/TODO; every code step shows full content. The `@2xl` container breakpoint is a concrete value (tunable, noted in spec).

**Type/name consistency:** `cols` accepts `auto|1|2|3` consistently across `form.grid`, `form` convenience, and `form.modal`. `color` tokens (`primary|accent|warning|danger|success`) match between the button `match` and the footer/guidance usage (`color="danger"`). `<x-slot:delete>` in the Task 5 demo matches the `@isset($delete)` slot in `form/modal.blade.php`. Demo `view=` paths match created partial paths.
