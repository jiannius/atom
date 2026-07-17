# `<atom:rating>` — Star rating input + display

## Context

Following `<atom:slider>`, `<atom:rating>` is the next Flux-gap form component. atom has no
star-rating control. It serves **two** uses: an interactive input (`wire:model`) and a readonly
display of a fixed value (e.g. a product's average rating). Brainstorming fixed the scope:
**half-step precision**, **readonly display**, **hover preview**, **clearable**, and a
**custom icon** prop. Branch `worktree-rating` (off `main`, independent of the open slider PR).

## Rendering approach

atom's icon set has no `star`; icons are lucide **outlines** (`fill="none"`). So the component ships
its **own solid star SVG** (`fill="currentColor"`) as the default glyph, and an `icon` prop swaps in
any atom icon via `<x-dynamic-component :component="'atom::icon.'.$icon"/>`.

Fractional fill (for half-steps AND arbitrary display values like 4.3) uses a **two-layer clip**:

- A **base** row of `count` icons in a muted color.
- A **fill** row — the *same* icons, same layout, absolutely positioned on top in the accent color,
  clipped with `clip-path: inset(0 calc(100% - var(--atom-rating-percent)) 0 0)`.

`--atom-rating-percent = (display / count) * 100%`, bound on the wrapper via Alpine `:style` (object
form — a string `x-bind:style` wipes the attr; slider/accordion gotcha). The two rows MUST share
identical layout (size + gap) or the clip misaligns, so size/gap live in `atom.css`, not Tailwind.

## Component

`components/rating/index.blade.php` — top-level form widget (sibling of `slider`/`toggle`).

### Props

| prop | default | purpose |
|---|---|---|
| `name` | `$attributes->wire('model')->value()` | model key + error lookup |
| `label` / `caption` / `error` | `null` | field chrome (like `toggle`/`slider`) |
| `count` | `5` | number of icons |
| `value` | `0` | initial value (0..count; halves allowed) |
| `half` | `false` | enable half-step **selection** (display always renders true fraction) |
| `readonly` | `false` | non-interactive display of `value` |
| `clearable` | `false` | click the current value again → reset to 0 |
| `icon` | `null` | swap the default star for an atom icon name (`heart`, `thumb-up`, …) |

### Markup shape

- Wrapper `<div data-atom-rating x-data="rating({count,half,readonly,clearable,value})"
  x-modelable="value" :style="{ '--atom-rating-percent': percent + '%' }" {{ merged bag (wire:model) }}>`.
  wire:model binds through x-modelable (slider/otp pattern).
- Optional label/caption block (reuse toggle/slider chrome).
- Interactive track `<div x-ref="track" role="slider" tabindex="0" aria-valuemin="0"
  :aria-valuemax="count" :aria-valuenow="value" aria-label
  x-on:pointermove/​pointerleave/​click/​keydown>` — or, when `readonly`, `role="img"` +
  `aria-label="{value} out of {count}"`, no tabindex/handlers.
  - `[data-atom-rating-base]` row (muted) + `[data-atom-rating-fill]` row (accent, clip-path).
- `<atom:error>` at the bottom.

## JS factory

`resources/js/alpinejs/rating.js`, registered in `atom.js`.

```js
export default (config = {}) => ({
    count: Number(config.count ?? 5),
    half: !!config.half,
    readonly: !!config.readonly,
    clearable: !!config.clearable,
    value: Number(config.value ?? 0),
    hover: null,

    get display () { return this.hover ?? this.value },
    get percent () { return Math.min(100, Math.max(0, (this.display / this.count) * 100)) },

    fromPointer (e) {
        const rect = this.$refs.track.getBoundingClientRect()
        const raw = ((e.clientX - rect.left) / rect.width) * this.count
        return this.half
            ? Math.min(this.count, Math.max(0.5, Math.round(raw * 2) / 2))
            : Math.min(this.count, Math.max(1, Math.ceil(raw)))
    },

    onMove (e) { if (!this.readonly) this.hover = this.fromPointer(e) },
    onLeave () { this.hover = null },
    onClick (e) {
        if (this.readonly) return
        const v = this.fromPointer(e)
        this.value = (this.clearable && v === this.value) ? 0 : v
    },
    onKey (e) {
        if (this.readonly) return
        const step = this.half ? 0.5 : 1
        let v = this.value
        if (['ArrowRight', 'ArrowUp'].includes(e.key)) v = Math.min(this.count, v + step)
        else if (['ArrowLeft', 'ArrowDown'].includes(e.key)) v = Math.max(0, v - step)
        else if (e.key === 'Home') v = 0
        else if (e.key === 'End') v = this.count
        else return
        e.preventDefault()
        this.value = v
    },
})
```

## CSS

Hand-written in `resources/css/atom.css` — structural (so it works in atom's no-Tailwind rig) with
sensible default colors that Tailwind classes can override:

- `[data-atom-rating] { position: relative; display: inline-flex; }`
- `[data-atom-rating-base], [data-atom-rating-fill] { display: flex; gap: .25rem; }`
- `[data-atom-rating-fill] { position: absolute; inset: 0; clip-path: inset(0 calc(100% - var(--atom-rating-percent, 0%)) 0 0); pointer-events: none; }`
- default colors: base `#d4d4d8` (zinc-300), fill `#f59e0b` (amber-500); `html.dark` base `#3f3f46`.
- `[role="slider"] { cursor: pointer; outline: none; }` + a `:focus-visible` ring; `cursor:default` when readonly.

## Docs

`resources/views/docs/demos/rating.blade.php` + `demos/rating/` partials: basic, half-steps,
readonly (display an average), clearable, custom icon (`heart`), custom count.

## Testing

- **Pest** `tests/Feature/RatingTest.php` (model on `SliderTest`/`OtpInputTest`): renders base + fill
  rows with `count` icons each, `x-data="rating({...})"` + `x-modelable="value"` + `wire:model`
  wiring, `role="slider"` (interactive) vs `role="img"` (readonly), half/clearable flags forwarded,
  custom-icon prop swaps the glyph, error/caption chrome.
- **Playwright** `tests/e2e/rating.spec.js` (drive `/atom/docs/rating`, plain Alpine): hover previews
  fill (percent var tracks pointer); click commits value; ArrowRight/Left change value via keyboard;
  clearable resets on re-click; readonly ignores interaction. Assert the reactive path via
  `--atom-rating-percent` + `aria-valuenow`, not pixel-perfect drag.
- **Worktree gotcha** ([[atom-testing]]): real `composer install` + `npm ci` in the worktree (done);
  symlinked vendor makes `testbench serve` resolve to the MAIN checkout.

## Ship

`npm run build` → commit `dist/` → README catalog entry → `gh pr create --draft`. Tag **v3.12.0** at
merge (after slider's v3.11.0).

## Out of scope (v1)

Quarter/arbitrary-step precision beyond halves, RTL mirroring, per-icon tooltips/labels.
