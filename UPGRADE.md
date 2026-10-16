# Upgrading to atom 4

Each change that breaks a host is listed once, under the release that made it. The entries are written as the work lands; the codemod and the full checklist come with the 4.0 release.

## Tokens

**CSS integration.** Delete your atom `@source` lines and the theme block copied from skeleton-project (the font, `primary`, the zinc remap, `muted`, `accent`, the `border-color` base rule). Add `@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';` after `@import 'tailwindcss';`. Then set your brand colour as `--color-accent` in `@theme`, and do not set `accent-foreground` unless you must. A derived colour you do set goes in `@theme` too, not in a plain `:root` rule: `accent-soft` and `accent-ink` are inline tokens, which utilities read where they are used.

**Root size.** The 14px root is gone; spacing utilities follow Tailwind (`p-6` is 24px, not 21px). Remove any `html { font-size }` override.

**Type sizes and density.** `text-xs`/`sm`/`base` follow the density profile of the page: compact (`layouts.sidebar`) renders them at 12/13/14px, comfortable (a bare `<atom:html>`, `layouts.auth`) at 13/14/15px. Pass `density="compact"` or `"comfortable"` to `<atom:html>` to change a page, or set `data-atom-density` on any element for a subtree. A touch device (`pointer: coarse`) gets taller controls and rows in either profile. `text-lg`, `text-xl` and `text-2xl` are now 16, 18 and 22px, and `font-medium` and `font-semibold` are weights 510 and 590 (Geist is a variable font, so they draw as asked).

**Browsers.** The derived accent colours (`accent-foreground`, `accent-hover`, `accent-ink`) and the focus ring use CSS relative colour: Chrome and Edge 119, Safari 16.4, Firefox 128. Below that floor atom falls back to plain colours: a white label on the accent, the accent itself for hover and for accent text, and a blue (`#3b82f6`) focus ring, so nothing becomes unreadable or invisible, but the label does not adapt to a light accent and hover shows no change.

**Accent fallback.** If your app does not set `--color-accent`, the accent is near-black (`#1c1b18`), which is barely visible as a fill in dark mode. A dark-mode app should set its own accent.

**Font.** Geist is the default. Pass `fonts="inter"` to `<atom:html>` to keep the old font, or `:fonts="false"` to load none and use the system fonts.
