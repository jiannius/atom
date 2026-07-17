# `<atom:slider>` — Range slider form component

## Context

atom is at v3.10.0 with ~57 components and a clean tree. Recent work has been filling Flux-gap
components (accordion, progress, input.otp, command palette, pagination). atom ships every common
form input — input, textarea, select, checkbox, radio, toggle, input.otp — but has **no slider /
range input**. This spec adds `<atom:slider>` to close that gap.

Scope was fixed during brainstorming: **single-thumb only** (no dual-thumb range in v1 — can be
added later), built on a **native `<input type=range>`** so keyboard nav, touch, and ARIA come for
free.

## Component

`components/slider/index.blade.php` — top-level form widget, sibling of `toggle`/`checkbox`/`radio`.
Self-contained field chrome (label + caption + error) following the `toggle/index.blade.php` pattern.

### Props

| prop | default | purpose |
|---|---|---|
| `name` | `$attributes->wire('model')->value()` | model key, also drives error lookup |
| `label` | `null` | field label (through `t()`) |
| `caption` | `null` | helper text under the label (`<atom:caption>`) |
| `error` | `$errors?->first($name)` | error string (`<atom:error>`) |
| `min` | `0` | range lower bound (cast int/float) |
| `max` | `100` | range upper bound |
| `step` | `1` | increment |
| `bubble` | `false` | floating value above the thumb, shown **on interaction** (hover / focus / drag) |
| `labels` | `false` | static min…max value text at the track ends |
| `required` / `disabled` | — | forwarded to the native input |

Fill track (colored from min to the thumb in the primary color) is **always on** — it is the core
styling, not a prop.

### Markup shape

- Outer `<label>` wrapper carrying `data-atom-slider`, `x-data="slider({min,max,step,value})"`,
  and `x-modelable="value"` plus the merged attribute bag (so `wire:model` binds through
  x-modelable — the proven `input/otp.blade.php` pattern that survives Livewire hydration and
  external value changes).
- label / caption block (reuse toggle's structure).
- Track row: native `<input type=range min max step x-model="value">`, styled entirely via
  hand-written CSS (see below). The Alpine `value` ⇄ input `x-model` ⇄ `x-modelable` chain keeps
  Livewire, Alpine, and the DOM in sync.
- Optional value bubble: `<output>` positioned at `left: var(--atom-slider-percent)` with
  `translateX(-50%)`, hidden at rest, revealed on `:hover`/`:focus-within`/`[data-dragging]`.
- Optional min/max labels: a `flex justify-between` row rendering `min` and `max`.
- `<atom:error>` at the bottom.

## JS factory

`resources/js/alpinejs/slider.js`, registered in `resources/js/atom.js` (import + `Alpine.data('slider', slider)`).

```js
export default (config = {}) => ({
    min: Number(config.min ?? 0),
    max: Number(config.max ?? 100),
    step: Number(config.step ?? 1),
    value: config.value ?? (config.min ?? 0),

    init () {
        // wire:model hydrates `value` via x-modelable after init.
        this.clamp()
        this.$watch('value', () => this.clamp())
    },

    get percent () {
        const span = this.max - this.min
        if (span <= 0) return 0
        return Math.min(100, Math.max(0, ((Number(this.value) - this.min) / span) * 100))
    },

    clamp () {
        const n = Number(this.value)
        if (Number.isNaN(n)) return
        const c = Math.min(this.max, Math.max(this.min, n))
        if (c !== n) this.value = c
    },
})
```

The blade binds `style="--atom-slider-percent: {{ }}%"` via `:style="`--atom-slider-percent: ${percent}%`"`
so both the fill gradient and the bubble position track the value reactively.

**Gotcha (from memory):** Alpine `x-bind:style` as a STRING wipes the whole style attribute — use the
object form `:style="{ '--atom-slider-percent': percent + '%' }"` (accordion hit this).

## CSS

Hand-written in `resources/css/atom.css` (NOT expressible as Tailwind utilities, and atom consumers
rebuild their own Tailwind — so slider thumb/track styling must ship in atom's own dist, per the
established no-Tailwind-consumer rule). Includes:

- `input[type=range]` reset: `appearance:none`, transparent background, sized track.
- Track fill: `background: linear-gradient(to right, <primary> 0 var(--atom-slider-percent), <track> var(--atom-slider-percent) 100%)`.
- `::-webkit-slider-thumb` + `::-moz-range-thumb`: round thumb, primary border, shadow, focus ring.
- `:disabled` opacity/cursor.
- Dark-mode variants (`.dark` selector, matching how other atom.css blocks handle dark).
- Value bubble base position/visibility (hidden default; visible on hover/focus-within/dragging).

`data-dragging` is toggled by the input's `x-on:pointerdown`/`pointerup` (or `:active` CSS) so the
bubble shows while dragging on touch where `:hover` is unreliable.

## Build & docs

- `npm run build` → commit the regenerated `dist/` (mandatory when JS/CSS change).
- Docs demo partial under `resources/views/docs/` (e.g. `slider.blade.php`) showing: basic, with
  bubble, with min/max labels, custom min/max/step, disabled, wire:model live value. The docs page
  appears automatically (fallback) but a real partial documents usage.
  - Gotcha: no `<` / `>` literals inside a `<atom:...>` tag attribute (e.g. an example
    `description`) — TagCompiler 500s (input.otp spec hit this).

## Testing

Follow the atom test rig (Pest + Testbench + Playwright), same as recent components.

- **Pest** (`tests/` render tests): renders `<atom:slider>` with various props — asserts native
  `input[type=range]` present with correct `min`/`max`/`step`, label/caption/error render, bubble &
  labels appear only when their props are set, `wire:model`/`x-modelable` wiring present.
- **Playwright e2e** (`*.spec.js`): drag the thumb → value + fill % update; arrow-key nav changes
  value; `wire:model` round-trips to a Livewire property; bubble hidden at rest and shown on
  focus/hover; disabled blocks interaction.
- **Worktree gotcha (memory [[atom-testing]] L22):** must run a real `composer install` in the
  worktree — symlinking `vendor` makes `testbench serve` resolve `Jiannius\Atom` to the MAIN
  checkout and serve main's dist/PHP, hiding worktree edits. Worktree also needs its own `npm ci`.

## Ship

- README catalog entry for `<atom:slider>`.
- Squash-merge via PR (`gh pr create --draft` → merge), tag **v3.11.0**, push tag.

## Out of scope (v1)

- Dual-thumb range ([min,max] pair).
- Vertical orientation.
- Tick marks / snap-to-tick visual grid beyond native `step`.
