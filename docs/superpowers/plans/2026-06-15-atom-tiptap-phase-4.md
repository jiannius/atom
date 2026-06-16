# `<atom:tiptap>` — Phase 4 Implementation Plan (chat composer)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]` checkboxes.

**Goal:** Ship `<atom:tiptap.chat>` — a chat composer built on the same v3 engine: enter-to-send, file attach (paperclip), a file tray with previews + remove, a submit button, and paste/drop file capture (fixing the v2 bugs where paste/drop were unwired and gated on a non-existent `this.chat`). On send it dispatches `input` with `{ body, files }`.

**Architecture:** Reuse the existing `tiptap()` Alpine factory with a `chat: true` config flag (mirrors how v2's single `editor()` factory branched). The chat blade has no toolbar/bubble-menus; instead a row of chat controls (formatting dropdown, attach, submit) and a files tray. The `DisableEnterKeyExtension` already in the engine (`resources/js/tiptap.js`) dispatches an `editor-enter` DOM event; the factory wires it to `sync()` only in chat mode. Chat's `sync()` dispatches `{ body: getHTML(), files }` (chat messages are HTML-friendly, matching v2) and clears, unlike the main editor which emits JSON over `wire:model`.

**Tech Stack:** Tiptap v3 JS, Alpine, Livewire `WithFileUploads` (consumer side), Blade, Pest.

**Reference:** v2 chat — `components/editor/chat.blade.php`, `components/editor/button/chat-formatting.blade.php`, `components/editor/button/chat-upload.blade.php`, and the chat methods in `resources/js/alpinejs/editor.js` (`files`, `paste`, `drop`, `readFiles`, the chat branch of `sync`, the `editor-enter` listener in `onCreate`). Port these.

## v2 bugs to FIX in this phase
- `paste()`/`drop()` were gated on `this.chat` which never exists in the Alpine data (only `config.chat` does) → they always early-returned. Gate on `config.chat` (closure var) instead.
- `paste`/`drop` were never wired in the chat blade (`x-on:paste`/`x-on:drop` absent) → wire them on the editor element in `<atom:tiptap.chat>`.

## File map (Phase 4)
```
Create:
  components/tiptap/chat.blade.php            # <atom:tiptap.chat>
  components/tiptap/chat/formatting.blade.php # <atom:tiptap.chat.formatting> (bold/italic/... dropdown)
  components/tiptap/chat/upload.blade.php     # <atom:tiptap.chat.upload> (paperclip attach)
  resources/views/docs/demos/tiptap/chat.blade.php   # docs demo (optional)
Modify:
  resources/js/alpinejs/tiptap.js            # add chat support (files/paste/drop/readFiles/sync-branch/enter)
  resources/js/atom.js + dist/               # rebuild
  tests/Feature/TiptapTest.php               # chat render coverage
  resources/views/docs/demos/tiptap.blade.php # add a Chat example (optional)
```

---

### Task 4.1: chat support in the Alpine factory

**Files:** Modify `resources/js/alpinejs/tiptap.js`; rebuild `dist/`

Add chat-mode capability to the `tiptap()` factory: a `files` array, `paste`/`drop`/`readFiles`, an `editor-enter`→`sync` wire, `disableEnterKey: config.chat` passed to the engine, and a chat branch in `sync()`.

- [ ] **Step 1: Edit `resources/js/alpinejs/tiptap.js`** to the following (adds the chat bits; keeps Phase-2 JSON-over-wire for non-chat):

```js
export default (config) => {
    let tiptap

    const parseContent = (value) => {
        if (!value) return ''
        if (typeof value !== 'string') return value
        const trimmed = value.trim()
        if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
            try { return JSON.parse(trimmed) } catch (e) { return value }
        }
        return value
    }

    return {
        ts: 0,
        loading: true,
        editorContent: config.content ?? '',
        files: [],                       // chat attachments

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                if (value === JSON.stringify(tiptap.getJSON())) return
                this.commands().setContent(parseContent(value), { emitUpdate: false })
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,
                disableEnterKey: config.chat,
                config: {
                    content: parseContent(this.editorContent),
                    placeholder: config.placeholder,
                    editable: !config.readonly,
                    autofocus: config.autofocus,
                    editorProps: { attributes: { class: config.class } },
                    onCreate ({ editor }) {
                        _this.loading = false
                        _this.ts++
                        if (config.chat) {
                            editor.options.element.addEventListener('editor-enter', () => _this.sync())
                        }
                    },
                    onSelectionUpdate () { _this.ts++ },
                    onTransaction () { _this.ts++ },
                    ...(config.chat
                        ? {}
                        : (config.lazy ? { onBlur: () => _this.sync() } : { onUpdate: () => _this.sync() })),
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
        isActive (...args) { return this.ts >= 0 && tiptap?.isActive(...args) },
        isEmpty () { return !tiptap || tiptap.isEmpty },

        // chat: paste files (or plain text) into the composer
        paste (e) {
            if (!config.chat) return
            const clipboard = e.clipboardData
            const files = Array.from(clipboard.items).filter(i => i.kind === 'file').map(i => i.getAsFile())
            const text = clipboard.getData('text')
            if (files.length) this.readFiles(files)
            else if (text) this.commands().insertContent(text)
        },

        // chat: drop files into the composer
        drop (e) {
            if (!config.chat) return
            this.readFiles(e.dataTransfer.files)
        },

        // chat: add files to the tray (with image previews)
        readFiles (files) {
            if (!files || !files.length) return
            this.files = [
                ...this.files,
                ...Array.from(files).map(file => ({
                    file,
                    src: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                })),
            ]
            this.$nextTick(() => this.commands().focus())
        },

        sync () {
            if (!tiptap.isEditable) return

            if (config.chat) {
                const body = tiptap.isEmpty ? '' : tiptap.getHTML()
                this.$dispatch('input', { body, files: this.files.map(f => f.file) })
                this.$nextTick(() => { tiptap.commands.clearContent(); this.files = [] })
                return
            }

            this.editorContent = tiptap.isEmpty ? '' : JSON.stringify(tiptap.getJSON())
            this.$dispatch('input', this.editorContent)
        },
    }
}
```

Note: non-chat behavior is unchanged from Phase 2 (JSON over wire, onUpdate/onBlur sync). Chat: no onUpdate/onBlur (sends only on Enter/submit), Enter wired via `editor-enter`, body = HTML, clears after send. `paste`/`drop` now gate on `config.chat` (the v2 `this.chat` bug fixed).

- [ ] **Step 2: Build** — `npm run build` (clean; chunk warning fine).
- [ ] **Step 3: Existing tests green** — `vendor/bin/pest tests/Feature/TiptapTest.php` (7/7 — non-chat markup unchanged).
- [ ] **Step 4: Commit** — `git add resources/js/alpinejs/tiptap.js dist/ && git commit -m "feat(tiptap): chat support in the alpine factory (files/paste/drop/enter-to-send)"`

---

### Task 4.2: chat buttons

**Files:** Create `components/tiptap/chat/formatting.blade.php`, `components/tiptap/chat/upload.blade.php`

Port from v2 `components/editor/button/chat-formatting.blade.php` and `chat-upload.blade.php`, swapping `<atom:editor.button>`→`<atom:tiptap.toolbar.button>` and `<atom:editor.menu.button>`/`<atom:menu.item>` as the v2 file used. Read each v2 file first.

- [ ] **Step 1: `components/tiptap/chat/formatting.blade.php`** — port the v2 `chat-formatting.blade.php` (an `<atom:dropdown>` with `<atom:tiptap.toolbar.button label="Text Formatting">` trigger and an `<atom:menu popover>` of bold/italic/strike/sub/super/underline/bulletList/orderedList `<atom:menu.item x-on:click="commands().toggleX()">` rows). Keep SVGs + commands verbatim.

- [ ] **Step 2: `components/tiptap/chat/upload.blade.php`** — port the v2 `chat-upload.blade.php`:

```blade
@props([
    'multiple' => true,
    'accept' => '*',
])

<div>
    <input
    type="file"
    x-ref="fileInput"
    x-on:click.stop=""
    x-on:input.stop=""
    x-on:change="readFiles($event.target.files)"
    accept="{{ $accept }}"
    @if ($multiple) multiple @endif
    class="hidden">

    <atom:tiptap.toolbar.button label="Attach" x-on:click="$refs.fileInput.click()">
        {{-- paperclip SVG from v2 chat-upload.blade.php (copy verbatim) --}}
    </atom:tiptap.toolbar.button>
</div>
```
(Copy the exact paperclip `<svg ...>` from the v2 file. `readFiles` is the factory method — in scope via the chat editor's Alpine data.)

- [ ] **Step 3: Commit** — `git add components/tiptap/chat/ && git commit -m "feat(tiptap): chat formatting + attach buttons"`

---

### Task 4.3: `<atom:tiptap.chat>` component

**Files:** Create `components/tiptap/chat.blade.php`

Port v2 `components/editor/chat.blade.php`, transformed: `editor(...)`→`tiptap(...)`, wire `x-on:paste`/`x-on:drop` on the editor element (the v2 bug fix), use the new chat buttons + `tiptap.toolbar.button` for submit, and the existing mention block (commented until Phase 5).

- [ ] **Step 1: Write `components/tiptap/chat.blade.php`**

```blade
@props([
    'label' => null,
    'caption' => null,
    'autofocus' => false,
    'mention' => null,
    'variant' => null,
    'placeholder' => 'Write something...',
])

@php
$transparent = $variant === 'transparent';
@endphp

@if ($label || $caption)
    <atom:input.field :label="$label" :caption="$caption">
        <atom:tiptap.chat :attributes="$attributes->merge(compact('autofocus', 'mention', 'placeholder', 'variant'))" />
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="tiptap({
        chat: true,
        placeholder: @js($placeholder),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses(['editor-content editor-chat-content m-3 focus:outline-none', $attributes->get('class')])),
    })"
    x-modelable="editorContent"
    class="group/editor"
    {{ $attributes->except(['class']) }}>
        <div x-show="loading"><atom:skeleton /></div>

        <div x-show="!loading" @class([
            'editor relative rounded-lg',
            'shadow-sm bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10' => !$transparent,
            'has-focus:outline-1 has-focus:outline-zinc-200' => !$transparent,
            'border-0 bg-transparent' => $transparent,
        ])>
            {{-- Phase 5: mention block (re-enable when components/tiptap/mention.blade.php exists)
            @if ($mention)
                <atom:tiptap.mention :options="is_string($mention) ? $mention : data_get($mention, 'options', [])" />
            @endif
            --}}

            <div class="flex items-end">
                <div x-ref="editor" x-on:input.stop="" x-on:paste="paste($event)" x-on:drop.prevent="drop($event)" class="grow"></div>

                <div class="shrink-0 p-2 flex items-center group-[.is-loading]/editor:hidden">
                    <atom:tiptap.chat.formatting />
                    <atom:tiptap.chat.upload />
                    <atom:tiptap.toolbar.button label="Submit" x-on:click="sync()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4v7a4 4 0 0 1-4 4H4"/><path d="m9 10-5 5 5 5"/></svg>
                    </atom:tiptap.toolbar.button>
                </div>
            </div>

            <div x-show="files.length" class="py-2 px-3 flex flex-col gap-2">
                <template x-for="(file, i) in files" hidden>
                    <div class="group flex items-center gap-2">
                        <figure class="shrink-0 size-6 bg-zinc-200 rounded-md overflow-hidden border border-zinc-300 flex items-center justify-center">
                            <img x-show="file.src" x-bind:src="file.src" class="w-full h-full object-cover">
                            <atom:icon.file x-show="!file.src" class="size-4" />
                        </figure>

                        <div x-text="file.file.name" class="grow text-xs text-muted-more truncate"></div>

                        <button type="button" x-on:click="files.splice(i, 1)" aria-label="{{ t('Remove') }}" class="shrink-0 flex items-center justify-center text-muted-foreground">
                            <atom:icon.delete class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
```

Note: `x-on:paste`/`x-on:drop.prevent` on the editor `<div>` is the v2-bug fix (they were missing). The remove button got an `aria-label` (v2 had none). `paste`/`drop` are factory methods (Task 4.1).

- [ ] **Step 2: Commit** — `git add components/tiptap/chat.blade.php && git commit -m "feat(tiptap): <atom:tiptap.chat> composer (enter-send, attach, file tray, fixed paste/drop)"`

---

### Task 4.4: tests + docs demo

**Files:** Modify `tests/Feature/TiptapTest.php`; create `resources/views/docs/demos/tiptap/chat.blade.php` + add to the docs page

- [ ] **Step 1: Add chat render tests to `tests/Feature/TiptapTest.php`** (new `describe('tiptap.chat', ...)` block):

```php
describe('tiptap.chat', function () {
    it('renders the chat composer with submit + attach + paste/drop wiring', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)
            ->toContain("x-data=\"tiptap(")
            ->toContain('chat: true')
            ->toContain('x-on:paste="paste($event)"')
            ->toContain('x-on:drop.prevent="drop($event)"')
            ->toContain('aria-label="Submit"')
            ->toContain('aria-label="Attach"');
    });

    it('renders the file tray template', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)
            ->toContain('x-for="(file, i) in files"')
            ->toContain('files.splice(i, 1)')
            ->toContain('aria-label="Remove"');
    });

    it('omits the rich-text toolbar in chat mode', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" />');

        expect($html)->not->toContain('role="toolbar"');
    });
});
```
Share `errors` in the file's `beforeEach` if not already (TiptapTest already does). Run `vendor/bin/pest tests/Feature/TiptapTest.php` → 10 pass.

- [ ] **Step 2: Docs demo** — `resources/views/docs/demos/tiptap/chat.blade.php`:
```blade
<atom:tiptap.chat placeholder="Type a message..."/>
```
Add a `<atom:docs.example title="Chat" .../>` block to `resources/views/docs/demos/tiptap.blade.php` pointing at `atom::docs.demos.tiptap.chat`. Build is not required for blade-only, but run `npm run build` if you touched JS in this task (you didn't).

- [ ] **Step 3: Full suite** — `vendor/bin/pest` → green.

- [ ] **Step 4: Commit** — `git add tests/Feature/TiptapTest.php resources/views/docs/demos/tiptap* && git commit -m "test(tiptap): chat render coverage + docs demo"`

---

## Self-review notes
- One factory (`tiptap()`) with `config.chat` — matches v2's single-factory approach; non-chat path unchanged from Phase 2.
- Chat `sync()` dispatches `{ body: getHTML(), files }` (HTML body, matches v2 chat contract) and clears; the main editor still emits JSON. This intentional divergence is because chat messages are lightweight/displayed-as-HTML, not persisted rich docs.
- Fixed both v2 chat bugs: `paste`/`drop` now gate on `config.chat` AND are wired via `x-on:paste`/`x-on:drop` in the blade.
- Mention block stays commented (Phase 5). No e2e here (chat enter-to-send + file tray need a Livewire server + real paste/drop, untestable in atom's rig) — verify on humblebear; Pest covers the rendered wiring.

## Done when
- `vendor/bin/pest` green (TiptapTest now 10, no regressions).
- `<atom:tiptap.chat>` renders: composer, formatting dropdown, attach, submit, file tray; paste/drop wired; no toolbar.
- `npm run build` clean; `dist/` committed.

**Next:** Phase 5 (mention), Phase 6 (shim + cutover → tag v3.6.0).
