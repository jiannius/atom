# atom v4.0.0 plan

**Scope:** a Flux-informed foundation, an architecture review, code simplification, and the host migration.

Prepared 2026-09-29 against `jiannius/atom` `v3.29.5` (`e086c91`). Research was read-only: nothing in atom or in any host repo was changed. This replaces the 3.x-phased draft, which is archived as `v4-flux-evidence.md` in the same folder.

---

## 0. Reading guide

### Evidence tags

| Tag | Meaning |
|---|---|
| **[V]** | Verified by reading source (`path:line`). |
| **[V-run]** | Verified by running something in the scratchpad (never inside atom or a host). |
| **[V-scan]** | Measured by the host-usage scanner (`scratchpad/usage/scan.py`, `vals.py`, `props.py`, `inv.py`; raw output in `usage/usage.json`). Regex-based, so treat counts as approximate. |
| **[Iss]** | From a Flux GitHub issue. |
| **[Doc]** | From fluxui.dev. |
| **[I]** | Inference or judgement. Not verified. |

### Flux sources

- `livewire/flux` HEAD `ee9ad7f` (v2.20.0+3). The free JS is minified only; I formatted `dist/flux-lite.min.js` into `scratchpad/fluxjs/flux-lite.js`, and every Flux JS line number refers to that file.
- Flux Pro Blade and Flux's tests are not public. Public CI is `composer validate` + `php -l`.
- All 1,586 public issue titles are in `scratchpad/issues/all.tsv`.

### Hosts found on disk (`~/Projects/jiannius`) [V]

| Host | atom required | atom locked | Livewire | Tailwind installed | Notes |
|---|---|---|---|---|---|
| **humblebear** | `^3.27.5` | v3.27.5 | v4.3.1 | 4.0.8 | |
| **sudo** | `^3.24` | v3.24.0 | v4.3.0 | 4.0.8 | |
| **apikan** (repo) | `^3.0` | **v3.0.2** | v4.3.0 | 4.0.8 | |
| **skeleton-project** | `^3.0` | v3.1.0 | v4.3.1 | 4.0.8 | The template for new projects. |
| **permission** (package) | — | — | — | — | One Blade file uses `<atom:>` tags. |

- **humblebear-l2 is not on `jiannius/atom`.** It requires `jiannius/atom-livewire` 0.51.79 on Laravel 10 (`humblebear-l2/composer.json`). Its `<atom:…>` tags belong to that older package, so the v4 migration does not apply to it.
- **smgdms and toocrm are not on disk.** Nothing about them is verified.

---

## 1. Summary

### The decision

- **atom v4.0.0 is a major release**, built on a long-lived **`v4` branch**. `main` stays 3.x until general availability (GA).
- Breaking changes are allowed wherever they remove a bug class or buy real simplification. Compatibility shims exist only where they are one line and clearly worth it.
- **A separate 3.x maintenance track (security and data-loss only) starts now.**

### What urgently needs fixing in 3.x (details in §2)

1. **Stored XSS through remote select options: verified in code, reachable in humblebear.** A contact's `name` travels from `humblebear/app/Actions/GetOptions.php:302-303` through atom's `GetOptions::handle()` → `getOptionHtml()`, which concatenates it unescaped (`src/Actions/GetOptions.php:313`, `:477`, `:485-501`). It then reaches `select.js` `option.html` (`resources/js/alpinejs/select.js:201-202`) and is rendered with `x-html` (`components/select/listbox.blade.php:287`). humblebear wires this path up through `<x-select.contact>` → `'options' => 'contacts'` (`humblebear/resources/views/components/select/contact.blade.php:10`). I have not exploited or tested it; the data path is verified.
2. **Data loss in `atom:purge-editor-images` once a host migrates to Tiptap.** The command treats only `AsEditorContent` columns as references (`src/Commands/PurgeEditorImages.php:68`) and extracts `<img src>` from HTML (`:75-84`). But `AsTiptapContent` writes to the same `…/editor/` folder (`src/Casts/AsTiptapContent.php:119-123`) and stores JSON. Running the command without flags deletes every Tiptap image (`:28-38`). It has no dry run despite what CLAUDE.md implies, and it only scans the top level of `app/Models` (`:98-116`). **No on-disk host has migrated yet** (humblebear still has 1 `AsEditorContent` column, `Task::description`) [V], so nothing on disk is affected today.
3. **The `Builder::filter()` column oracle** (same class as #54). Filter keys come from the host's public, client-writable `$filters` (41 humblebear files declare `public $filters` [V]). Any key naming an existing column, discovered through `show columns`, becomes a `where`/`like` on that column (`src/Macros/Builder.php:168-268`, `:113-166`). That allows boolean-blind probing of columns that are never displayed. The mechanism is verified; I have not verified which host models expose sensitive columns.

4. **The editor (`<atom:tiptap>`) carries four more security issues** (full audit in §10):
   - it bundles `prosemirror-view` 1.41.9, which is affected by the high-severity paste-XSS advisory GHSA-c8x8-7fp4-3x9w;
   - its cast `unserialize()`s client-supplied strings;
   - its server renderer puts any URL into an `<iframe src>`;
   - its chat composer hands raw HTML to hosts, and humblebear stores it and renders it with `x-html`.
   
   Fixes: 3.x patches A6–A8. v4 verdict: **rewrite on the same Tiptap engine** (milestones ME1–ME6).

### Top architecture changes in v4 (details in §4)

- **Split the `AtomComponent` "god trait".**
  - The toast/modal/alert/confirm/command/wirekey helpers move to **`Livewire\Component` macros**, which is Flux's pattern: `Component::macro('modal', …)` in `flux/src/Concerns/InteractsWithComponents.php:17`.
  - Host `$this->toast()` call sites don't change (206 + 208 + 132 + 129 uses [V-scan]).
  - Macros are not browser-callable, because Livewire exposes only reflected public methods (`vendor/livewire/livewire/src/Drawer/BaseUtils.php:108-121`, `HandleComponents.php:684-693`) [V].
  - Table, editor, breadcrumbs and recaptcha state become opt-in traits.
  - `WithFileUploads` stops being forced onto every component.
- **Alpine plus a shared JS core** (the "Flux mixins on Alpine's lifecycle" option from the draft): component, durable, ids, events, keyboard and overlay. It replaces per-factory lifecycle, positioning and keyboard code.
- **The host-imported `atom.tailwind.css` becomes the only CSS integration.** It provides `@source`, `@theme` tokens and `@custom-variant dark`, replacing each host's hand-kept `@source` list.
- **No global side effects by default.**
  - The global `Date::use(Carbon)` swap moves to one line in each host.
  - JS prototype extensions and the `window.dd`/`window.empty` globals are removed.
  - Unused macros are deleted; component-only macros become internal.
- **Safe by construction.**
  - Output is escaped by default; HTML is opt-in through `HtmlString` or `trusted_html`.
  - Remote options are structured data rendered with `x-text`.
  - `filter()` requires a column allowlist.
  - Asset serving is limited to files listed in the manifest.
- **Docs are a Livewire-backed demo app whose partials double as conformance fixtures.** The Boost guideline is generated and slimmed.

### Simplification headlines (details in §5)

- **Dead code:** about 390 lines of PHP, 153 lines of JS prototypes, 1 unreferenced built stylesheet (`editor.css`, 158 lines), 1 dead prop, and 6 dead macro methods.
- **Unused props:** 122 of the 272 props declared by the components hosts actually use are never passed by any on-disk host. About 20 of those are auto-derived plumbing (`name`, `error`, `for`, `labelId`); the rest are removal candidates.
- **Duplication:** measured duplicated sibling code of about 510 lines. Examples: select listbox/filter share 156 lines, the two paginations 93, alert/confirm 59, chart bar/area 76, SendBroadcast/Now 63.
- **Unadopted components:** 13 of 141 components are used by no on-disk host (accordion, command, context menu, and others).

---

## 2. 3.x security and critical track (runs now, on `main` → later a `3.x` branch)

**Rules for this track:**
- It only takes exploitable security bugs and data-loss bugs.
- Each fix is a patch release: v3.29.6, v3.29.7, and so on.
- Each fix is **forward-merged into `v4`** within the same week.

| # | Issue | Evidence | 3.x fix (patch) | v4 fix |
|---|---|---|---|---|
| **S1** | Stored XSS via remote option `label`/`caption` | [V] data path above; also `{!! t($label) !!}` in `components/input/field.blade.php:19` and the checkbox, radio, toggle, badge, rating and slider components; `t()` does not escape (`src/Helpers.php:71-79`) | Escape `label` and `caption` in `GetOptions::getOptionHtml()` and in the `select.js` fallback builder (`:205-209`). Keep the `html` key as an explicitly trusted pass-through, documented as "you escape it". Size **S**. | Remote options become structured (`label`, `caption`, `avatar`, `color`, `badge`) and render with `x-text`. `trusted_html` is the only HTML key. `{!! !!}` is allowed only at allowlisted sites. |
| **S1-host** | humblebear builds option `html` with unescaped user data | [V] `humblebear/app/Actions/GetOptions.php:376-389` (`$doc->name`, `$doc->number`) and `:436` | **Host PR in humblebear**, not atom: wrap the fields in `e()`. Coordinate with S1. | The same code moves to `trusted_html` with a lint. |
| **S2** | `atom:purge-editor-images` deletes Tiptap images after migration | [V] as above | Scan `AsTiptapContent` columns too (walk the JSON image nodes); recurse `app/Models`; resolve the disk exactly the way the casts do; make **dry run the default**, with `--delete` to act. Size **S**. | Rewrite against the Tiptap JSON only (the legacy cast is removed); read disk and folder from `config('atom.editor.*')`. |
| **S3** | `filter()` targets any existing column from a client-set key | [V] mechanism; [unverified] exploitability | **Decision needed (Q5):** either an opt-in model allowlist (`protected array $filterable`) enforced when present, plus a log line when a key hits a column outside `$fillable`/`$visible`; or defer to v4 only. Size **S–M**. | A column allowlist is required. `show columns` becomes `Schema::getColumns()` (portable). The 7-day column cache (`Builder.php:127`) is invalidated after migrations. |
| **S4** | Public `AtomComponent` methods are browser-callable, and their return values are sent to the client (`HandleComponents.php:695-698`) | [V] 21 atom-declared public methods, 32 including WithPagination/WithFileUploads (`tests/Feature/AtomComponentSurfaceTest.php:21-82`) | **Audit only** (Pest: call each one as a guest on a fixture and record side effects and returns). Fix in 3.x only if something is exploitable. Size **S**. | Helpers become Component macros; table actions stay as explicit public actions; everything else is `protected`. |

**How long 3.x is supported:** until every host listed as active (humblebear, sudo, apikan, skeleton-project, plus smgdms and toocrm if their owners confirm) runs v4 in production, **plus 90 days** [I]. Announce the end-of-life date at v4.0.0 GA. After that, 3.x takes security fixes only on request.

---

## 3. Flux findings, condensed

The full per-topic evidence is in the archived draft, §1.

1. **Morph survival [V].**
   - Custom elements (`ui-*`) whose JS-owned attributes and inline position are made "durable": a MutationObserver re-asserts them after a morph (`flux-lite.js:1537-1607`, `4251-4283`).
   - Lists are server-rendered, with an automatic option `wire:key` (`stubs/.../select/option/variants/default.blade.php:10`).
   - Client-minted ids are seeded through `_x_bindings.id` (`flux-lite.js:1338-1346`; `livewire.esm.js:9001-9008`).
   - `wire:ignore` appears only where the browser owns the state (`<dialog>`, toast).
   - `wire:model` binds to an `el.value` getter/setter with non-bubbling `input`/`change` events (`flux-lite.js:1843-1879`).
   - **Verdict: adapt** the durable attribute and position idea, server-rendered lists and id seeding; **skip** custom elements.
   - In Alpine, `x-bind` attributes already survive a morph (`livewire.esm.js:2633-2643`, `8686-8687`); imperatively set attributes do not.
2. **Lifecycle [V] and [Iss].**
   - Boot once per node, mount deferred to a microtask, unmount on real disconnect (`flux-lite.js:1718-1786`).
   - Injected nodes are tagged `data-appended` and removed at boot (`1992-1994`, `5778`).
   - Custom elements still produced a lifecycle bug class: #2605 (boot on detached clones), #2524 (children not parsed yet), #2678/#1565 (skipped mount), #2653/#2707 (leaks), #2708 (duplicates after back/forward).
   - Livewire snapshots the **live** DOM for back/forward (`livewire.esm.js:12583-12590`).
   - **Verdict: adopt the discipline, not the vehicle.**
3. **Overlays [V].**
   - One Popoverable (`popover="manual"`, own light-dismiss, scopes, focus restore; `flux-lite.js:2870-2970`).
   - One Anchorable (floating-ui with offset, flip, shift, size, hide and autoUpdate; strategy and inline `position` from one variable; durable; cleanup; `4142-4283`).
   - `<dialog>` with `closedby`.
   - Flux's JS scroll lock is its most-reported overlay bug family (#1251, #839); atom's CSS `:has()` lock avoids it.
   - **Verdict: adopt the overlay shape, keep atom's CSS scroll lock.**
4. **CSS [V] and [V-run].**
   - `dist/flux.css` is a Tailwind v4 source file the host imports: `@source "../stubs"`, `@theme` accent tokens, `@custom-variant`, and `@layer base` rules (`flux.css:1-37`, `173-212`).
   - Visual defaults use `[:where(&)]` (`2197a4f`).
   - **Correction to atom's record:** bare `data-active:` and `backdrop:` variants *do* compile on Tailwind 4.0.7 (`scratchpad/tools/twtest/out.css`).
   - **Verdict: adopt**, and in v4 make it the only integration path.
5. **Blade [V].**
   - The tag compiler is the same code as atom's (`flux/src/FluxTagCompiler.php` vs `src/Services/TagCompiler.php`).
   - `with-field` composition, and routing prefixed attributes to sub-elements (`FluxManager.php:108-167`).
   - **Verdict: adopt prefix routing; keep atom's tag compiler.**
6. **Accessibility [V].**
   - Two shared keyboard primitives: activedescendant for listboxes (`6013-6136`) and roving tabindex for menus (`4806-4884`).
   - The error region is always present (`error.blade.php:25-29`).
   - Labels are wired client-side, which atom's server-derived ids already beat.
   - **Verdict: adopt the primitives and the live region.**
7. **Events [V].**
   - `Flux::modal()->show()` adds `scope` inside components (`InteractsWithComponents.php:15-53`).
   - The JS API always sends an object `detail` (`flux-lite.js:7406-7450`).
   - **Verdict: adapt** (atom's names stay the same; add a normaliser and scope).
8. **Testing [Iss].**
   - No public tests.
   - Recurring themes: navigate lifecycle races, teardown leaks, back/forward duplicates, bubbling (`.self`, which Livewire 4 now adds by default: `livewire.esm.js:15163-15167`), Escape propagation, scroll lock, dark-mode flash on navigate, CSP compatibility (#2277), and layout shift from JS-applied display rules (#2400).

---

## 4. Architecture review of the whole package

**How to read the table.** Each row has four fields:
- **Now**: what atom has today (evidence tag and citation in brackets).
- **Verdict**: keep, change or remove.
- **Why**: the reason for the verdict.
- **v4 design**: what the new version does, with any better precedent (Flux, Livewire or Laravel) noted.

| Area | Now | Verdict | Why | v4 design (better precedent) |
|---|---|---|---|---|
| **`AtomServiceProvider`** (105 lines) | [V] `register()` sets the `atom` alias and the tag precompiler; it's registered in `register()` on purpose so it runs before Livewire's `wire:key` precompiler (`:19-29`). `boot()` loads routes, a migrations dir that doesn't exist (`:37`), translations, views and anonymous components. It then applies the **global Date swap** (`:79`) and **mixes macros into 7 framework classes** (`:87-93`), registers commands, boots `Asset`, and defines `POST /atom/action/{name}` inline (`:49-53`). | **Change** | A UI library changes app-wide behaviour (dates, macros) without being asked, and the routes are split between the provider and `routes/web.php`. | The tag compiler stays in `register()`. **No `Date::use`.** Macros are only those that earn their keep (row below). All routes live in one `routes/atom.php` under a `name('atom.')` group with controllers (`AssetController`, `ActionController`). The missing migrations call goes. **Flux:** its provider is thin, with asset routes in `AssetManager`, `flux/src/FluxServiceProvider.php:22-45`. |
| **`Atom` singleton, `app('atom')`** (395 lines) | [V] A grab-bag: `asset`, `recaptcha`, `broadcast`, `sitemap`, `uid` (no callers), `action`/`webAction`, `modal`, `command`, `toast`, `alert`, `confirm`, `breadcrumbs`, and an 18-argument `mail()` (`src/Atom.php:17-395`). **There is no `atom()` helper**, even though CLAUDE.md implies one. Host use [V-scan]: `broadcast` 15, `mail` 13, `sitemap` 3, `webAction` 15. | **Change** | One class mixing UI dispatch, transport (mail and broadcast), SEO and security makes the surface unclear and the class hard to test. | An **`Atom` facade over a small `AtomManager`** that handles UI dispatch only: `toast`, `modal`, `alert`, `confirm`, `command`, `action`. `broadcast`, `sitemap` and `mail` move to `Jiannius\Atom\Support\{Broadcast,Sitemap,Mail}` with direct construction or their own facades (see Q2). `uid()` is removed. **Flux:** a small `FluxManager` plus the `Flux` facade (`flux/src/FluxManager.php`). |
| **Builder macros** (351 lines) | [V] `toTable`, `toPage`, `filter`, `whereDateBetween`, `breakdown`, `randomCode`, `tableColumns`, `tableHasColumn`, `tableColumnType`. Host use [V-scan]: `toTable` 46, `toPage` 45, `filter($this->filters)` 39, `breakdown` 15, `whereDateBetween` 9, `randomCode` 0, `tableHasColumn` 0. `show columns` is MySQL-only, and the column cache lasts 7 days (`:127`). | **Change** | `filter()` is the S3 oracle. The schema cache goes stale after a migration (a filter on a new column is silently ignored for 7 days [I]). The macros are mixed into **Query and Eloquent** builders both. | **Keep** `toTable`, `toPage`, `filter` and `whereDateBetween`, on the Eloquent builder only. `filter()` requires an allowlist: the model's `$filterable`, or explicit keys passed to `filter($filters, only: [...])`. Columns come from `Schema::getColumns()`, cached per request. **Move** `breakdown` into humblebear (the only user). **Remove** `randomCode` and `tableHasColumn`. |
| **ComponentAttributeBag macros** (132 lines) | [V] 9 macros are mixed into every Blade component in every host. Used internally: `fieldId` (8), `modifier` (1), `hasLike` (1). **Unused anywhere:** `size`, `field`, `getAny`, `getLike`, `classes`, `styles`. Hosts use none of them [V-scan]. | **Change** | Global pollution of every host's attribute bag for 3 internal call sites. | A single internal `Jiannius\Atom\View\Attributes` helper, called as `Attr::fieldId($attributes, …)`. Add the prefix-routing helper `Attr::after($attributes, 'input:')` (Flux `attributesAfter`). The 6 unused macros are deleted. |
| **Str, Stringable, Arr, Request macros** | [V] Str/Stringable: `namespace` (used by `resolveAction`), `dotpath` (0 uses), `interval` (host 3), `initials` (4 internal, 5 host). Request: `portal` (host 3), `subdomain`, `hostWithoutSubdomain` and `isLivewireRequest` (0; the one internal "use" is Livewire's own method, `components/navlist/item.blade.php:31`). `Arr::pick` (host 47, internal 1). | **Change** | Most of these are app helpers, not UI. | **Keep** `Arr::pick`, `Str::initials` and `Str::interval`. `namespace` becomes a private method. **Remove** `dotpath`, `subdomain`, `hostWithoutSubdomain` and `isLivewireRequest`. **Move** `portal` into humblebear. |
| **Global PHP helpers** (`src/Helpers.php`) | [V] `num()`, `is_enum()`, `is_using_trait()`, `t()`, `js()`, `carbon()`, all behind `function_exists`. Host use [V-scan]: `t` 5,026, `num` 665, `carbon` 590, `js` 275, `is_using_trait` 6, `is_enum` 0 (5 internal). | **Keep** (except `is_using_trait` → move to host) | Heavily used; renaming would churn 6,000+ call sites for no bug gain. | Keep the names. Document that **`t()` returns plain text** and that atom never calls it inside `{!! !!}`. |
| **`t()` shim** | [V] `t($str, $count = 1, $params = [])`: a numeric `$count` means `trans_choice`, an array means `__($str, $count)` (`Helpers.php:71-79`). | **Keep**, with sharper semantics | 5,026 host uses. The ambiguous second argument is the only wart. | Unchanged signature. Add a Pest test pinning both call forms. Components stop wrapping `t()` output in `{!! !!}`. |
| **Carbon Date swap** | [V] `Date::use(\Jiannius\Atom\Services\Carbon::class)` (`AtomServiceProvider.php:79`) turns every `now()` and Eloquent date into an **immutable** subclass (`Services/Carbon.php:7`) with `pretty()`, `local()`, `recent()`, `toDateRangeString()`. humblebear references the class directly 69 times [V-scan]. | **Change** | An app-wide semantic change (mutable → immutable) imposed by a UI kit. | Keep the `Carbon` class and the `carbon()` helper. **Remove the swap from the provider.** `atom:upgrade` adds `Date::use(\Jiannius\Atom\Support\Carbon::class);` to the host's `AppServiceProvider`, so hosts keep today's behaviour explicitly. |
| **JS prototypes and globals** | [V] `Array.prototype` gets `pluck/unique/sum/where/firstWhere/toggle`; `String.prototype` gets 11 methods, including `toDateString` and `toTimeString`; `Number.prototype.currency` (`resources/js/prototypes/*.js`, 153 lines). Globals: `window.atom`, `window.dd` (= `console.log`), `window.empty` (`resources/js/atom.js:62-64`). Host use [V-scan]: in Alpine expressions `.currency()` 15, `.headline()` 4, `.limit()` 1; `empty()` 27; `dd()` 1 (sudo); `atom.*` ~60. | **Remove** prototypes, `dd` and `empty` | Extending built-in prototypes risks colliding with future native methods and with other libraries, which is the "smooshgate" class of problem [I]. `dd` shadows a Laravel idiom for a mere alias. | `window.atom` only, plus an Alpine magic `$atom`. Helpers live on `atom.str.*`, `atom.num.currency()`, `atom.empty()`. `atom:upgrade` rewrites the ~47 host sites. **Flux:** only `window.Flux` and `$flux` (`flux-lite.js:7470-7472`). |
| **`AtomComponent` trait** (337 lines) | [V] Forces `WithPagination` **and `WithFileUploads`** on every host component. It declares four public state buckets (`$_breadcrumbs`, `$_table`, `$_editor`, `$_recaptcha`) that are serialised into every snapshot. It exposes 21 atom-declared public methods (32 with the bundled traits), all browser-callable (S4). It holds the `_table` signing gate (`:82-125`). Host use [V-scan]: ~466 host components use it; `$this->modal` 208, `toast` 206, `alert` 132, `wirekey` 129; `_table` 57 references; the `breadcrumbs()` convention 161. | **Change (split)** | Payload bloat, a forced upload endpoint, a browser-callable helper surface, and four concerns in one trait. | 1. **Component macros** for `toast`, `modal`, `alert`, `confirm`, `command`, `wirekey` (no host change). 2. **`WithAtomTable`**: `$_table`, the sort gate and the table actions (explicitly public and documented). 3. **`WithAtomEditor`**: `$_editor` plus `WithFileUploads`. 4. **`WithBreadcrumbs`**: computed, not a public prop, if the breadcrumbs JS can read it from a `data-` attribute instead. 5. **`WithRecaptcha`**. Everything else is `protected`. `atom:upgrade` swaps `use AtomComponent` for the traits each component actually needs. |
| **Actions, `WebAction`, `GetOptions`** (505 lines) | [V] `resolveAction` (App class wins over the package class); `webAction` gate (`Atom.php:88-138`). `GetOptions` has `PUBLIC_OPTIONS`/`$guest`/`$auth` allowlists, JSON merge, and a cache with **no TTL** (`GetOptions.php:447-465`) [I: host JSON edits don't show until `cache:clear`]. Host use: 4 `GetOptions` subclasses, 1 `WebAction` (`SearchMyinvoisTin`), `atom.action` from JS 3 times. | **Keep and harden** | The design is sound since v3.19–v3.20. The remaining problems are output escaping (S1) and the unbounded cache. | Options are structured data (S1 v4). The cache is keyed by name with a TTL and busted on deploy by the file's mtime. `webAction` moves into `ActionController`. Named option methods get a `#[Option(guest: true)]` attribute instead of three parallel arrays [I: simpler, less drift]. |
| **TagCompiler** (164 lines) | [V] A near-copy of `FluxTagCompiler`. Hazard: an `<atom:…>` inside a `//` comment in `@php`/`@props` compiles by accident (`atom-tagcompiler-hazards.md`). | **Keep** | It works, and matches Flux. | Add a Pest lint that fails on `<atom:` inside `//` or `#` comments in components. Optionally adopt Flux's `delegate-component` for variant dispatch (`flux/src/FluxTagCompiler.php:12-23`) to replace the `x-dynamic-component` plus `:attributes` forwarding in `select`, `input` and `date-picker` (`components/select/index.blade.php:62-77`). |
| **Services directory** | [V] `Asset`, `Broadcast`, `Carbon`, `Color` (about 34 live lines plus ~120 lines of commented-out code: 86 `//` lines and the rest in block comments), `Docs`, `Recaptcha`, `Sitemap`, `TableSort`, `TagCompiler`. | **Change** (re-layer) | A flat bag mixing view, security, support and transport concerns. | `src/View/` (TagCompiler, Assets, Attributes, Docs), `src/Security/` (Signed, the generalised TableSort; Recaptcha), `src/Support/` (Carbon, Color, Broadcast, Sitemap, Mail), `src/Livewire/` (traits, macros), `src/Http/` (controllers), `src/Database/` (Builder macros, casts, Enum). Delete Color's dead comments. |
| **Editor cast and purge** | [V] `AsEditorContent` (legacy HTML, `env('FILESYSTEM_DISK')`) and `AsTiptapContent` (JSON, `config('atom.editor.disk')`) share 50 lines. `MigrateTiptapContent` exists. Purge is coupled to the legacy cast only (S2). `<atom:editor*>` are alias components (`components/editor/*.blade.php`). humblebear still uses the legacy cast on 1 column (`Task::description`) and `<atom:editor*>` in 5 views [V]. | **Remove the legacy cast in v4; rewrite purge** | Two casts and one command already drifted into a data-loss bug. | v4 ships `AsTiptapContent` only; the alias components and `editor.css` are removed. Purge reads Tiptap JSON and shares its disk/folder resolver with the cast. **Precondition:** hosts run `atom:tiptap-migrate` on 3.x first; `atom:upgrade` refuses to continue while any `AsEditorContent` cast remains. |
| **Asset serving** | [V] A catch-all `GET /atom/{file}` over `dist/assets` with an immutable cache header and content type limited to css/js (`src/Services/Asset.php:30-49`). It shadows any host `/atom/*` route and has no 304. `<atom:html>` injects the CSS, the module script (with `data-navigate-once`) and fonts, placing the script and fonts **after `</body>`** (`components/html.blade.php:180-187`). No CSP nonce. Committed `dist/` is 1.3 MB (apexcharts 580 KB and tiptap 475 KB lazy-loaded; core `atom.js` 100 KB). | **Change** | Hardening plus Flux parity. | `AssetController` serves **only filenames listed in `manifest.json`**, with correct MIME types and Last-Modified/304. `@atomStyles` and `@atomScripts(nonce: …)` directives so hosts without `<atom:html>` can include assets. Script inside `<body>`. **Flux:** per-file routes, 304, nonce, `data-navigate-once` (`flux/src/AssetManager.php`). Keep committing `dist/`, but **CI fails if `npm run build` changes `dist/`**. |
| **`<atom:html>` shell** (188 lines, 16 props) | [V] The document shell plus SEO meta plus GTM/GA/FB pixel plus fonts plus dark bootstrap. Hosts pass only `title`/`noindex` (plus `dark` ×3) and configure analytics through `config/page.php` env keys [V-scan]. | **Change** | Too many concerns in one component. | `<atom:html>` = shell + assets + dark bootstrap. `<atom:head.seo/>` and `<atom:head.analytics/>` partials read `config('page.*')` and are included by default. The dark bootstrap re-applies inside `livewire:navigating` → `onSwap` (Flux `flux-lite.js:7475-7479`) rather than after `livewire:navigated` (`html.blade.php:161`). |
| **Docs system** | [V] Local-env routes (`routes/web.php:6-25`), `Services\Docs` parses `@props` (172 lines), `resources/views/docs` (≈170 files, 1,472 lines). Demos are plain Blade with no `$wire`, so no Livewire demo is possible (memory). `resources/views/e2e` (12 test views) and a test route ship inside the package. | **Change** | Docs, e2e fixtures and conformance fixtures are three parallel example sets. | Docs pages become **Livewire-backed** (a `DocsPage` component). Each demo partial is also a conformance fixture (§7). E2E views and routes move to `workbench/`/`tests/`. Docs routes stay local-only. |
| **Boost guideline propagation** | [V] `resources/boost/guidelines/core.blade.php` (397 lines) is copied into host CLAUDE.md on every `composer update`, but humblebear's copy is hand-diverged (`atom-boost-guidelines-propagation.md`). It overlaps README (940 lines) and CLAUDE.md (164 lines) [I]. | **Change** | Three hand-kept copies of the same facts drift. | Slim guideline (~120 lines [I]): principles, the component contract, "read `vendor/jiannius/atom/resources/views/docs/demos`", the version, and an **UPGRADE pointer**. Prop tables are generated from `@props` docblocks by `Services\Docs`. A Pest test keeps the guideline and README in sync with the generated tables. **Never rely on the guideline to deliver an upgrade step.** |
| **Directory layout** | [V] `components/` at the root, `json/` at the root, `resources/{css,js,views,boost}`, `docs/superpowers` (planning notes). `tests/` (676 KB) and `docs/` (536 KB) ship to every host because there is **no `.gitattributes`**. `composer.lock` is committed in a library. No CI config at all. | **Change** (minimal) | Hosts' `@source` lines hardcode `vendor/jiannius/atom/components/**`. | Keep `components/` (moving it breaks host CSS for no gain). Move `json/` to `resources/json/`. Add `.gitattributes export-ignore` for tests, docs, workbench and configs. Add GitHub Actions CI. Delete `composer.lock` from the library [I: conventional for libraries]. |
| **JS architecture** | [V] 23 Alpine factories plus 28 inline `x-data="{…}"` objects in Blade. Hand-rolled idempotency, positioning and keyboard handling per factory (archived draft §2). | **Change** | This is the root of the bug classes (§6). | Alpine plus a shared core (§6.1). Inline `x-data` objects move into registered factories, which also makes atom compatible with Livewire's CSP build (Flux #2277 [V] `6865c8b`). |

---

## 5. Code-simplification audit

Line counts are from `wc -l`. The "measured" ones come from the scripts in `scratchpad/usage/`.

### 5.1 Dead or unused code (remove in v4)

| Item | Evidence | Size |
|---|---|---|
| `Color.php` commented-out code | [V] about 120 dead lines (86 `//` lines plus block comments), about 34 live | ~120 |
| Unused attribute-bag macros: `size`, `field`, `getAny`, `getLike`, `classes`, `styles` | [V] 0 uses in atom or on-disk hosts | ~70 |
| Unused Request macros: `subdomain`, `hostWithoutSubdomain`, `isLivewireRequest` | [V] | ~50 |
| `Str`/`Stringable` `dotpath` and the duplicated `Stringable` copies | [V] | ~35 |
| `Builder::randomCode`, `tableHasColumn` | [V] 0 uses | ~30 |
| `Atom::uid()` | [V] no callers anywhere (memory confirms it was kept only as host API; hosts: 0) | ~15 |
| `AsEditorContent` plus `<atom:editor*>` aliases | [V] legacy after the Tiptap migration | 75 + 3 files |
| `resources/css/editor.css`, built as `dist/assets/editor-*.css` | [V] **never referenced**: no `asset()->version('editor.css')` anywhere | 158 |
| `<atom:html editor>` and `<atom:layouts.sidebar editor>` props | [V] declared (`html.blade.php:16`) but never read | 2 props |
| JS prototypes, `window.dd`, `window.empty` | [V] | 153 |
| Non-existent `database/migrations` load | [V] `AtomServiceProvider.php:37` | 1 |
| E2E views and a test route inside the package | [V] `resources/views/e2e` (12 files), `routes/web.php:10-12` | moved, not deleted |

**Dead-code total:** about **390 PHP + 153 JS + 158 CSS lines**.

### 5.2 Duplication across siblings (measured with difflib, lines shared)

| Pair | Lines each | Shared | Consolidation |
|---|---|---|---|
| `select/listbox` vs `select/filter` | 299 / 241 | **156** | One listbox implementation; `variant="filter"` becomes a chip-trigger mode. |
| `table/pagination` vs `pagination` | 125 / 114 | **93** (ratio 0.78) | `table.pagination` renders `<atom:pagination>`. |
| `alert` vs `confirm` | 74 / 129 | **59** | One dialog base: alert = confirm without a cancel button. |
| `toast` vs `alert` | 120 / 74 | 37 | Share the notification item partial. |
| `chart/bar.js` vs `chart/area.js` | 109 / 119 | **76** | One `chart(type)` factory. |
| `SendBroadcast` vs `SendBroadcastNow` | 64 / 64 | **63** | One event class plus a `now` flag. |
| `AsEditorContent` vs `AsTiptapContent` | 75 / 129 | 50 | Moot: the legacy cast is removed. |
| `date-picker/date` vs `range` Blade | 63 / 97 | 39 | A shared trigger/field partial. |
| `select.js` vs `command.js` | 330 / 121 | 54 | Both move to `core/keyboard.activatable()`. |
| `dropdown.js` vs `context-menu.js` | 93 / 87 | 28 | Both move to `core/overlay`. |

**Duplication total:** about **510 lines consolidated**, from the measured shared lines after removing one side.

### 5.3 Per-component code the shared core replaces [I, estimates]

| Area | Lines replaced |
|---|---|
| Lifecycle, listener and injected-node code in `select`, `dropdown`, `tooltip`, `context-menu`, `date-picker`, `date-range`, `time-picker`, `command`, `modal`, `lightbox`, `mention` (1,425 lines together [V]) | ~350–450 |
| Positioning (`helpers/floatingui.js` plus the structural position rules in `atom.css:149-168`) | ~70 |
| Keyboard (virtual focus in `select.js:223-260`, and its copy in `command.js`) | ~120 |

The core itself adds about 600–900 lines [I]. **The net JS size stays roughly flat.** The gain is one implementation per concern, not fewer bytes.

### 5.4 Merge, split, or trim

- **Merge:**
  - listbox + filter (above);
  - alert + confirm (above);
  - the two paginations (above);
  - `input.email`/`input.tel` wrapper logic (29% shared, `components/input/*`) into an input base;
  - `menu.item` + `navlist.item` link/button polymorphism (both reimplement `href`/`newtab`/`rel`/icon/badge: `menu/item.blade.php` has a 44-line PHP header, `navlist/item.blade.php` 33) → a shared `atom::_link-or-button` partial (Flux: `button-or-link.blade.php`).
- **Split:**
  - `button/index.blade.php` (205 lines, 170 of them PHP header, 13 props [V]): `type="delete"` implies inverted + danger + icon + confirm wiring, and `social` implies variant + icon. Split into `<atom:button>` (variant, size, icon) plus `<atom:button.social>` plus a delete behaviour carried by `<atom:confirm.trigger>`. Host use: `social` 6, `inverted` 3, `passphrase` 3, `type=delete` 47 [V-scan].
  - `modal/index.blade.php` (124 lines, 89 PHP) → derive from the dialog base.
  - `html.blade.php` → partials (§4).
- **Over-configurable props** [V-scan, on-disk hosts only]: among components hosts use, **122 of 272 declared props are never passed** (≈20 are auto-derived plumbing: `name`, `error`, `for`, `labelId`). The worst offenders:
  - `navlist.item`: 7 of 10 never passed (`as`, `iconDot`, `iconSuffix`, `badgeColor`, `accent`, `badge`, `count`);
  - `tooltip`: 4 of 5 (`interactive`, `position`, `align`, `kbd`);
  - `select`: 5 of 9 (`inline`, `prefix`, `suffix`, plus plumbing);
  - `textarea`: 5 of 10;
  - `tabs.item`: 5 of 9;
  - `menu.item`: 5 of 10;
  - `navlist.group`: 4 of 5;
  - `list.item`: 3 of 3.
  - **Default rule:** remove a never-passed prop unless it is a documented a11y/keyboard option or the component is new. The review list is generated by `props.py`.
- **Components with zero on-disk host adoption** (13; not reached through a variant hosts use): accordion(+item), command(+group/item/trigger), context-menu, kbd, standalone pagination, progress, rating, slider, `input.otp`, `table.actions`, `form.modal`, `toast.trigger`, `uploader.dropzone`. These are recent Flux-gap features (v3.7–v3.14). **Default: keep and migrate them to the core** (cheap); see Q3.
- **Heavy PHP headers:** `button` (170 lines), `modal` (89), `menu/item` (44), `heading` (41), `select/native` (37), `select/listbox` (36), `input/index` (34), `table/index` (34), `navlist/item` (33). Total **1,407 of the 7,461 component lines are `@php` blocks** [V]. The v4 rule: variant→class maps live in `match` blocks of ≤30 lines; derivations (ids, routing) move to `View\Attributes` helpers; no business logic in Blade.

---

## 6. v4 target design

The substance is unchanged from the archived draft §4. Only what differs for a major release is spelled out here.

### 6.1 JS: Alpine plus a shared core

Modules in `resources/js/core/`:

- `component.js`: `atomComponent(name, factory)` gives idempotent init (an element-scoped AbortController, aborting any previous one); cleanup of `[data-atom-appended]`; `listen()`, `appended()`, `onDestroy()`; exceptions are caught and named, so one component can't break Livewire's boot.
- `durable.js`: MutationObserver re-assertion of JS-owned attributes (Flux `1537-1607`).
- `ids.js`: `ensureId()` stores the id in `_x_bindings.id` so the morph seeds it (`livewire.esm.js:9001-9008`).
- `events.js`: `emit`, `on`, `readDetail`.
- `keyboard.js`: `activatable` (listbox) and `focusable` (roving tabindex).
- `overlay.js`: see below.

**v4 changes compared with the draft:**
- No shims. `Alpine.data` names may change where it simplifies things, e.g. one `listbox` factory instead of separate select and filter factories.
- `atom.floatingui` (6 humblebear uses) is replaced by `atom.overlay()`/`atom.anchor()` with no alias.
- **State attributes on morphable nodes are set only through `x-bind` or `durable()`.** Today's imperative `data-open`/`aria-expanded` in `dropdown.js:25,59-60,84-85` and `context-menu.js:31,72` is a latent morph bug, and it goes.
- **JS-generated lists live only inside declared islands** (`wire:ignore` + `data-atom-island`). Static options are server-rendered.

**Overlay primitive:**
- `popover="manual"`, with the floating-ui strategy and the inline `position` set from one constant, written together with `margin:0; right:auto; bottom:auto`.
- Size middleware; durable position; cleanup on every close and before every reopen.
- Scopes; Escape `stopPropagation`; focus restore; close on `livewire:navigating`.
- Parts are found through `data-atom-part`, never by position.
- Dialogs keep `wire:ignore.self`, because Livewire's morph calls `close()` on a dialog whose server HTML lacks `open` (`livewire.esm.js:8727-8731`). The CSS `:has()` scroll lock stays.

### 6.2 Events

- Names stay: `atom-modal-show/close`, `atom-toast-show`, `atom-alert-show`, `atom-confirm-show`, `atom-command-show/close`. Hosts dispatch them directly 136 times [V-scan], so renaming buys nothing.
- **Changes:** `detail` is always an object (`readDetail(e)` normalises `null`); a single PHP `AtomManager::dispatch()` builds every payload; an optional `scope` is added (Flux).
- Contract tests pin PHP keys == JS keys, covering the bubbling no-payload close that humblebear uses 30 times.

### 6.3 Field and accessibility base

- **Ids:** server-derived through `Attr::fieldId`; a caller-supplied id wins; never random.
- **Association:** `<label for>` on native controls; `aria-labelledby` on the element with the role for composite widgets; `aria-describedby` for caption and error; `aria-invalid`.
- **Error region:** always rendered, `role="alert" aria-live="polite"` (Flux `error.blade.php:25-29`).
- **Attribute routing:** by prefix, `input:`, `label:`, `field:`, `error:` (Flux).
- **Escaping by default:** labels, captions and errors use `{{ }}`. HTML only through `Illuminate\Support\HtmlString`. The on-disk host scan found no literal HTML in `label="…"` values, and the bound labels checked are plain expressions (e.g. `$errors->first(...)`, `humblebear/resources/views/components/input/address.blade.php:150`) [V-scan], so the break is expected to be small.

### 6.4 CSS

- **`atom.tailwind.css` is required in v4.** It contains `@source` for `components/**` and the atom views; `@theme` tokens (`--color-muted`, `--color-muted-foreground`, and an accent triplet, with `.dark` overrides); and `@custom-variant dark`.
- `atom:upgrade` replaces each host's `@source` block (humblebear `resources/css/app.css:4-10`, sudo `:3-8` [V]) with one `@import`.
- The served `atom.css` holds only structural rules written against `data-atom-*` hooks, each with a stated layer. Positioning is inline JS. Visual defaults use `[:where(&)]`.
- A CI compile check runs on Tailwind 4.0.7 and on latest, and fails on warnings (Flux #2701).

### 6.5 Server surface

- **Public means endpoint:** Component macros for the helpers; public table actions only in `WithAtomTable`, with a guest-call audit test; everything else `protected`.
- **Client-writable input that reaches SQL, method names, paths or HTML** must be signed (`Security\Signed`), `#[Locked]`, or allowlisted (`filter()` columns).
- **`{!! !!}`/`x-html`** only at allowlisted sites, enforced by a static test.

### 6.6 Component contract checklist

The 17 items in the archived draft §4.8 still apply. For v4, add:

18. [ ] No global side effects: no prototypes, no globals beyond `window.atom`, no framework macros except the allowlisted ones.
19. [ ] It works with only `atom.tailwind.css` imported plus the served `atom.css`.
20. [ ] Its docs demo partial *is* its conformance fixture.

---

## 7. Test infrastructure

Unchanged from the archived draft §5, with v4 adjustments.

**Consumer fixture**
- `tests/consumer/`: its own `package.json` with Tailwind v4 CLI (4.0.7 and latest); compiles `@import "tailwindcss"; @import "../../resources/css/atom.tailwind.css";`.
- Served through a workbench route plus `<atom:html :styles>`.
- **Needs your approval** (Q1: dependency change).

**Harness**
- The Livewire-backed docs demos (M16) double as fixtures. Until then, a generic `HarnessFixture` provides: a bump counter, a `.live` value, a remove/re-add toggle, a server-side `invalid`/`disabled` toggle, a re-render triggered from inside the component, and a `wire:navigate` A↔B page pair.

**Conformance checks C-1…C-9**, per interactive component, in `conformance.spec.js` driven by a registry:
- C-1 survives a re-render (node identity, open state, value, focus, no console errors);
- C-2 survives a double init;
- C-3 survives navigate in / back / forward;
- C-4 tears down cleanly (CDP listener count, Chromium only);
- C-5 is positioned correctly after scrolling to several offsets, with compiled CSS;
- C-6 keyboard (multi-item list; Escape doesn't propagate; focus returns);
- C-7 accessible name and every idref resolves to exactly one element;
- C-8 dark-mode contrast against its own surface;
- C-9 server-driven props update.

**Mutation catalog and runner**
- `tests/mutations/catalog.json` plus `scripts/mutate.mjs`: back up, apply the regex (throw if it matched nothing), rebuild or `page.route()`, require the spec to **fail**, restore, check `git status`.
- Seeded with the 12 historic defects, **plus S1** (unescaped label), **S2** (purge scanning only the legacy cast) and **S3** (a filter on an unallowlisted column).

**Static guards (Pest)**
- no `Str::random`/`uniqid`/`ulid()` in components;
- `x-data` and `id` byte-identical across two renders;
- the escaping allowlist;
- the Livewire-callable surface;
- the macro allowlist;
- no `<atom:` inside `//` comments;
- the Boost/README prop tables in sync with `@props`.

**CI (GitHub Actions, new)**
- Pest; Playwright (Chromium, plus WebKit for positioning); the consumer compile at the Tailwind floor and latest; the `dist/` freshness diff; mutation runs on release branches.

---

## 8. Host migration

### 8.1 Measured usage [V-scan] (drives what breaks)

| | humblebear | sudo | skeleton-project | apikan | permission |
|---|---|---|---|---|---|
| Blade files with atom tags | 522 | 28 | 26 | 8 | 1 |
| Tag uses / distinct components | 6,932 / 134 | 372 / 45 | 124 / 15 | 20 / 14 | 2 / 1 |
| Components using `AtomComponent` (≈ half of 932 matches) | ~423 | ~21 | ~22 | 0 | — |
| `$this->modal`/`toast`/`alert`/`wirekey` | 200/195/132/129 | 8/7/0/0 | 0/4/0/0 | — | — |
| `t()` / `num()` / `carbon()` / `js()` | 4,869 / 642 / 567 / 267 | 43 / 23 / 20 / 6 | 102 / 0 / 2 / 0 | 12 / 0 / 1 / 0 | js 2 |
| `toTable` / `toPage` / `filter` / `breakdown` | 45 / 45 / 39 / 15 | 1 / 0 / 0 / 0 | — | — | — |
| Enum trait / `Arr::pick` | 141 / 42 | 1 / 1 | 4 / 2 | 3 / 2 | — |
| `app('atom')->broadcast`/`mail`/`sitemap` | 15/12/3 | — | — | 0/1/0 | — |
| JS `atom.*` / `empty()` / prototypes in Alpine | ~60 / 27 / 20 | 0 / 0 / 0 (`dd()` 1) | — | — | — |
| Direct `atom-*` event dispatches | 136 | 0 | 0 | 0 | — |
| `AsEditorContent` columns / `<atom:editor*>` views | 1 / 5 (§10.4) | 0 | 0 | 0 | — |
| `GetOptions` subclass / `WebAction` classes | 1 / 1 | 1 / 0 | 0 | 0 | — |
| `wire:navigate` view files | 27 | 10 | — | — | — |

The most-used components (all hosts): `button` 680, `table.cell` 592, `input` 584, `dd` 504, `table.column` 472, `heading` 302, `select` 296 (native 214, filter 60, listbox 22), `card` 280, `link` 232, `badge` 174, `navlist.item` 171, `tooltip` 166, `checkbox` 156, `modal` 142, `date-picker` 111 (range 50).

**Reading:** most v4 breaks are PHP/JS plumbing that can be automated. The tag API of the top-15 components is kept stable on purpose.

### 8.2 UPGRADE.md: breaking changes, draft

**PHP**
1. **`AtomComponent` split.** `use AtomComponent` becomes the specific traits: `WithAtomTable` if the component uses `_table`/`toTable`/`sort`; `WithAtomEditor` if it uses `_editor`/tiptap; `WithBreadcrumbs` if it defines `breadcrumbs()`; `WithRecaptcha` if it calls `verifyRecaptcha()`. Add `WithPagination`/`WithFileUploads` explicitly where they are needed. `$this->toast()`/`modal()`/`alert()`/`confirm()`/`command()`/`wirekey()` keep working as Component macros. **Automated.**
2. Trait helpers are no longer browser-callable (`$wire.toast()` etc.). **Grep report.**
3. `app('atom')` becomes the `Atom` facade (`Atom::toast()`…). `app('atom')->broadcast()`/`sitemap()`/`mail()` move to `Support\*` (Q2). **Automated.**
4. The Date swap is removed. Add `Date::use(\Jiannius\Atom\Support\Carbon::class);` to `AppServiceProvider` to keep the old behaviour. **Automated.** `Jiannius\Atom\Services\Carbon` becomes `Support\Carbon`. **Automated.**
5. `filter()` requires an allowlist (`$filterable` on the model or `only:`). **Semi-automated:** the command lists each call site together with the keys seen in the host's `$filters` defaults.
6. Removed macros: `Request::subdomain`/`hostWithoutSubdomain`/`isLivewireRequest`/`portal`; `Str::dotpath`; `Builder::randomCode`/`tableHasColumn`/`breakdown`; the 6 attribute-bag macros; `is_using_trait()`. The ones humblebear uses (`portal` 3, `breakdown` 15, `is_using_trait` 6) are **copied into the host by the command**.
7. `AsEditorContent` and `<atom:editor*>` are removed. **Precondition:** `php artisan atom:tiptap-migrate` on 3.x. The command blocks until done.
8. Remote options: the `html` key becomes `trusted_html`; `label`/`caption` are now text. **Automated rename, plus manual review of every `trusted_html` builder** (humblebear `GetOptions.php:378`, `:436`).
9. `Services\*` namespaces move to `View\`/`Security\`/`Support\`. **Automated.**

**Blade**

10. Labels, captions and errors are escaped; use `new HtmlString(...)` for HTML. **Grep report.** The scan found no literal-HTML labels.
11. Prop removals from the never-passed list (§5.4), plus renames from the merges: `button` `social` moves to `<atom:button.social>`; `type="delete"` confirm wiring moves to an explicit `confirm` attribute; the `editor` prop is removed. **Automated for the renames; grep report for removed props.**
12. `<atom:html>` scripts move inside `<body>`; the analytics partials are included by default. No action unless a host overrides `@stack` order.

**JS**

13. Prototypes, `window.dd` and `window.empty` are removed. `.currency()` becomes `atom.num.currency()`, `.headline()`/`.limit()` become `atom.str.*`, `empty(x)` becomes `atom.empty(x)`. **Automated** (a regex inside `x-*`/`:attr` values and `.js` files, with a review list).
14. `atom.floatingui()` becomes `atom.overlay()`/`atom.anchor()`. **Manual** (6 sites in humblebear).
15. Overlays: Escape no longer bubbles past an overlay it closed, and focus returns to the trigger. **Note only.**

**CSS**

16. Replace the hand-kept `@source` block with `@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';`. **Automated.**
17. The new tokens `muted`/`accent` may override a host's own tokens of the same name. **Grep report.**

**Assets**

18. `/atom/{file}` serves only manifest files; hosts not using `<atom:html>` use `@atomStyles`/`@atomScripts`. **Grep report.**

### 8.3 Automation

- **`php artisan atom:upgrade-check`,** backported in the last 3.x minor as a read-only report. It is the productised version of `scratchpad/usage/scan.py`. It lists every breaking item above with file:line and counts, so each host can size its migration before switching.
- **`php artisan atom:upgrade [--dry-run]`,** shipped in v4. An idempotent codemod for items marked **Automated**: it prints a unified diff in dry-run mode and applies it otherwise. Items marked grep report or manual go into a generated `ATOM-UPGRADE-TODO.md` in the host.
  - The rewrite engine is plain regex/PHP-parser rewrites [I]. Adding `nikic/php-parser` is a dependency decision (Q1); regex covers everything except the trait split, which needs a PHP AST for safety.
- **Boost guideline:** regenerate the slim v4 guideline. `atom:upgrade` also **rewrites the host's copied atom section in CLAUDE.md** (humblebear's is hand-diverged), so the host's agents stop following 3.x advice.

### 8.4 Order and timeline

1. **skeleton-project** (124 tag uses; the template for all new projects, so it is also the v4 reference app) on `v4.0.0-beta.1`.
2. **apikan** (20 uses; locked at v3.0.2, so it jumps straight from 3.0 to 4.0 and skips 3.x changes; the check tool matters most here).
3. **sudo** (372 uses; has a `GetOptions` subclass).
4. **permission** package (2 uses).
5. **humblebear** (6,932 uses) on `v4.0.0-rc`. Precondition: the Tiptap migration and S1-host fix are done on 3.x. The migration is done on a humblebear branch, run through humblebear's own browser tests (memory mentions its QA flow) before GA.
6. **smgdms, toocrm**: unverified. Their owners run `atom:upgrade-check` first.

**v4.0.0 GA** comes after humblebear's rc migration is green. After GA:
- `main` becomes 4.x;
- 3.x continues on a `3.x` branch under §2's support rule.

---

## 9. v4 milestones

**Conventions**
- Each milestone is **one reviewable PR into `v4`**, squash-merged.
- Pre-release tags are cut on `v4` (`v4.0.0-alpha.N`, `-beta.N`, `-rc.N`).
- **"Done" always includes:** Pest green, Playwright green, the touched components' conformance checks passing, each new guard mutation-tested, a fresh `dist/` committed if JS/CSS changed, and the UPGRADE.md entry written.
- Sizes: S ≤ 1 day, M 1–3 days, L 3–5 days [I].

### Track A: 3.x (on `main`, forward-merged into `v4`)

| ID | Milestone | Done when | Size | Depends on |
|---|---|---|---|---|
| **A1** | v3.29.6: S1 escape remote option `label`/`caption`; coordinate the S1-host humblebear PR | The mutation (restore unescaped concatenation) fails a Pest + e2e test that renders `<img onerror>` as text | S | — |
| **A2** | v3.29.7: S2 purge scans Tiptap JSON, recurses models, same disk resolver, dry-run default | A fixture with a Tiptap image survives a purge; the legacy-only mutation fails | S | — |
| **A3** | S4 audit of the browser-callable trait surface (report plus tests) | Every public method is guest-called in Pest with its side effects and return recorded; a fix PR exists if anything is exploitable | S | — |
| **A4** | (conditional, Q5) S3 `filter()` allowlist, opt-in in 3.x | An unallowlisted key is ignored and logged when `$filterable` is set | S–M | A3 triage |
| **A5** | v3.30.0: backport `atom:upgrade-check` (read-only) | Its output on the four on-disk hosts matches the scan within ±5% | M | M17 design |
| **A6** | **Editor engine security bump** (§10.3 E-S1, E-S6): `@tiptap/*` → ≥ 3.31.3 (brings `prosemirror-view` ≥ 1.42.3, the GHSA-c8x8-7fp4-3x9w paste-XSS fix) and `ueberdosis/tiptap-php` → ^2.2; rebuild `dist/` | `npm ls prosemirror-view` ≥ 1.42.3; the built `tiptap-*.js` chunk hash changes; existing tiptap e2e and Pest tests green | S | — (urgent: a published high-severity advisory) |
| **A7** | **Editor storage and renderer hardening** (E-S2, E-S3, E-S4): `unserialize($v, ['allowed_classes' => false])` in both casts and the migrate command; `AsTiptapContent::set()` rejects non-JSON strings; `Youtube::embedUrl()` drops non-YouTube URLs; `style` values (colour, font-size, image width/float) validated in the PHP renderer | New Pest tests: a serialized-object payload comes back as a string or null, never an object; a `javascript:` or `https://evil` YouTube src renders no iframe; `color: red; position:fixed` renders no style. Each is mutation-tested | S | — |
| **A8** | **Chat sanitiser** (E-S5): add `Jiannius\Atom\Tiptap\Content::sanitize(string $html): string` (tiptap-php parse, then render with the hardened extension set). **Host PR in humblebear:** sanitise in `submit()` before `Message::create` | Pest: hostile chat HTML comes back with only schema nodes, safe hrefs and no event attributes; humblebear's chat test covers it | S | A7 |

### Track B: `v4` branch

| ID | Milestone | Scope | Done when | Size | Depends on |
|---|---|---|---|---|---|
| **M0** | Branch and CI | Create `v4` from `main`; GitHub Actions (Pest, Playwright, dist freshness); `.gitattributes` export-ignore; remove `composer.lock`; CLAUDE.md v4 section (fix "no test suite") | CI green on an empty change; `composer archive` excludes tests and docs | S | — |
| **M1** | Test rig | Consumer Tailwind fixture; `HarnessFixture`; navigate pair; workbench routes; e2e views moved out of `resources/views` | The rig spec (compiled `.flex`, navigate round trip) is green | M | M0, Q1 |
| **M2** | Conformance suite and baseline | C-1…C-9 plus registry for every interactive component; failures recorded as `test.fail` | The baseline red list is committed | M–L | M1 |
| **M3** | Mutation runner | Catalog plus runner, 15 seed entries | All seeds fail when mutated and pass when restored | M | M1 |
| **M4a** | `AtomComponent` split | Component macros (toast/modal/alert/confirm/command/wirekey); `WithAtomTable`/`WithAtomEditor`/`WithBreadcrumbs`/`WithRecaptcha`; the rest protected; drop the forced `WithFileUploads` | Surface test: only the table actions are callable; the macro helpers are not callable from `$wire`; the existing trait tests are ported | M | M0 |
| **M4b** | Manager, facade and service re-layer | `Atom` facade plus `AtomManager` (UI dispatch only); `src/{View,Security,Support,Livewire,Http,Database}`; `ActionController`/`AssetController`; `routes/atom.php` | Everything resolves; `app('atom')` removed; asset route serves only manifest files, with 304 | M | M4a |
| **M5** | Global side effects out | Remove the Date swap, the unused macros and `is_using_trait`; internal `View\Attributes`; `filter()` allowlist plus `Schema::getColumns`; `GetOptions` cache TTL | Macro-allowlist test green; the S3 mutation fails | M | M4b |
| **M6** | JS globals out | Remove prototypes, `dd` and `empty`; `atom.str/num/empty`; `$atom` magic; remove `atom.floatingui` | Pest/e2e green; a static check finds no `defineProperty(...prototype` | S | M0 |
| **M7** | Shared JS core | `core/{component,durable,ids,events,keyboard}` plus `window.atom.core`; proven on the tooltip | Core unit tests green; tooltip passes C-1…C-4 and C-7 | M | M2 |
| **M8** | Overlay primitive | `core/overlay`; migrate dropdown, menu, context menu and tooltip; `data-atom-part`; durable state attributes | C-1…C-7 green for those four, including C-5 at 4 offsets; the #2707-shape and Escape mutations fail | L | M7 |
| **M9** | Event contract | `emit`/`on`/`readDetail`; `AtomManager::dispatch`; scope | Contract tests green; the null-detail mutation fails | M | M7, M4b |
| **M10a** | Field base, inputs | Field/label/error live region; `Attr::after` prefix routing; escaping by default; input, textarea, native select | C-7 green; the S1 and "id on wrapper" mutations fail | L | M5 |
| **M10b** | Field base, choice controls | Checkbox, radio, toggle, slider, rating (random ids removed), tel, email, color | C-7 and C-9 green | M | M10a |
| **M11a** | Consolidate: listbox | One listbox implementation (listbox + filter); structured remote options with `x-text`; static options server-rendered | ≥150 lines removed; the select-morph fixtures and C-1…C-9 green | L | M8, M9, M10a |
| **M11b** | Consolidate: dialog family | Dialog base; alert = confirm − cancel; toast item partial; modal header trimmed | ≥60 lines removed; C-1…C-8 green for modal/alert/confirm/toast | M | M8, M9 |
| **M11c** | Consolidate: small pairs | Pagination, chart factory, SendBroadcast merge, Color dead code, `<atom:button>` split, link/button partial, never-passed-prop removal (from the `props.py` list) | Measured removals as in §5; UPGRADE entries written | M | M10a |
| **M11d** | *(superseded; folded into the editor milestones ME2 and ME6, §10.7)* | — | — | — | — |
| **M12** | Date and time pickers | Date, time, range, time-picker on the core; panel-only islands (no whole-root `wire:ignore`, `range.blade.php:22`) | C-1…C-9 green, especially C-3 back/forward; the #52/#55 mutations fail | L | M8 |
| **M13** | Command and lightbox | On the dialog base plus keyboard core | C-1…C-8 green | M | M11b |
| **M14** | Remaining components | Accordion (random id `accordion/item.blade.php:7`), tabs (`focusable`), navlist.group, otp, tiptap toolbars, table inline `x-data` → registered factories (CSP-compatible) | Each passes its applicable checks; no inline `x-data="{"` left | M ×3 PRs | M7 |
| **M15** | CSS distribution | `atom.tailwind.css` (tokens, `@source`, dark variant) required; served `atom.css` only structural; CI compile on 4.0.7 and latest | The consumer fixture uses only the import; C-5/C-8 green | M | M1 |
| **M16** | Docs as fixtures | Livewire-backed docs pages; demo partials registered as conformance fixtures; generated prop tables; slim Boost guideline | The Pest sync test is green; `HarnessFixture` is removed where a demo covers it | M | M2, M11–M14 |
| **M17** | Upgrade tooling and UPGRADE.md | `atom:upgrade [--dry-run]` codemod; `ATOM-UPGRADE-TODO.md` generator; host CLAUDE.md section rewrite; UPGRADE.md complete | A dry run on a copy of each on-disk host produces a diff, and the host test suite is green after apply (done in the host repos) | L | M4–M16 |
| **M18** | Pilot hosts | skeleton-project → apikan → sudo → permission (host PRs) on `v4.0.0-beta` | Each host's tests and a smoke QA pass | host work | M17 |
| **M19** | humblebear | Host PR on `v4.0.0-rc`, after A1/A2 and the Tiptap migration | humblebear's test suite plus QA pass | host L | M18 |
| **M20** | v4.0.0 GA | Open the `v4` → `main` PR (merge commit, keeping the milestone history, Q6); tag `v4.0.0`; create a `3.x` branch from the pre-merge `main`; announce the 3.x end-of-life date | Tag pushed; release notes link UPGRADE.md | S | M19 |

**Dependency backbone:**

```
M0 → M1 → M2 → M3
M0 → M4a → M4b → M5
M0 → M6
M2 → M7 → M8 → M11a → M12
M7 → M9 (M9 also needs M4b)
M5 → M10a → M10b
M8 + M9 → M11b → M13
M10a → M11c
M4b/M5 → ME2 → ME3
M1 + M2 + M7 + M8 + M9 → ME1 → ME4 (ME4 also needs M10a) → ME5
ME2 + M17 → ME6 → M19
M7 → M14
M1 → M15
all of the above → M16 → M17 → M18 → M19 → M20
```

---

## 10. The editor (`<atom:tiptap>`): audit, verdict and plan

### Sources and scope

- **Tiptap release notes** v3.26.1 → v3.31.3, pulled with `gh api repos/ueberdosis/tiptap/releases`; saved to `scratchpad/tiptap/notes-3.26-3.31.md`.
- **tiptap-php releases** 2.1.1 → 2.2.0.
- **The prosemirror-view advisory** GHSA-c8x8-7fp4-3x9w, via `gh api repos/ProseMirror/prosemirror-view/security-advisories`.
- **Public docs** for Flux `<flux:editor>` (Pro, docs only), Tiptap's Simple Editor template, and Filament v4's RichEditor.
- **atom's editor:** `components/tiptap/**` (928 lines), `resources/js/tiptap.js` (114), `resources/js/alpinejs/tiptap.js` (124), `resources/js/alpinejs/mention.js` (101), `src/Tiptap/**` (259), `src/Casts/AsTiptapContent.php` (129), `src/Commands/MigrateTiptapContent.php` (94), `resources/css/tiptap.css` (200), plus 4 Pest files (374 lines) and `tests/e2e/tiptap.spec.js` (172).
- **humblebear's editor views and model**, read-only.

### 10.1 What changed in Tiptap 3.26 → 3.31, and tiptap-php 2.1 → 2.2

**Short answer to "has Tiptap come a long way?"** Not in capability. It is the same major version and about 3.5 months of minor and patch releases, mostly fixes. The atom-relevant exception is one **high-severity security fix** that on its own makes the update mandatory. The quality gap the user senses is in **atom's integration, not the Tiptap version**.

**Security, verified:**

- **3.31.2** (2026-09-03): `@tiptap/pm` bumps `prosemirror-view` to ^1.42.3, fixing **GHSA-c8x8-7fp4-3x9w**, rated high: "When a user pastes attacker-provided HTML into a ProseMirror editor component, this can cause attacker-controlled JavaScript code to run". The affected range is `< 1.42.3`.
  - **atom's lockfile and build use `prosemirror-view` 1.41.9** [V] (`package-lock.json` → `node_modules/prosemirror-view/package.json`), so atom is affected.
  - humblebear renders this editor today through `<atom:editor>` in its task form and chat.
- **3.27.0:** `extension-link` `isAllowedUri` fix. Unknown hyphenated schemes such as `unknown-protocol://` had been accepted.
- **3.31.3:** collaboration-caret colour sanitisation. Not used by atom.

**Features and fixes relevant to atom:**

| Area | Change |
|---|---|
| Suggestion (mentions) | 3.27.0 added `props.mount()` for fully managed popup positioning (Floating UI `autoUpdate`), `dismissOnOutsideClick`, async `items()` with debounce, in-flight abort, `loading`, `initialItems` and `minQueryLength`. atom's mention hand-rolls a `fixed` popup (`components/tiptap/mention.blade.php:21`) and its own filtering (`resources/js/alpinejs/mention.js:76-79`). |
| Lists | 3.27.0: ordered list `type` (a/A/i/I), including **paste from Google Docs, Word and LibreOffice**. 3.30.0: `ListKeymap` Tab sinks a paragraph into the previous list. TaskItem label a11y fix. |
| New open-source extensions | 3.29.0: **Find & Replace**, **RubyText**; `insertDefaultBlock` command. 3.27.2: FileHandler `consumePasteEvent` (stops duplicate content when an image and HTML are pasted together). |
| Stability | Placeholder flicker fixes (3.27.1, 3.27.3, including while a modal overlay is open). Selection-on-blur fix (3.27.4). Blockquote backspace crash and freeze (3.27.4, 3.30.0). Table fixes: colgroup width parse, empty-cell insert crash, cursor after deleting the last row (3.27.4, 3.29.0, 3.30.0). The prosemirror-model regression where pasting inserted extra empty paragraphs (3.29.0). YouTube: iframe-without-src paste crash, `/live/` URLs, string width/height (3.28.0–3.30.6). StarterKit now pins its bundled deps exactly (3.30.0). |
| Deprecations | None found in the 3.26–3.31 notes. |
| **tiptap-php** | 2.1.2 (2026-08-24): "non scalar types would crash the render attributes". A crafted JSON attribute can **500 every render of that record** (a stored render crash). 2.2.0 (2026-08-31): "Hardened rendering and merging of attributes". No published advisory. atom locks 2.1.1 [V]. |

**Official Tiptap UI.** Tiptap's **Simple Editor template and UI components are MIT but React/Next.js only** [Doc], so they are a feature benchmark, not a drop-in. Its feature list: marks, heading and list dropdowns, a link popover, an image-upload node, highlight, text align, undo/redo, find & replace, dark mode, a mobile-aware toolbar.

### 10.2 The "pro-grade" bar and the benchmarks

**How to read the table.** Each row is one area of the bar, followed by what each benchmark does:
- **The bar** — what a pro-grade editor must do.
- **Flux `<flux:editor>`** — Pro; public docs only [Doc].
- **Filament v4 RichEditor** — public docs only [Doc].
- **atom today** — verified in source [V] unless tagged otherwise.

| Area | The bar | Flux | Filament v4 | atom today |
|---|---|---|---|---|
| **Toolbar and menus** | Declarative toolbar (string shorthand plus composable items); contextual bubble menus per node; optional slash commands; custom items. | `toolbar="heading \| bold italic \| align ~ undo redo"`; `<flux:editor.toolbar>` items; custom items as Blade files. | `toolbarButtons`, `floatingToolbars` per node type, custom tools. | Presets plus a slot (`components/tiptap/index.blade.php:22-37`). Per-button inline `x-data` in link/image/table/youtube. Four bubble menus. No slash commands. |
| **Keyboard and a11y** | Documented shortcuts; toolbar follows the WAI-ARIA toolbar pattern (roving tabindex, arrow keys); buttons named with `aria-pressed`; the surface named and described; the mention list is a real listbox. | Shortcut table documented. | — | Buttons named with `aria-pressed` (`toolbar/button.blade.php:9-10`); surface `role=textbox`, `aria-labelledby` (`alpinejs/tiptap.js:44-51`). **No roving tabindex or arrow keys** on `role=toolbar`. The mention popup has **no listbox/option roles or `aria-activedescendant`** (`mention.blade.php:16-49`). The link menu's edit/remove are **`<div x-on:click>`** with unlabelled SVGs (`menu/link.blade.php:66-70`). |
| **Paste** | Office/Docs paste normalised; HTML sanitised to the schema; images from the clipboard uploaded; a patched `prosemirror-view`. | Markdown-style input rules. | — | Schema-only (ProseMirror default). **Vulnerable `prosemirror-view` 1.41.9.** Rich mode has no paste/drop image upload. Chat mode blocks paste and drop entirely (`alpinejs/tiptap.js:53`), then inserts `clipboard.getData('text')` through `insertContent`, which parses it **as HTML** (`:90`). |
| **Image upload** | Paste, drop and button; per-file progress and errors; type, size and dimension validation on the server; alt text; resize/align; private-disk support. | No built-in image upload documented. | Disk, directory and visibility (private uses signed URLs), accepted types, max size, **`preventFileAttachmentPathTampering`**. | Button only. One shared progress value. The error is a hard-coded English alert (`toolbar/image.blade.php:15`). A single `$_editor.images` bucket per component (two editors collide) with **no server-side validation** (`src/Traits/AtomComponent.php:44-60`). **No alt text.** Resized via Intervention with public visibility only (`AsTiptapContent.php:114-127`). |
| **Links** | Protocol allowlist in both editor and renderer; mailto, tel and relative URLs allowed; clear validation feedback; `rel` set. | Link item. | — | The client allows only `http(s)` (`menu/link.blade.php:38`), so mailto, tel and relative URLs are impossible. **The invalid-URL feedback dispatches an `alert` event nothing listens to** (`:39`); the only occurrence is in this file. The server's tiptap-php Link has a protocol allowlist (`vendor/.../Marks/Link.php:23-46`). |
| **Tables, placeholder, character count** | Table insert and edit menus; a translated placeholder; optional character/word count and `max`. | Table extensions bundled but off by default. | Tables, `maxLength`. | TableKit, resizable (`resources/js/tiptap.js:97`). Placeholder not translated (`index.blade.php:11`, `:59`). **No CharacterCount.** |
| **Livewire sync** | Debounced by default; dirty state; echoes from the server ignored by revision or deep-equal; never clobbers the caret; the field wrapper stays morphable. | `wire:model`, HTML output. | Form state. | Syncs on every `onUpdate` (`alpinejs/tiptap.js:66`). **`.live` gets no default debounce**, because Livewire only debounces INPUT/TEXTAREA (`livewire.esm.js:15237-15239`), so every transaction is a round trip. Echo suppression compares JSON **strings** (`alpinejs/tiptap.js:25`); a server that re-encodes (`json_encode` escapes `/` and unicode) would call `setContent` and reset the selection [I]. The whole root is `wire:ignore` (`index.blade.php:55`) but the label/error wrapper sits outside it (good). |
| **Validation and field integration** | `invalid` state, `aria-invalid`/`aria-describedby` on the surface, `<atom:form disabled>` respected, error region. | `invalid`, `disabled`, `label`, `description`. | — | The error shows through `input.field`, but the surface gets **no `aria-invalid`, no invalid styling, and no `disabled` prop or `@aware`** (`index.blade.php:1-13`). |
| **Read-only** | `readonly` and `disabled`, with the toolbar hidden and the surface marked. | `disabled`. | `disabled`. | `readonly` supported (`aria-readonly`, toolbar hidden). No `disabled`. |
| **Safe output rendering** | One server renderer that sanitises to the schema, validates URLs (link, image, iframe) and `style` values; never trusts client JSON. | Not documented. | `RichContentRenderer` sanitises; docs warn to use it or `sanitizeHtml()`. | `Content::render()` via tiptap-php (`src/Tiptap/Content.php:45-54`): schema-limited, attribute values escaped with `htmlentities` (`vendor/.../Utils/HTML.php:61-66`), Link protocols checked. **But see E-S3 and E-S4** (iframe src, style injection). |
| **JSON ↔ HTML parity** | One extension registry in JS and PHP, with a parity test. | — | PHP + JS plugin interface. | Parallel hand-kept lists (`resources/js/tiptap.js:88-111` vs `src/Tiptap/Content.php:19-40`). **The YouTube renderers diverge**: JS returns `null` for non-YouTube URLs (`node_modules/@tiptap/extension-youtube/dist/index.js:38-39`), while PHP returns the raw URL (`Youtube.php:54-61`). No JS↔PHP parity test. |
| **Storage, cast, image lifecycle** | One cast; canonical JSON; no deserialisation of untrusted data; temp uploads promoted on save; orphan cleanup that can't delete live images. | — | Attachments with visibility; path-tampering guard. | Two casts (`AsEditorContent` serialised HTML, `AsTiptapContent` JSON). **`unserialize` of stored strings without `allowed_classes`**, and a non-JSON client string is stored raw (E-S2). The purge command ignores Tiptap columns (§2, S2). |
| **Dark mode, mobile, size** | Content CSS themed for dark; touch-friendly menus; lazy bundle. | — | — | **`tiptap.css` has zero dark rules** and hard-codes light colours: mention chip `#e0f2fe`/`#075985`, table header `#f1f5f9`, borders `#d1d5db` (`resources/css/tiptap.css`). Lazy chunk 475 KB minified / **149 KB gzip** (`dist/assets/tiptap-*.js`). A `<link rel=stylesheet>` is injected **per instance** inside `<body>` (`index.blade.php:52`, `content.blade.php:5`). |
| **Host extensibility** | Register JS extensions plus the matching PHP render extension; custom toolbar items; custom nodes. | `document.addEventListener('flux:editor', e => e.detail.registerExtensions([...]))`; `enableExtension`/`disableExtension`. | `RichContentPlugin`, custom blocks, merge tags, mention providers. | None: the extension list is closed (`resources/js/tiptap.js:88-111`). Only toolbar composition via the slot. |
| **i18n** | Every UI string through the translator. | `lang/*.json` keys. | — | Button labels use `t()` (`toolbar/button.blade.php:9`). The placeholder default and upload error are not translated. |

### 10.3 Defects and gaps by severity (atom 3.29.5)

**Security** (A6/A7/A8 on 3.x, then fixed by design in v4)

- **E-S1. Paste XSS in the bundled engine.** `prosemirror-view` 1.41.9 < 1.42.3 (GHSA-c8x8-7fp4-3x9w, high) [V].
- **E-S2. PHP object injection surface in the cast.**
  - `AsTiptapContent::get()` runs `@unserialize($value)` on any stored string (`src/Casts/AsTiptapContent.php:24`).
  - `set()` stores any string that isn't valid JSON **unchanged** (`:50-53`). The value arrives from the browser through `wire:model`, so a client can persist a serialised-object string that `get()` will instantiate.
  - Exploiting it needs a host using `AsTiptapContent` (none on disk; it is the documented upgrade target) and a usable gadget chain. **I did not look for or attempt a chain.**
  - The same unrestricted `unserialize` is in `AsEditorContent::get()` (`:19`). That one is lower risk, because its `set()` always `serialize()`s (`:73`). It is also in `MigrateTiptapContent` (`src/Commands/MigrateTiptapContent.php:38`).
- **E-S3. Server-rendered `<iframe src>` accepts any URL.** `Youtube::embedUrl()` returns the input unchanged when it has no YouTube id (`src/Tiptap/Extensions/Youtube.php:54-61`), and `renderHTML` places it in an iframe (`:34-47`).
  - Crafted JSON (`{"type":"youtube","attrs":{"src":"javascript:…"}}`), or legacy HTML parsed through `div[data-youtube-video] iframe` (`:27-30`), renders a `javascript:` or arbitrary-origin iframe wherever `<atom:tiptap.content>`/`<atom:editor.content>` is used.
  - Pest covers only valid URLs (`tests/Feature/TiptapContentTest.php:29-44`).
- **E-S4. CSS injection through `style` attributes.** Custom `fontSize` (`src/Tiptap/Extensions/FontSize.php`, custom branch `font-size: {$value}`), image `width`/`float` (`AtomImage.php:19-44`), and tiptap-php `Color`/`Highlight` values are emitted into `style` unvalidated. That allows overlays and phishing UI inside rendered content. Medium.
- **E-S5. The chat composer hands raw HTML to the host.**
  - It sends `tiptap.getHTML()` (`resources/js/alpinejs/tiptap.js:113-116`), and atom offers no server sanitiser.
  - **humblebear stores it verbatim** (`humblebear/resources/views/livewire/app/chat.blade.php:27-33`) **and renders it with `x-html`** (`:294`, `:329`). A client calling `$wire.submit('<img src=x onerror=…>')` bypasses the editor completely, so this is stored XSS in task chat [V path; not exploited].
  - The fix belongs in the host, but atom's contract invites the bug.
- **E-S6. tiptap-php 2.1.1 crashes on non-scalar attributes** (fixed 2.1.2). A crafted JSON value makes every render of that record 500 [V, release notes].

**High** (correctness and reliability)

- **E-H1. No teardown.** `resources/js/alpinejs/tiptap.js` has no `destroy()`, so `tiptap.destroy()` never runs. The ProseMirror view, its listeners and four bubble-menu Floating-UI loops outlive a morph removal or a `wire:navigate` (the Flux #2653 class).
- **E-H2. Re-init stacking [I].** `init()` always creates a new `Editor` into `$refs.editor` (`:21`, `:33-34`) without clearing it. Re-initialising over a back/forward snapshot (the live DOM, `livewire.esm.js:12583-12590`) or an Alpine re-init is expected to stack a second editor surface. This is the #2708 and atom v3.29.2 class; conformance check C-2/C-3 would prove or disprove it.
- **E-H3. Sync contract.** No default debounce on `.live` (above). String-compare echo suppression. `JSON.stringify(getJSON())` on every transaction (`:25`, `:120`). No dirty state. With `.blur`, atom rewrites `wire:model.live.blur` → `wire:model.live` + an `onBlur` sync (`index.blade.php:19`, `:67`); that works, but only by special case.
- **E-H4. Image pipeline.**
  - The per-component `_editor.images` bucket is shared by every editor in the component.
  - No MIME, size or dimension validation beyond Livewire's global temp-upload rules.
  - `persist()` builds a path from the client-supplied `src` basename (`AsTiptapContent.php:97-105`); `afterLast('/')` stops traversal, but it trusts any existing temp filename.
  - Public visibility only; no alt text; no paste/drop in rich mode.
  - Orphan cleanup is broken (S2).
- **E-H5. Link UX.** Only `http(s)`; dead error feedback (`menu/link.blade.php:38-43`); non-button controls.
- **E-H6. Wrapping static prose destroys it [I, high confidence].** humblebear wraps static Blade markup in `<atom:editor.content>` for its privacy, terms and blog pages (`resources/views/livewire/web/privacy.blade.php:15`, `terms.blade.php:15`, `blog.blade.php:196-198`). `Content::render()` re-parses that slot through the Tiptap schema (`content.blade.php:8`, `Content.php:51-53`). Unknown elements like `<div class="text-4xl font-bold">` and their classes are not in the schema, so headings and styling are expected to be dropped. The component is being used as a prose wrapper, which it is not.
- **E-H7. Tests never exercise the Livewire runtime.**
  - The 9 e2e tests run against the plain-Blade docs page `/atom/docs/tiptap` (`tests/e2e/tiptap.spec.js:22`, `:32`, …), which has no Livewire. The 42 Pest cases are render and cast tests.
  - **Untested:** `wire:model` round trips; image upload; paste; re-render, navigate and teardown; readonly; chat send; hostile-content rendering (the YouTube/`javascript:`/style cases); unserialize safety; JS↔PHP HTML parity.
  - The one XSS test covers the mention label only (`TiptapContentTest.php:55`).

**Medium**

- No `disabled`/`@aware`, no `aria-invalid`, no invalid styling (§10.2).
- The toolbar has no roving tabindex. The mention popup has no listbox semantics and hand-rolled `fixed` positioning (`mention.blade.php:21`), which ignores the new suggestion `props.mount()`.
- No dark-mode content CSS (§10.2).
- Five toolbar/menu components carry inline `x-data` objects (image, table, youtube, link, text-color), so they aren't CSP-compatible and can't be unit-tested.
- A per-instance `<link rel=stylesheet>` inside `<body>`.
- Untranslated placeholder and error strings.
- **Legacy font-size presets** render Tailwind classes from `src/Tiptap/Extensions/FontSize.php` (`text-xs`…`text-xl`), a directory no host `@source`s [I: those classes may be missing from host CSS].
- `MigrateTiptapContent`:
  - skips rows that are already JSON but still serialised (`:42-44`), so PHP-serialised storage stays forever;
  - scans only the top level of `app/Models`;
  - `saveQuietly()` (`:56`) touches `updated_at` on every migrated row [I].

**Standing against the v4 foundation**

| Contract | Status |
|---|---|
| Idempotent init | Fails. |
| Teardown | Fails. |
| Back/forward | Expected to fail. |
| JS island | Too broad: the whole component root is `wire:ignore`. It should cover only the editor mount node and the menus. |
| Durable attributes | N/A, inside the island. |
| Events | Mixed. `$dispatch('input', …)` for the model; `editor-enter` as a DOM event; the dead `alert` event. |
| Field base | Partial. Label and error are outside the island; `aria-invalid` and describedby are missing. |
| Overlay core | Menus use Tiptap BubbleMenu (its own Floating UI), not atom's primitive. Acceptable, but they must be torn down. |

### 10.4 humblebear's real editor needs [V]

- **Correction to the brief:** humblebear has **1** `AsEditorContent` column, not 6. It is `App\Models\Task::description` (`humblebear/app/Models/Task.php:38`); the other matches were a `use` line and a plan document. There are **5** editor views.

| View | Use | Needs |
|---|---|---|
| `livewire/app/task/form.blade.php:160` | `<atom:editor wire:model.live.blur="form.description" label="Description" placeholder="Task description">` | Rich text with **embedded images** (humblebear's own upgrade plan lists "type, embed an image, save", `docs/superpowers/plans/2026-06-03-laravel-13-livewire-4-upgrade.md:626`), blur sync, label and field error. Stored today as `serialize(<Tiptap JSON string>)` through the legacy cast; shown only inside the editor (`form.blade.php:58`). |
| `livewire/app/chat.blade.php:351` | `<atom:editor.chat mention="getChatMentions" x-on:input="pushToQueue($event.detail)">` | Enter-to-send, attachments, **mentions from a `$wire` search** (`getChatMentions`, which is tenant-scoped, `:116-138`), paste/drop files. The output (HTML) is stored raw and rendered with `x-html` (E-S5). |
| `livewire/web/privacy.blade.php:15`, `terms.blade.php:15` | `<atom:editor.content>` wrapping **static Blade markup** | Plain prose typography; no Tiptap at all (E-H6). |
| `livewire/web/blog.blade.php:196` | `<atom:editor.content>{!! $this->blog->content !!}</atom:editor.content>` | Rendering stored blog HTML (origin unknown: authored elsewhere, not by an atom editor on disk). **Also, host-side:** `alt="{!! $this->blog->name !!}"` (`:190`) is unescaped. |

**Reading:**
- humblebear needs a **solid form field with images**, a **chat composer with mentions and attachments**, and a **safe renderer plus a separate prose wrapper**.
- It uses **none of** tables, YouTube, font size, text colour or highlight.
- The migration surface is tiny: one column and five views.

### 10.5 Refactor or rewrite: the evidence

**What stays.** The engine choice is right and matches the field: Tiptap v3 in the browser plus tiptap-php on the server, with JSON as canonical storage. Flux uses Tiptap (HTML output). Filament v4 uses Tiptap with a sanitising PHP renderer, and `->json()` storage is optional. So v4 keeps the engine, the prop surface (`label`, `caption`, `placeholder`, `toolbar` presets/slot, `mention`, `readonly`, `variant`), the docs demos, and the PHP render tests, which become regression tests.

**What has to change** touches nearly every layer:

| Layer | Lines today | Change needed |
|---|---|---|
| Cast and storage | 129 + 75 | Replace (E-S2, one cast, no unserialize). |
| Server renderer and PHP extensions | 259 | Rewrite into a sanitising renderer with validators (E-S3, E-S4, parity registry). |
| Alpine factory | 124 | Rewrite on the M7 core (E-H1–E-H3). |
| Toolbar and menus | ~650 of 928 | Rewrite from inline `x-data` to declarative items plus one registered factory (CSP, a11y, testability). |
| Mention | 101 + 52 | Replace with the suggestion `props.mount()`/async API and listbox semantics. |
| Chat | 82 + 77 | Re-contract to emit JSON through the same factory. |
| Image pipeline | spread across the trait, cast and toolbar | Rewrite (E-H4, S2). |
| CSS | 200 | Retheme with dark mode and tokens. |

**Estimates [I]**

- **Refactor in place:** about 70–85% of the ~1,950 lines change anyway. It would also keep contracts that cause defects (a shared `_editor.images` bucket, HTML chat output, serialised storage, inline toolbar objects) unless those are broken too, and breaking them is what a rewrite is. Size ≈ **L–XL**, with the risk of carrying hidden coupling.
- **Rewrite on the same engine** (a structured rebuild behind the same Blade tag): ≈ **5 PRs, 12–18 days**, plus the humblebear pilot. Migration cost is small: 1 column and 5 views on disk; smgdms and toocrm unknown.

**Verdict: rewrite** (a v4 structured rebuild on the existing Tiptap engine), with the three 3.x security patches (A6–A8) first.

**Reasons:**
1. The defects are architectural: storage contract, renderer trust, sync, lifecycle and upload model. They are not local bugs.
2. v4 permits the contract breaks the fixes require.
3. The measured migration surface is tiny.
4. The foundation (M7 core, M10 field base, M2 checks) supplies most of what the rewrite needs, so the rebuild is mostly composition.

### 10.6 Target design (v4)

**Extension set** (one registry, shared by JS and PHP)

- **Default:** StarterKit (Link configured with a protocol allowlist of `http`, `https`, `mailto`, `tel` and relative, `autolink`, `openOnClick: false`, `rel="noopener noreferrer nofollow"`), Placeholder (translated), TextAlign, Highlight (palette only), Subscript/Superscript, TextStyle + Color (palette only), Image (a custom node: `src`, `alt`, `width` as a %/px enum, `align` as an enum; no free-form style), FileHandler (paste/drop images), CharacterCount (opt-in `max`).
- **Opt-in:** TableKit, YouTube (strict: JS and PHP both drop non-YouTube URLs; iframe with `referrerpolicy` and a restricted `allow`), Mention (suggestion `props.mount()` plus async `items`), Find & Replace.
- **Removed:** free-form font size. Legacy preset keys are mapped to classes by the migration, not at render time.

**Composition (Blade vs JS)**

- Blade declares the chrome:
  - `toolbar="heading | bold italic underline | bullet ordered | link image ~ undo redo"` (Flux-style shorthand), **or** `<atom:tiptap.toolbar>` with `<atom:tiptap.button command="toggleBold" icon="bold" shortcut="Mod-B" label="Bold"/>` items;
  - bubble menus as Blade partials, `data-atom-part="menu-link|menu-image|menu-table"`.
- **One** registered `Alpine.data('tiptap')` factory (`atomComponent()` from M7) interprets the items: command dispatch, `isActive`, `aria-pressed`, a roving-tabindex toolbar through `core/keyboard.focusable`. No per-button inline `x-data`, so it is CSP-compatible.
- Bubble menus and the mention list use Tiptap's BubbleMenu and suggestion `mount()`, **registered for teardown** through `onDestroy()`.

**Livewire sync contract**

- **Island:** `wire:ignore` + `data-atom-island` on the editor mount node and its menus only. Label, error, caption and the `invalid`/`disabled` state stay morphable (C-9).
- **Value:** canonical Tiptap JSON (string) through `x-modelable`.
  - Emitted **debounced (default 400 ms)**, or on blur with `.blur`. Livewire's `.live` needs this, because it won't debounce a `<div>` (`livewire.esm.js:15237-15239`).
  - Echo suppression uses a revision counter plus deep-equal on the parsed doc, never string equality.
  - External changes apply only when the editor is not focused, or through an explicit `atom-tiptap-set` event.
  - `data-dirty` while there are unsynced changes. `destroy()` flushes a pending sync, then calls `editor.destroy()`.
- **Init:** idempotent (C-2). The mount node is cleared of any snapshotted `.ProseMirror` before `new Editor` (C-3).

**Storage, cast and renderer**

- **One cast, `AsRichText`** (replacing `AsTiptapContent`/`AsEditorContent`; name open, Q9).
  - The column holds **canonical JSON** (a `json` column is recommended).
  - `set()` accepts only an array or a JSON string. It **normalises through the tiptap-php schema** (unknown nodes and attributes stripped), **validates attributes** (URL allowlist for link, image and iframe; colour palette/hex regex; width/align enums), and promotes temp uploads.
  - `get()` returns the decoded array. **No `unserialize`, ever.**
- **`RichText::render($json)`** (tiptap-php, same registry and validators) is the only HTML path.
  - Also `RichText::text($json)` for search, snippets and notifications, and `RichText::sanitize($html)` for legacy HTML.
  - `<atom:tiptap.content :content>` renders **stored rich text only**.
  - A new `<atom:prose>` is a plain typography wrapper for static markup. That fixes E-H6 by splitting the two jobs.

**Image lifecycle** (ties into §2 S2)

- **Upload** through a `WithAtomEditor` action scoped per editor field (`_editor.images.{field}`), with server-side validation (mimes, max KB, max dimensions) and per-file progress/error events. Paste, drop and button all use the same path.
- **Stored** on `config('atom.editor.disk')` with `config('atom.editor.visibility')`; private means signed URLs at render (Filament precedent). The node keeps `src` + `data-id`, and alt text is editable in the image bubble menu.
- **Purge:** walks JSON image nodes in every `AsRichText` column across models (recursively). Dry run by default. **A grace period** (only files older than N days and unreferenced). The same disk resolver as the cast. A reference table is optional (Q10).

**Host extension API**

- **JS:** `document.addEventListener('atom:tiptap', e => e.detail.registerExtensions([...]))`, plus `enable`/`disable` (the Flux precedent).
- **PHP:** `RichText::extend(fn () => [new MyNode])`, so the server renderer knows the same nodes. A **parity test** renders fixtures through JS `getHTML()` (Playwright) and `RichText::render()` (PHP) and compares normalised DOM.
- Custom toolbar items are host Blade components passed in the toolbar slot.

**Chat composer**

- The same factory in `chat` mode: Enter to send, Shift+Enter for a newline, attachments, mentions.
- It emits `{json, files}`, not HTML. The host stores JSON and renders through `RichText::render()`.

**Migration of existing content**

- `atom:tiptap-migrate` v2, dry run by default. Per cast column, recursively over models:
  1. `unserialize($v, ['allowed_classes' => false])`;
  2. detect JSON vs HTML;
  3. HTML → JSON via `RichText::sanitize` + tiptap-php;
  4. normalise and validate;
  5. write canonical JSON with a query-builder update (no timestamp touch);
  6. report counts and failures.
- **Reversible by design (user decision, 2026-09-29).** HTML → JSON is lossy for anything outside the Tiptap schema (unknown tags, classes, inline styles), so the migration must never be a blind in-place overwrite. The 3.x `atom:tiptap-migrate` overwrites in place with no backup and reports only a count; v2 replaces that with:
  1. **Backup first.** Before any write, copy the original stored value per row into a sidecar store: an `atom_rich_text_backups` table (model, key, column, original value, migrated_at), or a `<column>_legacy` column if the host prefers. `atom:tiptap-migrate --restore` puts any row, or all of them, back byte-for-byte.
  2. **Round-trip report.** For every value, convert HTML → JSON → HTML and compare against the original, normalised on both visible text and structure (tag and attribute inventory). The dry run writes a report that lists every row where anything would be lost, and what (dropped tags, classes, styles, text diff).
  3. **Convert only clean rows automatically.** A row whose round trip is lossless is migrated. A flagged row is left untouched and listed for manual review, with `--accept=<ids>` to migrate it once reviewed. Nothing is silently lost.
  4. **Dual-read cast for a transition period.** `AsRichText` reads both legacy HTML and JSON, and upgrades a legacy value to JSON on its next save, so the migration is never a big-bang cutover. `atom:upgrade` still refuses to finish v4 while any flagged or unmigrated row remains, and the dual-read path is removed in the next major.
  5. Rehearse on a local copy of the host's data first (production stays off-limits). The backup table is dropped only by an explicit `--prune-backups` after the host signs off.
- Context for the risk: `<atom:editor>` has aliased `<atom:tiptap>` since v3.6.0, so every row a user has edited since then has already been through Tiptap's schema in the browser. Only rows untouched since then can still hold content the schema would drop, and those are exactly what the round-trip report surfaces.
- It must run while the host is **still on 3.x** (the last 3.x minor ships it), and `atom:upgrade` refuses to continue until it has.
- **humblebear:** `Task::description` (mixed legacy serialised HTML and v3.6 serialised JSON) → canonical JSON. Chat messages stay HTML history; see Q8.

### 10.7 Where the editor sits in the roadmap

**3.x first** (Track A, §9): **A6** engine bump (E-S1, E-S6), **A7** cast and renderer hardening (E-S2, E-S3, E-S4), **A8** `Content::sanitize()` plus the humblebear chat fix (E-S5), and **A2** purge (S2). All are patches.

**v4 milestones** (one PR each into `v4`; these replace M11d):

| ID | Milestone | Scope | Done when | Size | Depends on |
|---|---|---|---|---|---|
| **ME1** | Editor runtime on the core | `tiptap` factory on `atomComponent()`; narrow island; idempotent init and `destroy()`; sync contract (debounce, revision, deep-equal, dirty, flush on destroy); `readonly`/`disabled`/`invalid` from the M10a field base | Conformance C-1…C-4, C-7, C-9 green in the Livewire harness (typing across `.live` round trips keeps the caret; back/forward yields exactly one `.ProseMirror`; teardown leaves no listeners) | L | M1, M2, M7, M8, M9, M10a |
| **ME2** | Storage and renderer | `AsRichText` cast (JSON only, schema normalisation, validators, no unserialize); `RichText::render/text/sanitize`; strict YouTube; style validators; shared JS/PHP extension registry; `<atom:prose>`; remove `AsEditorContent`/`AsTiptapContent`, the `<atom:editor*>` aliases and `editor.css` | Pest hostile-content suite (javascript:/data: links, iframe src, style injection, serialized-object strings, non-scalar attrs) green; each case mutation-tested; JS↔PHP parity test green on 20 fixtures | M–L | M4b, M5 |
| **ME3** | Image lifecycle | Per-field upload action; validation; paste/drop/button; progress and errors; alt text; disk and visibility (signed URLs for private); purge v2 with grace period and dry-run default | Playwright: paste, drop and button uploads, a rejected type and size show errors; two editors in one component don't collide; the purge fixture keeps live images and moves only old orphans | L | ME2, M4a (`WithAtomEditor`) |
| **ME4** | Toolbar, menus and mentions | Declarative toolbar (shorthand plus items), roving tabindex, bubble menus as Blade partials with teardown, link menu (protocol allowlist, real buttons, working errors), mention via suggestion `mount()` with async `items`, a listbox with `aria-activedescendant`, dark-mode content CSS with tokens, translated strings, CharacterCount | C-6/C-7/C-8 green for toolbar, menus and mention; axe-style name checks; no inline `x-data="{"` under `components/tiptap` | L | ME1, M8, M10a |
| **ME5** | Chat composer and host extension API | Chat mode on the same factory emitting `{json, files}`; `atom:tiptap` JS event plus `RichText::extend()`; custom toolbar items; docs | A host fixture registers a custom node and toolbar button, and it renders identically in JS and PHP; the chat e2e sends text, mention and attachment | M | ME1–ME4 |
| **ME6** | Content migration and humblebear pilot | `atom:tiptap-migrate` v2 (backported to the last 3.x minor as well); `atom:upgrade` blocks on legacy casts; humblebear PR: migrate `Task::description`, switch to `AsRichText`, replace the 5 views (`<atom:tiptap>` for the task form, the chat composer on JSON, `<atom:prose>` for privacy/terms, `<atom:tiptap.content>` or sanitised render for the blog) | Migration is reversible (§10.6 "Migration of existing content"): backup table populated before any write, and `--restore` proven byte-for-byte on a local copy; the round-trip report lists every lossy row, and flagged rows stay untouched until accepted; the dual-read cast serves both formats; dry-run counts match humblebear's row count; after apply, humblebear's task-form tests (type, embed image, save, reload) and chat tests pass | M (atom) + M (host) | ME2, ME5, M17 |

**Placement:** ME1–ME5 run after the foundation (M7–M10a) and alongside the component migrations (M11–M14). ME6 is the editor's pilot step inside M19 (humblebear).

## 11. Open questions

### Defaults I'd apply (not questions any more, unless you object)

- **Architecture:** Alpine plus the shared core, not custom elements.
- **Label escaping:** on by default in v4, with `HtmlString` opt-in. The on-disk scan found no host passing HTML labels.
- **Trait helpers:** toast/modal/alert/confirm/command/wirekey become Component macros (call sites unchanged, not browser-callable); table actions stay public in `WithAtomTable`; everything else `protected`.
- **CSS:** `atom.tailwind.css` is the required integration, and `atom:upgrade` writes the import.
- **`window.atom.core`:** exposed as documented-internal, for tests.
- **Date swap:** removed from the package; the codemod adds the one-line opt-in to each host, so hosts keep immutable dates.
- **Legacy editor:** removed in v4; Tiptap migration becomes a hard precondition.

### Questions that genuinely need you

1. **Dependencies.** May atom add dev-only tooling: Tailwind v4 CLI in an isolated `tests/consumer/package.json`, GitHub Actions, and optionally `nikic/php-parser` for the `atom:upgrade` trait split? CLAUDE.md requires your approval for dependency changes.
2. **Non-UI services.** Keep `broadcast`, `sitemap` and `mail` inside atom (moved to `Support\`, off the facade), or extract them to a separate jiannius package or into humblebear, their main user (15/3/12 uses)?
3. **Unadopted components.** Keep and migrate the 13 components no on-disk host uses (accordion, command, context menu, kbd, pagination, progress, rating, slider, otp, `table.actions`, `form.modal`, `toast.trigger`, `uploader.dropzone`), or drop some from v4? Default is keep.
4. **3.x support and the missing hosts.** Is "until every active host is on v4 in production + 90 days" right? Who owns smgdms and toocrm, so they can run `atom:upgrade-check`?
5. **S3 in 3.x.** Ship an opt-in `filter()` allowlist as a 3.x patch now, or accept the risk until v4? An allowlist enforced by default in 3.x could break host filters on columns that aren't displayed.
6. **v4 → main merge style.** A merge commit (keeps ~25 milestone commits in main's history), or one squash commit? Your global rule prefers squash for local merges and platform squash for PRs; this merge is unusually large.
7. **Go-ahead for Track A now.** A1 (select XSS), A2 (purge data loss) and **A6–A8** (the editor's paste-XSS engine bump, the cast/renderer hardening, and the chat sanitiser) are verified in code. A6 answers a published high-severity advisory. Do you want them started before the v4 work?
8. **Chat message format** (§10.6). New chat messages as Tiptap JSON rendered server-side (recommended), or sanitised HTML? humblebear stores HTML bodies today and renders them with `x-html`. Existing messages stay HTML, sanitised on render, either way.
9. **Cast name.** A new `AsRichText` (a clean break), or keep the name `AsTiptapContent` with the new semantics?
10. **Editor image references.** Scan-based purge with a grace period (no new table; recommended), or an `atom_editor_images` reference table? That would be atom's first migration.
11. **Private editor images.** Should the default visibility be private (signed URLs, as Filament does) or public (today's behaviour)?
12. **Who fixes the humblebear host issues** found on the way? They are the chat `x-html` render (E-S5), the option `html` built from `$doc->name` (S1-host), and `alt="{!! $this->blog->name !!}"` (`blog.blade.php:190`). They are host code, outside atom.
