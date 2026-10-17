# Upgrading to atom 4

Each change that breaks a host is listed once, under the release that made it. The entries are written as the work lands; the codemod and the full checklist come with the 4.0 release.

## Tokens

**CSS integration.** Delete your atom `@source` lines and the theme block copied from skeleton-project (the font, `primary`, the zinc remap, `muted`, `accent`, the `border-color` base rule). Add `@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';` after `@import 'tailwindcss';`. Then set your brand colour as `--color-accent` in `@theme`, and do not set `accent-foreground` unless you must. A derived colour you do set goes in `@theme` too, not in a plain `:root` rule: `accent-soft` and `accent-ink` are inline tokens, which utilities read where they are used. Overriding `--color-accent-ink` changes the `accent-ink` utilities only: `accent-content` and the focus ring read atom's `--atom-accent-ink` role directly (so a nested `.dark` island keeps its own values), and a `--color-accent-ink` override does not reach them.

**Root size.** The 14px root is gone; spacing utilities follow Tailwind (`p-6` is 24px, not 21px). Remove any `html { font-size }` override.

**Type sizes and density.** `text-xs`/`sm`/`base` follow the density profile of the page: compact (`layouts.sidebar`) renders them at 12/13/14px, comfortable (a bare `<atom:html>`, `layouts.auth`) at 13/14/15px. Pass `density="compact"` or `"comfortable"` to `<atom:html>` to change a page, or set `data-atom-density` on any element for a subtree. A touch device (`pointer: coarse`) gets taller controls and rows in either profile. `text-lg`, `text-xl` and `text-2xl` are now 16, 18 and 22px, and `font-medium` and `font-semibold` are weights 510 and 590 (Geist is a variable font, so they draw as asked).

**Browsers.** The derived accent colours (`accent-foreground`, `accent-hover`, `accent-ink`) and the focus ring use CSS relative colour: Chrome and Edge 119, Safari 16.4, Firefox 128. Below that floor atom falls back to plain colours: a white label on the accent, the accent itself for hover and for accent text, and a blue (`#3b82f6`) focus ring, so the focus ring stays visible and a dark accent keeps a readable label. A light accent does not: the label is always white, so on a light accent such as `#f5c518` it is about 1.6:1 and hard to read. Hover shows no change.

**Accent fallback.** If your app does not set `--color-accent`, the accent is near-black (`#1c1b18`), which is barely visible as a fill in dark mode. A dark-mode app should set its own accent.

**Font.** Geist is the default. Pass `fonts="inter"` to `<atom:html>` to keep the old font, or `:fonts="false"` to load none and use the system fonts.

## Shells

`layouts.header` is new: use it for apps with about 6 or fewer top-level sections. It takes the same slots and props as `layouts.sidebar`, including the same `nav` markup, so an app can choose the shell per user (for example, the top nav for a role that sees only one or two modules). `<main>` is still unpadded; padding now comes from `<atom:page>`.

**What changes in the shells you already use.** `layouts.sidebar` is restyled: a 228px side surface, 28px nav rows with 16px icons, and a 48px sticky top bar that is now shown at every width below `lg`, and from `lg` up only when it has something to hold (it holds the new `crumbs` and `actions` slots, and the dark toggle when `dark` is set, which used to show on small screens only; remove a toggle you put in `navfoot` yourself). Both shells also take new slots, `context` (a workspace switcher) and `search`. Navigation items now carry `aria-current="page"`, and a `navlist.group` heading is 12px and no longer uppercase. `layouts.auth` is restyled with the comfortable density and a plain canvas (the dark gradient is gone).

**A class on a headed `navlist.group`.** `<atom:navlist.group heading="..." class="...">` now puts its attributes on the group's root element (the heading, the items and the header shell's dropdown trigger), not on the inner list of items as before. A class you passed to space or style that list now applies to the whole group; move it onto the items or wrap them. A group with no `heading` is unchanged.

**The top nav switches to a menu below 1024px.** `layouts.header` shows its items as a bar from `lg` up (below that the logo, context, items and account do not fit side by side), and its `search` slot only from `xl` (1280px). Below `lg` the nav is a menu, with the `context` slot at the top of the panel. A `profile` slot with no `dropdown` is plain content in both shells, not a button.

**Choosing the shell is yours.** Atom does not switch on an item count: it renders the nav before it knows where the nav goes. Decide in your own app layout, for example `<x-dynamic-component :component="$user->modules()->count() <= 2 ? 'atom::layouts.header' : 'atom::layouts.sidebar'">`. The choice is per page load; a role does not change mid-session, so `wire:navigate` never crosses shells.
