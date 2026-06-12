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
`<atom:form>` wires `wire:submit="submit"` and renders an automatic loading indicator. Use `<atom:input.*>`, `<atom:select>`, `<atom:textarea>`, `<atom:checkbox>`, `<atom:radio>`, `<atom:date-picker>`, `<atom:time-picker>`, `<atom:uploader>`. Submit with `<atom:button type="submit">`.
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
@endverbatim

### Toast / Alert / Confirm

From PHP, `$this->toast(...)` / `$this->alert(...)` / `$this->confirm(...)` (or `app('atom')->...()` outside Livewire). All translate `heading`, `subheading`, `message` through `t()`.

- `toast`: `message`, `variant` (`success|danger|warning|...`), `heading`, `subheading`, `position`, `delay`, `navigate`, `url`.
- `alert`: `variant`, `heading`, `subheading`, `message`, `button`, `onDismissed`.
- `confirm`: `variant`, `heading`, `subheading`, `message`, `buttonConfirm`, `buttonCancel`, `password`, `passphrase`, `onAccepted`, `onRejected`.

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

### Select with options

@verbatim
- Static: `<atom:select :options="[['value' => 1, 'label' => 'One']]" wire:model="x" />`.
- Dynamic (database / large lists): create `app/Actions/GetOptions.php` with a camelCase method matching the option `name`. The app-side class overrides the package's `Jiannius\Atom\Actions\GetOptions`. Reach it via `<atom:select name="users" />` (the select component will POST to `/atom/action/GetOptions` with the name).
- Enums: `<atom:select :options="ClientType::all()->map->option()->all()" />`.
@endverbatim

### Actions

`POST /atom/action/{Name}` is publicly mounted by the package. Names are dot-paths that map to namespaces (e.g. `Reports.Generate` → `Reports\Generate`). Resolution order: `App\Actions\{Name}` then `Jiannius\Atom\Actions\{Name}`. The host app's class wins, enabling per-app overrides of package actions.

@verbatim
<code-snippet name="Custom action" lang="php">
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

// PHP
app('atom')->action('SyncContacts', ['force' => true]);

// JS / Alpine
atom.action('SyncContacts', { force: true }).then(res => ...);
</code-snippet>
@endverbatim

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

For columns storing Tiptap editor HTML, cast with `Jiannius\Atom\Casts\AsEditorContent`. On save, the cast walks `<img>` tags, persists Livewire temporary uploads to `Storage::disk(env('FILESYSTEM_DISK'))` under `<folder>/editor/` (resized to 1000px, q=80), and rewrites URLs. Run `php artisan atom:purge-editor-images` periodically to clean orphaned images.

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
