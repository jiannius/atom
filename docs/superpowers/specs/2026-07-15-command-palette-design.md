# `<atom:command>` — Command Palette

**Date:** 2026-07-15
**Target release:** v3.9.0 (next minor; fourth Flux-gap component after accordion, progress, input.otp)

## Purpose

A searchable, keyboard-navigable ⌘K command palette overlay. Fills the biggest remaining Flux-parity gap in the atom component library. Consumers declare a static set of items (navigation links or actions) in Blade; the palette filters them client-side as the user types, supports full keyboard navigation, and opens via a global keyboard shortcut, a PHP/JS event, or a trigger button.

## Scope decisions (locked during brainstorming)

1. **Item source:** Static slot only (Flux-parity). Items are declared as `<atom:command.item>` children and filtered client-side. No server-driven search in v1 (can be added later by mirroring select's `get-options` callback if a consumer needs it).
2. **Open triggers:** All three —
   - global keyboard shortcut (default `cmd+k` / `ctrl+k`, prop-overridable, disablable),
   - PHP `atom()->command($name)->show()` and JS `atom.command(name).show()` events,
   - `<atom:command.trigger>` button.
3. **Item actions:** Polymorphic. `href` renders an anchor (supports `wire:navigate`); without `href` the item is a `<button>` carrying `wire:click` / `x-on:click`.
4. **Features in v1:** groups with headings, per-item leading icon, per-item trailing shortcut-hint badge, empty state (overridable slot).
5. **Overlay implementation:** The command component owns its own `<dialog>` and a dedicated `command` Alpine factory that reuses modal.js's open/close/backdrop/escape patterns and select.js's search/keyboard-nav patterns. `<atom:modal>` is left untouched.

## Architecture

### Component files (`components/command/`)

| File | Tag | Responsibility |
|---|---|---|
| `index.blade.php` | `<atom:command>` | Palette root: `<dialog>` + `x-data="command({ name, shortcut })"`. Renders a search-input header, the items container (default slot), and an empty-state region (overridable `empty` slot). Listens for `atom-command-show` / `atom-command-close` window events. |
| `group.blade.php` | `<atom:command.group heading="…">` | Wraps items in a labelled section. Carries `data-atom-command-group` so the factory can hide the whole group (heading included) when all its items are filtered out. |
| `item.blade.php` | `<atom:command.item>` | Polymorphic item. With `href` → `<a>` (works with `wire:navigate`); otherwise `<button>` forwarding `wire:click` / `x-on:click` from the attribute bag. Carries `data-atom-command-item` and `data-label` (used for filtering + type-ahead). Optional leading `icon`, required label (slot/text), optional trailing `shortcut` badge. |
| `trigger.blade.php` | `<atom:command.trigger>` | Button that dispatches `atom-command-show` for the palette's `name`. |

### Props

**`<atom:command>`**
- `name` (string, nullable) — palette identifier; defaults to the current Livewire component name (same fallback as `<atom:modal>`). Event routing keys on this.
- `shortcut` (string|false, default `"cmd+k"`) — global open shortcut. `false` disables the global listener. Parsed as `mod+key` (see below).
- `placeholder` (string, default translated `"Search…"`) — search input placeholder.

**`<atom:command.group>`**
- `heading` (string, nullable) — section label.

**`<atom:command.item>`**
- `href` (string, nullable) — when present, renders an anchor.
- `icon` (string, nullable) — leading `<atom:icon>` glyph name.
- `shortcut` (string, nullable) — display-only trailing badge text (e.g. `⌘K`). Not wired to any handler; purely a hint.

### JS factory (`resources/js/alpinejs/command.js`)

State: `open` (bool), `text` (search string), `activeIndex` (int).

- **init():** register a window `keydown` listener for the configured shortcut (unless `shortcut === false`). Store the handler and remove it on Alpine `destroy` + on `livewire:navigating` (wire:navigate-safe teardown — the tooltip regression lesson: no orphaned global listeners).
- **open/close:** reuse modal.js patterns on the `<dialog>` element — `showModal()` to open, `close()` to close, backdrop-click closes, Escape closes. `atom-command-show` / `atom-command-close` window events call these when `detail.name` matches.
- **shortcut parsing:** split on `+`; `cmd`/`meta`/`ctrl`/`mod` → modifier (mac uses `metaKey`, others `ctrlKey`), last token → key. Match in the keydown handler; `preventDefault()` on match and toggle `open`.
- **search filter:** watch `text`; walk `[data-atom-command-item]`, show/hide by case-insensitive `data-label.includes(text)`. Hide any `[data-atom-command-group]` whose visible-item count is 0. Toggle the empty state when zero items are visible overall. Reset the active index after filtering.
- **keyboard nav:** up / down / home / end move the active item among *visible* items only, using virtual focus (`aria-activedescendant` on the search input + `data-active` on the item), lifted from select.js. Enter calls `.click()` on the active item's element (anchor navigates; button fires its handler). Escape closes.
- **on open:** focus the search input, clear `text`, reset active to the first visible item. **on close:** clear `text`.

Registered in `resources/js/atom.js` alongside the other `Alpine.data(...)` factories.

### PHP + JS entry points (mirror `modal`)

- `src/Atom.php` — add `command($name)` returning an anonymous fluent object with `show()` and `close()` that dispatch `atom-command-show` / `atom-command-close` Livewire events on the current component. Direct copy of the `modal()` shape.
- `resources/js/helpers/command.js` — `atom.command(name)` returning `{ show(), close() }` that dispatch window CustomEvents. Direct copy of `helpers/modal.js`.
- `src/Traits/AtomComponent.php` — add `command($name = null)` delegating to `app('atom')->command(...)`, mirroring the trait's `modal()` helper.

### Styling

Same strategy as `<atom:modal>`: Tailwind arbitrary-variant utility classes inline in the blade (`[&[data-open]]:…`, `[&::backdrop]:…`, scale/opacity transitions), no new CSS file. The palette is top-anchored (not vertically centered), has no close-X, and leads with the search header. Consuming apps rebuild Tailwind to pick up the new utility classes (the established humblebear rule — atom ships base CSS only).

## Data flow

1. Consumer places `<atom:command>` once (typically in a layout) with declared `<atom:command.group>` / `<atom:command.item>` children.
2. User presses `⌘K` (or a trigger/event fires) → factory opens the `<dialog>`, focuses search.
3. User types → factory filters items by `data-label`, hides empty groups, shows empty state if nothing matches.
4. User navigates with arrows and presses Enter (or clicks) → the active item's element is clicked → anchor navigates (`wire:navigate`) or button fires `wire:click` / `x-on:click`.
5. Escape / backdrop click / a matching `atom-command-close` event closes the palette and clears the search.

## Error / edge handling

- No items declared → empty state renders immediately.
- `shortcut="false"` → no global listener registered.
- All items filtered out → groups hidden, empty state shown, active index reset to none; Enter is a no-op.
- Palette name collision with modal events → separate event namespace (`atom-command-*`), so no interference.
- wire:navigate teardown → global keydown listener removed on `destroy` / `livewire:navigating` to avoid orphaned handlers.

## Testing

- **Pest (blade render):** component structure; item polymorphism (`href` → `<a>`, no `href` → `<button>`); groups render headings; icon and shortcut badge render; empty-state slot overridable; `name` fallback to current Livewire component.
- **Playwright e2e (behavior-only; atom's rig has no Tailwind, so essential display CSS is inlined in the test fixture):** open via shortcut; open via `atom-command-show` event and via trigger button; typing filters items and hides empty groups; arrow up/down + Enter activates an item; Escape closes; backdrop click closes; `shortcut="false"` does not open on ⌘K.

## Docs

Add a demo partial under `resources/views/docs/` and a `/atom/docs/command` page (a fallback page auto-exists, but a real demo showcases groups, icons, shortcut hints, and the open triggers). Remember: each docs example partial is both rendered live and shown as its own source.

## Ship checklist

1. `npm run build` and commit the regenerated `dist/`.
2. Open a draft PR from `worktree-command-palette`.
3. Squash-merge, then tag `v3.9.0` and push the tag (atom release flow).
