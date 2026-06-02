# Component Directory — Design

**Date:** 2026-06-02
**Status:** Approved

## Goal

A browsable component directory for the Atom package: every component listed, with live previews and usage guidelines, available to any developer working in a consuming app.

## Decisions

| Question | Decision |
| --- | --- |
| Audience / where it lives | Package-mounted routes (Telescope/Horizon-style) — every consuming app gets it locally |
| Page content | Flux-style: live rendered previews + code snippets |
| Coverage | Framework + 22 priority components hand-authored; all others get auto-generated fallback pages |
| Access | Routes register only when `app()->environment('local')`; no config, no gate in v1 |
| Layout | Persistent sidebar (search + 6 categories), content swaps in place |
| Content structure | Blade demo partials as source-of-truth — each example partial is both rendered live and read as text for the code snippet |

## Architecture

### Routes (`routes/web.php`)

Registered only when the app environment is `local`:

- `GET /atom/docs` → directory index, named `atom.docs`
- `GET /atom/docs/{component}` → component page, named `atom.docs.show`

`routes/web.php` is currently empty and already loaded by `AtomServiceProvider`; the routes go there, not in the provider.

### `Services\Docs`

Follows the `Services\Asset` pattern. Responsibilities:

- `components()` — scan `components/` top level → collection of all components: name, category, title, whether a hand-authored demo view exists. Categories come from a hardcoded map mirroring the README's six sections (Form inputs, Buttons & links, Display & typography, Feedback & overlays, Layout & navigation, Miscellaneous). Unmapped components fall into Miscellaneous, so new components appear in the directory automatically.
- `icon` and `logo` are special-cased as single entries pointing at gallery pages, not per-glyph pages.
- `props($component)` — extract the `@props([...])` array from the component's blade file (index.blade.php or the flat file) for the auto prop table.
- `source($view)` — return a demo partial's raw blade source for code display.

### Views (`resources/views/docs/`)

```
docs/
├── layout.blade.php        # docs chrome: <atom:html> + atom navlist sidebar (dogfoods atom itself)
├── index.blade.php         # landing: overview in the content area
├── show.blade.php          # shell: hand-authored demo view if present, else fallback
├── fallback.blade.php      # auto page: tag + prop table + source link + "examples pending"
├── gallery/
│   ├── icon.blade.php      # searchable glyph grid, click-to-copy tag
│   └── logo.blade.php
└── demos/
    ├── button.blade.php    # hand-authored page: a sequence of example sections
    └── button/
        ├── variants.blade.php   # tiny example partial — rendered live AND shown as source
        └── sizes.blade.php
```

**Core mechanism:** an example section `@include`s its partial (live render) and also displays the same file's source as the code snippet (copy button via the existing `$clipboard` Alpine magic). Code shown is code run, by construction — no drift.

## Component page anatomy

Every `/atom/docs/{component}` page, inside the sidebar layout:

1. **Header** — title, one-line description, the tag (`<atom:button>`) with copy button.
2. **Example sections** (hand-authored pages only) — heading → live render in a bordered preview panel → source code block with copy. Typically 3–6 sections per component (variants, sizes, icons, special flows).
3. **Prop reference table** — auto-generated from `@props` parsing, appended to every page including hand-authored ones. Prop tables are never maintained by hand.
4. **Source link** — the component's path under `components/`.

Fallback pages are items 1, 3, 4 plus an "examples pending" note.

Sidebar search and gallery search are client-side Alpine filters over the already-rendered list — no server round-trip, no search endpoint.

## Priority set (hand-authored in v1)

- **Form:** input, textarea, select, checkbox, radio, toggle, date-picker, time-picker, uploader, editor, form
- **Buttons:** button, link
- **Feedback:** modal, toast, alert, confirm, tooltip
- **Layout/display:** table, tabs, card, badge

Plus icon and logo galleries (auto-generated). Remaining ~27 components launch with fallback pages.

(22 names listed; "~19" was the working estimate during brainstorming — the list above is canonical.)

## v1 boundaries

- **No Livewire round-trips.** Docs pages are plain Blade routes. `wire:model` appears in snippets but renders inert; Alpine-driven interactivity (modals, dropdowns, pickers, toasts via window events) works fully. A Livewire-backed demo component is a possible later addition.
- **No config file or non-local access.** Hard-coded `environment('local')` check.
- **No syntax-highlighting library.** Styled `<pre>` blocks only.
- **No versioned docs, no prose/MDX pages.** This is a component directory, not a documentation site.

## Error handling

- Unknown component slug → 404.
- Component without a parseable `@props` block → empty prop table, page still renders.
- Broken demo partial → throws normally; it is a local dev tool and the stack trace is desirable.

## Verification

No test suite exists in this package. Verify by installing the branch in a host app (e.g. toocrm-l13), then walking `/atom/docs`, each priority page, a fallback page, and both galleries with Playwright. Confirm: sidebar navigation and search, live examples render, copy buttons work, prop tables populate, local-only gating (route absent when env is not local).
