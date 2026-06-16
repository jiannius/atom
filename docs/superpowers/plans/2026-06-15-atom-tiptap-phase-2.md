# `<atom:tiptap>` — Phase 2 Implementation Plan (JSON storage round-trip + image upload)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]` checkboxes.

**Goal:** Persist editor content as **Tiptap JSON** end-to-end: the editor emits/consumes JSON over `wire:model`, a new `AsTiptapContent` cast stores JSON (dual-reading legacy serialized-HTML so existing rows still load), and the image toolbar button uploads files through Livewire whose temp URLs the cast persists to disk on save.

**Architecture:** **JSON travels over the wire.** The Alpine factory's `sync()` emits `JSON.stringify(editor.getJSON())`; the editor's initial `content` accepts either a JSON string (new rows) or an HTML string (legacy rows) — Tiptap parses both natively. The cast therefore stores/returns JSON directly and needs **no** server-side HTML↔JSON conversion (that — plus SSR display — is Phase 3). Image persistence walks the JSON's image nodes (plain array traversal, no regex), resizes + stores temp uploads to `config('atom.editor.disk')`, or defers to a model `tiptapStoreImage()` override.

**Tech Stack:** Tiptap v3 JS, Alpine, Livewire 4 `WithFileUploads`, Intervention Image (already a dep, used by the v2 cast), Pest + Testbench.

**Reference:** spec `docs/superpowers/specs/2026-06-15-atom-tiptap-design.md`; Phase 0+1 plan + the existing v2 cast `src/Casts/AsEditorContent.php` and v2 image button `components/editor/button/image.blade.php` + the `_editor.images` lifecycle in `src/Traits/AtomComponent.php`.

## Worktree
`.claude/worktrees/tiptap-rebuild`, branch `worktree-tiptap-rebuild`. Deps installed. Run all commands there. Phase 0+1 are committed (271 Pest + tiptap e2e green).

## File map (Phase 2)
```
Create:
  src/Casts/AsTiptapContent.php          # JSON cast: set (walk+persist images), get (dual-read)
  tests/Feature/AsTiptapContentTest.php  # Pest: round-trip, dual-read, image persist, override
Modify:
  resources/js/alpinejs/tiptap.js        # sync()/init() -> JSON over the wire
  components/tiptap/toolbar/image.blade.php  # URL-insert -> Livewire upload (port from v2 button/image)
  src/Traits/AtomComponent.php           # reuse/confirm $_editor.images lifecycle for tiptap uploads
  resources/js/atom.js + dist/           # rebuild after JS change
```

---

### Task 2.1: JSON over the wire (Alpine factory)

**Files:** Modify `resources/js/alpinejs/tiptap.js`; rebuild `dist/`

Change the content format the factory exchanges with Livewire from HTML to a JSON string. The editor's initial `content` must accept BOTH a JSON string and a legacy HTML string.

- [ ] **Step 1: Edit `resources/js/alpinejs/tiptap.js`** — replace the `init()`, `createTiptap()` content handling, `isEmpty()`, and `sync()` with JSON-aware versions:

```js
export default (config) => {
    let tiptap

    // Parse the wire value into something Tiptap's `content` accepts.
    // New rows = JSON string; legacy rows = HTML string; empty = ''.
    const parseContent = (value) => {
        if (!value) return ''
        if (typeof value !== 'string') return value          // already an object/array
        const trimmed = value.trim()
        if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
            try { return JSON.parse(trimmed) } catch (e) { return value }
        }
        return value                                          // legacy HTML string
    }

    return {
        ts: 0,
        loading: true,
        editorContent: config.content ?? '',

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                // compare against the editor's own JSON-string output to avoid loops
                if (value === JSON.stringify(tiptap.getJSON())) return
                this.commands().setContent(parseContent(value), { emitUpdate: false })
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,
                config: {
                    content: parseContent(this.editorContent),
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
        isActive (...args) { return this.ts >= 0 && tiptap?.isActive(...args) },

        isEmpty () { return !tiptap || tiptap.isEmpty },

        sync () {
            if (!tiptap.isEditable) return
            this.editorContent = tiptap.isEmpty ? '' : JSON.stringify(tiptap.getJSON())
            this.$dispatch('input', this.editorContent)
        },
    }
}
```

Note: `isEmpty()` now delegates to Tiptap (the old `.striptags()` HTML check no longer applies to JSON). `sync()` emits `''` when empty so required-validation still sees an empty string.

- [ ] **Step 2: Build** — `npm run build` (expect clean; tiptap chunk-size warning is fine).

- [ ] **Step 3: Run existing tests** — `vendor/bin/pest tests/Feature/TiptapTest.php` (the render markup is unchanged, should still be 6/6).

- [ ] **Step 4: Commit** — `git add resources/js/alpinejs/tiptap.js dist/ && git commit -m "feat(tiptap): JSON over the wire (editor emits/consumes tiptap JSON)"`

---

### Task 2.2: `AsTiptapContent` cast

**Files:** Create `src/Casts/AsTiptapContent.php`; Test `tests/Feature/AsTiptapContentTest.php`

A JSON cast. `get()` dual-reads (JSON passthrough, or legacy serialized-HTML → HTML string). `set()` parses the JSON, walks image nodes, persists Livewire temp uploads, stores JSON. Model-level override hook: if the model defines `tiptapStoreImage(string $tmpPath, string $key): string`, call it; else default-persist (Intervention resize → `config('atom.editor.disk')`). Mirror the v2 `AsEditorContent` persistence (resize ≤1000 q80, temp-file resolution, unlink) but operate on JSON nodes.

- [ ] **Step 1: Write the failing test `tests/Feature/AsTiptapContentTest.php`**

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

// Minimal model using the cast (no DB — we call the cast directly).
class TiptapCastModel extends Model
{
    protected $casts = ['body' => AsTiptapContent::class];
}

function castGet($value): mixed
{
    return (new AsTiptapContent)->get(new TiptapCastModel, 'body', $value, []);
}

function castSet($value): mixed
{
    return (new AsTiptapContent)->set(new TiptapCastModel, 'body', $value, []);
}

describe('AsTiptapContent', function () {
    it('stores a JSON string unchanged when there are no images', function () {
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hi']]]]]);

        expect(castSet($json))->toBe($json);
    });

    it('returns stored JSON as-is on get', function () {
        $json = '{"type":"doc","content":[]}';

        expect(castGet($json))->toBe($json);
    });

    it('dual-reads legacy serialized-HTML rows as HTML on get', function () {
        // old AsEditorContent stored serialize()'d HTML
        $legacy = serialize('<p>legacy <strong>html</strong></p>');

        expect(castGet($legacy))->toBe('<p>legacy <strong>html</strong></p>');
    });

    it('leaves non-temp image src untouched', function () {
        $json = json_encode([
            'type' => 'doc',
            'content' => [['type' => 'image', 'attrs' => ['src' => 'https://cdn.example.com/a.png']]],
        ]);

        expect(castSet($json))->toBe($json);
    });

    it('stores null for empty content', function () {
        expect(castSet(''))->toBeNull();
        expect(castSet(null))->toBeNull();
    });
});
```

- [ ] **Step 2: Run, expect fail** — `vendor/bin/pest tests/Feature/AsTiptapContentTest.php` → FAIL (class missing).

- [ ] **Step 3: Write `src/Casts/AsTiptapContent.php`**

```php
<?php

namespace Jiannius\Atom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AsTiptapContent implements CastsAttributes
{
    /**
     * Read a stored value. New rows are Tiptap JSON (returned as-is for the
     * editor, which loads JSON natively). Legacy rows were stored by the v2
     * AsEditorContent cast as serialize()'d HTML — unserialize them so the
     * editor (and the SSR renderer) get the raw HTML string, which Tiptap also
     * loads natively. No forced migration: a legacy row becomes JSON only when
     * re-saved through the editor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $unserialized = @unserialize($value);

        if ($unserialized !== false || $value === 'b:0;') {
            return $unserialized;   // legacy HTML string
        }

        return $value;              // JSON string
    }

    /**
     * Prepare a value for storage. The editor sends a Tiptap JSON string. Walk
     * its image nodes; any src pointing at a Livewire temporary upload is
     * persisted to disk and rewritten. Returns the (possibly rewritten) JSON
     * string, or null when empty.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return null;
        }

        if (! is_string($value)) {
            $value = json_encode($value);
        }

        $doc = json_decode($value, true);

        // Not JSON (e.g. a direct legacy-HTML assignment) — store as-is.
        if (! is_array($doc)) {
            return $value;
        }

        $this->walkImages($doc, function (array &$node) use ($model, $key) {
            $src = $node['attrs']['src'] ?? null;

            if ($src && $this->isTemporaryUpload($src)) {
                $node['attrs']['src'] = $this->persist($model, $key, $src);
            }
        });

        return json_encode($doc);
    }

    /**
     * Recursively walk every image node in the document, mutating in place.
     */
    protected function walkImages(array &$node, callable $callback): void
    {
        if (($node['type'] ?? null) === 'image') {
            $callback($node);
        }

        if (! empty($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as &$child) {
                if (is_array($child)) {
                    $this->walkImages($child, $callback);
                }
            }
        }
    }

    /**
     * Is this src a Livewire temporary preview URL?
     */
    protected function isTemporaryUpload(string $src): bool
    {
        return (bool) preg_match('/\/livewire-[^\/]+\/preview-file\//', $src);
    }

    /**
     * Persist a temporary upload to permanent storage and return its URL.
     * A model may override persistence via tiptapStoreImage($tmpPath, $key).
     */
    protected function persist(Model $model, string $key, string $src): string
    {
        $base = head(explode('?', $src));
        $tmpname = str($base)->afterLast('/')->toString();
        $tmppath = storage_path('app/private/'.config('livewire.temporary_file_upload.directory').'/'.$tmpname);

        if (! file_exists($tmppath)) {
            return $src;
        }

        if (method_exists($model, 'tiptapStoreImage')) {
            $url = $model->tiptapStoreImage($tmppath, $key);
            @unlink($tmppath);

            return $url;
        }

        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $image = $manager->read($tmppath);
        $image->scaleDown(width: 1000);
        $image->save(quality: 80);

        $disk = Storage::disk(config('atom.editor.disk') ?: config('filesystems.default'));
        $folder = collect([data_get($disk->getConfig(), 'folder'), 'editor'])->filter()->join('/');
        $extension = pathinfo($tmppath, PATHINFO_EXTENSION);
        $filename = strtolower(str()->random(20)).'-'.time().'.'.$extension;
        $path = $disk->putFileAs($folder, $tmppath, $filename, 'public');

        @unlink($tmppath);

        return $disk->url($path);
    }
}
```

- [ ] **Step 4: Run, expect pass** — `vendor/bin/pest tests/Feature/AsTiptapContentTest.php` → 5 pass.

- [ ] **Step 5: Add a model-override test** (append to the `describe`):

```php
    it('uses the model tiptapStoreImage override when present', function () {
        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public function tiptapStoreImage($tmpPath, $key) { return 'https://cdn/overridden.png'; }
        };

        // a temp-upload src + an on-disk temp file so persist() runs
        $dir = config('livewire.temporary_file_upload.directory');
        $tmp = storage_path('app/private/'.$dir);
        if (! is_dir($tmp)) { mkdir($tmp, 0777, true); }
        file_put_contents($tmp.'/test-temp.png', 'x');

        $json = json_encode(['type' => 'doc', 'content' => [
            ['type' => 'image', 'attrs' => ['src' => '/livewire-abc/preview-file/test-temp.png']],
        ]]);

        $out = json_decode((new AsTiptapContent)->set($model, 'body', $json, []), true);

        expect($out['content'][0]['attrs']['src'])->toBe('https://cdn/overridden.png');
    });
```

Run: `vendor/bin/pest tests/Feature/AsTiptapContentTest.php` → 6 pass. (If the temp dir/config isn't set in the test env, set `config(['livewire.temporary_file_upload.directory' => 'livewire-tmp'])` in a `beforeEach`.)

- [ ] **Step 6: Full suite** — `vendor/bin/pest` → all green.

- [ ] **Step 7: Commit** — `git add src/Casts/AsTiptapContent.php tests/Feature/AsTiptapContentTest.php && git commit -m "feat(tiptap): AsTiptapContent cast (JSON store, dual-read legacy HTML, image persist + model override)"`

---

### Task 2.3: image upload button (Livewire temp upload)

**Files:** Modify `components/tiptap/toolbar/image.blade.php`; confirm `src/Traits/AtomComponent.php` `$_editor.images` lifecycle

Replace the Phase-1 URL-insert image button with the real upload flow, ported from the v2 `components/editor/button/image.blade.php`. The v2 flow: a hidden `<input type=file multiple accept=image/*>`, on change `$wire.uploadMultiple('_editor.images', files, ...)`, and on success insert each temp URL via `commands().setImage({ src })`. The trait's `updatedAtomComponent` already converts `_editor.images` uploads to `temporaryUrl()` strings — confirm it's intact (it is, from v2). The cast (Task 2.2) then persists those temp URLs on save.

- [ ] **Step 1: Read** `components/editor/button/image.blade.php` and the `$_editor` block + `updatedAtomComponent` in `src/Traits/AtomComponent.php`.

- [ ] **Step 2: Rewrite `components/tiptap/toolbar/image.blade.php`** porting the v2 upload button, wrapped with the new accessible toolbar button. The v2 button used its own `x-data` with `read()`/`finish()` calling `commands()`/`$wire`. Keep that logic; swap the trigger to `<atom:tiptap.toolbar.button label="Image" :active>`-style and keep the uploading-state button. Concretely:

```blade
<div
x-data="{
    uploading: false,
    progress: 0,
    read (files) {
        this.uploading = true
        this.$wire.uploadMultiple('_editor.images', Array.from(files),
            () => this.finish(true),
            () => this.finish('error'),
            (e) => this.progress = e.detail.progress,
            () => this.finish(),
        )
    },
    finish (response) {
        if (response === 'error') atom.alert({ heading: 'Error', message: 'Failed to upload images', variant: 'danger' })
        else if (response) this.$wire.get('_editor.images').forEach(url => commands().setImage({ src: url }))
        this.uploading = false
        this.progress = 0
    },
}">
    <input type="file" x-ref="fileinput" x-on:change="read($event.target.files)" class="hidden" accept="image/*" multiple>

    <atom:tiptap.toolbar.button label="Image" active="isActive('image')" x-show="!uploading" x-on:click="$refs.fileinput.click()">
        <atom:icon.image class="size-5" />
    </atom:tiptap.toolbar.button>

    <atom:tiptap.toolbar.button label="Uploading" x-show="uploading" class="opacity-50 pointer-events-none gap-1">
        <atom:icon.loading class="size-4 shrink-0" /> <span x-show="progress > 0 && progress < 100" x-text="`${progress}%`" class="text-xs"></span>
    </atom:tiptap.toolbar.button>
</div>
```

Note: `commands()` and `isActive()` are in scope (editor's Alpine data); the nested `x-data` here inherits them. `_editor.images` is the existing reserved trait property. Do NOT add `@if` inside the `<atom:tiptap.toolbar.button>` attribute list (TagCompiler gotcha) — `active="..."`/`x-show` are fine as plain attributes.

- [ ] **Step 3: Build** — `npm run build` (no JS file changed here, but the button is blade; build only if you touched JS — you didn't, so skip unless dist needs the blade... blade isn't built. Skip build.)

- [ ] **Step 4: Render test** — add to `tests/Feature/TiptapTest.php` a case asserting the default editor's image button now wires the upload:

```php
    it('wires the image button to a livewire upload', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)
            ->toContain("uploadMultiple('_editor.images'")
            ->toContain('type="file"');
    });
```
Run `vendor/bin/pest tests/Feature/TiptapTest.php` → now 7 pass.

- [ ] **Step 5: Full suite** — `vendor/bin/pest` → green.

- [ ] **Step 6: Commit** — `git add components/tiptap/toolbar/image.blade.php tests/Feature/TiptapTest.php && git commit -m "feat(tiptap): image toolbar button uploads via livewire (persisted by the cast on save)"`

---

## Self-review notes
- **JSON over the wire** is the load-bearing decision: the editor accepts JSON (new) or HTML (legacy) as initial `content`; `sync()` always emits JSON. The cast stores JSON and dual-reads legacy serialized-HTML. No tiptap-php in this phase.
- **`tiptapStoreImage` is a MODEL method**, not a Livewire-component method (the cast runs during model save and has the `$model`, not the component). The spec said "Livewire component"; the cast reality makes it a model method — note this in the Phase-6 docs.
- **`config('atom.editor.disk')`** replaces the v2 `env('FILESYSTEM_DISK')`; falls back to `config('filesystems.default')`. No atom config file is shipped — consumers may set `atom.editor.disk` in their own config, else the default disk is used.
- Image button reuses the existing `$_editor.images` trait lifecycle (unchanged from v2) — confirm it's present; do not duplicate it.
- The `<atom:tiptap.content>` SSR renderer + PHP custom extensions + `atom:tiptap-migrate` are **Phase 3** (need tiptap-php). Not here.

## Done when
- `vendor/bin/pest` green (TiptapTest 7 + AsTiptapContentTest 6, no regressions).
- Editor round-trips JSON over `wire:model`; legacy HTML rows still load.
- Image button uploads via Livewire; the cast persists temp URLs to the configured disk (or the model override) on save.
- `npm run build` clean; `dist/` committed.

**Next:** Phase 3 (SSR display + migration): `<atom:tiptap.content>`, PHP custom extensions, `atom:tiptap-migrate`.
