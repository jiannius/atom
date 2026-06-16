# `<atom:tiptap>` — Phase 6 Implementation Plan (cutover + ship v3.6.0)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development for tasks 6.1–6.2. The SHIP (6.3) is done by the controller (irreversible: squash-merge, tag, push). Steps use `- [ ]` checkboxes.

**Goal:** Retire the v2 editor: turn `<atom:editor>` / `.chat` / `.content` into thin back-compat aliases for `<atom:tiptap>`, delete the old editor blades + JS, update Boost guidelines + docs + host migration notes, then ship **v3.6.0** (squash-merge → main, tag, push, worktree cleanup).

**Architecture:** The old and new components share an identical prop surface, so each alias is a one-line forward via `:attributes="$attributes"`. The v2 `editor()` Alpine factory and all `components/editor/{button,menu,mention}` files are deleted (nothing internal references them — verified; consumers use the `<atom:editor>` component, which now delegates to the v3 `tiptap()` engine). `editor.css` stays in the Vite build for consumers who reference it directly (the alias itself loads `tiptap.css` via the inner component).

**Tech Stack:** Blade, Vite, Pest, git.

**Reference:** spec `docs/superpowers/specs/2026-06-15-atom-tiptap-design.md` (Release section); the existing shims' targets (`components/tiptap/{index,chat,content}.blade.php`).

## Pre-flight facts (verified)
- No `<atom:editor>` / `editor()`-factory references inside atom outside `components/editor/*` (consumers use the component).
- `html.blade.php` `'editor' => false` prop is DEAD (declared, never used) — consumers can't load editor.css via `<atom:html>`; only the old editor blades emitted the editor.css `<link>`.
- Old + new prop surfaces match (`name,label,caption,required,error,readonly,autofocus,variant,mention,placeholder,toolbar`); old toolbar button names == new `tiptap.toolbar.*` partial names.
- v2 npm deps already removed in Phase 0; package.json is v3-clean. No dep changes here.

## File map (Phase 6)
```
Replace (with thin aliases):
  components/editor/index.blade.php     -> <atom:tiptap :attributes="$attributes"/>
  components/editor/chat.blade.php      -> <atom:tiptap.chat :attributes="$attributes"/>
  components/editor/content.blade.php   -> <atom:tiptap.content :attributes="$attributes">{{ $slot }}</atom:tiptap.content>
Delete:
  components/editor/button/*            (18 files)
  components/editor/menu/*              (5 files)
  components/editor/mention.blade.php
  resources/js/alpinejs/editor.js
Modify:
  resources/js/atom.js                  (remove editor import + Alpine.data('editor', ...)) + rebuild dist
  resources/boost/guidelines/core.blade.php  (AsTiptapContent, new components, migrate, type=module note)
  resources/views/docs/demos/editor.blade.php (note it's an alias; keep tiptap docs primary)
Keep (back-compat):
  resources/css/editor.css + its vite input
```

---

### Task 6.1: aliases + delete old editor + drop the editor factory

**Files:** as in the map above

- [ ] **Step 1: Safety grep** — confirm nothing (besides the files we delete) references the old editor:
```bash
grep -rn "x-data=\"editor(\|'editor'\|atom:editor\.\|atom:editor\b" resources/js components resources/views --include='*.js' --include='*.blade.php' | grep -v 'components/editor/' | grep -v 'docs/demos/editor'
```
Expect: only `resources/js/atom.js` (the registration we're removing). If anything ELSE references `editor()` factory or `<atom:editor.button>` etc., STOP and report.

- [ ] **Step 2: Replace `components/editor/index.blade.php`** with:
```blade
{{-- Deprecated alias: <atom:editor> now forwards to <atom:tiptap> (Tiptap v3). --}}
<atom:tiptap :attributes="$attributes" />
```

- [ ] **Step 3: Replace `components/editor/chat.blade.php`** with:
```blade
{{-- Deprecated alias: <atom:editor.chat> now forwards to <atom:tiptap.chat>. --}}
<atom:tiptap.chat :attributes="$attributes" />
```

- [ ] **Step 4: Replace `components/editor/content.blade.php`** with:
```blade
{{-- Deprecated alias: <atom:editor.content> now forwards to <atom:tiptap.content>. --}}
<atom:tiptap.content :attributes="$attributes">{{ $slot }}</atom:tiptap.content>
```
(The old `.content` received the stored value as slot content; `<atom:tiptap.content>` uses `(string) $slot` when no `:content` prop, and renders via tiptap-php — legacy HTML round-trips.)

- [ ] **Step 5: Delete the now-orphaned old editor internals:**
```bash
git rm -r components/editor/button components/editor/menu components/editor/mention.blade.php resources/js/alpinejs/editor.js
```

- [ ] **Step 6: Remove the editor factory from `resources/js/atom.js`** — delete the line `import editor from './alpinejs/editor'` and the line `Alpine.data('editor', editor)`. Leave `tiptap`/`mention` registrations.

- [ ] **Step 7: Build** — `npm run build` → clean (the editor chunk should disappear; tiptap chunk-size warning fine). If the build errors on a missing `./alpinejs/editor` import, you missed Step 6.

- [ ] **Step 8: Full suite** — `vendor/bin/pest` → green. The existing `ComponentRenderTest` / `BoostGuidelinesTest` may compile `<atom:editor>` — confirm the alias compiles + renders (it should: it just forwards to `<atom:tiptap>`). If a test referenced the old editor's internal markup, update it to the alias behavior.

- [ ] **Step 9: Commit** — `git add -A && git commit -m "feat(tiptap): <atom:editor> is now a back-compat alias for <atom:tiptap>; delete v2 editor"`

---

### Task 6.2: Boost guidelines + docs + migration notes

**Files:** `resources/boost/guidelines/core.blade.php`, `resources/views/docs/demos/editor.blade.php`

- [ ] **Step 1: Update the editor/cast guidance in `resources/boost/guidelines/core.blade.php`.** Find the section (~line 251) that currently says:
> For columns storing Tiptap editor HTML, cast with `Jiannius\Atom\Casts\AsEditorContent`. On save, the cast walks `<img>` tags, persists Livewire temporary uploads to `Storage::disk(env('FILESYSTEM_DISK'))` … Run `php artisan atom:purge-editor-images` …

Replace it with v3.6.0 guidance (keep it concise, match the file's prose style):
> Rich text uses **`<atom:tiptap>`** (editor), **`<atom:tiptap.chat>`** (chat composer: enter-to-send, attachments), and **`<atom:tiptap.content>`** (server-side render of stored content). Bind with `wire:model` — the value is **Tiptap JSON**. Toolbar via a preset (`toolbar="full|basic|minimal|none"`) or a `<x-slot:toolbar>` of `<atom:tiptap.*>` buttons. Mentions via `mention="callbackMethod"` (live `$wire` search) or `:mention="[...]"` (static).
> Cast the storage column with **`Jiannius\Atom\Casts\AsTiptapContent`** (stores JSON; dual-reads legacy HTML, so existing rows keep working). Images: the cast persists Livewire temp uploads to `config('atom.editor.disk')` (falls back to the default disk) — or define `tiptapStoreImage(string $tmpPath, string $key): string` **on the model** to control persistence. Render stored content with `<atom:tiptap.content :content="$model->body"/>`. Migrate legacy HTML columns to JSON with `php artisan atom:tiptap-migrate` (after switching the cast to `AsTiptapContent`). `<atom:editor>` / `.chat` / `.content` remain as back-compat aliases.

- [ ] **Step 2: Add the host upgrade note** as a short block (in the same file, near the editor guidance, or wherever the file groups breaking/version notes — match its structure):
> **Upgrading to v3.6.0 (editor):** atom.js now loads as an ES module — if you include it via your own `<script>` tag (not `<atom:html>`), add `type="module"`. Switch editor columns from `AsEditorContent` to `AsTiptapContent`, then run `php artisan atom:tiptap-migrate`. Run `npm run build` (new Tailwind utilities). `<atom:editor>` keeps working as an alias.

- [ ] **Step 2b:** if `core.blade.php:37` lists the `$_editor` bucket, leave it (still used by image upload).

- [ ] **Step 3: Update `resources/views/docs/demos/editor.blade.php`** — add a one-line note that `<atom:editor>` is now an alias for `<atom:tiptap>` and point at the tiptap page. Keep its existing `<atom:docs.example>` (the alias renders fine). E.g. prepend:
```blade
<atom:callout>This component is a back-compat alias. New code should use <code>&lt;atom:tiptap&gt;</code>.</atom:callout>
```
(Use whatever callout/prose component the docs pages use — check a sibling docs page; if unsure, a plain `<p>` is fine.)

- [ ] **Step 4: Run `vendor/bin/pest tests/Feature/BoostGuidelinesTest.php`** → green (it compiles documented atom tags — the new `<atom:tiptap*>` tags + the `<atom:editor>` alias must all compile). Then full suite.

- [ ] **Step 5: Commit** — `git add resources/boost/guidelines/core.blade.php resources/views/docs/demos/editor.blade.php && git commit -m "docs(tiptap): Boost guidelines + editor docs for v3.6.0 (tiptap, cast, migrate, type=module)"`

---

### Task 6.3: SHIP v3.6.0 (controller-only — NOT a subagent task)

The controller performs these (irreversible: merge to main, tag, push). Run from the worktree, operate on the primary checkout via `git -C`.

- [ ] **Step 1: Final verification in the worktree**
```bash
cd /Users/tj/Projects/jiannius/atom/.claude/worktrees/tiptap-rebuild
npm run build          # clean
vendor/bin/pest        # ALL green
git status --short | grep -v '^?? vendor' | grep -v '^?? node_modules'   # only intended, or clean
```

- [ ] **Step 2: Squash-merge into main** (per CLAUDE.md squash-by-default). The primary checkout is at `/Users/tj/Projects/jiannius/atom` on `main`.
```bash
MAIN=/Users/tj/Projects/jiannius/atom
git -C "$MAIN" status --short      # confirm clean
git -C "$MAIN" merge --squash worktree-tiptap-rebuild
git -C "$MAIN" commit -m "feat(tiptap): <atom:tiptap> — Tiptap v3 rich text editor (replaces <atom:editor>)

Greenfield v3 rebuild: floating-ui menus, hybrid preset/slot toolbar, JSON
storage via AsTiptapContent (dual-reads legacy HTML), server-side render via
ueberdosis/tiptap-php (<atom:tiptap.content>), pluggable image persistence
(tiptapStoreImage), <atom:tiptap.chat> composer, @-mentions on floating-ui,
atom:tiptap-migrate. <atom:editor> retained as a back-compat alias. atom.js
now ships as an ES module (loaded type=module by <atom:html>).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

- [ ] **Step 3: Tag + push**
```bash
MAIN=/Users/tj/Projects/jiannius/atom
git -C "$MAIN" tag v3.6.0
git -C "$MAIN" push origin main
git -C "$MAIN" push origin v3.6.0
```

- [ ] **Step 4: Worktree cleanup** — `ExitWorktree` with `action: "remove"` (`discard_changes: true` — the per-task commits live in the squash on main).

- [ ] **Step 5: Update memory** — mark the tiptap rebuild SHIPPED v3.6.0; record the verify-on-hb debt; clear the now-stale "RESUME AT" / worktree-keep notes.

---

## Self-review notes
- Aliases forward via `:attributes="$attributes"` — the established atom idiom (input→uploader). Legacy props ride in the attribute bag; `<atom:tiptap>` binds its matching `@props`.
- `editor.css` is intentionally KEPT (consumers may reference it; the alias loads tiptap.css via the inner component). Not deleted.
- The `editor()` factory deletion is safe only because nothing else uses it — Task 6.1 Step 1 grep is the gate; STOP if it finds anything.
- Ship steps are controller-only (squash/tag/push/cleanup) — do not delegate.

## Done when
- `<atom:editor>`/`.chat`/`.content` render as aliases; old editor blades + `editor.js` gone; build clean; full suite green.
- Boost + editor docs updated; BoostGuidelinesTest green.
- main has the squash commit; `v3.6.0` tagged + pushed; worktree removed; memory updated.

## VERIFY-ON-HUMBLEBEAR (post-ship debt — atom's rig can't test these)
Editor: image upload→persist, `wire:model` JSON round-trip, `<atom:tiptap.content>` SSR vs the live editor's HTML, chat send/paste/drop, mention `@`-trigger/keyboard/`$wire`-callback/positioning, atom.js `type=module` + Livewire `navigate`, `atom:tiptap-migrate` on real data, the `<atom:editor>` alias parity. Plus the pre-tiptap debt (recaptcha v3.5.18, select a11y v3.5.19, table cross-page v3.5.20, uploader drag-drop v3.5.21).
