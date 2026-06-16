# `<atom:tiptap>` — Phase 5 Implementation Plan (mentions)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]` checkboxes.

**Goal:** Ship `@`-mention suggestions for `<atom:tiptap>` and `<atom:tiptap.chat>`: a dropdown sourced from a static option array OR a live `$wire` callback (debounced), with keyboard nav, custom item rendering via slot, rendering `<span class="mention">`. Replace v2's hand-rolled `getBoundingClientRect` positioning with the existing `floatingui` helper (flip/shift, viewport-aware).

**Architecture:** A new Alpine factory `resources/js/alpinejs/mention.js` (imports `helpers/floatingui.js`) owns the suggestion lifecycle (`start`/`update`/`exit`/`keydown` exposed on its root element so the engine's `MentionConfiguration` can drive it), the fetch (static filter | `$wire.$call` debounced 300ms), keyboard nav, and floating-ui positioning anchored to a virtual element built from the suggestion's `clientRect`. A thin `components/tiptap/mention.blade.php` carries `class="tiptap-mention"` (what `resources/js/tiptap.js` queries for `mentionTemplate`) + the dropdown template. The PHP-side `AtomMention` extension (Phase 3) already renders stored mentions; the engine's `Mention.configure(MentionConfiguration(...))` wiring already exists.

**Tech Stack:** Tiptap v3 `@tiptap/extension-mention` (in the engine), `@floating-ui/dom` (via `helpers/floatingui.js`), Alpine, Livewire (`$wire.$call`), Pest.

**Reference:** v2 `components/editor/mention.blade.php` (the logic to port); `resources/js/helpers/floatingui.js` (the positioning helper, default export `(anchor, element, config) => autoUpdateCleanup`); `resources/js/tiptap.js` `MentionConfiguration` (calls `element.start/update/keydown/exit`).

## File map (Phase 5)
```
Create:
  resources/js/alpinejs/mention.js           # the factory (lifecycle + fetch + floating-ui)
  components/tiptap/mention.blade.php         # <atom:tiptap.mention> (thin: x-data="mention(...)")
  resources/views/docs/demos/tiptap/mention.blade.php  # docs demo (optional)
Modify:
  resources/js/atom.js + dist/               # register Alpine.data('mention', mention) + rebuild
  components/tiptap/index.blade.php           # re-enable the commented mention block
  components/tiptap/chat.blade.php            # re-enable the commented mention block
  tests/Feature/TiptapTest.php               # mention render coverage
  resources/views/docs/demos/tiptap.blade.php # add a Mention example (optional)
```

---

### Task 5.1: mention Alpine factory

**Files:** Create `resources/js/alpinejs/mention.js`; Modify `resources/js/atom.js`; rebuild `dist/`

- [ ] **Step 1: Write `resources/js/alpinejs/mention.js`**

```js
import floatingui from '../helpers/floatingui'

export default (config = {}) => ({
    show: false,
    props: null,
    timer: null,
    cleanup: null,
    pointer: 0,
    options: Array.isArray(config.options) ? config.options : [],
    callback: config.callback ?? null,
    filteredOptions: [],

    init () {
        // The engine's MentionConfiguration calls element.start/update/exit/keydown.
        this.$el.start = (props) => this.start(props)
        this.$el.update = (props) => this.update(props)
        this.$el.exit = (props) => this.exit(props)
        this.$el.keydown = (props) => this.keydown(props)
    },

    start (props) { this.props = props; this.pointer = 0; this.fetch() },
    update (props) { this.props = props; this.fetch() },

    exit () {
        this.show = false
        this.props = null
        this.filteredOptions = []
        if (this.cleanup) { this.cleanup(); this.cleanup = null }
    },

    keydown (props) {
        const key = props.event.key
        if (key === 'Escape') { this.exit(); return true }
        if (key === 'Enter' && this.filteredOptions.length) {
            props.event.preventDefault()
            props.event.stopPropagation()
            this.select(this.filteredOptions[this.pointer > -1 ? this.pointer : 0])
            return true
        }
        if (key === 'ArrowUp' && this.filteredOptions.length) { this.arrowUp(); return true }
        if (key === 'ArrowDown' && this.filteredOptions.length) { this.arrowDown(); return true }
        return false
    },

    arrowUp () { this.pointer = ((this.pointer + this.filteredOptions.length) - 1) % this.filteredOptions.length; this.scroll() },
    arrowDown () { this.pointer = (this.pointer + 1) % this.filteredOptions.length; this.scroll() },

    scroll () {
        const ul = this.$refs.dropdown.querySelector('ul')
        const li = Array.from(this.$refs.dropdown.querySelectorAll('li'))[this.pointer]
        if (!ul || !li) return
        if (this.pointer === 0) ul.scrollTop = 0
        else if (this.pointer === this.filteredOptions.length - 1) ul.scrollTop = ul.scrollHeight
        else {
            const top = li.getBoundingClientRect().top - ul.getBoundingClientRect().top
            const floor = ul.getBoundingClientRect().height
            if (top > floor) ul.scrollTop += li.getBoundingClientRect().height
            else if (top < 0) ul.scrollTop += top
        }
    },

    fetch () {
        this.pointer = 0

        if (this.callback) {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => {
                this.$wire.$call(this.callback, this.props.query)
                    .then(res => { this.filteredOptions = [...res]; this.position() })
            }, 300)
        }
        else {
            const query = (this.props.query ?? '').toLowerCase()
            this.filteredOptions = this.options.filter(opt => {
                const searchable = typeof opt === 'object'
                    ? (opt.searchable || `${opt.label ?? ''} ${opt.small ?? ''} ${opt.caption ?? ''}`).trim().toLowerCase()
                    : opt.toString().toLowerCase()
                return searchable.includes(query)
            })
            this.position()
        }
    },

    // floating-ui positioning (replaces v2's hand-rolled getBoundingClientRect math).
    // The suggestion's clientRect() is a function returning the caret DOMRect — use it
    // as a virtual reference element so flip/shift keep the menu on-screen.
    position () {
        if (this.cleanup) { this.cleanup(); this.cleanup = null }

        this.$nextTick(() => {
            if (!this.filteredOptions.length || !this.props?.clientRect) { this.show = false; return }
            const anchor = { getBoundingClientRect: () => this.props.clientRect() }
            this.cleanup = floatingui(anchor, this.$refs.dropdown, { placement: 'bottom-start', offset: 6 })
            this.show = true
        })
    },

    select (opt) {
        if (typeof opt === 'string') this.props.command({ id: opt })
        else this.props.command({ id: opt.id, label: opt.render || opt.label || opt.value })
        this.exit()
    },
})
```

- [ ] **Step 2: Register in `resources/js/atom.js`** — import + register alongside the other factories:
```js
import mention from './alpinejs/mention'
// ... in the Alpine registration block:
Alpine.data('mention', mention)
```
(Match the exact import/registration style used for `tiptap`/`dropdown` in this file.)

- [ ] **Step 3: Build** — `npm run build` → clean (the `floatingui` helper is already bundled; no new dep).

- [ ] **Step 4: Commit** — `git add resources/js/alpinejs/mention.js resources/js/atom.js dist/ && git commit -m "feat(tiptap): mention factory (suggestion lifecycle + floating-ui positioning)"`

---

### Task 5.2: `<atom:tiptap.mention>` blade + re-enable the blocks

**Files:** Create `components/tiptap/mention.blade.php`; Modify `components/tiptap/index.blade.php`, `components/tiptap/chat.blade.php`

- [ ] **Step 1: Write `components/tiptap/mention.blade.php`** (thin — logic lives in the factory; ports v2's dropdown markup; `class="tiptap-mention"` is REQUIRED so the engine finds it; dropdown is `fixed` for floating-ui's fixed strategy):

```blade
@props([
    'options' => null,
])

@php
if (is_string($options)) {
    $callback = $options;
    $options = [];
} else {
    $callback = null;
    $options = is_array($options) ? $options : [];
}
@endphp

<div x-data="mention({ options: @js($options), callback: @js($callback) })" class="tiptap-mention">
    <div
    x-ref="dropdown"
    x-on:keydown.up.prevent="arrowUp()"
    x-on:keydown.down.prevent="arrowDown()"
    x-bind:class="(!show || !filteredOptions.length) && 'invisible'"
    class="fixed max-w-lg min-w-72 rounded-lg border shadow-lg z-10 bg-white dark:bg-zinc-800/50">
        <ul class="flex flex-col max-h-[300px] overflow-auto p-2">
            <template x-for="(opt, i) in filteredOptions" hidden>
                <li
                x-on:mouseover="pointer = i"
                x-on:click="select(opt)"
                x-bind:class="pointer === i && '*:bg-zinc-100 *:border-zinc-200 dark:*:bg-zinc-700 dark:*:border-transparent'"
                class="cursor-pointer">
                    <div class="rounded-md p-3 border border-transparent">
                        @if ($slot->isNotEmpty())
                            {{ $slot }}
                        @else
                            <template x-if="typeof opt === 'string'" hidden>
                                <div x-text="opt"></div>
                            </template>

                            <template x-if="typeof opt === 'object'" hidden>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <div x-show="opt.type" x-text="opt.type" class="uppercase bg-zinc-100 border rounded font-medium text-zinc-500" style="font-size: 0.65rem; padding: 1px 3px;"></div>
                                        <div x-text="opt.label" class="font-medium text-sm"></div>
                                    </div>
                                    <div x-show="opt.caption" x-text="opt.caption" class="text-sm text-zinc-500"></div>
                                </div>
                            </template>
                        @endif
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
```

- [ ] **Step 2: Re-enable the mention block in `components/tiptap/index.blade.php`** — replace the commented block:
```blade
            {{-- Phase 5: mention. Re-enable when components/tiptap/mention.blade.php exists.
            @if ($mention)
                <atom:tiptap.mention :options="is_string($mention) ? $mention : data_get($mention, 'options', [])" />
            @endif
            --}}
```
with the live block (simplify the prop pass-through — `mention` is a callback string OR an options array, passed straight through):
```blade
            @if ($mention)
                <atom:tiptap.mention :options="$mention" />
            @endif
```

- [ ] **Step 3: Re-enable the mention block in `components/tiptap/chat.blade.php`** — same replacement (uncomment → `@if ($mention) <atom:tiptap.mention :options="$mention" /> @endif`).

- [ ] **Step 4: Commit** — `git add components/tiptap/mention.blade.php components/tiptap/index.blade.php components/tiptap/chat.blade.php && git commit -m "feat(tiptap): <atom:tiptap.mention> dropdown + enable in editor & chat"`

---

### Task 5.3: tests + docs demo

**Files:** Modify `tests/Feature/TiptapTest.php`; create `resources/views/docs/demos/tiptap/mention.blade.php` + add to the docs page

- [ ] **Step 1: Add mention render tests** to `tests/Feature/TiptapTest.php`:

```php
describe('tiptap.mention', function () {
    it('renders the mention dropdown wired to a wire callback (string)', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" mention="searchUsers" />');

        expect($html)
            ->toContain('class="tiptap-mention"')
            ->toContain('x-data="mention(')
            ->toContain('searchUsers')          // callback name passed to the factory
            ->toContain('x-ref="dropdown"');
    });

    it('renders the mention dropdown from a static option array', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" :mention="[\'Alice\', \'Bob\']" />');

        expect($html)
            ->toContain('class="tiptap-mention"')
            ->toContain('Alice')
            ->toContain('Bob');
    });

    it('does not render a mention dropdown when no mention prop is given', function () {
        $html = renderBlade('<atom:tiptap wire:model="body" />');

        expect($html)->not->toContain('tiptap-mention');
    });

    it('enables mentions in the chat composer too', function () {
        $html = renderBlade('<atom:tiptap.chat wire:model="message" mention="searchUsers" />');

        expect($html)->toContain('class="tiptap-mention"');
    });
});
```
Run `vendor/bin/pest tests/Feature/TiptapTest.php` → expect 14 pass (10 + 4). If the static-array assertion fails because `@js([...])` encodes Alice/Bob as a JS array inside `x-data` (it does — they appear in the `mention({ options: [...] })` string), confirm the substrings are present; adjust to assert the exact encoded form if needed.

- [ ] **Step 2: Docs demo** — `resources/views/docs/demos/tiptap/mention.blade.php`:
```blade
<atom:tiptap label="Note" :mention="['Alice', 'Bob', 'Carol']" placeholder="Type @ to mention..."/>
```
Add an `<atom:docs.example title="Mentions" .../>` block to `resources/views/docs/demos/tiptap.blade.php`.

- [ ] **Step 3: Full suite** — `vendor/bin/pest` → green.

- [ ] **Step 4: Commit** — `git add tests/Feature/TiptapTest.php resources/views/docs/demos/tiptap* && git commit -m "test(tiptap): mention render coverage + docs demo"`

---

## Self-review notes
- The factory is the atom-consistent design (like `dropdown`/`tooltip`/`select`): inline-blade-Alpine → a registered factory using the shared `floatingui` helper. This both honors the spec's "replace hand-rolled positioning" and centralizes the logic.
- `class="tiptap-mention"` MUST match `resources/js/tiptap.js`'s `mentionTemplate: this.$root.querySelector('.tiptap-mention')`. The dropdown is `fixed` (the helper uses floating-ui `strategy:'fixed'`, returning viewport coords).
- `mention` prop = string (callback name) | array (static options). Passed straight through as `:options`. The blade's `@php` splits string→callback, array→options. Custom item rendering via the `<atom:tiptap.mention>` slot is preserved for direct use.
- The engine's `MentionConfiguration` + `Mention.configure` are ALREADY in `resources/js/tiptap.js` (Phase 1) — only added when `mentionTemplate` is found, i.e. when the mention block renders. No engine change needed.
- The PHP `AtomMention` (Phase 3) renders stored mentions server-side. No change needed.
- **NO e2e** — the suggestion popup + `$wire.$call` + floating-ui positioning need a real browser + Livewire server (untestable in atom's rig). Pest covers the rendered wiring; **verify the live `@`-trigger, keyboard nav, callback fetch, and positioning (incl. near viewport edges) on humblebear.**

## Done when
- `vendor/bin/pest` green (TiptapTest 14, no regressions).
- `<atom:tiptap mention="callback">` and `:mention="[...]"` both render the `.tiptap-mention` dropdown; chat too.
- `npm run build` clean; `dist/` committed.

**Next:** Phase 6 — `<atom:editor>` shim, delete old editor + v2 deps, Boost + docs, `type=module` migration note, **tag v3.6.0**.
