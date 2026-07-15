# Command Palette Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `<atom:command>` searchable ⌘K command-palette component to the atom library (Flux-parity gap #4).

**Architecture:** The palette is a native `<dialog>` driven by a dedicated `command` Alpine factory that reuses `<atom:modal>`'s open/close/backdrop patterns and `<atom:select>`'s search + keyboard-navigation patterns. Items are declared statically as slot children (`<atom:command.item>`, optionally inside `<atom:command.group>`) and filtered client-side. Opening is via a keyboard shortcut (Alpine key-modifier syntax, default `meta.k`), a PHP/JS event (`atom()->command($name)->show()` / `atom.command(name).show()`), or an `<atom:command.trigger>` button — all mirroring the existing modal wiring.

**Tech Stack:** Blade anonymous components, Alpine.js, Livewire 4 events, Tailwind (arbitrary-variant utility classes, no new CSS file), Pest + Orchestra Testbench (blade render tests), Playwright (e2e via `testbench serve`).

## Global Constraints

- Target release: **v3.9.0** (next minor). Do NOT tag inside the plan — tagging is the human's ship step.
- `<`/`>` characters must NEVER appear inside a `<atom:...>` tag attribute value (TagCompiler 500s) — this includes `<atom:docs.example description="...">`. Write `atom:command` in prose, never the angle-bracket form.
- Visibility toggling in JS MUST use the native `hidden` attribute (UA `display:none`), never a Tailwind `hidden` class — atom's test rig ships no Tailwind utilities, so Playwright `toBeVisible()` only observes UA-backed hiding.
- New Tailwind utility classes in blade are fine (consumers rebuild their own Tailwind — atom ships base CSS only). But any class the e2e relies on for *behavior* must be UA-backed or inline.
- `npm run build` regenerates `dist/`; the committed `dist/` is what `testbench serve` and consumers load. Any JS/CSS source change requires a rebuild + committing `dist/`.
- Follow existing house patterns: polymorphic element via `$el = $href ? 'a' : 'button'`; `Arr::toCssClasses([...])`; `<x-dynamic-component :component="'atom::icon.'.$icon"/>` for icons; `t(...)` for every UI string.

---

### Task 1: Blade components (`index`, `group`, `item`, `trigger`)

**Files:**
- Create: `components/command/index.blade.php`
- Create: `components/command/group.blade.php`
- Create: `components/command/item.blade.php`
- Create: `components/command/trigger.blade.php`
- Test: `tests/Feature/CommandTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces:
  - `<atom:command name shortcut placeholder>` — renders `<dialog data-atom-command x-data="command({ name: '<name>' })">` with a search input (`data-atom-command-search`, `role="combobox"`), a list container (`data-atom-command-list`, `role="listbox"`), and an empty-state region (`data-atom-command-empty`, `hidden`). Shortcut binding `x-on:keydown.<shortcut>.window.prevent="toggle"` when `shortcut` is truthy.
  - `<atom:command.group heading>` — `<div data-atom-command-group>` with optional `data-atom-command-heading`.
  - `<atom:command.item href icon shortcut>` — `<a>` when `href` set, else `<button type="button">`; carries `data-atom-command-item`, `data-label="<text>"`, `role="option"`.
  - `<atom:command.trigger name>` — button dispatching `atom-command-show` via `atom.command(name).show()`.
  - Factory method names the blade references (implemented in Task 3): `toggle`, `showCommand`, `closeCommand`, `backdropClick`, `filter`, `keyDown`, `keyUp`, `enterKey`, `home`, `end`; state `text`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CommandTest.php`:

```php
<?php

describe('command', function () {
    it('renders the dialog wired to the command factory with a search combobox', function () {
        $html = renderBlade('<atom:command name="palette"/>');

        expect($html)
            ->toContain('data-atom-command')
            ->toContain('x-data="command({ name: \'palette\' })"')
            ->toContain('x-on:atom-command-show.window="showCommand"')
            ->toContain('x-on:atom-command-close.window="closeCommand"')
            ->toContain('data-atom-command-search')
            ->toContain('role="combobox"')
            ->toContain('data-atom-command-list')
            ->toContain('data-atom-command-empty');
    });

    it('binds the default meta.k shortcut and disables it with false', function () {
        expect(renderBlade('<atom:command name="p"/>'))
            ->toContain('x-on:keydown.meta.k.window.prevent="toggle"');

        expect(renderBlade('<atom:command name="p" :shortcut="false"/>'))
            ->not->toContain('keydown.meta.k');
    });

    it('honours a custom shortcut', function () {
        expect(renderBlade('<atom:command name="p" shortcut="ctrl.slash"/>'))
            ->toContain('x-on:keydown.ctrl.slash.window.prevent="toggle"');
    });

    it('renders an item as an anchor when given href', function () {
        $html = renderBlade('<atom:command.item href="/dashboard" icon="search">Dashboard</atom:command.item>');

        expect($html)
            ->toContain('<a')
            ->toContain('href="/dashboard"')
            ->toContain('data-atom-command-item')
            ->toContain('data-label="Dashboard"')
            ->toContain('role="option"');
    });

    it('renders an item as a button when no href, forwarding wire:click', function () {
        $html = renderBlade('<atom:command.item wire:click="save">Save</atom:command.item>');

        expect($html)
            ->toContain('<button')
            ->toContain('type="button"')
            ->toContain('wire:click="save"')
            ->not->toContain('<a ');
    });

    it('renders a per-item shortcut badge', function () {
        expect(renderBlade('<atom:command.item shortcut="⌘K">Search</atom:command.item>'))
            ->toContain('<kbd')
            ->toContain('⌘K');
    });

    it('renders a group heading', function () {
        $html = renderBlade('<atom:command.group heading="Pages"><atom:command.item>Home</atom:command.item></atom:command.group>');

        expect($html)
            ->toContain('data-atom-command-group')
            ->toContain('data-atom-command-heading')
            ->toContain('Pages');
    });

    it('overrides the empty state via the empty slot', function () {
        expect(renderBlade('<atom:command name="p"><x-slot:empty>Nothing here</x-slot:empty></atom:command>'))
            ->toContain('Nothing here');
    });

    it('defaults the name to the current Livewire component name', function () {
        $component = new class ('my-page') {
            public function __construct(public string $name) {}
            public function getName(): string { return $this->name; }
        };

        $html = withLivewireContext($component, fn () => renderBlade('<atom:command/>'));

        expect($html)->toContain('name: \'my-page\'');
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest --filter=command`
Expected: FAIL — the `command` components do not exist yet (view-not-found / assertion failures).

- [ ] **Step 3: Create `components/command/index.blade.php`**

```blade
@props([
    'name' => null,
    'shortcut' => 'meta.k',
    'placeholder' => null,
])

@php
// current() returns false (not null) when no component is on the stack, so the
// nullsafe operator alone is not enough — mirror the modal's name default.
$name ??= (app('livewire')->current() ?: null)?->getName();
$placeholder ??= t('Search...');
$classes = Arr::toCssClasses([
    'group/command m-auto mt-[10vh] w-full max-w-xl overflow-hidden rounded-xl p-0 shadow-lg',
    'bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700',
    'backdrop:bg-black/40',
    '[&[data-open]]:flex [&[data-open]]:flex-col',
]);
@endphp

<dialog
wire:ignore.self
x-data="command({ name: @js($name) })"
x-on:atom-command-show.window="showCommand"
x-on:atom-command-close.window="closeCommand"
x-on:keydown.escape.stop.prevent="closeCommand"
@if ($shortcut) x-on:keydown.{{ $shortcut }}.window.prevent="toggle" @endif
x-on:click="backdropClick"
data-atom-command
{{ $attributes->class($classes) }}>
    <div class="flex items-center gap-2 border-b border-zinc-200 px-4 dark:border-zinc-700">
        <atom:icon.search class="size-5 shrink-0 text-zinc-400"/>

        <input
        type="text"
        role="combobox"
        aria-expanded="true"
        aria-controls="{{ $name }}-command-list"
        autocomplete="off"
        data-atom-command-search
        x-model="text"
        x-on:keydown.down.prevent="keyDown"
        x-on:keydown.up.prevent="keyUp"
        x-on:keydown.enter.prevent="enterKey"
        x-on:keydown.home.prevent="home"
        x-on:keydown.end.prevent="end"
        placeholder="{{ $placeholder }}"
        class="w-full border-0 bg-transparent py-4 text-base focus:outline-none focus:ring-0"/>
    </div>

    <div id="{{ $name }}-command-list" role="listbox" data-atom-command-list class="max-h-[60vh] overflow-y-auto p-2">
        {{ $slot }}

        <div data-atom-command-empty hidden class="px-3 py-8 text-center text-sm text-zinc-500">
            {{ $empty ?? t('No results.') }}
        </div>
    </div>
</dialog>
```

- [ ] **Step 4: Create `components/command/group.blade.php`**

```blade
@props([
    'heading' => null,
])

<div data-atom-command-group {{ $attributes->class('py-1 [&:not(:first-child)]:mt-1') }}>
    @if ($heading)
        <div data-atom-command-heading class="px-3 py-1 text-xs font-medium text-zinc-400">{{ $heading }}</div>
    @endif

    {{ $slot }}
</div>
```

- [ ] **Step 5: Create `components/command/item.blade.php`**

```blade
@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
])

@php
$el = $href ? 'a' : 'button';
$label = trim(strip_tags($slot->toHtml()));
$classes = Arr::toCssClasses([
    'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-start text-sm',
    'text-zinc-700 dark:text-zinc-200',
    'hover:bg-zinc-100 dark:hover:bg-zinc-800',
    'data-active:bg-zinc-100 dark:data-active:bg-zinc-800',
]);
@endphp

<{{ $el }} {{ $attributes->class($classes)->merge([
    'type' => $el === 'button' ? 'button' : false,
    'href' => $el === 'button' ? false : $href,
    'role' => 'option',
    'data-atom-command-item' => true,
    'data-label' => $label,
]) }}>
    @if ($icon)
        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4 shrink-0 text-zinc-400"/>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($shortcut)
        <kbd class="shrink-0 rounded border border-zinc-200 px-1.5 py-0.5 text-xs text-zinc-400 dark:border-zinc-700">{{ $shortcut }}</kbd>
    @endif
</{{ $el }}>
```

- [ ] **Step 6: Create `components/command/trigger.blade.php`**

```blade
@props([
    'name' => null,
])

@php
// Mirror the palette's own name default so a bare trigger pairs with a bare
// command inside the same Livewire component.
$name ??= (app('livewire')->current() ?: null)?->getName();
@endphp

<button
type="button"
x-data
x-on:click="atom.command(@js($name)).show()"
data-atom-command-trigger
{{ $attributes }}>
    {{ $slot }}
</button>
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `vendor/bin/pest --filter=command`
Expected: PASS (all cases in `CommandTest.php`).

- [ ] **Step 8: Commit**

```bash
git add components/command tests/Feature/CommandTest.php
git commit -m "feat(command): add command palette blade components"
```

---

### Task 2: PHP + JS entry points

**Files:**
- Modify: `src/Atom.php` (add `command()` after the `modal()` method, ~line 86)
- Modify: `src/Traits/AtomComponent.php` (add `command()` after the `modal()` helper, ~line 145)
- Create: `resources/js/helpers/command.js`
- Modify: `resources/js/helpers/index.js` (import + export `command`)
- Test: `tests/Unit/CommandEntryTest.php`

**Interfaces:**
- Consumes: the `atom-command-show` / `atom-command-close` event contract defined by Task 1's `index.blade.php`.
- Produces:
  - PHP: `atom()->command(string $name)` returns an object with `show(): void` and `close(): void` that dispatch `atom-command-show` / `atom-command-close` (with `name:`) on the current Livewire component.
  - PHP: `AtomComponent::command(?string $name = null)` delegates to `app('atom')->command(...)`, defaulting `$name` to the current component name.
  - JS: `atom.command(name)` returns `{ show(), close() }` dispatching the matching window `CustomEvent`s.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CommandEntryTest.php`:

```php
<?php

use Jiannius\Atom\Atom;

it('exposes a command() fluent with show and close on the Atom singleton', function () {
    $palette = app(Atom::class)->command('my-palette');

    expect($palette->name)->toBe('my-palette');
    expect(method_exists($palette, 'show'))->toBeTrue();
    expect(method_exists($palette, 'close'))->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/CommandEntryTest.php`
Expected: FAIL with "Call to undefined method Jiannius\Atom\Atom::command()".

- [ ] **Step 3: Add `command()` to `src/Atom.php`**

Insert directly after the `modal()` method (after its closing `}`, ~line 86):

```php
    /**
     * Trigger command palette from anywhere in the application
     */
    public function command($name)
    {
        return new class ($name) {
            public function __construct(public $name) {}

            public function show()
            {
                app('livewire')->current()->dispatch('atom-command-show', name: $this->name);
            }

            public function close()
            {
                app('livewire')->current()->dispatch('atom-command-close', name: $this->name);
            }
        };
    }
```

- [ ] **Step 4: Add `command()` to `src/Traits/AtomComponent.php`**

Insert directly after the `modal()` helper (after its closing `}`, ~line 145):

```php
    /**
     * Show command palette in front end
     */
    public function command($name = null)
    {
        return app('atom')->command($name ?? app('livewire')->current()->getName());
    }
```

- [ ] **Step 5: Create `resources/js/helpers/command.js`**

```js
export default (name = null) => {
    return {
        show () {
            return dispatchEvent(new CustomEvent('atom-command-show', { detail: { name } }))
        },

        close () {
            return dispatchEvent(new CustomEvent('atom-command-close', { detail: { name } }))
        },
    }
}
```

- [ ] **Step 6: Register the helper in `resources/js/helpers/index.js`**

Add the import alongside the others (after `import modal from './modal'`):

```js
import command from './command'
```

Add `command` to the exported object (after the `modal,` line):

```js
    command,
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/CommandEntryTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Atom.php src/Traits/AtomComponent.php resources/js/helpers/command.js resources/js/helpers/index.js tests/Unit/CommandEntryTest.php
git commit -m "feat(command): add PHP and JS palette entry points"
```

---

### Task 3: `command` Alpine factory

**Files:**
- Create: `resources/js/alpinejs/command.js`
- Modify: `resources/js/atom.js` (import + `Alpine.data('command', command)`)

**Interfaces:**
- Consumes: the DOM contract from Task 1 — `data-atom-command` (the `<dialog>` `$root`), `data-atom-command-search`, `data-atom-command-item` (+ `data-label`), `data-atom-command-group`, `data-atom-command-empty`. The blade calls `toggle`, `showCommand`, `closeCommand`, `backdropClick`, `filter`, `keyDown`, `keyUp`, `enterKey`, `home`, `end`, and binds `text` via `x-model`.
- Produces: the `command` Alpine data factory. No automated unit test (atom has no JS unit harness) — behavior is verified by the e2e suite in Task 5. This task's gate is a successful `npm run build`.

- [ ] **Step 1: Create `resources/js/alpinejs/command.js`**

```js
export default (config) => {
    return {
        open: false,
        text: '',
        activeIndex: -1,

        init () {
            // Re-filter (and re-pick the active item) whenever the query changes.
            this.$watch('text', () => this.filter())
        },

        // Bound to the keyboard shortcut in the blade.
        toggle () {
            this.open ? this.closeCommand() : this.showCommand()
        },

        showCommand (e = null) {
            if (e?.detail?.name && e.detail.name !== config.name) return
            if (this.$root.open) return // showModal() throws on an already-open dialog

            this.$root.showModal()
            this.$root.setAttribute('data-open', '')
            this.open = true
            this.text = ''

            this.$nextTick(() => {
                this.filter()
                this.$root.querySelector('[data-atom-command-search]')?.focus()
            })
        },

        closeCommand (e = null) {
            if (e?.detail?.name && e.detail.name !== config.name) return

            this.$root.close()
            this.$root.removeAttribute('data-open')
            this.open = false
            this.text = ''
        },

        backdropClick (e) {
            // Only a click on the dialog element itself is the backdrop; clicks
            // on inner content have a descendant target.
            if (e.target === this.$root) this.closeCommand()
        },

        items () {
            return Array.from(this.$root.querySelectorAll('[data-atom-command-item]'))
        },

        visibleItems () {
            return this.items().filter(el => !el.hidden)
        },

        // Show/hide items by label match, hide emptied groups, toggle the empty
        // state, and re-pick the active item. Uses the native `hidden` attribute
        // (UA display:none) so hiding works without Tailwind loaded.
        filter () {
            let text = (this.text || '').toLowerCase()

            this.items().forEach(el => {
                let label = (el.getAttribute('data-label') || '').toLowerCase()
                el.hidden = !!text && !label.includes(text)
            })

            this.$root.querySelectorAll('[data-atom-command-group]').forEach(group => {
                group.hidden = !group.querySelector('[data-atom-command-item]:not([hidden])')
            })

            let empty = this.$root.querySelector('[data-atom-command-empty]')
            if (empty) empty.hidden = this.visibleItems().length > 0

            this.resetActive()
        },

        // Virtual focus: mark the active item without moving DOM focus off the
        // search input, so the user can keep typing (mirrors select.js).
        setActive (index) {
            this.items().forEach(el => el.removeAttribute('data-active'))

            this.activeIndex = index
            let el = this.visibleItems()[index]
            let search = this.$root.querySelector('[data-atom-command-search]')

            if (!el) {
                search?.removeAttribute('aria-activedescendant')
                return
            }

            if (!el.id) el.id = `${config.name || 'command'}-item-${index}`
            el.setAttribute('data-active', '')
            search?.setAttribute('aria-activedescendant', el.id)
            el.scrollIntoView({ block: 'nearest' })
        },

        resetActive () {
            this.setActive(this.visibleItems().length ? 0 : -1)
        },

        move (dir) {
            let els = this.visibleItems()
            if (!els.length) return

            let next = this.activeIndex < 0
                ? (dir > 0 ? 0 : els.length - 1)
                : (this.activeIndex + dir + els.length) % els.length

            this.setActive(next)
        },

        keyDown () { this.move(1) },
        keyUp () { this.move(-1) },
        home () { this.setActive(0) },
        end () { this.setActive(this.visibleItems().length - 1) },

        enterKey () {
            let el = this.visibleItems()[this.activeIndex]
            if (el) el.click() // anchor navigates; button fires its wire:click / x-on:click
        },
    }
}
```

- [ ] **Step 2: Register the factory in `resources/js/atom.js`**

Add the import alongside the others (after `import accordion from './alpinejs/accordion'`):

```js
import command from './alpinejs/command'
```

Add the registration inside the Alpine init block (after `Alpine.data('accordion', accordion)`):

```js
    Alpine.data('command', command)
```

- [ ] **Step 3: Build to verify the factory compiles**

Run: `npm run build`
Expected: build succeeds, `dist/manifest.json` regenerated, no bundler errors.

- [ ] **Step 4: Commit (source + rebuilt dist)**

```bash
git add resources/js/alpinejs/command.js resources/js/atom.js dist
git commit -m "feat(command): add command Alpine factory and register it"
```

---

### Task 4: Docs demo (e2e fixture)

**Files:**
- Create: `resources/views/docs/demos/command.blade.php`
- Create: `resources/views/docs/demos/command/basic.blade.php`

**Interfaces:**
- Consumes: `<atom:command>`, `<atom:command.group>`, `<atom:command.item>`, `<atom:command.trigger>` from Task 1; the built factory from Task 3.
- Produces: a live demo at `/atom/docs/command` that is BOTH the rendered documentation AND the fixture the Task 5 e2e drives. It contains one palette named `command-demo` with grouped items (some `href`, some `x-on:click`), one item carrying a shortcut badge, plus a trigger button. Also serves as the source shown in the docs.

Note: `/atom/docs/command` already resolves via the docs fallback page; this task replaces the fallback with a real demo.

- [ ] **Step 1: Create `resources/views/docs/demos/command.blade.php`**

Descriptions must contain no `<`/`>` (TagCompiler constraint).

```blade
<atom:docs.example
title="Basic"
description="Press cmd+k (or click Open palette) to launch. Type to filter, arrow keys to navigate, Enter to select, Esc to close."
view="atom::docs.demos.command.basic"/>
```

- [ ] **Step 2: Create `resources/views/docs/demos/command/basic.blade.php`**

```blade
<div x-data>
    <atom:command.trigger name="command-demo">
        <atom:button>Open palette</atom:button>
    </atom:command.trigger>

    <atom:command name="command-demo">
        <atom:command.group heading="Pages">
            <atom:command.item href="/atom/docs" icon="search">Dashboard</atom:command.item>
            <atom:command.item href="/atom/docs/button">Buttons</atom:command.item>
            <atom:command.item href="/atom/docs/modal">Modals</atom:command.item>
        </atom:command.group>

        <atom:command.group heading="Actions">
            <atom:command.item icon="close" shortcut="⌘K" x-on:click="$dispatch('command-demo-picked', 'new')">New record</atom:command.item>
            <atom:command.item x-on:click="$dispatch('command-demo-picked', 'export')">Export</atom:command.item>
        </atom:command.group>
    </atom:command>

    <div class="mt-4" x-data="{ picked: '' }" x-on:command-demo-picked.window="picked = $event.detail">
        Picked: <span data-atom-command-result x-text="picked"></span>
    </div>
</div>
```

- [ ] **Step 3: Verify the demo page renders (no 500)**

Run (background): `vendor/bin/testbench serve`
Then: `curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/atom/docs/command`
Expected: `200`. (If it 500s, the most likely cause is a `<`/`>` inside a `docs.example` attribute — check Step 1.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/docs/demos/command.blade.php resources/views/docs/demos/command
git commit -m "docs(command): add command palette demo page"
```

---

### Task 5: Playwright e2e (integration gate)

**Files:**
- Create: `tests/e2e/command.spec.js`

**Interfaces:**
- Consumes: the live `/atom/docs/command` demo from Task 4 and the built factory from Task 3.
- Produces: automated verification of open (shortcut / event / trigger), search filtering + empty-group hiding, keyboard navigation, Enter activation, and Escape / backdrop close. This is the gate for the Task 3 factory — a red test here means the factory needs fixing (then rebuild `dist/`).

- [ ] **Step 1: Ensure assets are built**

Run: `npm run build`
Expected: success (Task 3 already built; re-run in case the factory was edited).

- [ ] **Step 2: Write the e2e spec**

Create `tests/e2e/command.spec.js`:

```js
import { test, expect } from '@playwright/test'

// Drives the live command-palette demo on /atom/docs/command.
const palette = (page) => page.locator('dialog[data-atom-command]')
const items = (page) => palette(page).locator('[data-atom-command-item]')

test('opens via the meta.k shortcut and closes on Escape', async ({ page }) => {
  await page.goto('/atom/docs/command')

  await expect(palette(page)).toBeHidden()

  await page.keyboard.press('Meta+k')
  await expect(palette(page)).toBeVisible()
  await expect(palette(page).locator('[data-atom-command-search]')).toBeFocused()

  await page.keyboard.press('Escape')
  await expect(palette(page)).toBeHidden()
})

test('opens via the trigger button', async ({ page }) => {
  await page.goto('/atom/docs/command')

  await page.getByRole('button', { name: 'Open palette' }).click()
  await expect(palette(page)).toBeVisible()
})

test('filters items as you type and hides empty groups', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  // "Buttons"/"Dashboard" are in the Pages group; "Export" in Actions.
  await page.keyboard.type('export')

  await expect(items(page).filter({ hasText: 'Export' })).toBeVisible()
  await expect(items(page).filter({ hasText: 'Dashboard' })).toBeHidden()

  // the Pages group has no visible items now → the group (and its heading) hides
  await expect(palette(page).locator('[data-atom-command-group]').first()).toBeHidden()
})

test('shows the empty state when nothing matches', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  await page.keyboard.type('zzzznomatch')

  await expect(palette(page).locator('[data-atom-command-empty]')).toBeVisible()
})

test('arrow keys navigate and Enter activates the active item', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  await page.keyboard.type('export')       // narrows to the single Export action
  await page.keyboard.press('ArrowDown')   // active index → 0 (Export)
  await page.keyboard.press('Enter')       // fires x-on:click → dispatch command-demo-picked

  await expect(page.locator('[data-atom-command-result]')).toHaveText('export')
})

test('closes on backdrop click', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')
  await expect(palette(page)).toBeVisible()

  // click the ::backdrop: viewport top-left, outside the top-anchored (mt-[10vh],
  // horizontally-centred max-w-xl) dialog box. A backdrop click targets the
  // <dialog> element itself, so backdropClick's `e.target === $root` check fires.
  await page.mouse.click(5, 5)
  await expect(palette(page)).toBeHidden()
})
```

- [ ] **Step 3: Run the e2e spec**

Run: `npx playwright test command.spec.js`
Expected: all 6 tests PASS. (Playwright's `webServer` boots `testbench serve` automatically.)

If any fail, fix `resources/js/alpinejs/command.js`, re-run `npm run build`, and re-run this step until green.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/command.spec.js dist
git commit -m "test(command): add command palette e2e coverage"
```

---

### Task 6: Ship

**Files:**
- No new files. Final verification + PR.

**Interfaces:**
- Consumes: everything from Tasks 1–5.
- Produces: a green full test run and a draft PR from `worktree-command-palette`.

- [ ] **Step 1: Full Pest run**

Run: `vendor/bin/pest`
Expected: all tests PASS (including the new `command` + `command-entry` suites, no regressions).

- [ ] **Step 2: Full e2e run**

Run: `npx playwright test`
Expected: all specs PASS (including `command.spec.js`).

- [ ] **Step 3: Confirm `dist/` is committed and clean**

Run: `git status --porcelain dist`
Expected: no output (dist already committed in Tasks 3 & 5; a dirty dist means a rebuild was not committed).

- [ ] **Step 4: Push and open a draft PR**

```bash
git push -u origin worktree-command-palette
gh pr create --draft --title "feat(command): command palette (⌘K)" --body "$(cat <<'EOF'
## Summary
Adds `<atom:command>` — a searchable ⌘K command-palette component (Flux-parity gap #4, after accordion, progress, input.otp).

- Native `<dialog>` + dedicated `command` Alpine factory (reuses modal open/close/backdrop + select search/keyboard-nav patterns).
- Static slot items: `<atom:command.item>` (polymorphic — `href` → anchor with wire:navigate support, else button with wire:click / x-on:click), optionally grouped under `<atom:command.group heading>`.
- Per-item icon + shortcut-hint badge; overridable empty state.
- Opens via keyboard shortcut (Alpine key-modifier syntax, default `meta.k`), `atom()->command($name)->show()` / `atom.command(name).show()`, or `<atom:command.trigger>`.
- Docs demo at `/atom/docs/command`.

## Tests
- Pest: blade render + PHP entry point.
- Playwright: open (shortcut/trigger), filter + empty-group hiding, empty state, keyboard nav + Enter, backdrop close.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 5: Report the PR URL and note the release step**

The human ships by squash-merging the PR, then tagging **v3.9.0** and pushing the tag (atom release flow). Do NOT tag from the plan.

---

## Notes for the implementer

- **Cross-platform shortcut:** the default `meta.k` maps to ⌘K on macOS and Win+K on Windows/Linux (Alpine's `.meta` = `metaKey`). This matches the repo's existing `modal.trigger` convention. Consumers wanting Ctrl+K set `shortcut="ctrl.k"`. Not fixing this per-OS is intentional (consistency with modal + no custom JS parser).
- **Why no orphan-listener cleanup:** the shortcut is an Alpine `x-on:...window` binding in blade, which Alpine tears down automatically on element removal / `wire:navigate`. That is why the factory has no manual `addEventListener`/`destroy` (contrast the tooltip regression, which used a hand-rolled global listener).
- **Item auto-close:** selecting an item does not explicitly close the palette in this version — an `href` item navigates away (palette gone with the page), and action items leave the palette open so a follow-up (toast/modal) reads naturally. If a consumer wants auto-close on an action item, they add `x-on:click="atom.command('name').close()"`. (Kept out of v1 scope — YAGNI.)
```
