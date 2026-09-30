## Atom

Atom (`jiannius/atom`) is a Tailwind + Alpine + Livewire component library for Laravel. The component catalogue is at `vendor/jiannius/atom/components/` — always check it before writing custom markup.

### Component directory & usage snippets

Every component ships with a canonical, verified usage example:

@verbatim
- **Demo snippets:** `vendor/jiannius/atom/resources/views/docs/demos/<component>/*.blade.php` — each file is a tiny, copy-paste-ready Blade example (these power the live `/atom/docs` pages, so they are rendered and verified, never stale). Read the relevant one before writing `<atom:...>` markup.
- **Prop reference:** the `@props([...])` block at the top of `vendor/jiannius/atom/components/<component>/index.blade.php` (or `components/<component>.blade.php` for flat components) is the authoritative prop list.
- **Browsable docs:** with `APP_ENV=local`, visit `/atom/docs` for live previews, prop tables, and searchable icon/logo galleries.
@endverbatim

@verbatim
### Tag syntax

Use `<atom:name>` for every Atom component. Dot-paths map to subdirectories:

- `<atom:button>` → `components/button/index.blade.php`
- `<atom:icon.close />` → `components/icon/close.blade.php`
- `<atom:input.email />` → `components/input/email.blade.php`

Never write `<x-atom::name>`, `<x-icon />`, or bespoke equivalents when an Atom component exists.

### Icons

- Always `<atom:icon.name />`. Names live in `vendor/jiannius/atom/components/icon/`.
- Default size is `size-5`. Override with `class="size-4"` etc.
- Some icons accept `variant="solid"`. Pass any other Tailwind classes via `class`.
- Icons are decorative by default and marked `aria-hidden`. For a meaningful standalone icon, pass `aria-label` (or `title`) to expose it to assistive tech.
- An icon-only button (`<atom:button icon="..." />`, no slot) is auto-labelled from the icon name; override with `aria-label="..."`. Don't add a redundant `aria-label` to a button that already has visible text.

### Security rules that always apply

- Chat HTML from the browser is untrusted: clean it with `Jiannius\Atom\Tiptap\Content::sanitize()` before you store it, and print it through `<atom:tiptap.content>`. Never print editor or chat HTML with `x-html`, `{!! !!}` or `->html()`. `sanitize()` is for chat HTML only: the `<atom:tiptap>` JSON value is stored as JSON and printed with `<atom:tiptap.content>`.
- A URL a user typed goes through `safe_url($url)` (PHP) or `atom.safeUrl(url)` (JS) before it reaches your own `href`, `formaction`, `window.open()` or `location`; `{{ }}` escaping does not stop `javascript:`. It checks the scheme only, so a redirect must also check the host.
- A `'html'` key in a select option set is trusted, not escaped: wrap every interpolated user or database field in `e()`.
- `POST /atom/action/{Name}` is public and unauthenticated, so an action is browser-callable only if it implements `WebAction`. Implement it only when the browser must call it; then add `authorize()`, validate every param, and return columns, not models. A component that shadows `action()` must declare it `protected`.
- A string `<atom:select options="name">` needs `app/Actions/GetOptions.php` extending `\Jiannius\Atom\Actions\GetOptions`, with `name` declared in `$auth` (the default) or `$guest`, and the query scoped to the current tenant. Otherwise the select renders empty, silently.

### Keep the layout on Atom

Views drift when they hand-roll what Atom already has.

- Forms: `<atom:form.grid>`, `<atom:form.modal>` and `<atom:form.actions>`, never bare `grid-cols-2`.
- Notices, status and empty lists: `<atom:callout>`, `<atom:badge>` and `<atom:empty>`, not hand-built boxes.
- Navigation: `<atom:navlist>`, not hand-rolled links. Sections: `<atom:separator>`, not `<hr>`.
- Give every border or divider a light-mode colour: `border-t border-zinc-200 dark:border-zinc-700`, since Tailwind v4 defaults to `currentColor`.
@endverbatim


@verbatim
### Going deeper

The rules above are the ones that apply to every view. The **`atom-components`
skill** carries the rest — the `AtomComponent` trait and the method names it
occupies, forms and reCAPTCHA, modals, tables (filters, bulk selection, sticky
selection), navigation and breadcrumbs, toast/alert/confirm, dropdowns, tooltips,
status primitives, `<atom:select>` option sets, Actions and the `WebAction`
contract, enums, the editor and the full sanitising rules, safe URLs, share buttons,
mail, broadcasting, and the project conventions.

Activate it before writing non-trivial Atom markup or any Livewire component.
The skill loads only if Boost skills are enabled (`boost.json`) and the agent supports
skills (Claude Code, Codex, Cursor, Gemini, Amp). Otherwise run
`php artisan boost:install` and enable skills.
@endverbatim
