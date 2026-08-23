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
@endverbatim

### Livewire components — `AtomComponent` trait

Livewire 4 single-file components are the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every single-file Livewire component class. It provides `WithPagination` + `WithFileUploads`, reserved state buckets (`$_breadcrumbs`, `$_table`, `$_editor`), and helper methods:

- `$this->modal($name = null)->show() / ->slide() / ->close()` — `$name` defaults to the current component name.
- `$this->toast(...)`, `$this->alert(...)`, `$this->confirm(...)` — dispatch UI events.
- `$this->action('Name', $params)` — invoke an Action class (see Actions).
- `$this->wirekey(...$args)` — stable `wire:key` based on args, or random ULID-based key when called with none.

Define a `breadcrumbs(Breadcrumbs $b)` method to populate `$_breadcrumbs` automatically on mount.

@verbatim
<code-snippet name="Single-file Livewire component with Atom" lang="php">
<?php
use Livewire\Component;
use Jiannius\Atom\Traits\AtomComponent;

new class extends Component {
    use AtomComponent;

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

**reCAPTCHA v3.** Add the `recaptcha` prop to protect a submit: `<atom:form wire:submit="create" recaptcha>` (or `recaptcha="signup"` to set the score action). It mints a token client-side, attaches it to the component, then runs the submit. In the Livewire method, verify it (the component must use the `AtomComponent` trait):
```php
public function create() {
    $this->verifyRecaptcha();   // throws a validation error on a bot; no-op if recaptcha is unconfigured
    // ...
}
```
Requires `config('services.recaptcha.site_key' / 'secret_key' / 'min_score')` and a built site (consumers run their Tailwind/Vite build). With no site key configured the form behaves as a normal `wire:submit` form.
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
`<atom:table :paginate="$this->rows">` with `x-slot:columns` / `x-slot:rows` is the data table. Drive sort, pagination and checkboxes through the `$_table` state (from `AtomComponent`) plus the `toTable($filters)` Eloquent builder macro on a `#[Computed]` method.

- **Search:** `<atom:table.search wire:model="filters.search" />` — the standard listing search (search icon, Enter to run). Don't hand-roll an input.
- **Filters:** wrap the filter controls in `<atom:table.filters>`; it auto-renders active-filter chips + a "Clear all". Use `<atom:select variant="filter">`, `<atom:date-picker variant="range">`, or custom selects inside it — each control needs a `wire:model` (that is the chip's key; without it no chip registers). Put overflow filters in `<x-slot:more>` — a "More filters" popover by default, or set `overflow="card"` for an expandable row.
- **Trashed:** add the `trashed` prop to `<atom:table>` to append an icon toggle at the end of the header bar — it drives `$_table.show_trashed` and `toTable()` applies `onlyTrashed()`. Bare `trashed` = archived preset (archive icon, "Show archived"); `trashed="voided"` = voided preset (trash icon, "Show voided"). `<atom:table.trashed :variant="..." />` is the standalone component for custom placement.
- **Row actions:** `<atom:table.actions>` as the last cell of a row renders a ⋯ menu — put `<atom:menu.item>`s inside. It stops row-click propagation, so it works inside a clickable `<atom:table.row>`. Delete items use the confirm pattern (`type="delete"` or `<atom:confirm.trigger>`).
- **Loading:** built in — pagination/sort show a dim overlay (rows stay put); search shows a spinner in the search box (rows stay). For a lazy/deferred table, add the `skeleton` prop (or `:skeleton="N"`) to `<atom:table>` to show placeholder rows on first load until the data resolves.
- **Selection & bulk actions:** add `checkbox` to a header `<atom:table.column checkbox>` and `:checkbox="$item->id"` to a row `<atom:table.cell :checkbox="$item->id">`. Selected ids accumulate in `$_table.checkboxes` **across pages**, and the header checkbox selects/deselects the current page. Put bulk-action buttons in `<x-slot:checked>` — that bar replaces the header while a selection exists (shows the count). **Cross-page "Select all N":** add `:select-all` to `<atom:table>` and expose a `public function tableQuery()` returning the full scoped + filtered query (then `#[Computed] items() => $this->tableQuery()->toTable()`). A "Select all N" button appears in the bar; clicking it sets a `$_table.select_all` flag (no id list — scales to any size). Bulk actions read `$this->tableSelection()` (the trait helper): it returns the whole `tableQuery()` when select-all is on, else `->whereKey($_table.checkboxes)` — so `$this->tableSelection()->delete()` works for both. Individual/page toggles exit select-all mode. Changing a filter, running a search, or toggling the trashed view **clears the selection** (the checked rows may no longer be visible) — so build bulk actions against the currently-visible result set. A custom (non-atom) filter control can opt into the same clear by dispatching the event itself: `x-on:input="$dispatch('table-filter:changed')"`.
@endverbatim

### Navigation & layout shell

@verbatim
- **App shell:** `<atom:layouts.sidebar>` renders the sidebar + header + main grid. Fill its slots — don't rebuild the chrome: `<x-slot:brand>` (logo/name), `<x-slot:nav>` (main nav), `<x-slot:navfoot>`, `<x-slot:dropdown>` (user menu items) or `<x-slot:profile>`, `<x-slot:footer>`. The page body goes in the default slot.
- **Sidebar nav:** build the `nav` slot with `<atom:navlist>` — never hand-roll `<a>` tags. Items are `<atom:navlist.item icon="..." :href="route('...')" wire:navigate>Label</atom:navlist.item>`; current-route highlight is automatic (override with `:current="request()->routeIs('x.*')"`). Group with `<atom:navlist.group heading="Section">`; add `expandable` for a collapsible group. Trailing count/badge via `count="3"` or a `<x-slot:badge color="amber">NEW</x-slot:badge>`.
- **Breadcrumbs:** add a `breadcrumbs(Breadcrumbs $b)` method to the Livewire component (see the AtomComponent section) and drop `<atom:breadcrumbs />` in the page — the trail builds itself from navigation; a single crumb renders as the page heading.
- **Tabs:** `<atom:tabs :tabs="[...]" wire:model="tab" />` (each tab `['label' => ..., 'value' => ..., 'icon' => ...]`), or compose `<atom:tabs.item>` children. `variant="button"` for the pill style.
- **Links:** `<atom:link :href="..." />` for inline prose links (dotted underline). Nav/actions use `<atom:navlist.item>` / `<atom:button>`, not link.
- **Heading levels:** `<atom:heading>` is a `<div>` unless you pass `level` — most app headings (card titles, stat labels) are visual, not structural. `size` and `level` are independent. `<atom:layouts.sidebar>` already emits the page's `<h1>` from its `title` prop (visually hidden — the visible title comes from the breadcrumbs, a nav landmark with no heading element), so **never add your own `<h1>` in the page body**; start nested sections at `level="2"`.
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
- `<atom:dropdown>` wraps a trigger and an `<atom:menu popover>`. The trigger is the first child (or a `[data-atom-dropdown-trigger]` / first `<button>`); clicking it toggles the menu via the native popover API, positioned with Floating UI. Props: `position` (`top`/`bottom`/`left`/`right`), `align` (`start`/`center`/`end`), and `locked` (keep the menu open when an item is clicked — the default closes on inside-click).
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
- **Status pills:** `<atom:badge status="$order->status" />` derives colour + label from an enum (or any `['color' => ..., 'label' => ...]`); or pass `color` (a named colour or `#hex`) + `label` directly. `size` is `xs`/`default`/`lg`. Stack many with `<atom:badge.group max="3">` (collapses the overflow to `+N`).
- **Notice boxes:** `<atom:callout variant="info" heading="..." content="..." closeable />` — `variant` is `info`/`success`/`warning`/`danger`; the icon + colours follow it. Never build your own coloured alert `<div>`.
- **Empty states:** `<atom:empty heading="No invoices" subheading="..." icon="inbox" />`; `subtle` for a one-line box, `size="sm"` for an inline row.
- **People:** `<atom:avatar name="Jane" :src="$url" size="sm" />` (falls back to initials; `<atom:avatar.group max="4">` for stacks); `<atom:profile :name="..." :email="..." :avatar="..." />` for an avatar + name/email chip (defaults to the authed user).
- **Description lists:** `<atom:dd.group cols="2"><atom:dd label="Email">{{ $user->email }}</atom:dd></atom:dd.group>` — empty values show a `--` filler.
- **Loading placeholders:** `<atom:skeleton />` (paragraph) and `<atom:placeholder-bar size="60%x12" />` (`WIDTHxHEIGHT`, width may be `%`). Tables render their own loading skeleton, so you rarely place these by hand.
@endverbatim

### Select with options

@verbatim
- Static: `<atom:select :options="[['value' => 1, 'label' => 'One']]" wire:model="x" />`.
- Dynamic (database / large lists): create `app/Actions/GetOptions.php` `extends \Jiannius\Atom\Actions\GetOptions`, with a camelCase method matching the option `name`, and **declare that name** in `$auth` or `$guest`. Reach it via `<atom:select name="users" />` (the select component will POST to `/atom/action/GetOptions` with the name). Three rules, all load-bearing:
  - **It must extend the package class** — that is what carries the `WebAction` contract the endpoint requires; a standalone `App\Actions\GetOptions` shadows the package's and the select 404s.
  - **Every option set must be declared.** `protected array $auth = ['users'];` needs a signed-in caller; `protected array $guest = ['brands'];` is readable by anyone. An undeclared name returns `[]` (the select renders empty) and logs a warning. Default to `$auth` — the endpoint is public and unauthenticated, so a `$guest` set hands every row it can return to a stranger. The package's own sets (countries, states, dialcodes, currencies, colors, postcodes) are always readable and are not re-declared.
  - **Scope the query anyway.** `$auth` only asks whether the caller is signed in; it does not restrict rows. Filter to the current org/tenant inside the method — a bare `User::all()` is a customer list any logged-in user can download.
- Enums: `<atom:select :options="ClientType::all()->map->option()->all()" />`.
@endverbatim

### Actions

PHP classes in `App\Actions\` invoked by name. Names are dot-paths that map to namespaces (e.g. `Reports.Generate` → `Reports\Generate`). Resolution order: `App\Actions\{Name}` then `Jiannius\Atom\Actions\{Name}`. The host app's class wins, enabling per-app overrides of package actions.

**From PHP, any action is callable.** `app('atom')->action('SyncContacts', $params)`, or `$this->action(...)` inside a Livewire component. Pass `method` in `$params` to invoke something other than `handle`.

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
- `num($value)->currency('USD')` / `->filesize()` / `->format()` — Laravel Number helper shorthand.
- `carbon($value)` — produces `Jiannius\Atom\Services\Carbon` (also installed globally via `Date::use()`).

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
- **`<atom:form.modal>`.** Composes modal + form + footer (Save + optional `delete` slot). Fields go in the default slot; width derives from `cols`. Like all `<atom:form>`, it wires `wire:submit="submit"` — the Livewire component must define a `submit()` method.
- **Checkboxes.** Multiple related checkboxes → always `<atom:checkbox.group>` (never loose stacked `<atom:checkbox>`). Default variant; use `variant="card"` only when each option needs its own description or icon.
- **Description lists (show pages).** Group label/value pairs in `<atom:dd.group>`; use `cols="2"` only for many fields on a wide page — same density logic as forms.
- **Section separation.** Prefer `<atom:separator>` over ad-hoc `<hr>` or border classes; separate logical field groups with a separator and a short title (e.g. "Address", "Registration & Tax").
@endverbatim
- **Component method order** (top of class to bottom):
    1. Validation (`$rules`, `$messages`, `#[Rule]` properties)
    2. `mount()`
    3. `breadcrumbs()`
    4. `#[Computed]` properties
    5. Livewire lifecycle hooks (`updated*`, `hydrate`, …)
    6. Custom methods
- **PHPDoc.** Every public and private method has a one-line PHPDoc describing what it does or returns. Skip inline `//` comments unless the logic is non-obvious.
