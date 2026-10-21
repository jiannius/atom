## Atom

Atom (`jiannius/atom`) is a Tailwind + Alpine + Livewire component library for Laravel. The component catalogue is at `vendor/jiannius/atom/components/` — always check it before writing custom markup.

### Component directory & usage snippets

Every component ships with a canonical, verified usage example:

@verbatim
- **Demo snippets:** `vendor/jiannius/atom/resources/views/docs/demos/<component>/*.blade.php` — each file is a tiny, copy-paste-ready Blade example (these power the live `/atom/docs` pages, so they are rendered and verified, never stale). Read the relevant one before writing `<atom:...>` markup.
- **Prop reference:** the `@props([...])` block at the top of `vendor/jiannius/atom/components/<component>/index.blade.php` (or `components/<component>.blade.php` for flat components) is the authoritative prop list.
- **Browsable docs:** with `APP_ENV=local`, `/atom/docs`.
@endverbatim

@verbatim
### Tag syntax

Use `<atom:name>` for every Atom component. Dot-paths map to subdirectories:

- `<atom:button>` → `components/button/index.blade.php`
- `<atom:icon.close />` → `components/icon/close.blade.php`
- `<atom:input.email />` → `components/input/email.blade.php`

Never write `<x-atom::name>` or bespoke equivalents of an Atom component.

### Icons

- Always `<atom:icon.name />`. Names live in `vendor/jiannius/atom/components/icon/`.
- Default size is `size-5`. Override with `class="size-4"` etc.
- Some icons accept `variant="solid"`. Pass any other Tailwind classes via `class`.
- Icons are `aria-hidden` by default; a meaningful standalone icon needs `aria-label` (or `title`).
- An icon-only button (`<atom:button icon="..." />`, no slot) is auto-labelled from the icon name; override with `aria-label="..."`. Don't add a redundant `aria-label` to a button that already has visible text.

### Security rules that always apply

- Chat HTML from the browser is untrusted: clean it with `Jiannius\Atom\Tiptap\Content::sanitize()` before you store it, and print it through `<atom:tiptap.content>`. Never print editor or chat HTML with `x-html`, `{!! !!}` or `->html()`. `sanitize()` is for chat HTML only: the `<atom:tiptap>` JSON value is stored as JSON and printed with `<atom:tiptap.content>`.
- A URL a user typed goes through `safe_url($url)` (PHP) or `atom.safeUrl(url)` (JS) before it reaches your own `href`, `formaction`, `window.open()` or `location`; `{{ }}` escaping does not stop `javascript:`. It checks the scheme only, so a redirect must also check the host.
- A public `$filters` passed to `->filter()` is untrusted: it still filters any visible column and calls any named scope, so pass only allowed keys (`filter(Arr::only($this->filters, [...]))`) when that matters. Don't `#[Locked]` it: the table controls bind to it. URL filters: `WithUrlFilters`.
- A `'html'` key in a select option set is trusted, not escaped: wrap every interpolated user or database field in `e()`.
- `POST /atom/action/{Name}` is public and unauthenticated, so an action is browser-callable only if it implements `WebAction`. Implement it only when the browser must call it; then add `authorize()`, validate every param, and return columns, not models. A component that shadows `action()` must declare it `protected`.
- A string `<atom:select options="name">` needs `app/Actions/GetOptions.php` extending `\Jiannius\Atom\Actions\GetOptions`, with `name` declared in `$auth` (the default) or `$guest`, and the query scoped to the current tenant. Otherwise the select renders empty, silently.

### Livewire components

Add only the traits a component uses: `WithTable`, `WithBreadcrumbs`, `WithEditor`, `WithRecaptcha`. `$this->toast()` and its siblings are PHP-only; JS uses `atom.toast()`.

### Keep the layout on Atom


- Forms: `<atom:form.section>`, `<atom:form.grid>`, `<atom:form.modal>` and `<atom:form.actions>`, never bare `grid-cols-2`.
- Panels, label/value pairs, tags, notices, status and empty lists: `<atom:card>`, `<atom:dd>`, `<atom:badge>`, `<atom:callout>`, `<atom:status>`, `<atom:empty>`, never hand-built boxes.
- Screens: `<atom:page>`, `<atom:page.header>`, `<atom:timeline>`; rules in the skill's Patterns section.
- Navigation: `<atom:navlist>`, not hand-rolled links, in `<atom:layouts.sidebar>` or `<atom:layouts.header>`. Sections: `<atom:separator>`, not `<hr>`.
- Colour with role tokens (`bg-surface`, `text-fg-muted`, `border-border`), not `zinc-*` or `dark:` pairs. One `variant="primary"` button per view; `type="submit"` and `"delete"` add no icon (`icon=`).
@endverbatim


@verbatim
### Going deeper

The rules above apply to every view. The **`atom-components` skill** carries the rest: the
component traits and the method names they occupy, forms and reCAPTCHA, modals, tables,
navigation, patterns, breadcrumbs, the calendar,
toast/alert/confirm, dropdowns, tooltips, `<atom:select>` option sets, Actions
and the `WebAction` contract, enums, the editor and sanitising, safe URLs, share
buttons, mail, broadcasting, and conventions.

Activate it before writing non-trivial Atom markup or any Livewire component.
It loads only if Boost skills are enabled (`boost.json`) and the agent supports
skills (Claude Code, Codex, Cursor, Gemini, Amp); otherwise run
`php artisan boost:install`.
@endverbatim
