# `<atom:context-menu>` — Right-click context menu

## Context

Next Flux-gap component after slider (v3.11.0) + rating (v3.12.0). atom has `<atom:dropdown>` (click-to-open,
floating-ui-positioned popover) but no **right-click context menu**. This adds `<atom:context-menu>`,
reusing the existing `<atom:menu>` / `<atom:menu.item>` surface and the `atom.floatingui` helper —
the only new behavior is *open on `contextmenu` at the cursor* instead of *open on trigger click*.
Branch `worktree-context-menu` (off `main`).

## Approach (settled)

- Reuse `<atom:menu popover>` + `<atom:menu.item>` for the items (decided in brainstorming) — no new
  item component.
- Positioning at the cursor via `atom.floatingui(virtualAnchor, popover, {placement:'bottom-start',
  offset:0, autoUpdate:false})`. `computePosition` accepts a **virtual element** (`{getBoundingClientRect}`)
  — a zero-size rect at the pointer `{clientX, clientY}`. Positioned once at the cursor.
- Close semantics mirror `dropdown.js`: item click (unless `locked`), native popover light-dismiss
  (Escape / outside-click), and `livewire:navigating`. (Scroll-close was dropped — a programmatic
  scroll-into-view spuriously closed the menu and it conflicts with `locked`; the pinned menu stays
  put on scroll and is dismissed by Escape / outside-click.)
- API uses a named `menu` slot so the component owns the `<atom:menu popover>` wrapper and the target
  is unambiguous (no "which child is the trigger" guessing):

```blade
<atom:context-menu>
    <div class="rounded border p-6">Right-click me</div>
    <x-slot:menu>
        <atom:menu.item icon="pencil">Edit</atom:menu.item>
        <atom:menu.item icon="trash" type="danger">Delete</atom:menu.item>
    </x-slot:menu>
</atom:context-menu>
```

## Files to create / modify

1. **`components/context-menu/index.blade.php`** — new. Props: `locked` (default false). Renders:
   ```blade
   <div x-data="contextMenu({ locked: … })" data-atom-context-menu>
       <div data-atom-context-menu-trigger>{{ $slot }}</div>
       <atom:menu popover>{{ $menu }}</atom:menu>
   </div>
   ```
2. **`resources/js/alpinejs/context-menu.js`** — new factory (mirrors `dropdown.js` shape):
   - `trigger` getter = `[data-atom-context-menu-trigger]`; `popover` getter = `[data-atom-menu]`.
   - `init()`: `contextmenu` listener on the trigger → `preventDefault()`, capture `x/y`, `show()`;
     `toggle` listener → dispatch `open`/`close` + clear `data-open` + `cleanup()`; item-click close
     when `!locked`; `livewire:navigating` → `hide()`.
   - `show()`: if already open, just re-run floating-ui with the new cursor anchor (don't re-call
     `showPopover()` — it throws on an open popover); else `showPopover()` + set `data-open`. Build the
     virtual anchor from `x/y` and call `atom.floatingui(anchor, popover, {placement:'bottom-start',
     offset:0, autoUpdate:false})`.
   - `destroy()`: `hide()` + `cleanup()` + remove the scroll/navigate listeners.
3. **`resources/js/atom.js`** — `import contextMenu from './alpinejs/context-menu'` +
   `Alpine.data('contextMenu', contextMenu)`.
4. **`resources/css/atom.css`** — append:
   ```css
   [data-atom-context-menu-trigger] { display: contents; }
   [data-atom-context-menu] [data-atom-menu] { position: fixed; inset: auto; margin: 0; }
   ```
   The reset is essential: UA `[popover]` styles (`inset:0; margin:auto`) center the menu and fight
   floating-ui's `left/top`. (dropdown leans on Tailwind for this; context-menu ships the reset so
   cursor positioning works regardless of the consumer's build.)
5. **`npm run build`** → commit `dist/`.
6. **Docs** — `resources/views/docs/demos/context-menu.blade.php` + `demos/context-menu/` partials:
   basic, locked. (No literal `<`/`>` inside `<atom:...>` attributes — TagCompiler 500s.)
7. **`README.md`** — add `<atom:context-menu>` near `<atom:dropdown>`.

## Verification

- **Pest** `tests/Feature/ContextMenuTest.php` (model `DropdownTest`): wrapper renders
  `data-atom-context-menu` + `x-data="contextMenu({`, `locked` forwarded, the `data-atom-context-menu-trigger`
  wrapper is present, the `menu` slot renders inside a `[data-atom-menu][popover]`, items render.
- **Playwright** `tests/e2e/context-menu.spec.js` (drive `/atom/docs/context-menu`, plain Alpine —
  assert visibility/aria/data-open only, NOT pixel position per the no-Tailwind-rig rule):
  right-click (`click({button:'right'})`) opens the menu (`data-open`, menu visible); clicking a
  `menuitem` closes it; `Escape` closes it and clears `data-open`; on the `locked` demo an item click
  keeps it open; the native browser menu is suppressed (`contextmenu` default-prevented).
- Worktree deps already installed (real `composer install` + `npm ci`).
- Commands: `./vendor/bin/pest --filter ContextMenu` then `npx playwright test context-menu`.

## Ship

Squash-per-task on `worktree-context-menu` → `gh pr create --draft`. Tag **v3.13.0** at merge.

## Out of scope (v1)

Nested submenus, programmatic open API, close-on-scroll, touch long-press synthesis (rely on the
native `contextmenu` event, which most touch platforms already synthesize).
