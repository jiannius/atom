## Atom

Atom (`jiannius/atom`) is a Tailwind + Alpine + Livewire component library for Laravel. The component catalogue is at `vendor/jiannius/atom/components/` — always check it before writing custom markup.

### Component directory & usage snippets

@verbatim
- **Demo snippets:** `vendor/jiannius/atom/resources/views/docs/demos/<component>/*.blade.php`, copy-paste examples verified in `/atom/docs`. Read one before writing `<atom:...>` markup.
- **Props:** the `@props([...])` block at the top of `components/<component>/index.blade.php` (or `components/<component>.blade.php`).
- **Docs:** with `APP_ENV=local`, `/atom/docs` has live previews and prop tables.
@endverbatim

@verbatim
### Tag syntax

Use `<atom:name>` for every Atom component. Dot-paths map to subdirectories:

- `<atom:button>` → `components/button/index.blade.php`
- `<atom:icon.close />` → `components/icon/close.blade.php`
- `<atom:input.email />` → `components/input/email.blade.php`

Never write `<x-atom::name>`, `<x-icon />`, or bespoke equivalents when an Atom component exists.

### Icons

- Always `<atom:icon.name />` (names in `vendor/jiannius/atom/components/icon/`), `size-5` by default (`class="size-4"`); some take `variant="solid"`.
- Icons are `aria-hidden`; a meaningful standalone one takes `aria-label` (or `title`). An icon-only button is labelled from the icon name (override with `aria-label`); a button with visible text needs none.

### Security rules that always apply

- Chat HTML from the browser is untrusted: clean it with `Jiannius\Atom\Tiptap\Content::sanitize()` before you store it, and print it through `<atom:tiptap.content>`. Never print editor or chat HTML with `x-html`, `{!! !!}` or `->html()`. `sanitize()` is for chat HTML only: the `<atom:tiptap>` JSON value is stored as JSON.
- A URL a user typed goes through `safe_url($url)` (PHP) or `atom.safeUrl(url)` (JS) before it reaches your own `href`, `formaction`, `window.open()` or `location`; `{{ }}` escaping does not stop `javascript:`. It checks the scheme only, so a redirect must also check the host.
- A public `$filters` passed to `->filter()` is untrusted: it still filters any visible column and calls any named scope, so pass only allowed keys (`filter(Arr::only($this->filters, [...]))`) when that matters. Don't `#[Locked]` it: the table controls bind to it. URL filters: `WithUrlFilters`.
- A `'html'` key in a select option set is trusted, not escaped: wrap every interpolated user or database field in `e()`.
- `POST /atom/action/{Name}` is public and unauthenticated, so an action is browser-callable only if it implements `WebAction`. Implement it only when the browser must call it; then add `authorize()`, validate every param, and return columns, not models. A component that shadows `action()` must declare it `protected`.
- A string `<atom:select options="name">` needs `app/Actions/GetOptions.php` extending `\Jiannius\Atom\Actions\GetOptions`, with `name` declared in `$auth` (the default) or `$guest`, and the query scoped to the current tenant. Otherwise the select renders empty, silently.

### Livewire components

Add only the traits a component uses: `WithTable`, `WithBreadcrumbs`, `WithEditor`, `WithRecaptcha`. `$this->toast()` and siblings are PHP-only (JS: `atom.toast()`). `AsEditorContent` and `<atom:editor*>` are gone: use `AsTiptapContent`; `atom:tiptap-migrate` converts old rows.

### Design rules

- A screen is `<atom:page>` opening with `<atom:page.header>`; crumbs from the shell's `crumbs` slot or the header's `crumbs` prop, not both. Never hand-build a title, crumb strip, sidebar toggle or ⋯ dropdown.
- One `variant="primary"` per screen: the next step for the status (Edit if there is no workflow) or the form's submit. None in an empty state, toolbar, bulk bar or row.
- Tone follows the consequence: danger destroys or cannot be undone (`type="delete"`); warning stops or hides but keeps the record (archive, void, cancel an order: `variant="warning" inverted`). Both confirm in their tone. Avoid `success` and `info` buttons.
- Footers, bars and `form.modal` order buttons primary, default, danger, then ⋯ (the header places its own `primary`). In a menu, warning then danger go last, after `<atom:menu.separator>`.
- Caps: header `actions` 2 (`size="sm"` 1), toolbar 2, bulk bar 3, card heading 1 link, rows 0 (`<atom:table.actions>`); the rest in `menu`.
- List filters live in `<atom:table.filters>` bound to `filters.*`. A state is `<atom:status>`, a tag or count `<atom:badge>`; an empty list `<atom:empty>` with one plain button.
- Forms: `<atom:form.section>`, `<atom:form.grid>`, `<atom:form.actions>`, never bare `grid-cols-2`. A main module's form is a page; a secondary one's (a tax rate, a tag) `<atom:form.modal>` or a slide-over.
- Toast = what the user just did; one `<atom:callout>` under the header = what they must know.
- Sentence case; buttons start with a verb ("Create …" a record, "Add …" a line). Never type `—` or `--` for a missing value.
- Use `<atom:card>`, `<atom:dd>`, `<atom:timeline>`, `<atom:navlist>` (in `<atom:layouts.sidebar>` or `<atom:layouts.header>`) and `<atom:separator>`, not hand-built boxes or `<hr>`; no margins between page blocks, no `gap-[..]`, no `shadow-*`. Colour with role tokens (`bg-surface`, `text-fg-muted`, `border-border`), not `zinc-*` or `dark:` pairs. `type="submit"` and `"delete"` add no icon (`icon=`).
- Full tables: the skill's Design rules and Patterns section.
@endverbatim


@verbatim
### Going deeper

The **`atom-components` skill** carries the rest: the traits, forms, modals, tables,
navigation, patterns, the calendar, notifications, dropdowns, option sets, Actions,
enums, the editor, safe URLs, mail and broadcasting. Activate it before writing
non-trivial Atom markup or a Livewire component.
The skill loads only if Boost skills are enabled (`boost.json`) and the agent supports
skills (Claude Code, Codex, Cursor, Gemini, Amp). Otherwise run
`php artisan boost:install` and enable skills.
@endverbatim
