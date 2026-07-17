# `<atom:kbd>` — Keyboard-key display

## Context

Small Flux-gap primitive. atom shows keyboard shortcuts inline in a couple of places (the command
palette item's `<kbd>`, tooltip's `kbd` prop) but has no reusable key-cap component. `<atom:kbd>`
renders one or more styled key caps and maps shortcut tokens to symbols — pairing with the command
palette (`⌘K`) and `<atom:tooltip kbd>`. **Pure blade — no JS, no atom.css, no dist rebuild.** Branch
`worktree-kbd` (off `main`).

## Approach (settled)

- `keys` prop (space- or `+`-separated string, or array) renders one `<kbd>` cap per token, mapping
  common tokens to symbols (`cmd/command/meta→⌘`, `shift→⇧`, `alt/option→⌥`, `ctrl/control→⌃`,
  `enter/return→⏎`, `esc/escape→⎋`, `tab→⇥`, `backspace→⌫`, `delete/del→⌦`, `space→␣`, arrows, `plus→+`).
  Unknown single chars are upper-cased (`k→K`); other unknown tokens are `ucfirst`.
- No `keys` → the slot is rendered as a single cap (`<atom:kbd>Esc</atom:kbd>`).
- Key-cap styling matches the existing inline `<kbd>` in `components/command/item.blade.php`
  (rounded border, `text-xs`, muted) via Tailwind classes — so no hand-written CSS is needed; consumers
  pick it up through their Tailwind build, exactly like the command palette's shortcut cap.

## Files

1. **`components/kbd/index.blade.php`** — new. Props `keys` (default null). Builds a `$map` token→symbol
   table + a `$render` closure; emits `<kbd data-atom-kbd-key>` caps. Multi-cap wrapped in
   `<span data-atom-kbd class="inline-flex items-center gap-1">`; single (slot) cap is a bare `<kbd
   data-atom-kbd data-atom-kbd-key>`. Forward `$attributes`.
2. **Docs** — `resources/views/docs/demos/kbd.blade.php` + `demos/kbd/` partials: single (slot),
   shortcut (`keys="cmd k"`), combo (`keys="cmd shift p"`), inline-in-text. (No literal `<`/`>` inside
   `<atom:...>` attributes.)
3. **`README.md`** — add `<atom:kbd>` near `<atom:tooltip>`/display primitives.

No `resources/js` / `resources/css` / `dist` changes.

## Verification

- **Pest** `tests/Feature/KbdTest.php`: `keys="cmd shift k"` → 3 `data-atom-kbd-key` caps containing
  `⌘`, `⇧`, `K`; `+`-separated `keys="ctrl+alt+del"` → `⌃`, `⌥`, `⌦`; unknown single char upper-cased;
  slot with no `keys` → 1 cap containing the slot text.
- **No e2e** — kbd is static/presentational, nothing interactive to drive (would be vacuous). Rendering
  is covered by Pest.
- Command: `./vendor/bin/pest --filter Kbd`. Worktree deps: `composer install` only (no npm — no dist).

## Ship

Squash-per-task on `worktree-kbd` → `gh pr create --draft`. Merge alongside context-menu (#12); tag the
resulting versions.

## Out of scope (v1)

`size` variants, OS-aware symbol switching (always show mac glyphs), press-state animation.
