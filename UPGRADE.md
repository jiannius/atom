# Upgrading to atom 4

Each change that breaks a host is listed once, under the release that made it. The entries are written as the work lands; the codemod and the full checklist come with the 4.0 release.

## Tokens

**CSS integration.** Delete your atom `@source` lines and the theme block copied from skeleton-project (the font, `primary`, the zinc remap, `muted`, `accent`, the `border-color` base rule). Add `@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';` after `@import 'tailwindcss';`. Then set your brand colour as `--color-accent` in `@theme`, and do not set `accent-foreground` unless you must.

**Root size.** The 14px root is gone. `text-xs`/`sm`/`base` now render at 12/13/14px; spacing utilities follow Tailwind (`p-6` is 24px, not 21px). Remove any `html { font-size }` override.

**Font.** Geist is the default. Pass `fonts="inter"` to `<atom:html>` to keep the old font.
