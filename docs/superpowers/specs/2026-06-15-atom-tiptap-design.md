# `<atom:tiptap>` — Design Spec

**Date:** 2026-06-15
**Status:** Approved design, pending implementation plan
**Supersedes:** `<atom:editor>` (Tiptap v2) — see Coexistence below
**Release:** **`v3.6.0` — minor bump** (current `v3.5.21`; TJ decision). Justified because the back-compat alias + dual-read cast keep existing consumers working with **no code change** — `<atom:editor>` still renders, legacy HTML still loads/renders, JSON storage is opt-in on re-save. The breaking-by-convention bits (engine v2→v3, new deps) are absorbed by the shim. Host apps still `composer update jiannius/atom`, `npm run build` (new Tailwind utilities), and review migration notes.

## Goal

Replace the existing `<atom:editor>` with a clean, modern rich-text component built on **Tiptap v3**. The rebuild pursues four drivers, all approved:

1. **Tiptap v3 engine** — floating-ui menus (matches atom's existing FloatingUI stack, drops tippy.js), fatter StarterKit (fewer separate extension deps), server-side static rendering.
2. **Cleaner consumer API** — hybrid preset + slot toolbar composition instead of today's fixed string array.
3. **Decoupled subsystems** — pluggable image persistence (kill the hard `env('FILESYSTEM_DISK')` coupling), chat + mention as opt-in modules.
4. **JSON storage** — store Tiptap JSON instead of serialized HTML, with a zero-touch migration path for existing consumer data.

This is a ground-up rewrite. The current editor's accumulated problems (malformed/dead code, no accessible names on toolbar buttons, config-cache-unsafe disk access, regex-based HTML rewriting, dead chat paste/drop handlers, hand-rolled mention positioning, zero test coverage) are fixed by design rather than patched.

## Component family

| Component | Purpose |
|---|---|
| `<atom:tiptap>` | The editor. Binds `wire:model` (value = Tiptap JSON). |
| `<atom:tiptap.content>` | SSR read-only render of stored content (JSON→HTML; also accepts legacy HTML). |
| `<atom:tiptap.chat>` | Chat composer — enter-to-send, attachments, file tray; dispatches `input` with `{ body, files }`. |
| `<atom:tiptap.mention>` | Opt-in mention dropdown (also reachable via the `mention` prop). |
| `<atom:tiptap.toolbar.*>` | Per-feature button partials for slot composition (`bold`, `heading`, `link`, `image`, `separator`, …). |
| `<atom:tiptap.menu.*>` | Bubble menus (link, image, table, youtube) built on floating-ui. |

`<atom:editor>` is retained only as a thin back-compat alias (see Coexistence).

## Engine (`resources/js/tiptap.js`, rewritten for v3)

**Standard extension set, always loaded** (lazy-imported as one chunk on first mount). The toolbar config controls only which *buttons* render — never which extensions load. This makes slot-composed custom buttons and raw `editor.chain()…` calls "just work" without a fragile toolbar→extension inference step. v3's consolidated StarterKit keeps the bundle small.

Extensions:
- **StarterKit v3** — now bundles Document, Paragraph, Text, Heading, Bold, Italic, Strike, **Code**, **Link**, **Underline**, Blockquote, BulletList, OrderedList, ListItem, CodeBlock, HardBreak, HorizontalRule, Dropcursor, Gapcursor, **UndoRedo**, **ListKeymap**, **TrailingNode**.
- **Added on top:** Color, TextStyle, custom **FontSize** (px + preset classes), Highlight (multicolor), custom **Image** (float/align/width attrs), **TableKit** (table/row/header/cell, resizable), TextAlign, Subscript, Superscript, Youtube, Placeholder.
- **Opt-in:** Mention (only when `mention` prop/slot present).

**Menus** — Tiptap v3 BubbleMenu/FloatingMenu use floating-ui. Replaces the v2 tippy.js usage; consolidates onto the same positioning lib atom already uses for dropdown/select.

**Alpine bridge** (`resources/js/alpinejs/tiptap.js`) — a `tiptap()` factory exposing `editor`, `commands()`, `isActive()`, `loading`, `editorContent` (JSON), plus chat helpers. The Tiptap instance is held in a closure (not a reactive property) to avoid Alpine's proxy "mismatched transaction" error. A `ts` ticker forces re-render on selection/transaction so `aria-pressed`/active styles track state. Bind via `x-modelable="editorContent"` (immune to Livewire 4's `wire:model` `.self`, per the LW4 gotcha).

## Toolbar API — hybrid preset + slot

Zero-config presets:
```blade
<atom:tiptap wire:model="body" toolbar="basic"/>
```
Presets: `full` (everything), `basic` (heading, text formatting, lists, link, image), `minimal` (text formatting + link), `none` (no toolbar).

Full composition via slot:
```blade
<atom:tiptap wire:model="body">
  <x-slot:toolbar>
    <atom:tiptap.heading/>
    <atom:tiptap.bold/> <atom:tiptap.italic/>
    <atom:tiptap.separator/>
    <atom:tiptap.link/> <atom:tiptap.image/>
    <button type="button" x-on:click="editor.chain().focus().toggleBold().run()">Custom</button>
  </x-slot:toolbar>
</atom:tiptap>
```

## Accessibility (by design)

- Toolbar container `role="toolbar"`.
- Every button is a real `<button type="button">` with `aria-label` derived from its label (fixes today's icon-only / no-accessible-name gap at the source — the label currently only feeds a tooltip).
- `aria-pressed` bound to `editor.isActive(mark|node)` so screen readers announce active formatting.
- Bubble menus positioned by floating-ui; focus management handled per menu.

## Storage & cast (`AsTiptapContent`)

Column holds **Tiptap JSON** (text/json column).

**`set($value)`** — value is JSON from the editor:
1. Decode to PHP array.
2. Walk image nodes (`type === 'image'`) — no regex. For each `src` pointing at a Livewire temporary upload:
   - If the consumer's Livewire component defines `tiptapStoreImage($file): string`, call it and use the returned URL.
   - Else default: resize (≤1000px, q80) and persist to `config('atom.editor.disk')` (config-cache-safe — replaces `env('FILESYSTEM_DISK')`), under `<folder>/editor/`; rewrite `src`.
3. Store JSON.

**`get($value)`** — dual-format read:
- JSON → decode (return array/JSON for the editor + `.content`).
- Legacy serialized-HTML (old `AsEditorContent` rows) or raw HTML → pass through as HTML. The v3 editor and `tiptap-php` both load HTML losslessly.

This means **columns can hold mixed formats during migration** and everything still renders.

## Read-only rendering (`<atom:tiptap.content>`)

Server-side via `ueberdosis/tiptap-php`:
```blade
{!! (new Tiptap\Editor(['extensions' => [...atomCustomExtensions]]))
      ->setContent($value)   // JSON array OR legacy HTML string
      ->getHTML() !!}
```
Real SSR HTML — works for SEO, email, and no-JS. Accepts both stored formats. `editor.css` equivalent (`tiptap.css`) ships the prose / mention / table styling and is served via `atom()->asset()` as today.

## Migration of existing data

Two paths, consumer chooses:
- **Lazy / zero-touch (default)** — do nothing. Legacy HTML rows keep rendering via SSR; a row converts to JSON only when a user re-saves it through the editor. No deploy step.
- **One-shot** — optional `php artisan atom:tiptap-migrate` walks `AsTiptapContent` (and legacy `AsEditorContent`) columns, converts HTML→JSON via `tiptap-php` `setContent($html)->getJSON()`, backfills. For consumers wanting uniform JSON immediately. Feasible only because conversion runs server-side in PHP.

`atom:purge-editor-images` (renamed/retained as `atom:purge-tiptap-images`) walks JSON image nodes instead of regex-matching HTML.

## Image upload

Default flow: toolbar image button → Livewire temp upload → cast persists on save (see `set()` above). Decoupling:
- Disk is `config('atom.editor.disk')` (config value, never `env()` directly).
- Consumer override: define `tiptapStoreImage(TemporaryUploadedFile $file): string` on the Livewire component to fully control persistence (S3-signed, CDN, custom path). Same contract style as the table batch's `tableQuery()` consumer method. atom calls it if present, else uses the default.

## Chat (`<atom:tiptap.chat>`)

Own sub-component (not a mode branch). No toolbar by default; submit button, paperclip multi-attach, file tray (thumbnail/icon + name + remove). Enter = send, Shift+Enter = newline. Dispatches `input` with `{ body, files }`. Shares the `tiptap()` factory + extension set. Fixes today's dead/broken paste & drop (currently unwired and gated on a non-existent `this.chat`) — paste/drop file handling is wired and functional, or explicitly omitted if descoped in the plan.

## Mention

Opt-in via `mention` prop or `<atom:tiptap.mention>` slot:
- `mention="searchUsers"` — live `$wire` callback (debounced).
- `:mention="['Alice','Bob']"` or option objects — static.
- Item slot for custom rendering.
Rebuilt on Tiptap v3 suggestion + floating-ui positioning (replaces the hand-rolled `getBoundingClientRect` above-anchor math, which breaks near viewport edges). Renders `<span class="mention">`.

## Coexistence: `<atom:editor>` → alias

Tiptap v2 and v3 are the same npm package names at different majors and **cannot be bundled together**. Therefore the v2 `<atom:editor>` cannot coexist with the v3 engine.

Decision: **`<atom:editor>` becomes a thin back-compat shim** that translates its legacy props (`toolbar` array, `variant`, `mention`, etc.) onto `<atom:tiptap>` and renders it. Existing consumers keep working with no code change; new code uses `<atom:tiptap>`. The old editor's blades and JS are deleted — only the prop-translation shim remains. Behavioral parity for the shim is part of acceptance.

## Dependencies (require approval — per CLAUDE.md)

- **composer:** add `ueberdosis/tiptap-php` (SSR render, server-side migration, JSON image-node walking). *Approved during design.*
- **npm:** bump `@tiptap/*` and `@tiptap/extension-*` from `^2` to `^3`; drop extensions now folded into StarterKit/kits (link, underline, history→undoRedo, horizontal-rule, table-* → TableKit); add `@tiptap/extension-bubble-menu`/`floating-menu` v3.
- **PHP-side custom extensions** — `src/Tiptap/Extensions/*` (Image float/align/width, FontSize, Youtube, Mention) so `tiptap-php` `getHTML`/`getJSON` round-trip atom's custom attributes without dropping them.

## File map (proposed)

```
components/tiptap/
  index.blade.php            # the editor
  content.blade.php          # SSR read-only render
  chat.blade.php             # chat composer
  mention.blade.php          # mention dropdown
  toolbar/                   # button partials (bold, heading, link, image, separator, …)
  menu/                      # bubble menus (link, image, table, youtube)
components/editor/
  index.blade.php            # SHIM → <atom:tiptap> (legacy prop translation)
resources/js/
  tiptap.js                  # v3 engine (rewritten)
  alpinejs/tiptap.js         # Alpine factory
resources/css/
  tiptap.css                 # prose / mention / table styling
src/Casts/AsTiptapContent.php
src/Commands/PurgeTiptapImages.php
src/Commands/MigrateTiptapContent.php   # atom:tiptap-migrate
src/Tiptap/Extensions/*.php             # PHP-side custom node/mark defs
```

## Testing

- **Pest** — `<atom:tiptap>` / `.content` / `.chat` render (presets + slot toolbar), a11y markup (role=toolbar, aria-label/aria-pressed wiring), `AsTiptapContent` get/set (JSON round-trip, dual-read of legacy HTML, image-node persistence via fake disk, `tiptapStoreImage` override), `atom:tiptap-migrate` HTML→JSON, `<atom:editor>` shim parity, SSR render via tiptap-php with custom extensions.
- **Playwright** — limited (no Livewire server in the rig): editor mounts, toolbar buttons toggle marks, bubble menu appears on selection. Live wire:model / upload / mention runtime → verify on humblebear.
- **`npm run build`** + commit `dist/` (JS + CSS rewritten).

## Release

- **`v3.6.0` — minor bump** (TJ decision). Additive-by-default: the alias shim + dual-read cast mean existing consumers upgrade with no code change. New surface is `<atom:tiptap>`; `<atom:editor>` keeps working.
- Host-app upgrade notes (engine swap is transparent via shim, `<atom:editor>`→`<atom:tiptap>` for new code, JSON storage opt-in, lazy vs one-shot data migration, `composer update`, `npm run build` for new Tailwind utilities, optional `tiptapStoreImage()` override).
- Boost guidelines updated (the AI channel to consumers) so generated host-app code uses `<atom:tiptap>`.

## Out of scope / flagged for later

- Collaboration, comments, AI (v3 cloud features) — not now.
- Character count, markdown export — available in v3 but not surfaced unless requested.
- Rich code-block UX (syntax highlight) — engine supports; no toolbar button unless requested.

## Open risks

- **Custom-extension parity** between JS (editor) and PHP (`tiptap-php` SSR): the two must agree on attribute names/HTML output or round-trips drift. Mitigate with shared fixtures asserting JS-render == PHP-render for each custom node.
- **Shim parity**: legacy `<atom:editor>` consumers must see identical behavior; needs a parity test pass against the old prop surface.
- **Bundle size**: confirm v3 set isn't larger than v2 after StarterKit consolidation; measure post-build.
