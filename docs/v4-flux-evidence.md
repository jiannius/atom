# atom foundation plan: learning from Flux without adopting it

Prepared 2026-09-29 for `jiannius/atom` at `v3.29.5` (`e086c91`). Read-only research: nothing in the atom repo was changed.

**Decision already taken (not re-argued):** atom stays in-house. This plan borrows Flux's *engineering*, not Flux itself.

---

## 0. How to read this document

### Evidence tags

| Tag | Meaning |
|---|---|
| **[V]** | Verified by reading source. A `path:line` citation follows. |
| **[V-run]** | Verified by running something in the scratchpad (never inside atom). |
| **[Iss]** | From Flux's public GitHub issue tracker (`livewire/flux#N`), read through `gh`. |
| **[Doc]** | From fluxui.dev documentation pages. |
| **[I]** | Inference or design judgement. Not verified. Treat it as a hypothesis. |

### Sources and their limits

- **Flux source.** `git clone https://github.com/livewire/flux`, HEAD `ee9ad7f` (2026-09-28, `v2.20.0-3`), in `scratchpad/flux/`.
  - The public repo carries PHP, Blade stubs, `dist/flux.css`, and **minified** JS only.
  - `src/AssetManager.php` (`fluxJs()`/`fluxMinJs()`) serves `dist/flux-lite.min.js` to every non-Pro install. That is the current free bundle; `dist/flux.min.js` is stale, last touched Feb 2025.
  - I prettier-formatted the lite bundle into `scratchpad/fluxjs/flux-lite.js`. **Every Flux JS line number below refers to that formatted file** (sha1 of the minified input: `f4b44df4…`). Identifiers are minified, so I name each class by what it does ("Popoverable", "Anchorable" and so on). Flux's own issue #2707 quotes the unminified names `Popoverable`/`Anchorable`, which confirms those readings.
- **Flux Pro limit.** The Blade for Pro components (listbox/combobox select, date/time picker, calendar, tabs, popover, context, command, editor…) is **not public, and I did not examine it**. The *JS* for `ui-select` ships in the free lite bundle, so the select's behaviour is visible; its Pro markup is not. What I say about Pro components comes from that JS plus the docs, and goes no further.
- **Flux tests.** None are public. The public CI (`.github/workflows/ci.yml`) runs only `composer validate --strict` and `php -l` over `src/`. There is nothing public about how Flux tests JS. Their testing culture can only be inferred from issue triage: they demand copy-pasteable Volt repros and close issues they cannot replicate (#2605, #1565).
- **Flux issues.** I downloaded all 1,586 issue titles to `scratchpad/issues/all.tsv` and read the bodies and comments of the ones cited.
- **Livewire internals.** Read from atom's own vendored `vendor/livewire/livewire/dist/livewire.esm.js` (Livewire **v4.3.0**). That is read-only and is what atom's rig runs.
- **atom.** Its CLAUDE.md, `src/`, `components/`, `resources/js`, `resources/css/atom.css`, `tests/`, `git log v3.14.0..HEAD`, and the memory files under `~/.claude/projects/-Users-tj-Projects-jiannius-atom/memory/`.
- **Host apps.** I read only `humblebear` and `sudo`, read-only, because they are the two on disk. smgdms and toocrm were not available, so nothing below is verified for them.

---

## 1. Flux findings, topic by topic

### 1.1 Surviving Livewire morphs and re-renders

**What Flux does**

- **[V] Custom elements carry the behaviour; Blade renders plain server HTML.**
  - Every interactive primitive is a `ui-*` custom element registered by `A(name, cls) → customElements.define("ui-"+name)` (`flux-lite.js:1238-1240`).
  - The free bundle registers: modal, disclosure, checkbox(-group), dropdown, progress, tooltip, sidebar, button, switch, close, toast(-group), field, label, description, legend, radio(-group), select, selected, menu (+ submenu/checkbox/radio), and otp (`flux-lite.js:2058…7397`).
  - Blade stubs emit `<ui-dropdown>`, `<ui-modal>`, `<ui-field>`… (`stubs/.../dropdown.blade.php:19`, `modal/index.blade.php:111`, `field.blade.php:44`).
- **[V] State lives in DOM attributes, and those attributes are made "durable".**
  - `setAttribute`→`c(el,name,value)` and `removeAttribute`→`w(el,name)` register each attribute the JS owns with a per-element MutationObserver.
  - If anything else changes that attribute (in practice, Livewire's morph patching attributes back to the server's HTML), the observer immediately restores the JS value (`flux-lite.js:1537-1607`).
  - Examples of attributes kept this way: `data-open`, `aria-expanded`, `data-active`, `aria-selected`/`data-selected`, `popover`, `role`, `aria-controls`, `aria-activedescendant` (e.g. dropdown `flux-lite.js:4355-4390`, option activation `6112-6130`, `6616-6625`, `6807-6819`).
  - This is the core mechanism that lets Flux skip `wire:ignore`: **the server owns structure and content, the JS owns a declared set of attributes, and the JS set wins.**
- **[V] Positioning is durable too.** Anchorable's `hn()` writes the inline `position/left/top/right:auto/bottom:auto/maxHeight`, then watches the `style` attribute with a MutationObserver and re-applies it if a morph rewrites it (`flux-lite.js:4251-4283`).
- **[V] Flux uses `wire:ignore` only where the browser owns the state.**
  - `modal/index.blade.php:117` puts `wire:ignore.self` on the `<dialog>`, commented "the dialog element adds a 'close' attribute that isn't durable". The underlying reason is in Livewire: its morph *calls `dialog.close()`* when the server HTML lacks `open` (`livewire.esm.js:8727-8731` [V]).
  - `toast/index.blade.php:8` and `toast/group.blade.php:8` put `wire:ignore` on the toast host, whose content is cloned client-side from a `<template>`.
  - `input/file.blade.php:33`.
- **[V] Lists are server-rendered, never generated by JS.**
  - Select options are real `<ui-option>` children rendered by Blade. The JS only annotates them (activatable/selectable/filterable mixins, `flux-lite.js:6013-6199`).
  - A `MutationObserver` on the option list's `childList` re-syncs the active/selected state when a morph adds or removes options (`flux-lite.js:6464-6474`).
  - Free select options get an automatic `wire:key="{{ $value }}"` (`select/option/variants/default.blade.php:10`).
  - There is no `x-for`, so the "rows pulled out from under the loop" class cannot happen.
- **[V] Ids are minted client-side and seeded into Alpine's binding table.**
  - `R(el, prefix)` keeps an existing id or mints `lofi-<prefix>-<random>`, writes it durably, and **also stores it in `el._x_bindings.id`** (`flux-lite.js:1333-1346`).
  - That line matters because Alpine's morph calls `seedingMatchingId(to, from)`, which copies `from._x_bindings.id` onto the incoming server node *before* computing the morph key (`livewire.esm.js:8759`, `9001-9008` [V]). Livewire's key function falls back to `el.id` (`livewire.esm.js:14077-14081` [V]).
  - So a client-minted id never makes the morph treat the node as new. This is the same mechanism that made TJ's v3.15.4 `x-id`/`$id` select fix work.
- **[V] `wire:model` binds through a value property plus events, not entangle.**
  - The Controllable mixin redefines `el.value` with a getter/setter on the custom element and dispatches **non-bubbling** `input` and `change` by default (`flux-lite.js:1843-1879`). Livewire's `wire:model` becomes an Alpine `x-model` binding on that element (`livewire.esm.js:15203-15215` [V]), so it just reads and writes `el.value`.
  - A reentrancy guard (`D()`, `flux-lite.js:1347-1360`) stops the setter→onChange→dispatch loop.
- **[V] `.self` on `wire:model`.** Flux stubs append `.self` to `wire:model` on containers (modal/dropdown/tooltip, e.g. `dropdown.blade.php:9-16`) so that bubbling `input` events from nested fields don't write to the container's model. That fix came from issues #66 and #850 [Iss]. **Livewire 4 now adds `.self` to every `wire:model` itself** unless `.deep` is used (`livewire.esm.js:15163-15167` [V]), so on Livewire 4 this is framework-level behaviour.

**Verdict: ADAPT.**
- Adopt: the *ideas* of durable JS-owned attributes and durable inline positioning; server-rendered lists wherever the data is available at render time; the Alpine-binding id seeding.
- Skip: custom elements as the vehicle (see 1.2 and §4.1).

In Alpine the durable-attribute idea mostly already exists. **[V]** During morph Alpine calls `Alpine.cloneNode(from, to)`, which runs `initTree` on the incoming node with bindings evaluated (`livewire.esm.js:2633-2643`, `8686-8687`). So **attributes set through `x-bind` survive a morph, and attributes set imperatively with `setAttribute()` do not** (unless the node is `wire:ignore`d). atom's factories set state attributes imperatively in several places (see §2, "latent").

### 1.2 Component lifecycle

**What Flux does [V]**

- **Base element** (`flux-lite.js:1718-1786`):
  - `constructor()` → `boot()`, so it runs **once per DOM node**.
  - `connectedCallback` → `queueMicrotask(mount)`, *unless* the node was only moved: `wasDisconnected` is set by `disconnectedCallback`, and a disconnect followed by a reconnect in the same task skips the re-mount.
  - `disconnectedCallback` → a microtask later, if still detached, runs `unmount()` and every `onUnmount()` callback.
- **Mixins** attach behaviour through `new Mixin(el, opts)` with `boot`/`mount` phases and walker-based grouping (`flux-lite.js:1787-1842`).
- **Idempotent init.** Every node JS injects is tagged `data-appended` (`1951`, `2568`, `5922-5973`), and components delete prior `[data-appended]` children at boot (modal `1992-1994`, `ui-selected` `5778`). Hidden form inputs for submission are regenerated from a `childList` observer (`2498-2587`).
- **Cleanup.**
  - Popoverable scopes its document listeners to an `AbortController` that is aborted on close (`flux-lite.js:2871-2935`).
  - Scroll lock is reference-counted (`Pe` counter, `1624-1671`).
  - Components that lock scroll call unlock in `unmount()` (`2039-2044`, `4395-4400`).
- **wire:navigate.**
  - The script tag is emitted with `data-navigate-once` (`AssetManager.php`, `scripts()`), so it is never re-executed.
  - Appearance is re-applied inside `livewire:navigating` → `detail.onSwap` (`flux-lite.js:7475-7479`), before paint.
  - Swapped-in elements are new nodes, so constructors run again.

**Flux's own bugs in exactly this area [Iss]**

Custom elements did *not* remove lifecycle bugs; they moved them:
- **#2605.** `boot()` runs in the constructor, so a morph `swapElements` that `cloneNode(true)`s a subtree boots elements while **detached**, and `closest()` returns null. Closed as unreproducible.
- **#2524.** During a `wire:navigate` swap, `connectedCallback` fires **before the children are parsed**, and `querySelector` for `ui-option-empty` returns null.
- **#2678, #1565, #2514.** The `wasDisconnected` move-guard sometimes skips a needed re-mount after navigate. Tooltip `mount()` throws, and in #2678 the exception broke Livewire's `wire:submit` binding elsewhere on the page, so the form fell back to a native GET.
- **#2653.** `<flux:tooltip>` never ran its teardown when morph removed it. It leaked document/window listeners (+400 listeners / +1,599 detached nodes per cycle, measured with CDP).
- **#2707.** Date-picker and colour-picker never called `anchorable.cleanup()`, so each open stacked another floating-ui `autoUpdate`.
- **#2708.** Carousel indicators **duplicated after back/forward navigation**. This is the same class as atom's v3.29.2 date-range duplicate calendars.

**Verdict: ADAPT the discipline, SKIP the custom-element lifecycle.** The durable lessons, each independent of custom elements:
1. Tag every JS-injected node and remove prior ones at init.
2. Bind document/window listeners to an AbortController that is aborted on close and on teardown.
3. Pair every "reposition" with a "cleanup".
4. Reference-count global side effects (scroll lock).
5. Never let one component's init exception escape into Livewire's boot.

**[V] Why back/forward duplicates happen at all.** Livewire stores `document.documentElement.outerHTML`, the *live DOM including JS-injected nodes*, as the back/forward snapshot (`livewire.esm.js:12583-12590`) and re-initialises Alpine over it on `popstate`.
- **[I]** This is the most plausible real-world trigger of smgdms#141 (atom's duplicate range calendars). atom's memory records that the actual trigger was never pinned, and that the reproduced mechanism was Alpine destroy/init re-entry. The v3.29.2 fix (`destroyCalendar()` clears the containers first, `resources/js/alpinejs/date-range.js:91-98`) covers both.

### 1.3 Floating and overlay positioning

**What Flux does [V]**

- **One overlay stack shared by dropdown, tooltip, select, menu and navmenu.** The date-picker and colour-picker (Pro) use the same Anchorable, per issue #2707's list of call sites [Iss].
  - **Popoverable** (`flux-lite.js:2870-2970`):
    - forces `popover="manual"` durably;
    - tracks state through `beforetoggle`;
    - does its own light-dismiss (outside click, outside `focusin`, Escape) with listeners scoped to an AbortController;
    - supports **scopes**: opening one closes others in the same scope unless they are nested ancestors or descendants (`Ho()`);
    - restores focus to the previously active element on close (`2884-2899`).
  - **Anchorable** (`flux-lite.js:4142-4283`):
    - bundled floating-ui with `offset`, `flip`, `shift({padding:5})`, `size` (writes `maxHeight` = available height, optional `matchWidth`), and optional `hide` (closes a tooltip when the reference scrolls out);
    - `autoUpdate` while open and `cleanup()` on close;
    - RTL mapping of `start`/`end` (`cn()`, `4239-4250`).
  - **The strategy and the inline `position` are chosen by the same variable** (`Xs`, `4142`, `4201`, `4254`): `absolute` with native popover (top layer), `fixed` under the polyfill. atom's v3.25.1 bug existed because the strategy (JS) and the position (a Blade utility) were set in two places.
  - A popover polyfill is bundled (`flux-lite.js:1-1097`, apparently included twice). **CSS anchor positioning is not used.**
- **Modal:** native `<dialog>` + `showModal()`.
  - `closedby="none"` plus key/cancel interception when not escapable (`flux-lite.js:1882-1972`).
  - The outside click compares both the mousedown and click coordinates against the content box, so a drag-select that ends outside doesn't close it (`1900-1912`, #712).
  - A focus placeholder stops the dialog auto-focusing its first control.
  - Structural `dialog, ::backdrop { margin:auto }` is injected into `@layer base` (`2057`).
- **Toast:** `popover="manual"` host plus a cloned `<template>` (`flux-lite.js:5264-5340`, `toast/index.blade.php:8-10`).

**Flux's recurring bugs here [Iss]**

Scroll lock dominates, not wrong coordinates:
- #1251 "Modal permanently locks scrolling", #839 "Scroll lock styles not being cleaned up on Navigate", #358/#236/#1404 "scrolling not working after closing modal";
- #2230/#2224/#1461 layout shift from `scrollbar-gutter`;
- Escape bubbling out of popups (#1176);
- anchor cleanup leaks (#2707).

Few "panel in the wrong place" reports, because the position is inline, durable, and matched to the strategy.

**Verdict: ADOPT the shape** (one popover + anchor primitive, scopes, focus restore, size middleware, strategy and position chosen together, durable inline position, cleanup contract). **SKIP** Flux's JS scroll lock: atom's CSS `html:has([data-atom-modal][data-open]){overflow:hidden}` (`resources/css/atom.css:41-48`) is self-cleaning and naturally reference-counted, which avoids the entire #1251/#839 family. Keep floating-ui (atom already depends on it, `package.json`) and skip the polyfill; the targets are evergreen browsers [I].

### 1.4 Styling architecture

**What Flux does [V]**

- **`dist/flux.css` is a Tailwind v4 *source* file.** The host imports it into its own build (`README.md` "Set up Tailwind CSS").
  - Its first lines are `@source "../../flux-pro/stubs"; @source "../stubs";` (`dist/flux.css:1-2`), so **the host's Tailwind scans Flux's Blade**.
  - It declares `@theme` tokens (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`), a `.dark` override in `@layer theme` (`flux.css:25-37`), and `@custom-variant`s (`flux.css:6-23`).
  - Structural rules sit in `@layer base` (custom-element `display` rules, `flux.css:173-212`; added in #2400 to stop layout shift) and `@layer components`.
- **Visual defaults are zero-specificity.** Commit `2197a4f` (#2747, "Allow utility classes to override visual defaults") rewrote defaults to `[:where(&)]:border-zinc-200` etc., so a host `class=` wins without `!`.
- **Every element carries a `data-flux-*` hook,** documented as the global override surface [Doc customization]. CSS state comes from `:has()` and `data-*` instead of JS (`field.blade.php:9-41`, e.g. dimming a label when `[data-flux-control][disabled]`) — the "Use CSS" principle [Doc principles].
- **Dark mode.** The `.dark` class on `<html>`, set by `@fluxAppearance`'s inline head script from `localStorage['flux.appearance']` or the system preference (`AssetManager.php` `fluxAppearance()`); the reactive `$flux.appearance`/`$flux.dark` API (`flux-lite.js:7398-7482`); and the host adds `@custom-variant dark (&:where(.dark, .dark *))` [Doc dark-mode].
- **Customisation.**
  - `php artisan flux:publish` copies stubs into `resources/views/flux`, which is registered as an anonymous component path *before* the package's (`FluxServiceProvider.php:38-45`).
  - The cost: published copies go stale. The bug template tells reporters to "Delete any previously published Flux components" (`.github/ISSUE_TEMPLATE/bug_report.yml`).
- **Costs Flux pays for this model [Iss]:**
  - One malformed class in any stub warns in every host build (#2701, #2660).
  - Host builds need `vendor/` present at `npm run build` time (#2534).
  - Dynamic class names can't be scanned (#2304).
  - No support for a Tailwind `prefix` (#1766).
  - Theme override precedence surprises (#2569, #911).

**What this corrects in atom's record [V-run].** atom's memory (`atom-command-palette.md`) says "bare Tailwind state-variants like `data-active:` and pseudo-element variants like `backdrop:` DO NOT COMPILE in consumer builds — they're not stock Tailwind".
- I compiled a test file with the **same Tailwind version the hosts declare (`^4.0.7`**, `humblebear/package.json`, `sudo/package.json`) in the scratchpad. `data-active:bg-red-500`, `data-open:grid`, `backdrop:bg-black/50` and `[&[data-active]]:` **all compile** (`scratchpad/tools/twtest/out.css`).
- atom itself ships bare `data-current:` (`components/navlist/item.blade.php:43-44`) and `data-stashed:` (`components/layouts/sidebar.blade.php:57`).
- So the real failure in v3.9.0 was something else. Plausible causes **[I]**: the class never reached a host rebuild, the host's hand-written `@source` list missed a path, or an unlayered `atom.css` rule (e.g. `dialog[data-atom-modal]::backdrop`, `atom.css:50-73`) outranked a layered `backdrop:` utility.
- The rule "use `[&[data-active]]:`" is harmless but rests on a wrong premise. The real hazard is that **no atom test has ever compiled a single utility** (see §2).

**Verdict:**
- **ADOPT** a host-importable Tailwind source file (`@source` + `@theme` tokens + `@custom-variant dark`) as an *optional, additive* replacement for the `@source` lists every host hand-maintains today. humblebear's `resources/css/app.css:4-10` and sudo's `:3-8` already differ [V].
- **ADOPT** `[:where(&)]` defaults and `data-atom-*` hooks. atom already uses `[:where(&_…)]` in `components/dropdown.blade.php:12-16`, so this means making it the rule.
- **ADOPT** a CI compile check.
- **SKIP** publish/override for now. The stale-copy cost outweighs the benefit for a four-host in-house library [I].

### 1.5 Blade component architecture

**What Flux does [V]**

- **Tag compiler.** `FluxTagCompiler` (`src/FluxTagCompiler.php`) is line-for-line the parent of atom's `src/Services/TagCompiler.php`: the same regexes, the same inline `slot=` support. Flux adds `flux:delegate-component` (forward data, attributes and named slots to a computed component, `FluxTagCompiler.php:12-23`), which it uses for variants (`select/index.blade.php:17`).
- **Composition through `with-field`.** Any control wrapped in `<flux:with-field>` gets `<flux:field>` + label + description + error, but **only if** a label or description prop is present (`with-field.blade.php:28-58`).
- **Prefix-scoped attribute forwarding.**
  - `Flux::attributesAfter('label:', $attributes)` routes `label:*`, `description:*`, `error:*`, `field:*` and `input:*` to the right sub-element (`FluxManager.php:149-167`, `with-field.blade.php:31-34`, `input/index.blade.php:39`).
  - `Flux::splitAttributes` sends `class`/`style` to one node and everything else to another (`FluxManager.php:108-114`, `modal/index.blade.php:103-104`).
  - `Flux::forwardedAttributes` unescapes forwarded props that Blade double-escaped (`FluxManager.php:128-147`; that bug is #2710).
- **Default field name** comes from `wire:model`: `'name' => $attributes->whereStartsWith('wire:model')->first()` (`input/index.blade.php:17`, `with-field.blade.php:21`).
- **Other patterns.** `Flux::classes()->add(...)` (`ClassBuilder.php`) is equivalent to atom's `Arr::toCssClasses` and `$attributes->classes()` macro. `@blaze(fold: …)` compile-time folding (a Livewire Blaze package) produced its own bugs (#2564 `flux:error` folded as static hidden HTML) [Iss].

**Verdict:**
- **ADOPT** prefix-scoped attribute routing (`input:`, `label:`, `error:`…). atom's "id landed on the wrapper `<div>`" class (memory `atom-input-label-morph-key.md`; `atom-testing.md` "A probe beats reading the blade") is exactly what it prevents.
- **ADAPT** `with-field`: atom already has `<atom:input.field>` (`components/input/field.blade.php`).
- **SKIP** Blaze and delegate-component (no evidence atom needs them).
- The tag compiler needs no change; atom already copied it.

### 1.6 Accessibility

**What Flux does [V]**

- **Label wiring is client-side.** `<ui-field>` finds its first control (a `ui-*` form element or `input/textarea/select`, `flux-lite.js:5497-5588`). `<ui-label>`/`<ui-description>` mint ids and register themselves, and the field sets `aria-labelledby`/`aria-describedby` durably on the control, or on the control's first `<button>` for composite widgets (`elOrButton`). Clicking the label focuses or toggles the right part of each widget type (`focusOrTogggle`).
  - Consequence: **no association exists until JS runs.**
  - atom's server-derived ids are better on this point: `$attributes->fieldId()`, `src/Macros/ComponentAttributeBag.php:58-67`, emits a `for` in the served HTML.
- **The error region is always rendered** as `<div role="alert" aria-live="polite" aria-atomic="true">` and hidden when empty (`error.blade.php:25-29`), so the live region exists before a message arrives and a morph only fills it.
- **Two keyboard primitives, reused everywhere:**
  - **Activatable** (listbox/combobox virtual focus): `data-active` + `aria-activedescendant` durable, typeahead, arrow keys, filter-aware walker that skips disabled or filtered options (`flux-lite.js:6013-6136`, `6524-6569`, `6807-6819`).
  - **Focusable** (menus, roving tabindex): `tabindex 0/-1` management, first/prev/next, typeahead (`flux-lite.js:4806-4884`; menu `6862-6943`).
- **Escape is stopped from propagating** when it closes an open select (`stopImmediatePropagation`, `flux-lite.js:6658-6666`), in response to #1176 [Iss]. The other popovers only `hidePopover()` on Escape (`qo()`, `2962-2970`), so this is applied per component, not generally.
- **Pointer vs keyboard intent** is tracked globally (`flux-lite.js:1297-1320`), so `scrollIntoView` and focus moves happen only for keyboard users.
- The launch post claims "Accessible components" (Caleb's launch tweet, [x.com/calebporzio/status/1838227011564679651](https://x.com/calebporzio/status/1838227011564679651)). No public audit exists.

**Verdict:**
- **ADOPT** the two keyboard primitives as shared JS. atom's select already implements activedescendant by hand (`resources/js/alpinejs/select.js:223-260`), and the command palette re-implements it (memory `atom-command-palette.md`).
- **ADOPT** the always-present error live region.
- **ADOPT** a general Escape-stop rule and focus restore.
- **KEEP** atom's server-derived ids over Flux's client-side labelling.

### 1.7 Events and the PHP ↔ JS API

**What Flux does [V]**

- **PHP API.**
  - `Flux::modal($name)->show()/close()` dispatches `modal-show`/`modal-close` with `name`.
  - `$this->modal()` inside a component adds `scope: $component->getId()` (`src/Concerns/InteractsWithComponents.php:15-53`).
  - `Flux::modals()->close()` dispatches with no params (`:55-63`).
  - `Flux::toast(text, heading, duration, variant, position, link, action)` builds `{duration, slots:{text,heading}, dataset:{variant,position}, link?, action?}` (`:65-81`).
- **JS API.** `window.Flux` / `$flux` mirrors it: `toast(...)` normalises positional or object arguments into the same shape; `modal(name).show()` dispatches on `document` with `detail:{name}`; `modals().close()` sends `detail:{}`, always an object (`flux-lite.js:7406-7450`).
- **Modal listener.** `fluxModal(name, scope)` Alpine data (`flux-lite.js:7566-7584`), bound as `x-on:modal-show.document` (`modal/index.blade.php:123-126`). Scope matching lets two components each own a modal called "confirm".
- **Toast payload.** The documented `action` and `link` objects are `{label, event|href, params, dismiss}` and `{label, href, target, navigate}` [Doc toast].

**Verdict: ADAPT.**
- atom's surface is already nearly identical (`src/Atom.php:140-214`; `resources/js/helpers/modal.js`, `helpers/toast.js`).
- What atom lacks: one normalising reader (the null-`detail` bug, v3.24.1) and optional **scope**.
- Keep atom's event names (`atom-modal-show` …). They are host API: humblebear dispatches `atom-modal-close` directly in 9 places, mostly the no-payload bubbling form, e.g. `resources/views/livewire/app/settings/user/form.blade.php:196` [V].

### 1.8 Testing and QA: what Flux got wrong and fixed

- **[V]** No public tests. CI is `composer validate` + `php -l` (`.github/workflows/ci.yml`).
- **[Iss] Recurring themes, by title keyword count** (1,586 issues; counts overlap and are indicative only):

  | Theme | Titles |
  |---|---|
  | select/listbox/combobox | 292 |
  | a11y/keyboard/focus/label | 191 |
  | date/calendar/time | 187 |
  | modal/flyout | 157 |
  | dropdown/menu/popover/tooltip | 118 |
  | wire:model/sync | 107 |
  | positioning/scroll | 83 |
  | CSS/Tailwind | 77 |
  | dark mode | 46 |
  | morph/re-render/lost state | 36 |
  | wire:navigate | 26 |

- **[Iss] The ones that map onto atom's classes:**
  - lifecycle races under navigate (#2524, #2678, #2605);
  - teardown leaks (#2653, #2707);
  - back/forward duplicates (#2708, #697);
  - event bubbling into container models (#66, #850 → `.self`);
  - Escape bubbling (#1176);
  - label click not updating the model (#612, #624, #630, #668);
  - scroll lock not released (#1251, #839);
  - dark-mode flash on navigate (#1887, #1649, #1842);
  - CSP incompatibility of inline Alpine expressions, fixed by moving them into registered `Alpine.data` (`6865c8b`, #2277 [V]);
  - layout shift from JS-applied display rules, moved into CSS (#2394 → #2400 [V]).
- **Verdict: LEARN, don't copy.** Flux gives no test method to borrow. Its tracker is a free list of failure modes, and the conformance suite in §5 turns each into a check.

---

## 2. atom's own record, checked against the evidence

Fix commits `v3.14.0..v3.29.5` (43 commits, 34 `fix`/`feat`) plus the memory files.

### 2.1 The bug classes as you listed them

Each class is confirmed, with the extra evidence found.

**Morph survival — CONFIRMED, and still open at three sites.**
- History:
  - #16, #17, `1fa58f6`: select `uniqid` in `x-data` → morph re-evaluated it; `x-for` rows pulled out → `wire:ignore` on the option list; `x-id`/`$id`.
  - #55: the date-picker time panel had no `wire:ignore`.
  - #36/#39: `str()->random` ids used as morph keys.
  - #40: `command.group` churning its id.
  - #59: `wirekey()` dropped `0` → key collisions.
- **[V] Latent, still in the code:**
  - `components/accordion/item.blade.php:7` mints `Str::random(8)` per render and interpolates it into `x-init`, `x-on:click` and `x-bind:*` expressions. **[I]** An item's open state, keyed by that id, would reset on any Livewire re-render of the accordion.
  - `components/rating/index.blade.php:23` and `components/slider/index.blade.php:22` fall back to `Str::random(6)` ids when there is neither `id` nor `name`.
  - `wirekey()` with no arguments returns a random ULID per call (`src/Traits/AtomComponent.php:264-269`), so hosts can still reach this bug.
  - The dropdown sets `data-open`/`aria-expanded` imperatively (`resources/js/alpinejs/dropdown.js:25,59-60,84-85`, and `context-menu.js:31,72`) on nodes that are not `wire:ignore`d. Per 1.1 a morph removes them. **[I]** Mid-update while open, `data-open` would drop, so the menu goes to `opacity-0` (`components/dropdown.blade.php:15-16`) and `aria-expanded` becomes wrong. Unreproduced.
  - `components/date-picker/range.blade.php:22` puts `wire:ignore` on the *whole root*, which freezes the `$invalid` border on the inner input (`:16`, `:41`) at its first render. The `group-has-[[data-atom-error]]` CSS path still works.

**Alpine lifecycle re-entry — CONFIRMED.** #52 (`setCalendar()` with no cleanup). The real-world trigger is probably the back/forward snapshot (1.2). The defences are hand-rolled per factory and inconsistent:
- `select.js:63-67` and `dropdown.js:36-37` store an AbortController on the element;
- `tooltip.js:24-27` binds four `$root` listeners with no signal, so an Alpine re-init on the same node stacks them;
- `date-range.js:91-98` clears containers.

**Floating positioning — CONFIRMED.** #19 (UA popover `inset:0; margin:auto`), #35 (top layer: `absolute` vs `fixed`), `02139dd` (listbox scrolls the page). Root cause: strategy in `helpers/floatingui.js:23`, position in a Blade utility, reset rules in `atom.css:149-168`. That is three places.

**CSS that only works in some builds — CONFIRMED, with one correction.**
- #32 `[x-cloak]` undefined; #43 `hidden` beaten by Preflight, and `wire:loading` unstyled without Livewire's stylesheet; #20/#46/#47 the currentColor border default; #44 muted tokens; #25 `max-w-*` on a content-sized dialog.
- **Correction:** the bare `data-*:`/`backdrop:` variants *do* compile on Tailwind 4.0.7 (1.4). The class is really "atom has never verified a compiled utility".

**Event payload shapes — CONFIRMED.** #33: a no-payload `$dispatch` gives `detail === null`. Three channels with different shapes coexist:
- PHP → Livewire → window (`src/Atom.php:147-214`);
- JS helper → `window.dispatchEvent` (`resources/js/helpers/modal.js:4,16`);
- Blade `$dispatch` → bubbling from the element (hosts, e.g. humblebear).

**Server surface reachable from the browser — CONFIRMED, plus one possible new sink (below).**
- #26/#27 (the public action endpoint, `GetOptions` paths and methods), #34 (the trait's public `action()`), #57 (`raw:` sort SQLi).
- Still open: every public trait method is `$wire`-callable in every host. The list is pinned in `tests/Feature/AtomComponentSurfaceTest.php:21-82` (e.g. `tableSelection`, `selectAllTableMatching`, `verifyRecaptcha`). **Not audited for side effects or return-value exposure.** That is an open question, not a finding.

**Accessibility — CONFIRMED.** #36, #39-#42 (unnamed controls, the wrong element named), #25 (no headings).

**Test-rig blindness — CONFIRMED.**
- The rig has no Tailwind (`atom-testing.md`); `renderBlade` renders once; guards passed without the fix (#47; `atom-muted-token-contrast.md`; `atom-dropdown-toplayer-fixed.md`); vacuous single-item keyboard tests (`atom-command-palette.md`).
- **atom's own CLAUDE.md is stale here:** it still says "no test suite wired up" (`CLAUDE.md:7`, `:117`), but there are 64 Pest feature files and 31 Playwright specs.

### 2.2 Classes the list misses

1. **Unescaped output sinks (possible XSS).** **[V] Sink exists. [Unverified] exploitability.**
   - `select.js:209` builds option HTML as `'<span>'+option.label+'</span>'` and renders it through `x-html` (`components/select/listbox.blade.php:287`, `:98`, `:174`; `select/filter.blade.php:208`, `:227`).
   - `GetOptions::getOptionHtml()` concatenates `label` and `caption` unescaped (`src/Actions/GetOptions.php:473-503`).
   - `{!! t($label) !!}` appears in `components/input/field.blade.php:19` and in checkbox, radio, toggle, badge, rating and slider; `t()` does not escape (`src/Helpers.php:71-79`).
   - 35 `{!!` sites across components (excluding docs and icons) and 9 `x-html` sites.
   - Whether any host feeds user-entered data (client names, captions) through these is **not verified**.
   - Flux avoids the class by rendering options as escaped Blade (`{{ $slot }}`).
2. **Positional DOM coupling.**
   - #28: breadcrumbs found the Livewire root by position, and adding an `<h1>` broke it.
   - The dropdown still falls back to `:scope > *` for its trigger (`dropdown.js:7-17`).
   - Flux has the same weakness (`trigger()` = first `button,ui-button,a`, `overlay()` = last child `[popover]`, `flux-lite.js:4401-4410`) and the null-anchor crashes that follow (#2678).
3. **Attribute routing.** The bag is spread onto an Alpine wrapper while the real control gets an `only([...])` allow-list, so `id`/`aria-*`/`class` land on the wrong node. Examples: #21 table.column (bag on a wrapper inside `<th>`); memory `atom-input-label-morph-key.md` (tel/color/email ids on the wrapper).
4. **Sibling-component drift.** Props and defaults disagree across siblings: #22 (`dark` default differed between layouts and `<atom:html>`), #24 (`layouts.auth` didn't forward `vite`), and #55 vs the range picker (wire:ignore handled differently).
5. **Upgrade-channel hazards.**
   - Boost guidelines are copied into host CLAUDE.md on every `composer update`, but humblebear's copy is hand-diverged (`atom-boost-guidelines-propagation.md`).
   - Host migration steps were needed and missed: a `GetOptions` subclass must `extends` and declare exact names (`atom-getoptions-hardening.md`); humblebear's TIN lookup breaks on the v3.25.0 update (`atom-action-livewire-exposure.md`, not done).
6. **Null/zero/empty boundary values.** #33 null detail, #59 `wirekey(0)`, #28's hidden empty-trail crashes (`getLatestHref` reading `url`; `data.items is not iterable`).
7. **Blade compile hazards.** `<atom:…>` inside `//` comments in `@php`/`@props` splices component PHP into the comment (`atom-tagcompiler-hazards.md`), and `<`/`>` inside a tag attribute 500s (`atom-input-otp.md`).
8. **CSP incompatibility [I].** There are 28 inline `x-data="{…}"` objects in components, e.g. `components/table/index.blade.php`, `toast/index.blade.php` (`grep 'x-data="{'`). Livewire ships a CSP build (`vendor/livewire/livewire/dist/livewire.csp.js` exists [V]) that cannot evaluate inline expressions. Flux fixed the same thing in #2277. It only matters if a host ever enables a CSP.
9. **Listener/resource leaks [I].** No atom bug is on record, but the tooltip (`tooltip.js:24-27`) has the same shape as Flux #2653/#2707 minus the document listeners, which it does remove (`:56-57`).

---

## 3. Gap analysis: bug class → root cause in atom → Flux-informed prevention

| Bug class | Root cause in atom's architecture | Prevention (see §4) |
|---|---|---|
| Morph: per-render values | Ids and state tokens minted in Blade (`Str::random`, `uniqid`, positional counters) flow into `x-data`, `x-on`, `id` and the morph key. No rule or guard covers it except the select-specific `UidTest`. | Rule C1 (no per-render randomness; ids derived server-side or via `$id`). Static guard P4 plus a render-twice `x-data`/`id` byte-equality test for **every** component. |
| Morph: JS-owned DOM clobbered | JS generates DOM (`x-for` rows, Pikaday grids, `setAttribute` state) inside morph-owned markup. Each fix is a hand-placed `wire:ignore`, and the scope varies (whole root vs panel). | Rule C2 "every node has one owner": server-owned (morphable), Alpine-bound (`x-bind`, survives by `cloneNode`), or JS island (`wire:ignore` on a node marked `data-atom-island`). No imperative `setAttribute` on morphable nodes, except through `durable()`. Prefer server-rendered lists (Flux 1.1). |
| Lifecycle re-entry | Alpine can init an already-populated node (back/forward snapshot of live DOM; `destroyTree`/`initTree`). Each factory reinvents idempotency, or doesn't. | `atomComponent()` base (§4.2): element-scoped AbortController, `data-atom-appended` cleanup at init, `onDestroy` registry. Conformance checks "double init" and "back/forward". |
| Floating positioning | Strategy, position and resets split across JS (`floatingui.js`), Blade utilities and `atom.css`. No size/maxHeight. Position not durable against morph. | One overlay primitive (§4.3) that owns strategy + inline position + resets + size + durability + cleanup. Conformance "positioned after scroll at N offsets" with compiled Tailwind. |
| CSS only in some builds | atom's CSS pipeline has no Tailwind. Hosts hand-maintain `@source`. No test compiles a utility. Unlayered `atom.css` silently outranks utilities. | Consumer fixture with real Tailwind v4 (§5.1). Structural-vs-utility rule (§4.6). Optional `atom.tailwind.css` host import. CI compile check (floor 4.0.7 + latest). |
| Event payload shapes | Three dispatch channels, no shared reader, no schema. | `atom.events` (§4.4): `detail` is always an object; one reader normalises `null`; a PHP/JS contract test per event; optional `scope`. |
| Server surface | Livewire exposes public trait methods; the public endpoint; client-writable public props reach SQL. Each hole was fixed individually. | Server-surface rule (§4.7): an allowlisted public surface with a justification per method, a client-callable audit test, signed/locked props through a generic helper, output-escaping allowlist. |
| Unescaped output | `{!! !!}` and `x-html` used for convenience (labels with inline HTML). | Escape by default. HTML only through an explicit `html` key or an `HtmlString`. Static allowlist guard (P5). |
| Accessibility | Labels and ids routed through wrappers. Each component names its own control. No keyboard primitive. | Field base (§4.5) with prefix routing and a "reference resolves to exactly one element with the right role" assertion. Shared activedescendant and roving-tabindex primitives. |
| Positional coupling | Parts found by position (`> *`, first button). | `data-atom-part="trigger|panel|list|option|control"` hooks. No positional fallback in new code. A console warning when a part is missing (Flux warns: `flux-lite.js:4292-4300`). |
| Sibling drift, upgrade channel | No shared prop schema. Boost guideline is the only propagation channel, and an unreliable one. | Component contract checklist (§4.8) item "same prop means same default". A release-notes template with an explicit **Host action required** section. Don't rely on Boost propagation. |
| Test-rig blindness | No Tailwind; `renderBlade` renders once; guards not mutation-tested. | §5: consumer fixture, conformance suite, mutation catalog. |

---

## 4. Target foundation design

### 4.1 The architecture decision: custom elements or Alpine

**Constraints**

- **[V]** Hosts use `<atom:…>` tags and `atom-*` events.
- **[V]** The two hosts I read never call atom's Alpine factories directly (`grep x-data="select|dropdown|…` found nothing in humblebear or sudo). They do use `wire:navigate` heavily: 27 view files in humblebear, 10 in sudo.
- **[V]** atom's JS ships in `dist/` from atom's own route, so a JS change reaches every host on `composer update` with no host rebuild. A new *utility class* needs a host `npm run build` (`atom-release-flow.md` "Tailwind gotcha").
- **[V]** Livewire 4 bundles Alpine, so every host already has it.

**Option A — Alpine as today, plus discipline only (rules + tests, no shared code)**
- Effort: small.
- Risk: medium. Every factory keeps re-implementing the same guards, which is exactly how the tooltip missed the AbortController pattern the dropdown has.
- Locks in: nothing new.
- Why not: it's the status quo with more tests.

**Option B — Custom elements (`<atom-select>` etc.), Flux-style, keeping the Blade tags**

Pros:
- The lifecycle is tied to the DOM node.
- `el.value` + `input`/`change` makes `wire:model` work without Alpine.
- Works outside Alpine scopes.

Cons:
- **Flux's own tracker shows the custom-element lifecycle has its own bug class** (#2605 detached-clone boot, #2524 children not parsed yet, #2678/#1565/#2514 move-guard skipping mount, #2653 leaks).
- Rewrite cost is large. `select.js` is 330 lines plus two Blade variants; the date pickers wrap Pikaday; the command palette reuses select and modal.
- Two paradigms coexist for the whole migration.
- `x-modelable` consumers and `Alpine.$data` fixtures (every existing e2e spec) would need rewriting.
- Migration cost: L–XL. Risk: high. Locks in: a custom runtime you must maintain alongside Alpine anyway (Flux still ships Alpine glue: `fluxModal`, `fluxInputClearable`…, `flux-lite.js:7485-7620`).

**Option C (recommended) — Alpine components on a shared framework-agnostic core**

Keep Alpine as the component layer. `Alpine.data` names, `x-modelable` and `<atom:…>` props stay unchanged. Move every cross-cutting, bug-prone behaviour into small plain-JS primitives — **Flux's mixins, run on Alpine's lifecycle** — which each factory composes through one `atomComponent()` wrapper.
- Effort: medium, and incremental per component.
- Risk: low to medium.
- Locks in: a ~600–900 LOC internal core [I] that you own.
- Captures what actually made Flux robust: shared mixins, durable JS-owned attributes and position, server-rendered lists, cleanup contracts. Without the custom-element lifecycle traps and without a rewrite.

**Recommendation: C.** Re-evaluate custom elements only for a *new* component where Alpine is a poor fit (none identified). Doing C first costs nothing if that ever happens: the primitives are framework-agnostic by design, so a future custom element would compose the same ones.

### 4.2 Shared JS base: `resources/js/core/`

Plain ES modules, bundled into `atom.js`, exposed for tests as `window.atom.core` (documented internal and unstable).

| Module | Responsibility | Borrowed from |
|---|---|---|
| `component.js`: `atomComponent(name, factory)` | Wraps an Alpine data factory. **init:** creates `$root._atomScope = new AbortController()`, aborting any previous one first (idempotent re-init); removes prior `[data-atom-appended]` descendants it owns; catches and logs exceptions with the component name so a failure can't break Livewire boot (Flux #2678); exposes `listen(target, type, fn, opts)` (auto-signal), `appended(node)` (tags `data-atom-appended`), `onDestroy(fn)`. **destroy:** aborts the scope and runs `onDestroy` callbacks. | Flux `x`/`k` bases (`flux-lite.js:1718-1842`), `data-appended` (`1992-1994`) |
| `durable.js`: `durable(el).set(name, value)`, `.remove(name)`, `.release(name)` | MutationObserver that re-asserts JS-owned attributes after a morph. Only for nodes that must stay morphable **and** need imperative state (e.g. the dropdown trigger's `aria-expanded`). Prefer `x-bind` where possible. | `flux-lite.js:1537-1607` |
| `events.js` | See §4.4. | `flux-lite.js:7406-7450` |
| `keyboard.js`: `activatable(listEl, {host, wrap, typeahead})` and `focusable(containerEl, {wrap, typeahead})` | Listbox virtual focus (`data-active` + `aria-activedescendant`, a filter-aware walker that skips `[disabled]`/hidden options) and roving tabindex. Home/End/typeahead. `scrollIntoView` only under keyboard intent. | `flux-lite.js:6013-6136`, `4806-4884`, `1297-1320` |
| `overlay.js` | See §4.3. | Popoverable + Anchorable |
| `ids.js` | `ensureId(el, prefix)`: keeps an existing id or mints one **and** stores it in `el._x_bindings.id` so the morph seeds it (`livewire.esm.js:9001-9008`). Only for JS-minted ids; server-derived ids remain the default. | `flux-lite.js:1338-1346` |

**Lifecycle contract every factory must honour (enforced by conformance C-2/C-3):**

1. `init()` is idempotent. Running `Alpine.destroyTree(root); Alpine.initTree(root)` on the same node, or re-initialising over a back/forward snapshot of the live DOM, yields exactly the same DOM and listener count as a fresh page.
2. Every document/window listener goes through `listen()`.
3. Every injected node goes through `appended()`.
4. Every overlay opened is closed on `livewire:navigating` and on destroy.
5. No per-render value in the `x-data` expression. `x-data` is byte-identical across renders with identical props.

### 4.3 One floating/overlay primitive: `core/overlay.js`

`overlay({ trigger, panel, placement, gap, offset, matchWidth, scope, dismiss: {outside, escape, focusOut}, restoreFocus, onOpen, onClose })` returns `{ show, hide, toggle, isOpen, destroy }`.

- **Container.** Native `popover="manual"` for dropdown, listbox, tooltip, context menu, date and time panels. Native `<dialog>` stays separate (modal, command, lightbox) and shares only the event/scope layer.
- **Position.** floating-ui `computePosition` with `offset`, `flip`, `shift({padding:5})` and `size` (maxHeight = available height; optional matchWidth). **Strategy and inline `position` come from one constant, written inline together with `margin:0; right:auto; bottom:auto; left; top`.** This takes over `helpers/floatingui.js:32-38` and makes `atom.css:149-153,166-168` redundant (keep them until v4 as belt-and-braces).
- **Durable position.** A `style` MutationObserver re-applies the last computed position while open (Flux `hn()`). This removes the need for `wire:ignore` on panels just to keep position.
- **Lifecycle.** `show()` starts `autoUpdate`. `hide()` **always** runs its cleanup, and each `show()` cleans up any previous handle first (the #2707 bug shape). Closes on `livewire:navigating`. Light-dismiss listeners run under an AbortController aborted on close.
- **Scopes.** Opening an overlay in scope `S` closes the others in `S` except its ancestors and descendants (Flux `Ho()`). Default scopes: `menu`, `tooltip`.
- **Keyboard.** Escape closes the innermost open overlay and calls `stopPropagation()`, so a parent modal or host listener doesn't also close (#1176). Focus returns to the element that was focused before opening (Flux `2884-2899`).
- **State attributes.** `data-open` on root, trigger and panel, and `aria-expanded` on the trigger, all through `x-bind` from the component or through `durable()`. Never plain `setAttribute`.
- **Parts.** Found through `data-atom-part` hooks, never by position. Missing parts log a warning and no-op instead of throwing.
- **Scroll lock.** Keep atom's CSS `html:has(...[data-open])` approach for dialogs (atom.css:41-48). It is self-cleaning and avoids the Flux #1251/#839 family. Popovers don't lock scroll.

### 4.4 Event helper: `core/events.js` plus a PHP counterpart

- **JS.**
  - `atom.emit(name, detail = {}, { target = window })` always sends an object `detail`.
  - `atom.on(target, name, handler)` wraps the handler so it receives `detail ?? {}`.
  - The Blade listeners in `modal/index.blade.php:105-106` and the toast/alert/confirm/command components switch to calling factory methods that read through `readDetail(e)`.
- **PHP.** `Atom::dispatch(string $event, array $payload)` is the single place where `modal()`, `toast()`, `alert()`, `confirm()` and `command()` build payloads (today `src/Atom.php:140-300`). It pairs with `EventContract` constants listing the keys each event carries.
- **Scope (optional, additive).** `$this->modal('x')->show()` from inside a component adds `scope: $this->getId()`. The listener honours scope only when both sides have it (Flux `flux-lite.js:7566-7584`). Unscoped behaviour is unchanged.
- **Contract test.** For each event, the keys PHP dispatches equal the keys the JS reader consumes. Plus: a no-payload bubbling `$dispatch` and a window dispatch both work (the #33 reproduction shape, `atom-modal-close-null-detail.md`).
- **Names are frozen as host API.** `atom-modal-show/close`, `atom-toast-show`, `atom-alert-show`, `atom-confirm-show`, `atom-command-show/close`, plus the component DOM events (`open`, `close`, `opened`, `closed`, `input`, `table-filter:*`, `click-selected`, `add`).

### 4.5 Field, label and accessibility base

- **Composition.** Keep `<atom:input.field>` (`components/input/field.blade.php`) as the one wrapper. Standardise its parts: label (`data-atom-part="label"`), description/caption, error, and control (`data-atom-control` on the element that has the role).
- **Ids.** Server-derived through `fieldId()` (`src/Macros/ComponentAttributeBag.php:58-67`) for the control, label, description and error. A caller-supplied `id` wins. Never random.
- **Association.**
  - Native controls: `<label for>`.
  - Composite widgets: `aria-labelledby` = label id on the element with the role.
  - Always: `aria-describedby` = caption id + error id, and `aria-invalid` when there is an error.
- **Error region.** Always rendered: `role="alert" aria-live="polite"`, hidden when empty (Flux `error.blade.php:25-29`). A morph then only changes its text.
- **Attribute routing.**
  - Prefixes `input:`, `label:`, `field:`, `error:` go to their sub-element (Flux `attributesAfter`, `FluxManager.php:149-167`), as a new `$attributes->after('input:')` macro.
  - `class` and `style` go to the outer element unless prefixed.
  - `id`, `aria-*`, `name`, `wire:model*`, `x-model` go **to the control**.
- **Escaping.** Labels, captions and errors render with `{{ }}`. HTML only when the caller passes an `Illuminate\Support\HtmlString` [I: a small behaviour change; see migration strategy in P13].
- **Keyboard.** Listbox and combobox use `activatable`. Menus, tabs and radio groups use `focusable`.

### 4.6 Structural CSS vs utility CSS

The rule, written for CLAUDE.md:

1. **Structural CSS** lives in `resources/css/atom.css`, is served by atom, and never depends on the host's build. It covers anything whose absence breaks **function, positioning, visibility or accessibility**: `x-cloak`, `wire:loading` defaults, dialog/backdrop, listbox scroll containers, load-bearing sr-only, and any `[data-open]` visibility that function depends on.
   - It is written **only against `data-atom-*` hooks**, never against utility class names.
   - Each rule states its layer and why. It is **unlayered** when a host utility must not be able to break it (today's default), and `@layer base` when a host should be able to override it.
2. **Positioning is JS-owned inline style** from the overlay primitive, never a utility class.
3. **Utility CSS** (host-compiled) is for **visuals only**: colour, spacing, radius, type.
   - Visual defaults use `[:where(&)]:` so a host `class=` overrides without `!` (Flux #2747).
   - Every border and divide utility carries a light-mode colour (the existing palette guard).
4. **Tokens.** atom defines its own `@theme` tokens (at least `--color-muted`, `--color-muted-foreground` and an accent triplet, which memory says atom defines none of today) in an **optional** `resources/css/atom.tailwind.css`. That file also carries the `@source` lines and `@custom-variant dark`. Hosts `@import` it in place of their hand-kept `@source` blocks.
5. **Verification.** Every utility-dependent behaviour is asserted against the **compiled** consumer fixture (§5.1), never against source text alone.

### 4.7 Server-surface rule

1. **Public means endpoint.** Every public method on `AtomComponent` is browser-callable in every host.
   - The allowlist in `AtomComponentSurfaceTest.php` gets a **per-method justification**.
   - A new **client-callable audit test** invokes each allowed method through `Livewire::test(...)->call()` as a guest on a fixture component. It asserts no state change beyond the component's own and no return payload beyond what it documents.
   - Helpers that don't need to be client-callable become `protected`. This is breaking for hosts that call them from the browser, so it is decided per method.
2. **Client-writable input that reaches SQL, a method name, a path or HTML** must be `#[Locked]`, signed (generalise `Services\TableSort::sign()` into `Services\Signed`), or allowlisted in an `updating*` gate that checks **every shape** (scalar, array, dotted path). That last point is the v3.29.4 lesson recorded in memory.
3. **Web endpoints.** `WebAction` + `authorize()` (exists). Request data never names a class, method or file without an allowlist.
4. **Output.** `{{ }}` by default. `{!! !!}` and `x-html` only at allowlisted sites carrying a `{{-- trusted: reason --}}` marker, enforced by a static test.

### 4.8 Component contract checklist

Every component, and every PR that touches one, must satisfy these.

**Markup and props**
1. [ ] Parts are marked with `data-atom-part` / `data-atom-*` hooks. No positional lookups.
2. [ ] Every prop has an explicit default, and the same prop name means the same default across siblings.
3. [ ] Attribute routing is documented: which node gets `class`, `id`, `aria-*`, `wire:model`, and prefixed attributes.
4. [ ] No per-render randomness anywhere. `x-data` and `id` are byte-identical across two renders with the same props.
5. [ ] Every DOM node has one owner (server / Alpine-bound / JS island). JS islands carry `wire:ignore` + `data-atom-island` and contain only JS-generated DOM.
6. [ ] Lists are server-rendered when the data exists at render time. A client-rendered list sits inside a JS island.
7. [ ] Output is escaped. HTML is opt-in only.

**JS**
8. [ ] The factory is built with `atomComponent()`. Listeners go through `listen()`, injected nodes through `appended()`, overlays through `overlay()`.
9. [ ] `destroy()` leaves no document/window listener and no open top-layer element.
10. [ ] State attributes on morphable nodes are set with `x-bind` or `durable()`, never with a bare `setAttribute()`.
11. [ ] Events read payloads through `readDetail()` and emit through `atom.emit()`.

**Accessibility**
12. [ ] The accessible name resolves to exactly one element with the expected role. Every `aria-controls`/`labelledby`/`describedby` reference resolves to exactly one element.
13. [ ] The keyboard map is documented and tested on a multi-item list. Escape closes the innermost overlay and does not propagate. Focus returns to the trigger.

**CSS**
14. [ ] Structural rules are in `atom.css` against hooks. Visual defaults use `[:where(&)]`. No bare `border-*`/`divide-*` without a light colour.
15. [ ] It works in `html.dark`, including contrast of text on its own surface.

**Tests and release**
16. [ ] It is registered in the conformance registry and passes every applicable check. Any new guard has a mutation-catalog entry that fails with the defect restored.
17. [ ] Docs demo, Boost guideline and README are updated. Release notes list any **host action required**.

---

## 5. Test infrastructure

### 5.1 A real consumer fixture: compiled Tailwind v4, served by Livewire

**What exists today [V]**
- `testbench serve` with `tests/Fixtures/E2EServiceProvider.php` registers Livewire fixtures and `/atom/e2e/*` routes (`:16-41`).
- Pages load atom's served `dist/` CSS/JS but **no utilities** (`atom-testing.md`).

**Add**
- `tests/consumer/` holds a minimal host build:
  - its own `package.json` (tailwindcss + `@tailwindcss/cli`, pinned to the **host floor 4.0.7** with a second job on latest);
  - `app.css` = `@import "tailwindcss"; @import "../../resources/css/atom.tailwind.css";` (or, until P10 lands, the `@source` block copied from humblebear `resources/css/app.css:4-10`);
  - `@custom-variant dark (&:where(.dark, .dark *));`.
- `npm run build:consumer` compiles to `tests/consumer/build/app.css`, git-ignored.
  - **Decision needed:** these are new dev dependencies, even if isolated in their own `package.json`, and CLAUDE.md says "Don't change the package's dependencies without approval."
- A fixture route serves that file, and a fixture layout passes it through `<atom:html :styles="[...]">`. That prop exists (`components/html.blade.php:15`).
- **One generic Livewire harness** (`tests/Fixtures/HarnessFixture.php`) that renders `resources/views/e2e/harness/{component}.blade.php` with:
  - a `bump()` re-render counter;
  - a `.live`-bound value;
  - a `show` toggle that removes and re-adds the component;
  - a server-side `invalid` toggle;
  - an open-state-preserving re-render triggered from **inside** the component (the modal lesson in `atom-select-morph.md`).
- **Navigate pair.** `/atom/e2e/nav/a` ↔ `/atom/e2e/nav/b` with `wire:navigate` links, for navigate-in, back and forward.

### 5.2 The conformance suite

`tests/e2e/conformance.spec.js` loops over `tests/e2e/conformance/registry.js`. Each entry names the harness view, how to open it, the control's role and name, the keyboard map, and which checks apply. Checks carrying an issue reference start as `test.fail()`, and flip when a migration phase lands.

| # | Check | How |
|---|---|---|
| C-1 | **Survives re-render** | Grab JS handles to the control and the panel. Trigger `bump()` (while closed, and while open from inside). Assert the same node identity (`a === b`), the same open state, value, focus and caret, and no console errors (`page.on('console')`, the `option is not defined` lesson). |
| C-2 | **Survives double init** | `Alpine.destroyTree(root); Alpine.initTree(root)`. Assert the count of `[data-atom-appended]` and of injected nodes (e.g. `.pika-lendar`) is unchanged, and that one click toggles once. |
| C-3 | **Survives navigate** | A→B→back→forward. Assert no duplicate injected DOM, the component still works, no stranded top-layer element (`:popover-open` count 0 after navigating away), no console errors. |
| C-4 | **Teardown** | Remove through the `show` toggle. In Chromium, a CDP `DOMDebugger.getEventListeners` count on `document`/`window` is back to baseline, and an open overlay is closed (Flux #2653 method). |
| C-5 | **Positioned after scroll** | With the compiled fixture CSS, scroll to **several** offsets (0, 400, 1200, one past the fold; the "one offset passes by coincidence" lesson), open, and assert the panel rect is in the viewport and within `gap+2px` of the trigger's edge. Mouse coordinates only after `scrollIntoViewIfNeeded()`. |
| C-6 | **Keyboard** | On a list of at least 3 items: open (Enter/Space/ArrowDown), move (Down, Down, Up), Home/End, select (Enter). Escape closes, **a parent `keydown` listener does not fire**, and focus returns to the trigger. |
| C-7 | **Accessible name and references** | `getByRole(role, {name})` resolves to exactly 1. Every idref resolves to exactly 1 element of the expected role (`preg_match_all(...) === 1` style, never `>= 1`). |
| C-8 | **Dark mode** | Under `html.dark`, text/surface contrast ≥ 4.5 (3 for large text), computed in-page from `getComputedStyle` with a ~20-line WCAG function, so no axe dependency. Measured against the component's **own** background (the `atom-muted-token-contrast.md` lesson). |
| C-9 | **Server-driven props** | Toggle `invalid`/`disabled` on the server. Assert the control reflects it (catches the whole-root `wire:ignore` freeze). |

Pest keeps static and render-level guards (P4/P5): the render-twice byte-equality, the no-random guard, the escaping allowlist, the surface audit and the event contract.

### 5.3 Mutation-testing every guard

- **Catalog.** `tests/mutations/catalog.json`, where each entry is `{ id, file, find, replace, kind: "php|blade|js|css", spec, expect: "fail" }`.
  - `find` is the regex of the fix. `replace` restores the historical defect.
- **Runner.** `scripts/mutate.mjs`, per entry:
  1. copy the file to `$SCRATCH/<id>.bak`, **never** `git checkout` (the `atom-testing.md` lesson);
  2. apply the regex and **throw if it matched nothing** (a silent no-op mutation is the trap);
  3. for JS/CSS, either run `npm run build` or use `page.route()` to serve the dist bundle with the fix regexed out (the `atom-modal-close-null-detail.md` technique);
  4. run the named spec and require **failure**;
  5. restore from the backup and rebuild;
  6. finally assert `git status --porcelain` is unchanged.
- **Seed entries**, one per historic defect:
  - the `[x-cloak]` rule removed;
  - the `margin:0/right:auto/bottom:auto` reset removed;
  - `position:fixed` → `absolute` on the top layer;
  - `e?.detail?.name` → `e.detail.name`;
  - the derived id → `str()->random(8)`;
  - the option-list `wire:ignore` removed;
  - `destroyCalendar()` call removed from `setCalendar()`;
  - `TableSort::expression` verification bypassed;
  - `action()` made public;
  - `wirekey` array_filter restored;
  - the date-picker panel `wire:ignore` removed;
  - `[wire:loading]` rule removed.
- **When to run.** Before every release, and in the PR for any new guard. It is too slow per commit [I].

---

## 6. Phased roadmap

**Conventions**
- **One phase = one PR** on a worktree branch, squash-merged; tag the squash SHA (`atom-release-flow.md`).
- **"Done" always includes:** `composer test` green, `npm run test:e2e` green, the touched components' conformance entries passing (no `test.fail` left for them), every new guard mutation-tested, `npm run build` + `dist/` committed if `resources/js|css` changed, and the docs demo, Boost guideline and README updated.
- **Semver** is from the hosts' point of view:
  - they pin `^3.x` (humblebear `^3.27.5`, sudo `^3.24` [V]), so every minor and patch reaches them on `composer update`;
  - minors and patches **must** be backward compatible;
  - only P15 is a major.
- **Sizes** (rough): S ≈ ≤1 day, M ≈ 1–3 days, L ≈ 3–5 days [I].

### Phase 0: security triage of HTML sinks (a decision gate, before anything else)

- **Goal:** decide whether §2.2 #1 is a live vulnerability in any host.
- **Scope:**
  - hand this to the `debugger` agent / a security review: `resources/js/alpinejs/select.js:197-210`, `src/Actions/GetOptions.php:473-503`, `components/select/listbox.blade.php:98,174,287`, `components/select/filter.blade.php:208,227`, the `{!! t($label) !!}` sites;
  - check whether hosts' `GetOptions` subclasses or `:label` props carry user-entered data.
- **Done:** a written yes/no per host. If yes, a separate **patch** hotfix escaping `label`/`caption` in `getOptionHtml` (keeping the explicit `html` key as the trusted opt-in).
- **Semver:** patch if a fix ships.
- **Size:** S. **Depends on:** nothing.

### Phase 1: consumer fixture and harness (test-only)

- **Goal:** a rig where utilities compile and Livewire, morph and navigate are real.
- **Scope:** `tests/consumer/*`, `tests/Fixtures/HarnessFixture.php`, `resources/views/e2e/harness/*`, E2EServiceProvider routes, `tests/e2e/rig.spec.js` (a sanity test: `.flex` computes `display:flex`, the navigate pair round-trips).
- **Done:** the rig spec is green; one existing geometry test (e.g. `dropdown.spec.js`'s injected `@layer utilities` rule) is rewritten against the compiled CSS and still fails with the v3.25.1 defect restored.
- **Semver:** no release. **Back-compat:** n/a.
- **Size:** M. **Depends on:** the dependency approval (open question 1).

### Phase 2: conformance suite v1 plus the baseline red list (test-only)

- **Goal:** measure every interactive component against C-1…C-9.
- **Scope:** `tests/e2e/conformance.spec.js` and `registry.js`, one harness view per interactive component (select listbox/native/filter, date-picker date/time/range, time-picker, dropdown, context-menu, tooltip, modal, command, lightbox, accordion, tabs, navlist.group, otp, slider, rating, tel/email inputs, tiptap).
- **Done:** every check runs; failures are recorded as `test.fail()` with a short reason. That baseline is the real, measured list of debt.
- **Semver:** no release. **Size:** M–L. **Depends on:** P1.

### Phase 3: mutation catalog and runner (test-only)

- **Goal:** prove the guards bite.
- **Scope:** `scripts/mutate.mjs`, `tests/mutations/catalog.json` with the 12 seed entries (§5.3), `npm run test:mutations`.
- **Done:** all seed entries fail with the defect restored and pass without it; `git status` is clean afterwards.
- **Semver:** no release. **Size:** M. **Depends on:** P1 (P2 for the conformance-based entries).

### Phase 4: static morph guards, and fix what they find

- **Goal:** close the per-render-value class across all components, not just select.
- **Scope:**
  - Pest: no `Str::random|uniqid|random_int|ulid()` in `components/**` (allowlist with reasons);
  - no bare `wirekey()` in components;
  - render every component twice (with extra `fieldId`/`$id` mints in between) and assert byte-identical `x-data` and `id` attributes (generalise `UidTest`'s select group);
  - fix `components/accordion/item.blade.php:7` (`x-id`/`$id` or a derived id), `rating/index.blade.php:23` and `slider/index.blade.php:22` (fall back to `fieldId()`).
- **Done:** guards green; mutation entries added (restore `Str::random`); conformance C-1 for accordion, rating and slider flips.
- **Semver:** **patch** (v3.29.6). Host-transparent, no host rebuild.
- **Back-compat:** a caller-supplied `id` still wins.
- **Size:** S–M. **Depends on:** P2 for C-1 (the Pest part can land alone).

### Phase 5: server-surface and escaping guards

- **Goal:** turn the §4.7 rule into tests.
- **Scope:**
  - `AtomComponentSurfaceTest` gains per-method justifications and a client-callable audit on a fixture;
  - generalise `Services\TableSort` signing into `Services\Signed` (TableSort keeps its API);
  - a static escaping allowlist test for `{!!` and `x-html` with `trusted:` markers;
  - escape the non-allowlisted sites decided in P0.
- **Done:** tests green; mutations for "make `action()` public" and "unsigned `raw:`" still fail; the audit finds no unexpected side effects, or opens issues for any it does.
- **Semver:** **patch** if only tests plus internal refactors. **Minor** if a label escaping change ships, with notes: labels that relied on inline HTML must pass `new HtmlString(...)`. See open question 4.
- **Size:** M. **Depends on:** P0.

### Phase 6: core JS primitives

- **Goal:** `resources/js/core/{component,durable,events,keyboard,ids}.js` plus `window.atom.core`.
- **Scope:** new modules only, plus **one** proving migration: `tooltip.js` onto `atomComponent()` (its `$root` listeners unsignalled, `:24-27`). Unit tests run in the browser against the served bundle (Playwright `page.setContent` + `window.atom.core`), covering: idempotent init, `appended()` cleanup, `durable()` re-assert after a simulated morph (`el.removeAttribute` → restored), `readDetail(null)`.
- **Done:** unit tests green; tooltip passes C-1…C-4 and C-7; mutations for "no abort on re-init" and "durable observer disconnected" fail.
- **Semver:** **minor** (v3.30.0; new internal JS surface). Needs a dist rebuild, no host action.
- **Back-compat:** `Alpine.data('tooltip')` name and events unchanged.
- **Size:** M. **Depends on:** P2.

### Phase 7: the overlay primitive, with dropdown and context menu migrated

- **Goal:** one positioning and dismissal path.
- **Scope:** `core/overlay.js`; `helpers/floatingui.js` becomes a thin shim over it (hosts calling `atom.floatingui` keep working); migrate `dropdown.js`, `context-menu.js` and the tooltip to `overlay()`; `data-atom-part` hooks in `components/dropdown.blade.php`, `context-menu/*`, `menu/*`, `tooltip/*`; state attributes through `durable()`/`x-bind` (closes the latent `data-open` strip, `dropdown.js:25,59-60,84-85`).
- **Done:** C-1…C-7 green for dropdown, context menu and tooltip, including C-5 at four scroll offsets with compiled CSS; mutations for "strategy/position mismatch", "cleanup skipped on reopen" (#2707 shape) and "Escape propagates".
- **Semver:** **minor** (v3.31.0). **Behaviour changes to note:** Escape no longer bubbles past an overlay it closed; focus returns to the trigger on close.
- **Back-compat:** keep the `atom.css:149-168` position rules until P15; keep `open`/`close` DOM events; positional part lookup falls back **with a console warning** for one minor.
- **Size:** L. **Depends on:** P6.

### Phase 8: the event contract

- **Goal:** one payload shape, both directions.
- **Scope:** `core/events.js` (`emit`, `on`, `readDetail`); `Atom::dispatch()` + `EventContract`; `modal.js`, `command.js` and the toast/alert/confirm listeners read through `readDetail()`; optional `scope` from `$this->modal()`/`command()`; contract tests (PHP keys == JS keys; bubbling no-payload close; window close).
- **Done:** contract tests green; the null-detail mutation fails; humblebear's no-payload bubbling `$dispatch('atom-modal-close')` pattern is covered by a fixture.
- **Semver:** **minor** (v3.32.0). **Back-compat:** names unchanged; unscoped dispatch behaves exactly as today.
- **Size:** M. **Depends on:** P6.

### Phase 9: field and accessibility base

- **Goal:** one labelling and routing path for every form control.
- **Scope:** `components/input/field.blade.php`, `label.blade.php`, `error.blade.php` (always-present live region), a new `after(prefix)` attribute macro, `aria-describedby` for caption and error, `aria-invalid`; migrate input/general, textarea, native select, checkbox, radio, toggle, slider, rating, tel, email and color to the routing rules; C-7 for all of them.
- **Done:** C-7 green for every control; the "id lands on the wrapper" mutation fails; no accessible-name regressions (the #36-#42 fixtures still pass).
- **Semver:** **minor** (v3.33.0). **Host action:** `npm run build` if new utility classes (release note).
- **Back-compat:** unprefixed attributes keep today's routing.
- **Size:** L. Split into 9a (inputs, textarea) and 9b (choice controls, slider, rating) if needed. **Depends on:** P4.

### Phase 10: CSS distribution and tokens

- **Goal:** hosts stop hand-maintaining `@source`; atom defines its tokens.
- **Scope:** `resources/css/atom.tailwind.css` (`@source` for components, resources and `src/Actions`; `@theme` muted and accent tokens with `.dark` overrides; `@custom-variant dark`); a CI job compiling it with Tailwind 4.0.7 and latest (fails on warnings, the Flux #2701 lesson); `[:where(&)]` pass over visual defaults; CLAUDE.md gains the §4.6 rule; the stale memory rule about bare variants is corrected.
- **Done:** the consumer fixture switches to the import; C-5/C-8 still green; the compile job green on both versions.
- **Semver:** **minor** (v3.34.0). **Host action (optional):** replace the `@source` block with one `@import`. The old way keeps working.
- **Size:** M. **Depends on:** P1.

### Phase 11: migrate select (the worst offender)

- **Goal:** select listbox/filter/native on the core; no `x-for` in morphable markup.
- **Scope:** `resources/js/alpinejs/select.js`, `components/select/{listbox,filter,native,option,group}.blade.php`.
  - **Static (array) options are server-rendered** as option elements with `data-value` and escaped labels. JS annotates them through `activatable()`, and a `childList` observer re-syncs after a morph (Flux 1.1).
  - **Remote (string) options** stay client-rendered inside a declared JS island (`wire:ignore` + `data-atom-island`, as today at `listbox.blade.php:273`).
  - Keep `x-id`/`$id`.
- **Done:** C-1…C-9 green for all variants (including a pre-picked value + modal + inside-trigger re-render, the `atom-select-morph.md` shape); the historic mutations (uniqid in `x-data`, option-list `wire:ignore` removed) still fail; the sibling-sync fixture (#59) green.
- **Semver:** **minor** (v3.35.0). **Back-compat:** `x-modelable="selectValue"`, `options`/`filters`/`multiple`/`searchable` props, `open`/`close`/`add`/`click-selected`/`table-filter:*` events, the option `html` key (trusted opt-in) all kept.
- **Size:** L. **Depends on:** P6, P7, P8, P9.

### Phase 12: migrate the date pickers (date, time, range, time-picker)

- **Goal:** the Pikaday grid is an `appended()` JS island; panels use `overlay()`.
- **Scope:** `resources/js/alpinejs/{date-picker,date-range,time-picker}.js`, `components/date-picker/*`, `components/time-picker/*`.
  - Replace the range picker's whole-root `wire:ignore` (`range.blade.php:22`) with a panel-only island, so `invalid`/`disabled` stay server-reactive (C-9).
  - Durable panel position replaces the need for `wire:ignore` just to keep position.
- **Done:** C-1…C-9 green, **C-3 back/forward in particular** (the likely #141 trigger); the #52 and #55 mutations still fail.
- **Semver:** **minor**. **Back-compat:** `x-modelable` names and presets unchanged.
- **Size:** L. Split range from date/time if needed. **Depends on:** P6, P7.

### Phase 13: migrate modal, command and lightbox

- **Goal:** one dialog path on the core plus the event contract.
- **Scope:** `resources/js/alpinejs/{modal,command,lightbox}.js` and their Blade. Keep `wire:ignore.self` on the `<dialog>` (the Livewire `close()`-on-morph reason, `livewire.esm.js:8727-8731`). Outside-click compares mousedown and click coordinates (Flux #712 fix). Focus placeholder and focus restore. Keep the CSS scroll lock.
- **Done:** C-1…C-8 green; C-6 includes "Escape in a dropdown inside a modal closes only the dropdown".
- **Semver:** **minor**. **Back-compat:** `atom()->modal()->show()/slide()/close()`, the `dismissible`/`escapable`/`closeable` props, and the events are unchanged.
- **Size:** M. **Depends on:** P7, P8.

### Phase 14: remaining interactive components, then new components

- **14a — migrations,** each a small PR: accordion, tabs (`focusable`), navlist.group, otp, slider, rating, tel/email inputs, tiptap toolbars (inline `x-data` objects move into registered factories, which also removes the CSP blocker, Flux #2277), table filters and pagination inline `x-data`. Each is S–M, a **patch or minor**, and depends on P6 (plus P7 where an overlay is used).
- **14b — new components** must satisfy §4.8 before merge. Candidates, for you to prioritise:
  - a generic `<atom:popover>` (trivial once `overlay()` exists);
  - an autocomplete/combobox on `activatable()`.
  - Each is a **minor**.

### Phase 15: v4.0.0 cleanup (optional, and only if worth it)

- **Goal:** remove what P7–P14 deprecated: positional part fallbacks, the `atom.css` position rules made redundant by inline positioning, and anything P5 decided to make `protected`.
- **Semver:** **major**, with an upgrade guide and a host checklist for humblebear, smgdms, toocrm and sudo. **Do not rely on Boost-guideline propagation** (`atom-boost-guidelines-propagation.md`).
- **Size:** M. **Depends on:** everything above.

### Dependency graph

```
P0 ─► P5
P1 ─► P2 ─► P3
      P2 ─► P4 ─► P9 ─┐
      P2 ─► P6 ─► P7 ─┼─► P11 (select)
            P6 ─► P8 ─┘   P12 (date pickers)  ◄─ P6,P7
P1 ─► P10                P13 (modal family)   ◄─ P7,P8
                         P14a/b               ◄─ P6(+P7)
                         P15                  ◄─ all
```

---

## 7. Risks and open questions for you

### Decisions

1. **New dev dependencies for the rig.** Tailwind v4 CLI in an isolated `tests/consumer/package.json`, and (optionally) nothing else. CLAUDE.md requires your approval for dependency changes. Without it, P1 falls back to `@tailwindcss/browser` from a CDN, which the memory already rates screenshot-only, not the host's build.
2. **Phase 0 first?** The HTML sinks are verified; exploitability is not. Do you want the security triage run before the plan starts?
3. **Custom elements.** The recommendation is Alpine plus a shared core (Option C). Confirm you don't want a custom-element spike. Flux's tracker is the evidence that they bring their own lifecycle bugs.
4. **Label escaping (P5).** `{!! t($label) !!}` suggests some hosts pass HTML in labels. Escaping by default means those hosts must wrap with `HtmlString`, which breaks them. Options: ship it as a minor with a release note; ship it behind a config flag; or leave it until v4.
5. **Public trait methods.** Should any of `tableSelection`, `selectAllTableMatching`, `verifyRecaptcha` etc. stop being browser-callable (`protected`)? Each is breaking for any host calling it from the browser. The audit in P5 reports; you decide.
6. **`window.atom.core` exposure** for tests. Is it acceptable as a documented-internal namespace, or should tests build a separate bundle?
7. **Optional `atom.tailwind.css` import (P10).** Do you want hosts migrated to it, or offered it only?

### Risks

- **Migration regressions in hosts** that rely on undocumented behaviour: positional children, `data-open` styling, Escape bubbling to their own listeners. Mitigations: a deprecation window, console warnings, and a pre-release run on humblebear (the documented live-verification host in `atom-release-flow.md`).
- **The conformance suite could be flaky** (timing around Livewire round trips, and CDP listener counts are Chromium-only). Mitigations: wait on network responses, not timeouts; run C-4 in Chromium only.
- **Scope creep.** P9, P11 and P12 are the large ones. Keep splits available (9a/9b; range vs date/time).
- **Two-paradigm period.** Migrated and unmigrated factories coexist for several releases. The conformance baseline makes the remaining debt visible.

### Assumptions I could not verify

- smgdms and toocrm's Tailwind versions, `@source` lists, and use of atom events. Not on disk.
- The *installed* Tailwind version in hosts. Only the declared `^4.0.7` was read.
- The actual trigger of the v3.9.0 "bare variants don't compile" observation (see 1.4).
- Whether the accordion reset (§2.1) and the dropdown `data-open` strip under a live update happen in practice. The mechanisms are verified; the symptoms are not reproduced.
- Anything about Flux Pro Blade, and anything about Flux's private tests.

### Housekeeping this plan implies

- **atom's CLAUDE.md** says there is no test suite (`CLAUDE.md:7`, `:117`). That is stale, and it should also gain the §4.6 CSS rule and the §4.8 checklist.
- **Memory `atom-command-palette.md`** should be corrected: bare `data-*:`/`backdrop:` variants compile on Tailwind 4.0.7 [V-run].
