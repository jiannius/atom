---
name: atom-components
description: "Use when building or editing any Blade view, Livewire component, or admin screen in an app that uses jiannius/atom. Activate for atom: component markup, the component traits (`WithTable`, `WithBreadcrumbs`, `WithEditor`, `WithRecaptcha`), forms and reCAPTCHA, modals, data tables (sorting, filters, filters kept in the URL, bulk and sticky selection), sidebar navigation and breadcrumbs, the calendar (items, timezones, move, resize and select events), toasts/alerts/confirms, dropdowns and menus, tooltips, badges/callouts/empty states, select option sets, Action classes and the WebAction contract, enums, the editor and sanitising its HTML, safe URLs, share buttons, mail, and broadcasting. Covers the load-bearing rules and silent-breakage footguns that reading the component source alone will not reveal. Do not use for plain Laravel work that touches no Atom component."
license: MIT
metadata:
  author: jiannius
---
# Atom components

Deep reference for `jiannius/atom`. The always-on guidelines cover tag syntax, the
component directory, icons and the security rules that always apply; everything below loads only when you are actually
working with Atom components.

### Livewire components — helpers and opt-in traits

@verbatim
Livewire 4 single-file components are the default. A plain `Livewire\Component` already has the helpers below (v4: macros registered by atom, no trait to add), and takes only the traits it needs from `Jiannius\Atom\Traits\`:

- `WithTable`: `$_table` and the table methods; for `<atom:table>` or `toTable()` sorting. Add Livewire's `WithPagination` for a paginated table.
- `WithBreadcrumbs`: `$_breadcrumbs`; define `breadcrumbs(Breadcrumbs $b)` and it is built on mount.
- `WithEditor`: `$_editor` and `WithFileUploads`; for `<atom:tiptap>` with image upload.
- `WithRecaptcha`: `$_recaptcha` and `verifyRecaptcha()`; for `<atom:form recaptcha>`.

Rendering one of these without its trait throws a message naming the trait and the component class (so does a `breadcrumbs($b)` method without `WithBreadcrumbs`, unless it takes no parameter or carries a Livewire attribute such as `#[Computed]`; table markup rendered to a string in `mount()` or an action is not checked). Do not name a property or method after a trait hook (`withTable`, `withEditor`, `withBreadcrumbs`, `withRecaptcha`, `withUrlFilters`, `updatingWithTable`): Livewire runs the trait's hook for it. The table sort gate does not depend on these names.

Helpers, on every component:

- `$this->modal($name = null)->show() / ->slide() / ->close()`: `$name` defaults to the current component name.
- `$this->toast(...)`, `$this->alert(...)`, `$this->confirm(...)`, `$this->command(...)`: dispatch UI events.
- `$this->action('Name', $params)`: invoke an Action class (see Actions).
- `$this->wirekey(...$args)`: stable `wire:key` based on args, or random ULID-based key when called with none.

**PHP only, never `$wire.toast(...)`.** The helpers are macros, which Livewire never exposes to the browser (before v4 they were public trait methods and `$wire.toast(...)` worked). From JS use `atom.toast(...)`, `atom.alert(...)`, or dispatch `atom-toast-show`; for an action, `atom.action(...)` with the action opted into `WebAction`.

**JS helpers live on `atom`, not on the built-ins.** atom adds nothing to `Array`, `Number` or `String` and sets no `window.dd` / `window.empty`: write `atom.currency(n, symbol, round, maxPrecision)`, `atom.empty(x)`, `atom.arr.pluck(rows, 'id')` (also `unique`, `sum`, `where`, `firstWhere`, `toggle`), `atom.str.headline(s)` (also `limit`, `slug`, `camel`, `nl2br`, `striptags`, `toDateString`, `toDatetimeString`, `toTimeString`, `toDateFormat`) and `console.log()`, never `n.currency()`, `empty(x)` or `dd()`.
@endverbatim

@verbatim
**Names the traits occupy** — a class method beats a trait method or a macro of the same name with no error, so a component that redefines one shadows atom's. Whether that matters depends on who calls the name. **Safe to shadow** (sugar you call, never atom): `modal`, `command`, `toast`, `alert`, `confirm`, `action`, `wirekey`, `verifyRecaptcha` — but declare a shadowing `action()` **protected**, because Livewire exposes every public method a component declares and a public `action()` is callable from the browser as `$wire.action('AnyAction', {method: 'anyMethod'})`. **Silent breakage if shadowed** (atom or Livewire invokes these by name — the prefix is the protection): `$_breadcrumbs`, `$_table`, `$_editor`, `$_recaptcha` (read by the `toTable()` macro and the blades); `mountWithBreadcrumbs`, `updatedWithEditor`, `updatedWithTable` (Livewire convention — kills breadcrumbs, editor uploads, trashed clear); `resetTableCheckboxes`, `selectAllTableMatching`, `toggleTableShowSelected`, `clearTableSelectAll` (wired from markup by name); `tableSelection`, `tableSelectionQuery`, `tableRowsQuery`, `getTableCheckboxes`, `isTableSelectAll`, `isTableShowSelected`, `isTableShowTrashed`; and Livewire's own `$paginators`, `getPage`, `gotoPage`, `nextPage`, `previousPage`, `resetPage`, `setPage`, `queryStringHandlesPagination`, `_startUpload`, `_finishUpload`, `_removeUpload`, `_uploadErrored`. Yours to define: `breadcrumbs`, `tableQuery`, `tableSelectionQuery`. Everything else is free — the `#[Computed]` property feeding `<atom:table :paginate="...">` is yours to name, and atom neither defines nor looks for `items`/`rows`/`records`/`data`.
@endverbatim

@verbatim
<code-snippet name="Single-file Livewire component with Atom" lang="php">
<?php
use Livewire\Component;
use Jiannius\Atom\Traits\WithBreadcrumbs;

new class extends Component {
    use WithBreadcrumbs;

    public string $email = '';

    public function breadcrumbs($b)
    {
        return $b->home('Dashboard', route('dashboard'))->push('Invite User');
    }

    public function submit(): void
    {
        $this->validate(['email' => 'required|email']);
        // ...
        $this->toast('User invited', variant: 'success');
        $this->modal()->close();
    }
};
?>

<atom:modal>
    <atom:form>
        <atom:input.email wire:model="email" label="Email" />
        <atom:button type="submit">Invite</atom:button>
    </atom:form>
</atom:modal>
</code-snippet>
@endverbatim

### Forms

@verbatim
`<atom:form>` wires `wire:submit` (defaults to `submit`) — name the method via `wire:submit="create"` and the matching `<atom:button type="submit">` shows its loading spinner for *that* method automatically (the form drives the loading state, so it works for `create`/`save`/anything, not just `submit`). Use `<atom:input.*>`, `<atom:select>`, `<atom:textarea>`, `<atom:checkbox>`, `<atom:radio>`, `<atom:date-picker>`, `<atom:time-picker>`, `<atom:uploader>` (or `<atom:uploader.dropzone>` for a drag-and-drop drop target — both bind via `wire:model`). Submit with `<atom:button type="submit">` — no separate loading overlay needed.

**reCAPTCHA v3.** Add the `recaptcha` prop to protect a submit: `<atom:form wire:submit="create" recaptcha>` (or `recaptcha="signup"` to set the score action). It mints a token client-side, attaches it to the component, then runs the submit. In the Livewire method, verify it (the component must use the `WithRecaptcha` trait):
```php
public function create() {
    $this->verifyRecaptcha();   // throws a validation error on a bot; no-op if recaptcha is unconfigured
    // ...
}
```
Requires `config('services.recaptcha.site_key' / 'secret_key' / 'min_score')` and a built site (consumers run their Tailwind/Vite build). With no site key configured the form behaves as a normal `wire:submit` form.

**Error toast.** A failed submit (the Livewire method ends with validation errors) shows a danger toast headed "Please check the form", so a long form that hides its errors off screen doesn't look like it did nothing.

- It lists the errors of the fields the submitted form renders (`wire:model*` / `name`), each message once, five at most, then "and N more". An error with no field in the form (a failed API call, `addError('general', ...)`) is the host's to report, e.g. with `$this->toast(...)`.
- Outside a modal it is sticky: it stays until the user dismisses it with its ✕ or the same form's next submit comes back clean. Inside a `<dialog>` it is timed (6s) and closes with the dialog. It never scrolls or moves focus.
- The form's `wire:target` must name the submit method (atom sets it; don't override it) or the toast is off. It is on by default; opt out per form with `:error-toast="false"` (a `disabled` form never shows one).
- It needs `<atom:toast>` mounted on the page (the sidebar layout has it), and does nothing without. It is a singleton like every toast: if your own method already toasts its validation message, the last one shown wins.

**Sections and the save bar.** A form or settings page is `<atom:form.section heading="..." subheading="...">` blocks with the fields inside; the form's gap and a hairline separate them, so put them straight in `<atom:form>` (or `form.grid cols="1"` / `form.modal cols="1"`), never hand-rolled `grid-cols-2` and `border-t`. `layout="aside"` (the default) puts the heading in a 220px column beside the fields once the section is 768px wide, and above them below that; `layout="stacked"` always puts it above (slide-over, modal; use `level="3"` there). The fields are two across once their own box is 448px wide, one below that: `class="@md:col-span-2"` on a field spans the row. A `footer` slot adds a right-aligned row for a section that saves on its own (give it its own `<atom:form>`). **The page save bar is `<atom:form.actions bar>`**, the LAST thing in a page's form: sticky at the bottom of the screen, a blurred surface and a hairline that run the full width of the content area (its content stays on the page column; in the sidebar shell this assumes the page starts at `<main>`'s content edge, so no `mx-auto` on `<atom:page>`; it takes the page's bottom room, so it needs an `<atom:page>` inside a shell around it). Its `start` slot is live totals or a dirty state; the buttons go in the default slot, ending with the submit, which is the screen's one primary. In a modal or slide-over use `sticky` instead: `<atom:form.modal>` draws it, with its `actions` slot beside the submit.
@endverbatim

### Modals

@verbatim
- When `<atom:modal>` is the **root** of a single-file Livewire component, omit `name` — methods auto-resolve to the component name.
- For multiple modals in one component, give each `name="..."` and target with `$this->modal('confirm')->show()` — modals are matched by name only.
- Three independent close switches, all default true: `:dismissible="false"` blocks backdrop-close, `:escapable="false"` blocks ESC, `:closeable="false"` hides the × button. Combine the first two for a persistent modal.
- `<atom:modal.trigger name="...">` wraps any clickable to open the modal on click; `name` defaults to the component name like the modal itself. Optional `slide` (`left`/`bottom`, or empty for right) and `shortcut` (e.g. `meta.k`) props.
@endverbatim
- Close client-side:
  - From inside the modal: `$dispatch('atom-modal-close')` (DOM containment closes the enclosing modal).
  - From outside: `$dispatch('atom-modal-close', { name: 'modal-name' })` — targeting by name is required when the dispatcher isn't inside the modal DOM.

### Tables (admin listings)

@verbatim
`<atom:table :paginate="$this->rows">` with `x-slot:columns` / `x-slot:rows` is the data table. Drive sort, pagination and checkboxes through the `$_table` state (from `WithTable`, plus Livewire's `WithPagination`) plus the `toTable($filters)` Eloquent builder macro on a `#[Computed]` method.

- **Search:** `<atom:table.search wire:model="filters.search" />` — the standard listing search (search icon, Enter to run). Don't hand-roll an input. Bound with `wire:model` (or `data-filter-key`) inside `<atom:table.filters>` it is a filter like the selects beside it: its text shows as a chip named by the `placeholder`, and "Clear all" or the chip's ✕ empties the box and refreshes the list. Typing and then leaving the box (no Enter) registers a chip but does not run the search; Enter does, and nothing shows while the box has focus. With `wire:model.live` the rows filter as the user types, so the chip follows them too. A search with no binding is plain enter-to-refresh, no chip.
- **Filters:** wrap the filter controls in `<atom:table.filters>`; it auto-renders active-filter chips + a "Clear all". Use `<atom:select variant="filter">`, `<atom:date-picker variant="range">`, or custom selects inside it — each control needs a `wire:model` (that is the chip's key; without it no chip registers). Put overflow filters in `<x-slot:more>`; the "More filters" button sits in the bar right after the last filter. Its overflow is a popover by default, `overflow="card"` an expandable row below the bar, and `overflow="modal"` a modal that lays the slot out as a form (one field per row) — write that slot as labelled fields (`<atom:select label="Category" variant="listbox" table-filter>`, `<atom:date-picker variant="range" label="Created">`), not as bar controls. **A form field only registers a chip if you add `table-filter`** — the bar variants (`select variant="filter"`, `date-picker variant="range"`) do it on the strength of their `wire:model` alone, but a form is also full of selects that filter nothing, so there the flag is the opt-in. The chip's key is the `wire:model`, its name is the `label`. Without the flag the field still filters, it just does so invisibly.
- **`filter($this->filters)` takes untrusted input.** Livewire lets a client replace a public `$filters` array with any keys and values. `filter()` (atom ≥ 3.35.4) skips a key that is not a plain column (`col`, `col:operator`, or `col.path` on a JSON column), a column in the model's `$hidden`, any column outside a non-empty `$visible`, an operator other than `= != <> < <= > >= like` / `not like`, and a value shape that makes no sense for the column. It does **not** stop a client filtering on any other visible column, or calling any named scope with the value as sent. When a listing must not allow that, use `WithUrlFilters` (next bullet), whose declared keys are such an allow-list, or pass only the keys you allow: `filter(Arr::only($this->filters, ['search', 'status', 'dates']))`, and write scopes so arbitrary input is safe. **Never `#[Locked]` a `$filters` the table controls bind to** (`wire:model="filters.search"`): Livewire refuses every client update to a locked property, so search, filter selects and chips break. Lock it only when the server alone sets it. **`$visible` footgun:** it is a serialisation list, so a model whose `$visible` omits `created_at`, `status` or a foreign key silently loses those filters; filter such columns outside `filter()`. Lists: on a plain column `=` means one of and `!=`/`<>` none of; on a JSON column `=` means contains all of and `!=` does not contain all of.
- **Trashed:** add the `trashed` prop to `<atom:table>` to append an icon toggle at the end of the header bar — it drives `$_table.show_trashed` and `toTable()` applies `onlyTrashed()`. Bare `trashed` = archived preset (archive icon, "Show archived"); `trashed="voided"` = voided preset (trash icon, "Show voided"). `<atom:table.trashed :variant="..." />` is the standalone component for custom placement.
- **Row actions:** `<atom:table.actions>` as the last cell of a row renders a ⋯ menu — put `<atom:menu.item>`s inside. It stops row-click propagation, so it works inside a clickable `<atom:table.row>`. Delete items use the confirm pattern (`type="delete"` or `<atom:confirm.trigger>`).
- **Loading:** built in — pagination/sort show a dim overlay (rows stay put); search shows a spinner in the search box (rows stay). For a lazy/deferred table, add the `skeleton` prop (or `:skeleton="N"`) to `<atom:table>` to show placeholder rows on first load until the data resolves.
- **Lists (V4): `frameless`, `numeric`, `toolbar`, `views`.** A list is ONE panel you draw (`rounded-panel border bg-surface overflow-hidden`) holding `<atom:table.views>`, then a `<atom:table frameless>` whose `header` slot holds `<atom:table.toolbar>`; the table draws the hairline strip around the toolbar and swaps it for the `checked` bulk bar while rows are ticked. A table with no panel around it stays framed. A wide table scrolls sideways inside its panel, never the page. The toolbar takes the search and filters as its default slot and an `end` slot (an export, `trashed`); outside a table pass `strip`. **The `header` slot is an Alpine template: text the server renders into it is not refreshed by a re-render, so a count or total goes below the table.** `<atom:table.views wire:model.live="filters.view" :current="$filters['view']" :counts="fn ($view) => $this->count($view)">` with `<atom:table.views.item value="all" label="All" />` rows is saved views: the property holds the chosen `value` (so `WithUrlFilters` can carry it: declare `'view' => 'all'`, validate it in `isUrlFilterValid()`, never `#[Locked]`), the HOST supplies the counts (array by value, or a closure; atom never counts, and a closure runs per item per render), an item with an `href` is a link. Money is `<atom:table.column numeric>` and `<atom:table.cell numeric>` (right-aligned, tabular), the currency named once in the header (`Amount (RM)`); an identifier is `<span class="font-id">`. Cells have a floor height and 9px of padding, so a two-line cell (a name, a 12px line) grows instead of touching its borders; a header cell is a 12px muted label, not uppercase.
- **Long values, wrapping and attributes:** cells and headers are nowrap by default, so one long value grows its column and can push the table past its box (the box then scrolls sideways). Put `truncate` on that `<atom:table.cell truncate>` and it takes the room left over and ends in an ellipsis instead; do **not** reach for `w-full` on the table, which squeezes a wide table's nowrap cells below their text. To let a cell or header wrap, pass `class="whitespace-normal"` (a responsive `md:whitespace-normal` works too) — the nowrap default is zero-specificity and yields to any `whitespace-*` you pass. `<atom:table>` itself now prints what you pass (`id`, `class="mt-8"`, `x-show`, a stray attribute shows up as a DOM attribute), so a margin on it applies. `<atom:caption>` prints its attributes too (`id`, `x-show`, `class="mt-2"`); to recolour it or change `text-sm`, use the `!` prefix, because the default classes and yours tie in compiled order.
- **Filters in the URL (`WithUrlFilters`, atom ≥ 3.36):** opt-in, so a user who opens a record and comes back through the breadcrumb or Back keeps their filters (`?search=ap&status[0]=fresh&region=SG`). `use WithPagination, WithUrlFilters;` (plus `WithTable` for a table) and declare the keys once: `protected function urlFilters(): array { return ['search' => '', 'status' => [], 'region' => 'MY']; }` (key => default). Optional: `urlFiltersExcept(): array` (internal keys you keep in `$filters`, such as a tenant: never in the URL, never settable by the browser), `isUrlFilterValid(string $key, mixed $value): bool` (app checks: a date that parses, a status that exists), `urlFiltersPrefix()` (a page with two listings), `urlFiltersProperty()`. Keys are plain identifiers (no dot, no `col:operator`; use a scope for an operator) and the component throws at mount otherwise. A key that collides with `page` (on a component that uses `WithPagination`), a `#[Url]` property, your `queryString`, or a paginator page name (`paginate(10, pageName: 'p')`, checked at the end of the initial render only; `WithoutUrlPagination` changes nothing here) throws too. **The declaration is an allow-list on every path:** a URL value is dropped silently at mount (an undeclared key, a wrong shape, or a value `isUrlFilterValid()` refuses: it falls back to the default and the listing opens unfiltered), and a browser `$wire.set()` is refused with a 403 at every path shape (`filters`, `filters.x`, `filters.x.0`; undeclared keys, internal keys, a wrong shape, a nested array, a host-refused value). It covers named-scope keys and plain columns alike. Strings must be valid UTF-8 and at most `urlFilterMaxLength()` bytes (1000), lists at most `urlFilterMaxItems()` (200), on both paths. A throw from `isUrlFilterValid()` counts as a refusal and is logged (a warning without the message or value, once an hour per component, key and exception class). **Your `mount()` sees the raw, unsanitised URL values (the trait cleans afterwards): do not read `$this->filters` there; validate in `isUrlFilterValid()`.** A whole replacement that omits a declared key gets its default. It is the reason the property must not be `#[Locked]`. It does **not** replace `filter(Arr::only(...))` for a component that does not use the trait, and a declared scope still gets whatever shape-valid value arrives. Rules: **a multi-select default must be `[]`** (Livewire merges lists by index; a non-empty list default throws). **A non-null default can't be cleared by `null`:** `null` is not written, so a reload brings the default back; use `''` for "All" (written as `?region=`). Declare a static default once in `urlFilters()` and keep the `public array $filters` property in step (a key already in the property is never overwritten); for a default computed in `mount()`, declare `null` and fill it with `??=`, not `blank()`, because the URL is read *before* your `mount()` and `blank()` takes an explicit "All" for "no choice". **The breadcrumb needs `@persist('breadcrumbs')` around `<atom:breadcrumbs />`**, or the trail restarts on every navigation and the crumb or Back returns to the bare URL. Write list links with indexed keys (`status[0]=a`); `status[]=a` is rewritten by the browser. Sort is not persisted.
- **Selection & bulk actions:** add `checkbox` to a header `<atom:table.column checkbox>` and `:checkbox="$item->id"` to a row `<atom:table.cell :checkbox="$item->id">`. Selected ids accumulate in `$_table.checkboxes` **across pages**, and the header checkbox selects/deselects the current page. Put bulk-action buttons in `<x-slot:checked>` — that bar replaces the header while a selection exists (shows the count). **Cross-page "Select all N":** add `:select-all` to `<atom:table>` and expose a `public function tableQuery()` returning the full scoped + filtered query (then `#[Computed] items() => $this->tableQuery()->toTable()`). A "Select all N" button appears in the bar; clicking it sets a `$_table.select_all` flag (no id list — scales to any size). Bulk actions read `$this->tableSelection()` (the trait helper): it returns the whole `tableQuery()` when select-all is on, else `->whereKey($_table.checkboxes)` — so `$this->tableSelection()->delete()` works for both. Individual/page toggles exit select-all mode. Changing a filter, running a search, or toggling the trashed view **clears the selection** (the checked rows may no longer be visible) — so build bulk actions against the currently-visible result set. A custom (non-atom) filter control can opt into the same clear by dispatching the event itself: `x-on:input="$dispatch('table-filter:changed')"`.
- **Selection that survives a filter (`:sticky-selection`):** for the "build one batch across several searches" flow — tick 2, search, tick 1 more, act on all 3. Add `:sticky-selection` to `<atom:table>`; a filter or search change then keeps `$_table.checkboxes` and only drops `select_all` (that flag means "everything matching *this* query", so it can't survive the query changing). A persistent "Clear selection" appears in the checked bar, since part of the selection is off-screen, and the `header` slot keeps rendering instead of being swapped out for that bar (the two stack) — a table that hides its own search box the moment you tick a row can't support the flow. The trashed toggle still clears everything — live and soft-deleted rows aren't one result set. **Required with this prop:** override `tableSelectionQuery()` to return the scoped-but-*unfiltered* base, or ids ticked under an earlier filter get silently intersected away by the current one (the bar says 3 selected, the delete hits 1):
```php
public function tableSelectionQuery() { return Invoice::query(); }              // scoped, no filters
public function tableQuery() { return $this->tableSelectionQuery()->filter($this->filters); }
```
It defaults to `tableQuery()`, so a table without the prop behaves exactly as before. Don't be tempted to default it to a bare `newQuery()` on the model — that only stays tenant-safe where scoping is a global scope. **Also wire the "Show selected" toggle** the bar carries: render rows from `tableRowsQuery()` (`#[Computed] items() => $this->tableRowsQuery()->toTable()`), which returns the selection while the toggle is on and the filtered list otherwise, so the user can review and prune a batch a search has hidden. It's a filter *to* the selection, not a union with it — the search still returns only what matches, so a big batch never floods a result set.
@endverbatim

### Navigation & layout shell

@verbatim
- **App shell:** two shells, `<atom:layouts.sidebar>` (sidebar + 48px top bar + main) and `<atom:layouts.header>` (52px top nav + centred main). They take the **same props and slots**, so swapping one for the other changes only the tag. Fill the slots — don't rebuild the chrome: `<x-slot:brand>` (logo/name), `<x-slot:context>` (workspace switcher), `<x-slot:search>`, `<x-slot:nav>` (main nav), `<x-slot:navfoot>`, `<x-slot:crumbs>`, `<x-slot:actions>`, `<x-slot:dropdown>` (user menu **items only**: the shell already wraps them in `<atom:menu>`, so your own `atom:menu` double-wraps; the default profile card is the trigger row at the foot of the sidebar from `lg` up and the head of the menu elsewhere; its initials use the User model's public string-returning `initials()` if it has one, else the first letters of its `name`) or `<x-slot:profile>` (replaces that card), `<x-slot:footer>`. The page body goes in the default slot. `<main>` is **unpadded**: padding belongs to the page, not the layout, so don't add it to `<main>`.
- **Which shell:** the sidebar for many modules (about seven or more top-level sections), the top nav for about six or fewer, or a role that can open only one or two. Atom never switches on its own (it renders the nav before it knows where it goes), so choose in your own app layout, per user: `<x-dynamic-component :component="$user->modules()->count() <= 2 ? 'atom::layouts.header' : 'atom::layouts.sidebar'" :title="$title">` with the same `<x-slot:nav>` inside. The choice is per page load; a role does not change mid-session, so `wire:navigate` never crosses shells. Below `lg` (1024px) the top nav becomes a menu whose first two rows are the `context` and `search` slots; from `lg` to `xl` (1280px) `search` is an icon button that opens a popover, from `xl` it is inline. From `lg` up, nav items that do not fit the bar move into a trailing "More" dropdown on their own (long labels and counts are fine; do not shorten them to fit). A `profile` slot needs a `dropdown` to be a menu: alone it is plain content. `crumbs` sit in the sidebar's top bar but under the top nav's bar.
- **Density:** `layouts.sidebar` and `layouts.header` are compact (28px controls and nav rows, 13px text); `layouts.auth` and a bare `<atom:html>` are comfortable. Pass `density="comfortable"` to change a page; a touch device gets taller controls either way.
- **Shell nav:** build the `nav` slot with `<atom:navlist>` — never hand-roll `<a>` tags. Items are `<atom:navlist.item icon="..." :href="route('...')" wire:navigate>Label</atom:navlist.item>`; current-route highlight is automatic (override with `:current="request()->routeIs('x.*')"`). Group with `<atom:navlist.group heading="Section">`; add `expandable` for a collapsible group, and `persist-key="nav.purchase"` to have that group remember whether it's collapsed across page loads (localStorage, namespaced under `atom:navlist-group:`; the key must be stable across pages, not derived from the route). With a key, a stored value beats the `expanded` prop — `expanded` is only the starting state for a group the user has never touched. Trailing count/badge via `count="3"` or a `<x-slot:badge color="amber">NEW</x-slot:badge>`. The same markup works in both shells: in `layouts.header` the items are bar items (an icon beside a label is left out, a count is a pill) and a `navlist.group` becomes a dropdown, so never write a second nav for the top nav. The header's priority-plus ("More") measures the first `<atom:navlist>` in the `nav` slot only, so use one navlist there. Items carry `aria-current="page"` when current.
- **Breadcrumbs:** add a `breadcrumbs(Breadcrumbs $b)` method to the Livewire component (needs `WithBreadcrumbs`) and drop `<atom:breadcrumbs />` in the page, wrapped in `@persist('breadcrumbs')` — the trail builds itself from navigation, and only survives `wire:navigate` when persisted (otherwise each page restarts it, and a crumb or Back loses the query string the user left with); a single crumb renders as the page heading.
- **Tabs:** `<atom:tabs :tabs="[...]" wire:model="tab" />` (each tab `['label' => ..., 'value' => ..., 'icon' => ...]`), or compose `<atom:tabs.item>` children. `variant="button"` for the pill style.
- **Links:** `<atom:link :href="..." />` for inline prose links (the accent ink, a solid underline; 4.5:1 on a card or in a callout). Nav/actions use `<atom:navlist.item>` / `<atom:button>`, not link.
- **A URL a user typed is checked, not trusted.** `<atom:link>`, `<atom:button>`, `<atom:tabs.item>`, `<atom:menu.item>`, `<atom:list.item>`, `<atom:command.item>`, `<atom:navlist.item>`, `<atom:table.row>`, the breadcrumbs trail, toast `url`/`navigate`, the lightbox download and the `cta` button in `atom()->mail()` only follow `http`, `https`, `mailto`, `tel`, `sms` or a scheme-less URL; anything else (`javascript:`, `data:`, `vbscript:`, an entity- or tab-obfuscated one) renders with no `href` and does nothing on click, so a profile "website" or a CMS link can't run script. The one thing to know: a relative path whose **first segment contains a colon** (`foo:bar`) now counts as a scheme and is dropped. Write it `./foo:bar`. Any markup of your own that puts a user-supplied URL in an `href`, `formaction`, `window.open()` or `location` must go through `safe_url($url)` (PHP, returns the URL or `null`) or `atom.safeUrl(url)` (JS) first; `{{ }}` escaping does not stop `javascript:`. That check is about the scheme only: `//evil.com` is allowed, so a redirect to a user-supplied URL must also check the host.
- **Heading levels:** `<atom:heading>` is a `<div>` unless you pass `level` — most app headings (card titles, stat labels) are visual, not structural. `size` and `level` are independent. `<atom:layouts.sidebar>` already emits the page's `<h1>` from its `title` prop (visually hidden — the visible title comes from the breadcrumbs, a nav landmark with no heading element), so **never add your own `<h1>` in the page body**; start nested sections at `level="2"`. A page that opens with `<atom:page.header>` has its own visible `<h1>` (level 1), which hides the layout's fallback, so there is still exactly one.
@endverbatim

### Patterns

@verbatim
Whole screens built from Atom's components live as live pages in the docs (`/atom/docs`, local environment, under **Patterns**). The Blade file is the example: read it, copy it, change the data.

- **App shell (sidebar)** and **App shell (top nav):** `vendor/jiannius/atom/resources/views/docs/patterns/app-shell-sidebar.blade.php` and `app-shell-header.blade.php`. Both include **one** nav partial, `docs/patterns/nav.blade.php` (plain items, a count, a `navlist.group`, an expandable group), which is the swap shown in code: only the layout tag differs. They also show every slot filled: brand, workspace switcher, search, crumbs, actions and the account menu.
- Start a new app from the shell that fits the number of sections (see **Which shell**), keep the nav in its own partial so a second shell can reuse it, and put page padding on the page.
- **List page, document page, form page and slide-over form:** `vendor/jiannius/atom/resources/views/docs/patterns/list-page.blade.php` (page header, totals strip, then one panel of saved views, toolbar and a frameless table; `?view=unpaid|overdue|paid|draft`), `document-page.blade.php` (an invoice as unthemed paper with lines and totals, a rail with the balance, details and a timeline; `?status=overdue|draft|paid`), `form-page.blade.php` (`<atom:form.section>`s and the save bar in a `width="narrow"` page; `?layout=stacked`) and `slide-over-form.blade.php` (stacked sections and the pinned bar in a slide-over; `?open=1`). Same rules as below: read the file, copy the structure.
- **Record page:** `vendor/jiannius/atom/resources/views/docs/patterns/record-page.blade.php`: one record (a work order) with `<atom:page>`, `<atom:page.header>` (crumbs, title, status, facts, actions, overflow menu, primary), `<atom:stat.group>`, `<atom:page.tabs>` and the three empty states. Open it as `?status=draft|in-progress|blocked|done` to see the primary action change.

Rules the patterns follow (apply them to any new screen):
- **Page:** one `<atom:page>` pads the page (the shell's `<main>` never does); `<atom:page.header>` first, then stats, `<atom:page.tabs>` and content as siblings. Crumbs: the header's `crumbs` prop or the shell's `crumbs` slot, not both. The prop is static; only `<atom:breadcrumbs />` in `@persist('breadcrumbs')` keeps the where-you-came-from trail (and a listing's filters) and draws the component's `breadcrumbs()`, so use that for listings people filter and return to. A list screen with no record uses `page.header` too (title, facts, `primary` = "New ...").
- **One primary action per record status, and it is for the current status.** The `primary` slot holds exactly one `variant="primary"` button whose label follows the status (Draft: Schedule; In progress: Mark as done; Done: Create invoice). Secondary actions are plain buttons in `actions`; rare or destructive ones go in `menu`. Never two primaries, never a primary that does not apply to the current status.
- **Status tones:** neutral for draft or not started, info for in progress or scheduled, success for done, warning for waiting, blocked or needs attention, danger for failed or overdue, accent for what is yours to act on. `running` only while something is actually running. Always a word, never a colour alone.
- **Stats:** a strip of 3 to 5 under the header; label, value, small currency `prefix`, `breakdown`. A stat that is also a filter gets an `href`. Money is tabular (the stat does it; in a table use `tabular-nums`, and `align="right"`); identifiers (an order number, a reference) are monospace: `font-id`.
- **Lists:** header, a totals strip, then one panel (views, toolbar, frameless table, a foot with the count and total). One primary: the header's "New ...". A status per row is `<atom:status>`: overdue is danger, a part payment info, waiting neutral, done success. Money is `numeric` with the currency in the header, identifiers `font-id`. On a phone the table scrolls inside the panel and the toolbar's search takes the row.
- **Documents and forms:** a document (an invoice) is a printable page: keep the paper's own colours in both themes, 500 and 600 weights, tabular money on one right edge, and the history as `<atom:timeline>` beside or under it. A long form is `<atom:page width="narrow">` and one `<atom:form>` of `<atom:form.section>`s ending in `<atom:form.actions bar>`; a short create or edit form opens as a slide-over (`form.modal cols="1"`, stacked sections). The submit is the one primary.
- **Timelines:** one component, three kinds. `<atom:timeline>` is a record's activity (dot in a tone, what happened, who and when, a folded `detail`); `kind="step"` is a numbered spine for a process (`state` done, current, pending or failed; the current and failed ones start open); `kind="message"` is a two-speaker conversation (yours `side="end"`, 14px, a `composer` slot last). The host orders the items, and **an item rendered in a `@foreach` needs a `wire:key`** (`<atom:timeline.item wire:key="event-{{ $event->id }}" ...>`): its folded state is Alpine's, so a keyless re-render that adds or reorders items hands one item's state to another. A `DateTimeInterface` `time` shows in the viewer's timezone. Titles, actors and bodies print as text and are not translated; markup in a slot is yours to escape.
- **Empty states, three kinds:** `first-use` (nothing created yet: say what it is for, the next step creates the first one), `no-results` (a search or filter found nothing: say so, the next step clears or widens it), `error` (it failed to load: the next step is "Try again"). Every one has a next step, as a plain button (the screen's one primary stays in the header); an empty table is never a blank box.
- **Density and text:** the shells are compact (13px body, 28px controls), a bare page or auth is comfortable; long text and editors use `text-base` (14px) for reading. Set the brand as `--color-accent` once in `@theme`; do not hand-pick foreground or hover colours.
- **Controls (restyled in V4-3a):** every text control (input of any type, textarea, the three selects, date and time pickers) is the control token high (28px compact, 32 comfortable, 40 on a touch device), 13px text, a `border-control-border` box (a hairline in light, 3:1 grey in dark) and no shadow; the keyboard ring is a 2px `:focus-visible` outline, hugging a field's border, never a `:focus` one. A checkbox, radio and toggle are found by their boundary alone, so they are stroked `fg-subtle` and checked in `accent-ink`. Buttons are three tiers: `variant="primary"` (the solid accent, one per view), the default (bordered), `ghost`; `link` is text; `danger`, `warning`, `success` and `info` are solid tone fills, and `inverted` is the tone's tint (what `type="delete"` is). `type="submit"` and `"delete"` add **no icon**: pass `icon="check"` or `icon="delete"` if you want one. `size` `sm` and `xs` sit 4px and 8px under the control token, `md` and `lg` over: header and form buttons are the default size. A field's invalid state is `invalid` or an `error` (the danger tone), disabled is sunken. Never give a control a `shadow-*`, a `focus:ring-*` or a `zinc-*` colour.
- **Data display (restyled in V4-3b):** a panel, a rail box or a settings group is `<atom:card>` (fill plus a hairline, no shadow), headed by a 12px muted title; label and value pairs are `<atom:dd.group>`; what the reader must act on is one `<atom:callout>` under the header in the status's tone (a blocked job: warning), never a hand-built box. A tag or a count is `<atom:badge>`, a state is `<atom:status>`, and a colour name means a tone (see Badges). A row's person is a `size="xs"` avatar and a name; a table's checkbox, chips, bulk bar and pager read the same tokens as the controls. Money in a card or a stat is `tabular-nums`. Read `docs/patterns/document-page.blade.php` (the rail) and `record-page.blade.php` (`?status=blocked`) for them in a page.
- **Overlays and navigation (restyled in V4-3c):** a modal, a menu panel, a toast, the command palette and the lightbox's file panel are one raised layer: `bg-surface-raised`, a `border-strong` hairline and the single `shadow-pop` token; nothing else casts a shadow, so never give a tab strip, breadcrumb or accordion a `shadow-*`. A modal is 12px radius and 24px padding, its close button is the last tab stop (the focus lands on its first field), a slide-over only changes edge. A menu item is the control token high with a 16px icon; `variant="danger"` and `"warning"` read the tone ink. A toast is a white card with a 16px tone icon, not a dark pill. A tooltip is the inverse (`bg-fg text-canvas`) at 12px. `<atom:tabs>` is a line by default and a segmented control with `variant="button"`; the current tab is the foreground, the rest `fg-muted`; the page you are on in `<atom:breadcrumbs>` is the foreground. An icon in a prompt or toast takes the tone token (`text-danger-fg`, never `text-red-500`).
- **Text, editor, calendar and chart (restyled in V4-3d):** headings are the type scale at weight 590 in `text-fg` and a subheading or caption is `text-fg-muted`; a label is 13px at weight 510, an error is `text-danger-fg`, a link is `text-accent-ink` and needs no colour class (never `text-sky-600`). Editor and `<atom:tiptap.content>` text reads at the base step, 14px, with a field frame, a hairline toolbar and no italic quote. A calendar's `color` names map onto tones (`orange` and `yellow` are both warning), a chart's `color` is a tone or a hex and defaults to the accent, and a chart's height goes on a wrapper, not on the tag. Docs and demos use the same tokens: no `zinc-*`.
- A `page.header` in a modal or a slide-over is for a form only (`level="2" size="sm"`); a whole record is a page, never a slide-over.
@endverbatim

### Toast / Alert / Confirm

From PHP, `$this->toast(...)` / `$this->alert(...)` / `$this->confirm(...)` (or `app('atom')->...()` outside Livewire). All translate `heading`, `subheading`, `message` through `t()`.

- `toast`: `message`, `variant` (`success|danger|warning|...`), `heading`, `subheading`, `html`, `position`, `delay`, `navigate`, `url`.
- `alert`: `variant`, `heading`, `subheading`, `message`, `html`, `button`, `onDismissed`.
- `confirm`: `variant`, `heading`, `subheading`, `message`, `html`, `buttonConfirm`, `buttonCancel`, `password`, `passphrase`, `reason` (with `reasonLabel`/`reasonPlaceholder`), `onAccepted`, `onRejected`.

@verbatim
For confirm-before-action in Blade, use `<atom:confirm.trigger>`:
@endverbatim

@verbatim
<code-snippet name="Confirm trigger" lang="blade">
<atom:confirm.trigger
    heading="Delete Conversation"
    subheading="Are you sure?"
    variant="danger"
    x-on:confirmed="$wire.delete({{ js($row->id) }})">
    <atom:button icon="delete" variant="ghost" size="xs" />
</atom:confirm.trigger>
</code-snippet>
@endverbatim

@verbatim
`<atom:button type="delete">` auto-wires a danger confirm dialog and dispatches `confirmed` → `$wire.delete()` by default. Override by setting your own `wire:click` or `x-on:click`.
@endverbatim

### Dropdown & menu

@verbatim
- `<atom:dropdown>` wraps a trigger and an `<atom:menu popover>`. The trigger is the first element outside the menu carrying `data-atom-dropdown-trigger` (one inside the menu, such as a date-picker's input, belongs to that component and is ignored), else the first `<button>` outside the menu, else the first child that is not the menu (so a non-button trigger such as `<atom:link>` works, and the buttons inside the menu are never mistaken for it). Put `data-atom-dropdown-trigger` on an element outside the menu when it should toggle the menu and is not the first button or child (e.g. the second button of a split button). Clicking the trigger toggles the menu via the native popover API, positioned with Floating UI. Props: `position` (`top`/`bottom`/`left`/`right`), `align` (`start`/`center`/`end`), and `locked` (keep the menu open when an item is clicked — the default closes on inside-click).
- `<atom:menu>` is the surface — pass `popover` when nesting inside `<atom:dropdown>`, omit it to render inline.
- `<atom:menu.item>` takes `icon`, `iconSuffix`, `badge`/`badgeColor`, `href` (renders an `<a>`, plus `newtab`/`target`), and `variant` (`default`/`warning`/`danger`/`delete`/`remove`). `variant="delete"` auto-wires a danger confirm → `$wire.delete()` (override with your own `wire:click`/`x-on:click`; pass `phrase` to require a typed confirmation). `remove` uses the same red styling + delete icon but no auto-confirm.
- Accessibility is automatic: the trigger gets `aria-haspopup`/`aria-expanded`, the surface is `role="menu"`, items are `role="menuitem"`.
@endverbatim

### Tooltip

@verbatim
`<atom:tooltip content="Save" position="top" align="center" kbd="⌘S" :interactive="false">` — wraps a single trigger element as its slot child. `content` is auto-translated. Shows on hover **and** keyboard focus, and links the trigger via `aria-describedby`. Set `interactive` when the content has links/buttons (the tooltip stays open while the pointer is over it). For click-to-open panels use `<atom:dropdown>`, not a tooltip.
@endverbatim

### Status & display primitives

Reach for these instead of hand-rolling coloured pills, notice boxes, or empty states.

@verbatim
- **Badges:** `<atom:badge status="$order->status" />` derives colour + label from an enum (or any `['color' => ..., 'label' => ...]`); or pass `color` + `label` directly. `color` is a tone (`neutral` default, `info`, `success`, `warning`, `danger`, `accent`), `black` (solid), a `#hex` (its tint and ink are read from it), or a colour name that maps to a tone: blue/sky/cyan/indigo info, green/emerald/teal/lime success, yellow/amber/orange warning, red/rose danger, purple/violet/fuchsia/pink accent, gray/zinc/slate/stone neutral (as is any unknown name). Borderless pill, `size` `xs` (18px)/`default` (22)/`lg` (26). Stack many with `<atom:badge.group max="3">`. A state is `<atom:status>`, not a badge.
- **Notice boxes:** `<atom:callout variant="info" heading="..." content="..." closeable />` — `variant` is `info`/`success`/`warning`/`danger` (none is neutral); the tint, hairline, icon and heading follow it, the text stays the foreground. Never build your own coloured alert `<div>`.
- **Panels:** `<atom:card>` is a fill, a hairline and 16px of padding (`subtle` is the sunken fill, `inset` no padding, `divided` rules the child divs, a `cover` slot bleeds); never `rounded-panel border bg-surface p-4` by hand.
- **Status:** `<atom:status tone="success" label="Done" />`: a dot plus a word, `tone` is `neutral` (default), `info`, `success`, `warning`, `danger` or `accent`; `running` pulses the dot (still under reduced motion). The word is always printed, so colour is never the only signal; use it where a state is a word, and `<atom:badge>` for tags and counts.
- **Stats:** `<atom:stat.group>` around `<atom:stat label="Parts cost" prefix="RM" value="1,280.00" delta="+6%" inverse breakdown="7 parts" />`. `prefix` is a small currency, `delta` reads up or down from its sign (`inverse` when down is the good news), `href` makes it a link (a filter; scheme-checked), `tone="danger"` tints the value. Values are tabular and sized by content: never give a stat a height.
- **Empty states:** `<atom:empty kind="first-use|no-results|error" heading="No invoices" subheading="..." icon="inbox" />` with the next step in `<x-slot:action>`; `subtle` for a one-line box, `size="sm"` for an inline row (neither animates).
- **People:** `<atom:avatar name="Jane" :src="$url" size="sm" />` (falls back to initials on the neutral tone; 24, 28, 34, 48, 64px for `xs`, `sm`, default, `lg`, `xl`; `:square="false"` for a circle; `<atom:avatar.group max="4">` for stacks); `<atom:profile :name="..." :email="..." :avatar="..." />` for an avatar + name/email chip (defaults to the authed user). In a table, a person is a `size="xs"` avatar and their name.
- **Description lists:** `<atom:dd.group cols="2"><atom:dd label="Email">{{ $user->email }}</atom:dd></atom:dd.group>` — a muted label and a foreground value; empty values show a `--` filler. Never a hand-built `<dl>` grid.
- **Progress and pager:** `<atom:progress :value="60" variant="success" size="sm" label />` (6px track, 4 at `sm`; the accent ink by default) and `<atom:pagination :paginator="$rows" />` for a paginator outside a table (a table paginates itself). Both use tabular figures.
- **Share buttons:** `<atom:sharer :url="$url" :title="$title" />` needs sharer.js, which atom does not bundle: load it once on the page (`<script src="https://cdn.jsdelivr.net/npm/sharer.js@0.5.4/sharer.js"></script>`, docs at https://ellisonleao.github.io/sharer.js) or the share buttons do nothing (copy-link still works). The default `sites` (`facebook`, `twitter-x`, `linkedin`, `whatsapp`, `telegram`, `email`) were checked against sharer.js 0.5.4; atom sends `twitter-x` to it as `x`, and a site you add needs an atom icon of the same name. Pass raw text for `title`/`url`; atom escapes them.
- **Loading placeholders:** `<atom:skeleton />` (paragraph) and `<atom:placeholder-bar size="60%x12" />` (`WIDTHxHEIGHT`, width may be `%`); they pulse only without a reduced-motion preference. Tables render their own loading skeleton, so you rarely place these by hand.
@endverbatim

### Select with options

@verbatim
- Static: `<atom:select :options="[['value' => 1, 'label' => 'One']]" wire:model="x" />`.
- Dynamic (database / large lists): create `app/Actions/GetOptions.php` `extends \Jiannius\Atom\Actions\GetOptions`, with a camelCase method matching the option `name`, and **declare that name** in `$auth` or `$guest`. Reach it via `<atom:select options="users" wire:model="user_id" />` — **the option set travels on `options` as a plain string**, and the component POSTs it to `/atom/action/GetOptions`. An array `:options` is a static list instead; `name` is the field name and never selects an option set. Three rules, all load-bearing:
  - **It must extend the package class** — that is what carries the `WebAction` contract the endpoint requires; a standalone `App\Actions\GetOptions` shadows the package's and the select 404s.
  - **Every option set must be declared.** `protected array $auth = ['users'];` needs a signed-in caller; `protected array $guest = ['brands'];` is readable by anyone. An undeclared name returns `[]` (the select renders empty) and logs a warning. Default to `$auth` — the endpoint is public and unauthenticated, so a `$guest` set hands every row it can return to a stranger. The package's own sets (countries, states, dialcodes, currencies, colors, postcodes) are always readable and are not re-declared.
  - **Scope the query anyway.** `$auth` only asks whether the caller is signed in; it does not restrict rows. Filter to the current org/tenant inside the method — a bare `User::all()` is a customer list any logged-in user can download.
- **Option text is escaped; an `html` key is not.** The listbox renders an option through `x-html`. Atom escapes everything it builds itself — `label`, `caption`, `color` — so a name a user typed can never inject markup. If a set returns its own `'html' => '...'` (custom layout), that key is **trusted**: you build it, so wrap **every** interpolated user or database field in `e()` (`'html' => '<b>'.e($doc->name).'</b>'`). An unescaped field in `html` is stored XSS. Prefer plain `label`/`caption` and let atom render them. Don't pre-escape a `label` yourself — it would be escaped twice.
- Enums: `<atom:select :options="ClientType::all()->map->option()->all()" />`.
@endverbatim

### Calendar

@verbatim
- Wiring: bind the anchor date with `wire:model` (a string, `Y-m-d` or `Y-m`; empty or unreadable is today), pass the items as a closure of the visible window, and handle the events on the tag:
  ```blade
  <atom:calendar
      wire:model="date"
      :views="['month', 'week', 'day']"
      selectable
      :items="fn ($from, $to) => $this->calendarItems($from, $to)"
      x-on:calendar-select="$event.detail.waitUntil($wire.create($event.detail.start, $event.detail.end, $event.detail.allDay).then((saved) => saved || Promise.reject()))"
      x-on:calendar-item-move="$event.detail.waitUntil($wire.reschedule($event.detail.id, $event.detail.start, $event.detail.end).then((saved) => saved || Promise.reject()))"
      x-on:calendar-item-resize="$event.detail.waitUntil($wire.reschedule($event.detail.id, $event.detail.start, $event.detail.end).then((saved) => saved || Promise.reject()))"
      x-on:calendar-item-click="$wire.edit($event.detail.id)" />
  ```
  With no `wire:model` the calendar is static: it shows `month` (or the current month) and has no previous or next. Props: `views`, `view`, `week-start` (default `monday`), `weekend`, `max` (rows before "+N more", default `3`), `time-format` (`g:i A`), `date-format`, `selectable` (off), `draggable` and `resizable` (on), `slot-duration`, `scroll-time`, `now-indicator`, `name`.
- **The items method must be `protected`.** The view is bound to the component, so a closure can call it; a public method would be callable from the browser. The calendar calls it on every render with `$from` and `$to`: the 6-week month grid around the anchor, whatever the view, as `Jiannius\Atom\Services\Carbon` instants in the app timezone. The browser never sends a range. Return an iterable of arrays.
- **Load by overlap, not by start.** An item belongs in the window when it starts before the window ends and ends after the window starts, with a null end handled: `->where('starts_at', '<=', $to)->where(fn ($q) => $q->where('ends_at', '>=', $from)->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', $from)))`. A query on the start alone hides an item that began before the month and runs through it. `>=` on the end is a harmless over-fetch that keeps an item with no length sitting on the window's first instant. A date column compares against the viewer's dates, `$from->copy()->timezone(\Jiannius\Atom\Services\Calendar::timezone())->toDateString()`.
- Item keys: `id` (int or string, unique), `title` (text only), `start` (required); `end`, `all_day`, `color`, `icon`, `danger`, `href`, `draggable`, `resizable` optional. A missing `id`, `title` or `start` throws. `start` and `end` take a `DateTimeInterface` or an ISO string with an offset (exact instants), a string with no offset (read in `config('app.timezone')`) or a bare `Y-m-d` (a date, never converted; `all_day` defaults to true). An all-day `end` is the last day, included; a timed `end` is exclusive; `null` is no end. `color` is `red`, `orange`, `yellow`, `green`, `blue`, `purple`, `black`, `zinc`, `gray`, a hex colour, a name added in `config('atom.calendar.colors')` (`['teal' => ['tint' => '...', 'accent' => '...', 'dot' => '...']]`, classes your Tailwind build must see), or an enum with `color()`; an item with none is zinc. `href` goes through `safe_url()` and an item with one is a link, so it does not fire `calendar-item-click`.
- **Everything the calendar says is in the viewer's timezone** (`Carbon::getLocalTimezone()`), as wall-clock strings (`2026-10-09T09:30`, `2026-10-09`). **Convert each one with `Jiannius\Atom\Services\Calendar::fromLocal($string)`**, which returns an application-timezone instant, daylight saving included, and throws on anything else. Never store the string as it is, and never hand Eloquent a Carbon in another zone: it writes the wall clock without converting. A date (`allDay: true`) is a date, not an instant.
- Events (bubbling, on the calendar's root): `calendar-select` `{ start, end, allDay, waitUntil }`, not cancelable: for days `end` is the last day, **included** (a one-day select has `start === end`), for a time `end` is **exclusive**. `calendar-item-move` `{ id, start, end, allDay, waitUntil }` and `calendar-item-resize` `{ id, start, end, waitUntil }` are cancelable: `preventDefault()` puts the item back. `end` can be `null` on a move of a timed item that has none. `calendar-item-click` `{ id }` is also the keyboard's way to an item, since move and resize are pointer-only: open your edit form from it.
- **Pass the promise of your save to `waitUntil()`**, while the event is being handled, and make it reject when the save did not happen: `$event.detail.waitUntil($wire.reschedule(...).then((saved) => saved || Promise.reject()))`, with `reschedule()` and `create()` returning `true` once they have saved. The item (or the selection's placeholder) stays where it was put while the promise is pending, goes back if it rejects, and a payload that arrives meanwhile takes over once it settles. **A failed `validate()` does not reject the promise**: Livewire catches it on the server and `$wire.method()` resolves with `null`, so without the `.then(...)` the item stays where it was dropped. A thrown exception, an `abort()` or a failed `authorize()` does reject it, but Livewire then opens its error modal; returning does not reject the promise by itself, so return `false` to refuse and `true` once saved (any truthy value counts as saved, and the return value is sent to the browser, so return `true`, not a model). Without `waitUntil()` the item stays where it was dropped until the payload changes, so a failed save leaves it wrong.
- **Do not put `#[Renderless]` on the handler** unless you have thought it through: a renderless response sends no payload, so a resolved item stays exactly where it was dropped even if your method changed the time, and a created item never appears. A normal action re-renders and the new payload is shown. A renderless method must still `return true`: one that returns nothing resolves `undefined`, the tag rejects it, and the item snaps back after a successful save. With this tag every save method it calls must return `true`.
- Define every action the tag calls: `create(string $start, string $end, bool $allDay): bool`, `reschedule(int $id, string $start, ?string $end): bool` (both return `true` once saved) and `edit(int $id)` (open your form there). They are Livewire actions, so they are browser-callable: authorise and scope them. On the tag, the old wrapper's `modes` and `periods` props, an `x-model`, and a listener (`x-on:` or `@`, any modifier) for `calendar-date-clicked`, `calendar-date-selected`, `calendar-event-clicked`, `calendar-event-dropped`, `calendar-event-resized`, `calendar-changed` or `calendar-mounted` throw; use `views`, `wire:model` and the events above. A `$dispatch('add-calendar-events', ...)` elsewhere cannot be detected: search for it. Two calendars bound to the same property of the same component need a `name` each.
@endverbatim

### Actions

PHP classes in `App\Actions\` invoked by name. Names are dot-paths that map to namespaces (e.g. `Reports.Generate` → `Reports\Generate`). Resolution order: `App\Actions\{Name}` then `Jiannius\Atom\Actions\{Name}`. The host app's class wins, enabling per-app overrides of package actions.

**From PHP, any action is callable.** `app('atom')->action('SyncContacts', $params)`, or `$this->action(...)` inside a Livewire component. Pass `method` in `$params` to invoke something other than `handle`. This is a server-side path only — the helper is a macro, so `$wire.action(...)` cannot reach it (since 3.25 a protected trait method, a macro in v4; before 3.25 it could, bypassing the `WebAction` gate entirely).

**From the browser, an action is callable only if it opts in.** `POST /atom/action/{Name}` is mounted publicly — unauthenticated, reachable by anyone who can load the app — so it runs an action only when the class implements `Jiannius\Atom\Contracts\WebAction`. Anything else answers 404, the same answer an unknown action gets. The endpoint always calls `handle()`; `method` is ignored over HTTP.

Default to NOT implementing `WebAction`. Add it only when the browser genuinely needs to call the action, and when it is there, assume the caller is a stranger: validate every param, and add `authorize()` unless the action is safe for the anonymous public.

@verbatim
<code-snippet name="Server-only action (the default)" lang="php">
<?php
namespace App\Actions;

class SyncContacts
{
    /**
     * Sync contacts with the upstream provider.
     */
    public function handle(array $params): array
    {
        return ['synced' => 42];
    }
}

// PHP only — atom.action('SyncContacts') from JS would 404.
app('atom')->action('SyncContacts', ['force' => true]);
</code-snippet>

<code-snippet name="Browser-callable action" lang="php">
<?php
namespace App\Actions\Customer;

use Jiannius\Atom\Contracts\WebAction;

class Search implements WebAction
{
    /**
     * Only signed-in staff may search customers.
     */
    public function authorize(array $params): bool
    {
        return auth()->check() && auth()->user()->isStaff();
    }

    /**
     * Search customers by name.
     */
    public function handle(array $params): array
    {
        return \App\Models\Customer::query()
            ->where('name', 'like', '%'.$params['q'].'%')
            ->take(10)
            ->get(['id', 'name'])
            ->toArray();
    }
}

// JS / Alpine
atom.action('Customer.Search', { q: 'jane' }).then(res => ...);
</code-snippet>
@endverbatim

Two things the endpoint does not do for you: it does not filter what you return (`handle()`'s return value is JSON-encoded straight to the caller, so return columns, not whole models), and it does not rate-limit.

### Enums

Mix `Jiannius\Atom\Traits\Enum` into every backed enum. Cases must be `FULL_UPPERCASE`:

@verbatim
<code-snippet name="Atom-backed enum" lang="php">
<?php
namespace App\Enums;

use Jiannius\Atom\Traits\Enum;

enum ClientType: string
{
    use Enum;

    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
}

// Use in selects
ClientType::all()->map->option()->all();
</code-snippet>
@endverbatim

The trait provides `all()`, `option()`, `label()`, and other helpers — read `vendor/jiannius/atom/src/Traits/Enum.php` for the full surface.

### Editor content

@verbatim
Rich text uses `<atom:tiptap>` (editor), `<atom:tiptap.chat>` (chat composer: enter-to-send, attachments), and `<atom:tiptap.content>` (server-side render of stored content). Bind with `wire:model` — the stored value is **Tiptap JSON**. Choose toolbar buttons with a preset (`toolbar="full|basic|minimal|none"`) or compose your own via a `<x-slot:toolbar>` of `<atom:tiptap.*>` buttons. Mentions: `mention="searchMethod"` (live `$wire` search, debounced) or `:mention="['Alice','Bob']"` (static).

Cast the storage column with `Jiannius\Atom\Casts\AsTiptapContent` — it stores Tiptap JSON and dual-reads legacy serialized-HTML, so existing rows keep rendering and migrate to JSON on next save. Images: the cast persists Livewire temporary uploads to `config('atom.editor.disk')` (falls back to the default filesystem disk), or define `tiptapStoreImage(string $tmpPath, string $key): string` on the model to control persistence. Display stored content with `<atom:tiptap.content :content="$model->body"/>`. Convert legacy HTML columns to JSON with `php artisan atom:tiptap-migrate` (switch the cast to `AsTiptapContent` first). `<atom:editor>`, `<atom:editor.chat>`, `<atom:editor.content>` remain as back-compat aliases.

**Chat HTML from the browser is untrusted.** `<atom:tiptap.chat>` hands the host raw **HTML** (an `input` event with `{ body, files }`), and any client can call `$wire.submit()` or set the bound property with its own string, so what arrives may hold script, event handlers or `javascript:` links. Clean it **before you store it** with `Jiannius\Atom\Tiptap\Content::sanitize($html)` (schema-only HTML), or print it through `<atom:tiptap.content :content="$body"/>` / `Content::render($body)`, which re-parse through the same schema. **Never** print it with `x-html`, `{!! $body !!}` or `->html()` straight from the column or the request. `sanitize()` is for HTML: `<atom:tiptap>` (the JSON editor, `AsTiptapContent`) stores JSON, which `<atom:tiptap.content>` already prints safely, so store that as JSON and do not pass it through `sanitize()` (it returns HTML and would drop YouTube embeds). Rows stored before you adopt `sanitize()` stay unclean until you render them through `<atom:tiptap.content>` or backfill them. `sanitize()` returns `''` for empty, refused (over 128 KB or 5000 tags or document nodes, or output that would have over 5000 tags, a `<pre>` block or count the parser can't survive, something that is not a Tiptap document, one nested over 200 levels, or tiptap-php's `MINIFYHTML` placeholder; long whitespace runs and unclosed `<pre>` tags are normalised, not refused, and so are a processing instruction (replaced by a space) and loose top-level text (wrapped in a paragraph)) and failed input alike; `Content::sanitizeRefuses($raw)` tells a refusal apart. `<atom:tiptap.content>` / `Content::render()` apply the same kind of refusal to stored content with looser limits (`config('atom.editor.render_max_bytes')`, default 2 MB, and `render_max_tags`, default 20000): the content renders empty and a `Log::warning` says why, so if stored content goes blank check the log and raise the limits in `config/atom.php`. It does not check a mention's `data-id` (client-chosen): look the id up server-side before acting on it.

```php
use Jiannius\Atom\Tiptap\Content;

public function submit(array $message): void
{
    $body = Content::sanitize($message['body'] ?? '');

    if ($body === '') {
        return;
    }

    $this->task->messages()->create(['body' => $body]);
}
```

**Upgrading to v3.6.0 (editor):** `atom.js` now loads as an ES module — if you include it via your own `<script>` tag instead of `<atom:html>`, add `type="module"`. Switch editor columns from `AsEditorContent` to `AsTiptapContent`, then run `php artisan atom:tiptap-migrate`. Run `npm run build` to pick up new Tailwind utilities. `<atom:editor>` keeps working as an alias for `<atom:tiptap>`.
@endverbatim

### Mail

`app('atom')->mail(...)` sends a markdown email using `atom::mail.generic` by default. Pass `content` + optional `cta` for the default template, or `view` / `markdown` to use a custom template with `with` data.

@verbatim
<code-snippet name="Send mail" lang="php">
app('atom')->mail(
    to: $user->email,
    subject: 'Welcome',
    content: 'Thanks for signing up.',
    cta: ['label' => 'Get started', 'url' => route('dashboard')],
    queue: true,           // or queue: 'emails' for a named queue
    // later: now()->addHour(),
    // track: true,
    // attachments: [storage_path('invoice.pdf')],
);
</code-snippet>
@endverbatim

### Broadcasting

Use the Atom broadcast helper instead of creating local broadcast event classes.

@verbatim
<code-snippet name="Broadcast send and listen" lang="php">
// Sending (model / job / service)
app('atom')->broadcast()
    ->name('agent-response-ready')
    ->private('agent.' . $conversationId)   // ->public() for public channels
    ->with($message->toArray())
    ->sendNow();                            // ->send() queues it
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Listen via Echo" lang="blade">
<div x-data="{
    init() {
        Echo.private('agent.{{ $conversation->id }}')
            .listen('.agent-response-ready', (payload) => {
                this.$wire.onAgentResponse(payload)
            })
    }
}"></div>
</code-snippet>
@endverbatim

Event name is prefixed with `.` when listening. Private channels require auth in `routes/channels.php`.

### Blade helpers

- `t($str, $count, $params)` — wraps `__()` / `trans_choice` and gracefully handles empty strings. Use throughout in place of `__()` for consistency with the package's components.
@verbatim
- `js($value)` — safe PHP-to-JS in Blade attributes: `wire:click="delete({{ js($row->id) }})"`.
@endverbatim
- `num($value)->currency('USD')` / `->filesize()` / `->format()` — Laravel Number helper shorthand. For a unit price pass `maxPrecision: N` to `currency()` (min 2, max N decimals, half-up) so `0.065` stays `0.065` instead of becoming `0.07`; rounding is half-up unless `roundingMode: 'half-even'` (or `half-down`).
- `carbon($value)` — produces `Jiannius\Atom\Services\Carbon` (immutable, with `pretty()` and `local()`). `now()` and model dates are Laravel's own unless the app calls `Date::use(\Jiannius\Atom\Services\Carbon::class)`, so call `->pretty()` / `->local()` through `carbon($date)` otherwise.
@verbatim
- `safe_url($url)` — makes a URL scheme-safe: it returns the URL unchanged if it is `http`, `https`, `mailto`, `tel`, `sms` or scheme-less, else `null`. Use it on any user-supplied URL before it reaches an `href` or `formaction`; `{{ }}` escaping does not stop `javascript:`. Its scope is the scheme only: `//evil.com` is allowed, so a redirect built from user input must also check the host (compare it with your own, or allow only a relative path).
@endverbatim

### Other helpers

- `app('atom')->sitemap()->push($urls, 'monthly')->response()` — generate XML sitemap responses.
- `app('atom')->asset()->version('atom.css')` — manifest-hashed `/atom/<file>` path for Atom's bundled CSS/JS.
- `app('atom')->breadcrumbs()->home(...)->push(...)->build()` — for non-Livewire breadcrumb construction.

### Recommended project conventions

These are the conventions Atom-using projects should adopt unless they have a reason not to:

- **Spacing scale.** Default to `6` (`space-y-6`, `p-6`, `gap-6`). Use `3` only when intentionally tighter.
@verbatim
- **Form columns.** Choose columns to keep the form from scrolling much — a single column should roughly fit its container without heavy scrolling. Wrap field groups in `<atom:form.grid cols="auto">` (a container query: 1 column in a narrow container, 2 once it is wide enough — never 2 columns below ~`max-w-2xl`; this is enforced by CSS, not viewport). Operationally: ~≤5 fields → 1 column; longer/scrolly → 2 columns, pairing related fields. For a single-group form, put `cols` on `<atom:form>`. Force a fixed layout with `cols="2"`/`cols="3"`. Never use bare `grid-cols-2` (it will not collapse on mobile).
- **Modal width.** Match width to the form: 1-col & ≤4 simple fields → `max-w-lg`; 1-col with more/wider fields → `max-w-xl`; 1-col dense/settings → `max-w-2xl`–`max-w-3xl`; 2-col → minimum `max-w-2xl`, scaling to `max-w-4xl` (~10 fields) and `max-w-5xl` (15+). Reserve `max-w-6xl`/`7xl` for builder/full-tool screens, not forms. `<atom:form.modal>` sets a sensible width from `cols` automatically.
- **Form footer.** Use `<atom:form.actions>`: Save on the left (`<atom:button type="submit">`, label "Save"), Delete on the right (`<atom:button type="delete" variant="ghost" color="danger">`). No Cancel button — modal dismiss handles it.
- **`<atom:form.modal>`.** Composes modal + form + footer (Save on the left; on the right the optional `actions` slot for anything that must stay visible while the form scrolls, e.g. a running total, then the optional `delete` slot at the far edge). Fields go in the default slot; width derives from `cols`. Like all `<atom:form>`, it wires `wire:submit="submit"` — the Livewire component must define a `submit()` method.
- **Checkboxes.** Multiple related checkboxes → always `<atom:checkbox.group>` (never loose stacked `<atom:checkbox>`). Default variant; use `variant="card"` only when each option needs its own description or icon.
- **Description lists (show pages).** Group label/value pairs in `<atom:dd.group>`; use `cols="2"` only for many fields on a wide page — same density logic as forms.
- **Section separation.** Prefer `<atom:separator>` over ad-hoc `<hr>` or border classes; separate logical field groups with a separator and a short title (e.g. "Address", "Registration & Tax").
- **Colour with role tokens, never `zinc-*` or a `dark:` pair.** `bg-surface`, `bg-surface-sunken`, `text-fg`, `text-fg-muted`, `text-fg-subtle`, `border-border`, `border-border-strong`, `bg-accent`, `text-accent-ink` and the tones (`bg-danger-bg text-danger-fg`) are one name for both themes, so there is no pair to keep in step. Tailwind v4 draws a border in `currentColor`; atom's base rule gives every element the `border` token's colour, so `border-t` and `divide-y` are right as written, and a `dark:`-only border colour is the old defect (a heavy black rule in light mode).
@endverbatim
- **Component method order** (top of class to bottom):
    1. Validation (`$rules`, `$messages`, `#[Rule]` properties)
    2. `mount()`
    3. `breadcrumbs()`
    4. `#[Computed]` properties
    5. Livewire lifecycle hooks (`updated*`, `hydrate`, …)
    6. Custom methods
- **PHPDoc.** Every public and private method has a one-line PHPDoc describing what it does or returns. Skip inline `//` comments unless the logic is non-obvious.
