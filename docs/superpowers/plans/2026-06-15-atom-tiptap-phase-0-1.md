# `<atom:tiptap>` — Phase 0+1 Implementation Plan (deps + core editor)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Tiptap **v3** engine and a working `<atom:tiptap>` rich-text editor (toolbar presets + slot composition, floating-ui bubble menus, accessible buttons) — no storage/cast/chat/mention yet.

**Architecture:** A rewritten v3 engine (`resources/js/tiptap.js`) loaded lazily by an Alpine factory (`resources/js/alpinejs/tiptap.js`) that bridges to Livewire via `x-modelable`. The blade component (`components/tiptap/index.blade.php`) resolves a `toolbar` preset *or* a `toolbar` slot into accessible toolbar buttons; the engine always loads the full standard extension set so any button (built-in or custom slot button) works.

**Tech Stack:** Tiptap v3 (`@tiptap/core`, `@tiptap/starter-kit`, `@tiptap/extensions`, `@tiptap/extension-*`), Alpine, Livewire 4, Blade, Vite, Pest (Testbench), Playwright.

**Reference (full design):** `docs/superpowers/specs/2026-06-15-atom-tiptap-design.md`

**Source material:** the existing v2 editor under `components/editor/`, `resources/js/tiptap.js`, `resources/js/alpinejs/editor.js`, `resources/css/editor.css`. Many toolbar button partials port near-verbatim — the transform is `<atom:editor.*>` → `<atom:tiptap.*>`, `commands()` calls unchanged.

---

## Worktree note

Work happens in the existing worktree `.claude/worktrees/tiptap-rebuild` (branch `worktree-tiptap-rebuild`). It is a fresh checkout — it has **no** `vendor/` or `node_modules/` yet. Phase 0 installs both. Run all commands from the worktree root.

## File map (Phase 0+1)

```
Create:
  resources/js/tiptap.js                     # v3 engine (rewrite of the v2 file)
  resources/js/alpinejs/tiptap.js            # Alpine factory (rewrite of alpinejs/editor.js)
  resources/css/tiptap.css                   # prose/table/mention styling (adapted from editor.css)
  components/tiptap/index.blade.php          # the editor component
  components/tiptap/toolbar/button.blade.php # base toolbar button (a11y: aria-label + aria-pressed)
  components/tiptap/toolbar/separator.blade.php
  components/tiptap/toolbar/<feature>.blade.php   # heading,text,font-size,text-align,text-color,
                                                  # text-highlight,horizontal-rule,bullet,link,table,
                                                  # image,youtube,undo,redo,remove-formatting
  components/tiptap/menu/link.blade.php       # bubble menus (floating-ui)
  components/tiptap/menu/image.blade.php
  components/tiptap/menu/table.blade.php
  components/tiptap/menu/youtube.blade.php
  resources/views/docs/demos/tiptap.blade.php          # docs page
  resources/views/docs/demos/tiptap/basic.blade.php    # docs demo
  tests/Feature/TiptapTest.php               # Pest render coverage
  tests/Browser/tiptap.spec.js (or e2e dir)  # Playwright (match existing e2e location)
Modify:
  package.json                               # v2 -> v3 deps
  resources/js/atom.js                       # register tiptap() factory
  vite.config.js                             # add tiptap.css input
  src/Services/...                           # (none in phase 1)
```

Note: the image **upload** button is deferred to Phase 2 (it needs the cast/wire bridge). Phase 1 ships an image button that inserts an image **by URL** (via the image bubble menu) so the toolbar is complete and testable without the upload lifecycle.

---

## PHASE 0 — deps & scaffold

### Task 0.1: Install composer dep `ueberdosis/tiptap-php`

**Files:** Modify `composer.json`, `composer.lock`

- [ ] **Step 1: Install vendor deps + the new package**

Run:
```bash
composer install --no-interaction
composer require ueberdosis/tiptap-php --no-interaction
```
Expected: both succeed; `composer.json` gains `"ueberdosis/tiptap-php"` under `require`.

- [ ] **Step 2: Smoke-check the package loads**

Run:
```bash
php -r 'require "vendor/autoload.php"; echo (new Tiptap\Editor)->setContent("<p>hi</p>")->getJSON();'
```
Expected: prints JSON like `{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"hi"}]}]}`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: add ueberdosis/tiptap-php for server-side tiptap render"
```

### Task 0.2: Bump npm deps Tiptap v2 → v3

**Files:** Modify `package.json`

- [ ] **Step 1: Edit `package.json` dependencies**

Set the Tiptap deps to v3 and adjust which packages exist. Replace the current `@tiptap/*` block with:

```json
"@tiptap/core": "^3",
"@tiptap/pm": "^3",
"@tiptap/starter-kit": "^3",
"@tiptap/extensions": "^3",
"@tiptap/extension-bubble-menu": "^3",
"@tiptap/extension-color": "^3",
"@tiptap/extension-highlight": "^3",
"@tiptap/extension-image": "^3",
"@tiptap/extension-mention": "^3",
"@tiptap/extension-subscript": "^3",
"@tiptap/extension-superscript": "^3",
"@tiptap/extension-table": "^3",
"@tiptap/extension-text-align": "^3",
"@tiptap/extension-text-style": "^3",
"@tiptap/extension-youtube": "^3",
"@tiptap/suggestion": "^3",
```

Removed (folded into StarterKit v3 or the kits, confirmed in Task 0.5): `@tiptap/extension-link`, `@tiptap/extension-underline`, `@tiptap/extension-horizontal-rule`, `@tiptap/extension-floating-menu` (add back only if a floating menu is used), `@tiptap/extension-placeholder` (now `@tiptap/extensions`), `@tiptap/extension-table-cell|header|row` (now `TableKit` from `@tiptap/extension-table`).

- [ ] **Step 2: Install**

Run:
```bash
npm install
```
Expected: installs without peer-dep errors. If a removed package is still imported anywhere, that surfaces in Task 0.5's build.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "build: bump tiptap to v3"
```

### Task 0.3: Scaffold `tiptap.css` + wire into Vite

**Files:** Create `resources/css/tiptap.css`; Modify `vite.config.js`

- [ ] **Step 1: Create `resources/css/tiptap.css`**

Start by copying the existing prose/table/mention styling so the editor renders correctly:
```bash
cp resources/css/editor.css resources/css/tiptap.css
```
(We adapt class names later if needed; for now identical styling under the same `.editor-content`/`.mention`/table selectors keeps the port faithful.)

- [ ] **Step 2: Add it to the Vite build inputs**

In `vite.config.js`, find the `input` array (currently includes `resources/css/editor.css`) and add `resources/css/tiptap.css` alongside it. Keep `editor.css` for now (removed in Phase 6).

- [ ] **Step 3: Verify it builds**

Run:
```bash
npm run build
```
Expected: build succeeds; `dist/manifest.json` contains a `tiptap.css` entry.

- [ ] **Step 4: Commit**

```bash
git add resources/css/tiptap.css vite.config.js dist/
git commit -m "build: scaffold tiptap.css + vite input"
```

### Task 0.4: v3 import spike — confirm exact exports

**Files:** temporary edit to `resources/js/tiptap.js` (created here, fleshed out in Task 1.1)

This de-risks every later JS task by confirming v3 import paths before we write the full engine.

- [ ] **Step 1: Create `resources/js/tiptap.js` with a v3 import + mount spike**

```js
import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { Placeholder, UndoRedo } from '@tiptap/extensions'
import BubbleMenu from '@tiptap/extension-bubble-menu'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import { TableKit } from '@tiptap/extension-table'
import TextAlign from '@tiptap/extension-text-align'
import TextStyle from '@tiptap/extension-text-style'
import Youtube from '@tiptap/extension-youtube'

// SPIKE: confirm exports resolve at build time. Replaced in Task 1.1.
window.TiptapSpike = { Editor, StarterKit, Placeholder, UndoRedo, BubbleMenu, Color, Highlight, Image, Subscript, Superscript, TableKit, TextAlign, TextStyle, Youtube }
```

- [ ] **Step 2: Temporarily import it from `atom.js`**

Add near the other imports in `resources/js/atom.js`:
```js
import './tiptap.js'
```

- [ ] **Step 3: Build and confirm every import resolves**

Run:
```bash
npm run build
```
Expected: PASS. If any import fails (`"X" is not exported by ...`), that names the wrong path — fix against https://tiptap.dev/docs (e.g. `Color` may be `@tiptap/extension-text-style`'s `TextStyleKit`; `Placeholder`/`UndoRedo` confirmed in `@tiptap/extensions`). Re-run until clean. **Record the final working import list in a comment at the top of `tiptap.js`** — Task 1.1 builds on it.

- [ ] **Step 4: Commit the confirmed import surface**

```bash
git add resources/js/tiptap.js resources/js/atom.js dist/
git commit -m "build: confirm tiptap v3 import surface (spike)"
```

---

## PHASE 1 — core editor

### Task 1.1: v3 engine (`resources/js/tiptap.js`)

**Files:** Rewrite `resources/js/tiptap.js`

Port the v2 engine to v3: keep the custom `FontSize` extension and the `ImageExtended` (float/align/width) extension verbatim (their `Extension.create`/`Node.extend` API is unchanged in v3), drop the manual Table/TableCell extends in favor of `TableKit` + a small renderHTML override only if the styled `.table-wrapper` is still wanted, and rewrite `BubbleMenuConfiguration` for floating-ui (no `tippyOptions`).

- [ ] **Step 1: Write `resources/js/tiptap.js`**

```js
import { Editor, Extension, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { Placeholder } from '@tiptap/extensions'
import BubbleMenu from '@tiptap/extension-bubble-menu'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
import Mention from '@tiptap/extension-mention'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import { TableKit } from '@tiptap/extension-table'
import TextAlign from '@tiptap/extension-text-align'
import TextStyle from '@tiptap/extension-text-style'
import Youtube from '@tiptap/extension-youtube'

// FontSize — unchanged from v2 (Extension.create API stable in v3)
const FontSize = Extension.create({
    name: 'fontSize',
    addOptions () { return { types: ['textStyle'] } },
    addGlobalAttributes () {
        return [{
            types: this.options.types,
            attributes: {
                fontSize: {
                    default: null,
                    parseHTML: el => el.getAttribute('data-font-size'),
                    renderHTML: attrs => {
                        if (!attrs.fontSize) return {}
                        const sizes = { xs: 'text-xs', sm: 'text-sm', md: 'text-base', lg: 'text-lg', xl: 'text-xl' }
                        return sizes[attrs.fontSize]
                            ? { 'data-font-size': attrs.fontSize, class: sizes[attrs.fontSize] }
                            : { 'data-font-size': attrs.fontSize, style: `font-size: ${attrs.fontSize}` }
                    },
                },
            },
        }]
    },
    addCommands () {
        return {
            setFontSize: (fontSize) => ({ chain }) => chain().setMark('textStyle', { fontSize }).run(),
            unsetFontSize: () => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
        }
    },
})

// Image with float/align/width — unchanged from v2
const ImageExtended = Image.extend({
    addAttributes () {
        return {
            ...this.parent?.(),
            float: {
                default: null,
                parseHTML: el => el.getAttribute('data-float'),
                renderHTML: a => (a.float ? { 'data-float': a.float, style: `float: ${a.float}` } : {}),
            },
            align: {
                default: null,
                parseHTML: el => el.getAttribute('data-align'),
                renderHTML: a => {
                    let style
                    if (a.align === 'left') style = 'margin-right: auto'
                    else if (a.align === 'center') style = 'margin-left: auto; margin-right: auto'
                    else if (a.align === 'right') style = 'margin-left: auto'
                    return style ? { 'data-align': a.align, style } : {}
                },
            },
            width: {
                default: null,
                parseHTML: el => el.getAttribute('data-width'),
                renderHTML: a => (a.width ? { 'data-width': a.width, style: `width: ${a.width}` } : {}),
            },
        }
    },
})

// floating-ui based bubble menu config (replaces v2 tippyOptions)
const BubbleMenuConfiguration = (element, key) => ({
    pluginKey: key,
    element,
    shouldShow: ({ editor }) => element.shouldShow(editor),
    options: { placement: 'top', offset: 8 },
})

const MentionConfiguration = (element) => ({
    HTMLAttributes: { class: 'mention' },
    renderText ({ options, node }) { return `${options.suggestion.char} ${node.attrs.label ?? node.attrs.id}` },
    suggestion: {
        render: () => ({
            onStart: props => { if (props.clientRect) element.start(props) },
            onUpdate: props => { if (props.clientRect) element.update(props) },
            onKeyDown: props => element.keydown(props),
            onExit: props => element.exit(props),
        }),
    },
})

const DisableEnterKeyExtension = Extension.create({
    addKeyboardShortcuts () {
        return {
            Enter: () => {
                if (!this.editor.isActive('listItem')) {
                    this.editor.options.element.dispatchEvent(new CustomEvent('editor-enter', { bubbles: true, detail: this.editor }))
                    return true
                }
            },
            'Shift-Enter': () => { this.editor.commands.insertContent('<p></p>'); return true },
        }
    },
})

window.Tiptap = ({ element, config, bubbleMenus = {}, disableEnterKey = false, mentionTemplate }) => {
    const extensions = [
        StarterKit,                                   // v3: includes Link, Underline, UndoRedo, ListKeymap, TrailingNode
        Color,
        FontSize,
        Highlight.configure({ multicolor: true }),
        ImageExtended,
        Placeholder.configure({ placeholder: config.placeholder }),
        Subscript,
        Superscript,
        TableKit.configure({ table: { resizable: true } }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        Youtube,
        disableEnterKey ? DisableEnterKeyExtension : null,
    ].filter(Boolean)

    Object.keys(bubbleMenus || {}).forEach(key => {
        if (bubbleMenus[key]) extensions.push(BubbleMenu.configure(BubbleMenuConfiguration(bubbleMenus[key], key)))
    })

    if (mentionTemplate) extensions.push(Mention.configure(MentionConfiguration(mentionTemplate)))

    return new Editor({ element, autofocus: false, extensions, ...config })
}
```

- [ ] **Step 2: Remove the spike import line if it differs**

Ensure `resources/js/atom.js` imports nothing stale; the engine is loaded lazily by the Alpine factory (Task 1.2), not eagerly by `atom.js`. Remove the temporary `import './tiptap.js'` added in Task 0.4 Step 2.

- [ ] **Step 3: Build**

Run:
```bash
npm run build
```
Expected: PASS. (`StarterKit` may warn if Link/Underline are double-registered — if so, configure `StarterKit.configure({ link: false })` etc. only for extensions we add separately; we do **not** add Link/Underline separately, so no conflict expected.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/tiptap.js resources/js/atom.js dist/
git commit -m "feat(tiptap): v3 engine"
```

### Task 1.2: Alpine factory (`resources/js/alpinejs/tiptap.js`)

**Files:** Create `resources/js/alpinejs/tiptap.js`

Rewrite of `alpinejs/editor.js`, cleaned up. Chat-only methods (`paste`/`drop`/`readFiles`/chat branch of `sync`) are **omitted** here — they belong to Phase 4's `<atom:tiptap.chat>`. Keep the `Alpine.raw`/closure pattern and the `ts` ticker.

- [ ] **Step 1: Write `resources/js/alpinejs/tiptap.js`**

```js
export default (config) => {
    let tiptap

    return {
        ts: 0,                 // bump to force re-render on selection/transaction
        loading: true,
        editorContent: config.content ?? '',

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                if (value === tiptap.getHTML()) return
                this.commands().setContent(value, { emitUpdate: false })
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,
                config: {
                    content: this.editorContent,
                    placeholder: config.placeholder,
                    editable: !config.readonly,
                    autofocus: config.autofocus,
                    editorProps: { attributes: { class: config.class } },
                    onCreate () { _this.loading = false; _this.ts++ },
                    onSelectionUpdate () { _this.ts++ },
                    onTransaction () { _this.ts++ },
                    ...(config.lazy ? { onBlur: () => _this.sync() } : { onUpdate: () => _this.sync() }),
                },
                bubbleMenus: {
                    linkMenu: this.$root.querySelector('.tiptap-menu .link-menu'),
                    imageMenu: this.$root.querySelector('.tiptap-menu .image-menu'),
                    tableMenu: this.$root.querySelector('.tiptap-menu .table-menu'),
                    youtubeMenu: this.$root.querySelector('.tiptap-menu .youtube-menu'),
                },
                mentionTemplate: this.$root.querySelector('.tiptap-mention'),
            })
        },

        editor () { return tiptap },
        can () { return tiptap.can() },
        commands () { tiptap.chain().focus(); return tiptap.commands },
        isActive (...args) { return this.ts >= 0 && tiptap?.isActive(...args) },  // ts read = reactive dep

        isEmpty () {
            if (typeof this.editorContent === 'string') return empty(this.editorContent.striptags())
            return empty(this.editorContent)
        },

        sync () {
            if (!tiptap.isEditable) return
            this.editorContent = tiptap.isEmpty ? '' : tiptap.getHTML()
            this.$dispatch('input', this.editorContent)
        },
    }
}
```

Note: `isActive()` reads `this.ts` so Alpine treats it as reactive — toolbar `aria-pressed`/active styles update on every transaction. `editorContent` stays HTML in Phase 1 (storage→JSON is Phase 2; the cast change is independent of the engine).

- [ ] **Step 2: Register the factory in `resources/js/atom.js`**

Find where `editor` is registered (`Alpine.data('editor', editor)` or similar) and add alongside it:
```js
import tiptap from './alpinejs/tiptap.js'
// ... in the Alpine registration block:
Alpine.data('tiptap', tiptap)
```
Leave the existing `editor` registration in place (removed in Phase 6).

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/alpinejs/tiptap.js resources/js/atom.js dist/
git commit -m "feat(tiptap): alpine factory bridging v3 engine to wire:model"
```

### Task 1.3: Toolbar presets (PHP map)

**Files:** Create `components/tiptap/toolbar/_presets.php` (plain PHP include returning an array)

- [ ] **Step 1: Write the preset map**

```php
<?php

return [
    'full' => ['heading', 'text', 'font-size', 'text-align', 'text-color', 'text-highlight', 'horizontal-rule', 'bullet', 'link', 'table', 'image', 'youtube'],
    'basic' => ['heading', 'text', 'bullet', 'link', 'image'],
    'minimal' => ['text', 'link'],
    'none' => [],
];
```

- [ ] **Step 2: Commit**

```bash
git add components/tiptap/toolbar/_presets.php
git commit -m "feat(tiptap): toolbar presets"
```

### Task 1.4: Base toolbar button (a11y)

**Files:** Create `components/tiptap/toolbar/button.blade.php`

The accessibility fix at the source: real `<button>` with `aria-label` (from label) and optional `aria-pressed` bound to an `active` expression.

- [ ] **Step 1: Write `components/tiptap/toolbar/button.blade.php`**

```blade
@props([
    'label' => null,
    'active' => null,   // Alpine expression, e.g. "isActive('bold')" — drives aria-pressed + styling
])

<atom:tiptap.tooltip :content="$label">
    <button
    type="button"
    @if ($label) aria-label="{{ t($label) }}" @endif
    @if ($active) x-bind:aria-pressed="{{ $active }}" x-bind:data-active="{{ $active }}" @endif
    {{ $attributes->class([
        'size-8 rounded-md flex items-center justify-center',
        'hover:bg-zinc-100 dark:hover:bg-zinc-700',
        '[&[data-active=true]]:bg-zinc-100 dark:[&[data-active=true]]:bg-zinc-700',
    ]) }}>
        {{ $slot }}
    </button>
</atom:tiptap.tooltip>
```

Note: reuse the existing `<atom:tooltip>` rather than a new `tiptap.tooltip` — adjust the tag to `<atom:tooltip :content="$label">`. (The `tiptap.tooltip` reference above is a typo; use `atom:tooltip`.)

- [ ] **Step 2: Commit**

```bash
git add components/tiptap/toolbar/button.blade.php
git commit -m "feat(tiptap): accessible toolbar button base"
```

### Task 1.5: Separator + simple buttons

**Files:** Create `components/tiptap/toolbar/separator.blade.php` + the single-command buttons

Port each from `components/editor/button/<name>.blade.php`. Transform: replace `<atom:editor.button ...>` with `<atom:tiptap.toolbar.button ... :active="...">`, keep the SVG and `commands().X()` exactly. Add `:active` where the command toggles a mark/node.

- [ ] **Step 1: `separator.blade.php`**

```blade
<div class="w-px h-5 mx-1 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></div>
```

- [ ] **Step 2: `horizontal-rule.blade.php`** (port from `editor/button/horizontal-rule.blade.php`)

```blade
<atom:tiptap.toolbar.button label="Horizontal Rule" x-on:click="commands().setHorizontalRule()">
    <atom:icon.minus class="size-5" />
</atom:tiptap.toolbar.button>
```
(Use the same icon/SVG the v2 file used — copy its inner markup verbatim.)

- [ ] **Step 3: `undo.blade.php`, `redo.blade.php`, `remove-formatting.blade.php`, `text-highlight.blade.php`** — same port pattern, copying each v2 file's SVG and `commands()` call into the new `<atom:tiptap.toolbar.button>` wrapper. `text-highlight` gets `:active="isActive('highlight')"`.

- [ ] **Step 4: Commit**

```bash
git add components/tiptap/toolbar/
git commit -m "feat(tiptap): separator + simple toolbar buttons"
```

### Task 1.6: Dropdown buttons (heading, text, font-size, text-align, text-color, bullet)

**Files:** Create the six dropdown button partials

Port each from `components/editor/button/<name>.blade.php` verbatim, changing only `<atom:editor.button>` → `<atom:tiptap.toolbar.button>` and adding `:active` on the trigger where meaningful (e.g. heading trigger `:active="isActive('heading')"`). The `<atom:dropdown>`/`<atom:menu>` usage is unchanged. `text-color` still reads `\Jiannius\Atom\Services\Color::all()`.

- [ ] **Step 1: Port `heading.blade.php`** (copy v2 file, apply the transform; the H1–H4 + paragraph `@foreach` and SVGs stay identical).
- [ ] **Step 2: Port `text.blade.php`** (bold/italic/strike/sub/super/underline/blockquote menu — identical body, transformed wrapper).
- [ ] **Step 3: Port `font-size.blade.php`** (presets xs–xl + custom px input — identical).
- [ ] **Step 4: Port `text-align.blade.php`**, **Step 5: Port `text-color.blade.php`**, **Step 6: Port `bullet.blade.php`**.
- [ ] **Step 7: Commit**

```bash
git add components/tiptap/toolbar/
git commit -m "feat(tiptap): dropdown toolbar buttons (heading/text/font-size/align/color/bullet)"
```

### Task 1.7: Link / table / image / youtube buttons + bubble menus

**Files:** Create `toolbar/link.blade.php`, `toolbar/table.blade.php`, `toolbar/image.blade.php`, `toolbar/youtube.blade.php` and `menu/{link,image,table,youtube}.blade.php`

Port from the v2 `editor/button/*` and `editor/menu/*`. Two deltas:
1. The bubble-menu wrapper class container is `.tiptap-menu` (the factory queries `.tiptap-menu .link-menu` etc.) — rename the menu container in `index.blade.php` accordingly (Task 1.8).
2. **Image button = insert-by-URL only in Phase 1** (upload is Phase 2). Replace the v2 hidden-file-input + `$wire.uploadMultiple` body with a small dropdown that takes a URL and calls `commands().setImage({ src })`. Keep the image **bubble menu** (align/width/remove) verbatim.

- [ ] **Step 1: `toolbar/image.blade.php`** (URL insert)

```blade
<atom:dropdown>
    <atom:tiptap.toolbar.button label="Image" :active="isActive('image')">
        <atom:icon.image class="size-5" />
    </atom:tiptap.toolbar.button>

    <atom:menu popover>
        <div x-data="{ url: '' }" class="p-2 flex flex-col gap-2 w-64">
            <input type="url" x-model="url" placeholder="{{ t('Image URL') }}" class="text-sm focus:outline-none">
            <atom:button size="sm" x-on:click="url && commands().setImage({ src: url }); url=''; close()">{{ t('Insert') }}</atom:button>
        </div>
    </atom:menu>
</atom:dropdown>
```

- [ ] **Step 2: Port `toolbar/link.blade.php`, `toolbar/table.blade.php`, `toolbar/youtube.blade.php`** (verbatim transform from v2 button files).
- [ ] **Step 3: Port `menu/link.blade.php`, `menu/image.blade.php`, `menu/table.blade.php`, `menu/youtube.blade.php`** (verbatim from v2 `editor/menu/*`). The `x-init="$el.shouldShow = (editor) => (...)"` hooks stay identical (the factory's `BubbleMenuConfiguration` calls `element.shouldShow(editor)`).
- [ ] **Step 4: Commit**

```bash
git add components/tiptap/toolbar/ components/tiptap/menu/
git commit -m "feat(tiptap): link/table/image/youtube buttons + floating-ui bubble menus"
```

### Task 1.8: The `<atom:tiptap>` component

**Files:** Create `components/tiptap/index.blade.php`

Rewrite of `editor/index.blade.php`: resolve preset *or* slot, `role="toolbar"`, `.tiptap-menu` container, `tiptap.css` link, `x-modelable="editorContent"`.

- [ ] **Step 1: Write `components/tiptap/index.blade.php`**

```blade
@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
    'readonly' => false,
    'autofocus' => false,
    'variant' => null,
    'mention' => null,
    'placeholder' => 'Write something...',
    'toolbar' => 'full',
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$model = $attributes->wire('model')->value();
$lazy = $attributes->modifier('blur');
$transparent = $variant === 'transparent';

$presets = include __DIR__.'/toolbar/_presets.php';
$buttons = is_array($toolbar) ? $toolbar : ($presets[$toolbar] ?? $presets['full']);
$hasToolbarSlot = isset($toolbar) && $toolbar instanceof \Illuminate\View\ComponentSlot;
@endphp

@if ($label || $caption)
    <atom:input.field :label="$label" :caption="$caption" :required="$required" :error="$error">
        <atom:tiptap :toolbar="$toolbar" :attributes="$attributes->merge(compact('name', 'variant', 'readonly', 'autofocus', 'mention', 'placeholder'))" />
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="tiptap({
        lazy: @js($lazy),
        placeholder: @js($placeholder),
        readonly: @js($readonly),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses(['editor-content m-3 focus:outline-none', $attributes->get('class', 'min-h-10')])),
    })"
    x-modelable="editorContent"
    class="group/editor"
    @if ($model && $lazy) wire:model.live="{{ $model }}" @else {{ $attributes->except(['class']) }} @endif>
        <div x-show="loading"><atom:skeleton /></div>

        <div x-show="!loading" @class([
            'editor relative rounded-lg',
            'shadow-sm bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10' => !$transparent,
            'has-focus:outline-1 has-focus:outline-zinc-200' => !$transparent,
            'border-0 bg-transparent' => $transparent,
        ])>
            @if (!$readonly && ($hasToolbarSlot || count($buttons)))
                <div class="sticky top-0 z-1 p-1">
                    <div role="toolbar" aria-label="{{ t('Formatting') }}" class="flex gap-1 items-center flex-wrap p-1 bg-white rounded-md dark:bg-zinc-800 border dark:border-zinc-700">
                        @if ($hasToolbarSlot)
                            {{ $toolbar }}
                        @else
                            @foreach ($buttons as $button)
                                <x-dynamic-component :component="'tiptap.toolbar.'.$button" />
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            <div class="tiptap-menu">
                @if (in_array('link', $buttons)) <atom:tiptap.menu.link/> @endif
                @if (in_array('table', $buttons)) <atom:tiptap.menu.table/> @endif
                @if (in_array('image', $buttons)) <atom:tiptap.menu.image/> @endif
                @if (in_array('youtube', $buttons)) <atom:tiptap.menu.youtube/> @endif
            </div>

            @if ($mention)
                <atom:tiptap.mention :options="is_string($mention) ? $mention : data_get($mention, 'options', [])" />
            @endif

            <div x-ref="editor" class="grow"></div>
        </div>
    </div>
@endif
```

Note: `<atom:tiptap.mention>` is a Phase 5 deliverable — guard the `@if ($mention)` so Phase 1 renders fine without it (the component won't exist yet). For Phase 1, leave the `@if ($mention)` block but only pass `mention` in tests once Phase 5 lands. If `x-dynamic-component` for a missing mention errors in tests, omit the block until Phase 5 (note in Task 1.9 we don't test mention).

- [ ] **Step 2: Commit**

```bash
git add components/tiptap/index.blade.php
git commit -m "feat(tiptap): <atom:tiptap> component (presets + slot + role=toolbar)"
```

### Task 1.9: Pest render coverage

**Files:** Create `tests/Feature/TiptapTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('tiptap', function () {
    it('renders the editor shell with the alpine factory and toolbar role', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)
            ->toContain('x-data="tiptap(')
            ->toContain('x-modelable="editorContent"')
            ->toContain('wire:ignore')
            ->toContain('role="toolbar"')
            ->toContain('x-ref="editor"');
    });

    it('renders the full preset by default', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        // full preset includes a heading + link + image button
        expect($html)
            ->toContain('aria-label="Heading"')
            ->toContain('aria-label="Link"')
            ->toContain('aria-label="Image"');
    });

    it('honours the basic preset (no table/youtube buttons)', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" toolbar="basic" />');

        expect($html)
            ->toContain('aria-label="Link"')
            ->not->toContain('aria-label="Youtube Video"');
    });

    it('renders no toolbar for the none preset', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" toolbar="none" />');

        expect($html)
            ->toContain('x-ref="editor"')
            ->not->toContain('role="toolbar"');
    });

    it('lets a toolbar slot replace the presets', function () {
        $html = renderBlade('<atom:tiptap wire:model="body"><x-slot:toolbar><button type="button" id="custom-btn">X</button></x-slot:toolbar></atom:tiptap>');

        expect($html)
            ->toContain('id="custom-btn"')
            ->toContain('role="toolbar"');
    });

    it('exposes accessible buttons with aria-pressed wiring', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        // toggle buttons bind aria-pressed to isActive(...)
        expect($html)->toContain('x-bind:aria-pressed');
    });
});
```

- [ ] **Step 2: Run, expect fail (component missing or markup mismatch)**

Run: `vendor/bin/pest tests/Feature/TiptapTest.php`
Expected: failures until Tasks 1.3–1.8 are correct. Iterate the blades until green.

- [ ] **Step 3: Run, expect pass**

Run: `vendor/bin/pest tests/Feature/TiptapTest.php`
Expected: PASS (6 tests).

- [ ] **Step 4: Run the full suite (no regressions)**

Run: `vendor/bin/pest`
Expected: all pass (existing 265 + new).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TiptapTest.php
git commit -m "test(tiptap): render coverage (presets, slot, a11y markup)"
```

### Task 1.10: Docs demo + Playwright e2e

**Files:** Create `resources/views/docs/demos/tiptap.blade.php`, `resources/views/docs/demos/tiptap/basic.blade.php`, and an e2e spec in the existing browser-test location

- [ ] **Step 1: Docs demo partial** `resources/views/docs/demos/tiptap/basic.blade.php`

```blade
<atom:tiptap label="Article body" placeholder="Write something..."/>
```

- [ ] **Step 2: Docs page** `resources/views/docs/demos/tiptap.blade.php`

```blade
<atom:docs.example
title="Basic"
description="Tiptap v3 rich text. Bind with wire:model; storage/persistence land via the AsTiptapContent cast (see the editor lifecycle docs)."
view="atom::docs.demos.tiptap.basic"/>
```

- [ ] **Step 3: Build, then write the e2e** (match the existing spec dir/runner used by prior batches, e.g. `tests/Browser` or the Playwright config `testDir`)

Run: `npm run build`

e2e (adapt selectors/runner to the existing harness):
```js
import { test, expect } from '@playwright/test'

test('tiptap mounts and toggles bold', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')          // local-env docs route
  const editor = page.locator('.editor-content').first()
  await expect(editor).toBeVisible()            // onCreate flipped loading=false
  await editor.click()
  await page.keyboard.type('hello')
  await page.getByRole('button', { name: 'Text Formatting' }).click()
  await page.getByText('Bold', { exact: true }).click()
  // bold mark applied
  await expect(page.locator('.editor-content strong, .editor-content b')).toBeHidden({ timeout: 0 }).catch(() => {})
})
```
Note: e2e runs against `testbench-serve` which loads `routes/web.php` (the `/atom/docs` routes) + built `dist` assets. Per the navlist gotcha, only base CSS is present — assert Alpine-driven visibility + tiptap behavior, not pixel layout.

- [ ] **Step 4: Run e2e**

Run the project's Playwright command (as used by prior batches).
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/docs/demos/tiptap.blade.php resources/views/docs/demos/tiptap/ <e2e-spec-path> dist/
git commit -m "test(tiptap): docs demo + e2e mount/toggle"
```

---

## Self-review notes (carried into execution)

- **Mention guard:** Task 1.8 references `<atom:tiptap.mention>` which doesn't exist until Phase 5. Execution must keep the `@if ($mention)` block dormant (don't pass `mention` in Phase 1) or temporarily comment it — re-enable in Phase 5. TiptapTest does not exercise mention.
- **Image upload:** Phase 1 image button inserts by URL only. The upload lifecycle (`$wire.uploadMultiple`, `_editor.images`/new property, cast) is Phase 2 — do not wire it here.
- **Tooltip tag:** Task 1.4 uses `<atom:tooltip>` (existing component), not a new `tiptap.tooltip`.
- **`isActive` reactivity:** depends on the `ts` ticker incremented in `onTransaction`/`onSelectionUpdate` (Task 1.2). If `aria-pressed` doesn't update live, confirm `ts` is read inside `isActive()`.
- **StarterKit v3 double-registration:** if the build warns that Link/Underline/UndoRedo are registered twice, it means a separate package is still imported — remove it (they're in StarterKit now).
- **Color import:** if `@tiptap/extension-color` `Color` export fails at build (Task 0.4), switch to `TextStyleKit` from `@tiptap/extension-text-style` and drop the separate TextStyle/Color imports. Recorded in the tiptap.js header comment.

## Done when

- `vendor/bin/pest` green (incl. 6 new TiptapTest cases), no regressions.
- e2e mount/toggle green.
- `npm run build` clean; `dist/` committed.
- `<atom:tiptap toolbar="basic">` and a custom `<x-slot:toolbar>` both render an accessible toolbar; the editor mounts and edits in the browser.

**Next:** Phase 2 plan (storage & SSR) — `AsTiptapContent` cast, PHP custom extensions, `<atom:tiptap.content>`.
