## Atom

Atom (`jiannius/atom`) is a Tailwind + Alpine + Livewire component library for Laravel. The component catalogue is at `vendor/jiannius/atom/components/` — always check it before writing custom markup.

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
@endverbatim

### Livewire components — `AtomComponent` trait

Volt is the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every Volt class component. It provides `WithPagination` + `WithFileUploads`, reserved state buckets (`$_breadcrumbs`, `$_table`, `$_editor`), and helper methods:

- `$this->modal($name = null)->show() / ->slide() / ->close()` — `$name` defaults to the current component name.
- `$this->toast(...)`, `$this->alert(...)`, `$this->confirm(...)` — dispatch UI events.
- `$this->action('Name', $params)` — invoke an Action class (see Actions).
- `$this->wirekey(...$args)` — stable `wire:key` based on args, or random ULID-based key when called with none.

Define a `breadcrumbs(Breadcrumbs $b)` method to populate `$_breadcrumbs` automatically on mount.

@verbatim
<code-snippet name="Volt component with Atom" lang="php">
<?php
use Livewire\Volt\Component;
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
- When `<atom:modal>` is the **root** of a Volt component, omit `name` — methods auto-resolve to the component name.
@endverbatim
- For multiple modals in one component, give each `name="..."` and target with `$this->modal('confirm')->show()`.
- Props: `:closeable="false"` hides the × button; `:dismissible="false"` disables backdrop-close.
- Close client-side:
  - From inside the modal: `$dispatch('atom-modal-close')` (DOM containment closes the enclosing modal).
  - From outside: `$dispatch('atom-modal-close', { name: 'modal-name' })` — targeting by name is required when the dispatcher isn't inside the modal DOM.

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

### Tooltip

@verbatim
`<atom:tooltip content="Save" position="top" align="center" kbd="⌘S" :interactive="false" :toggleable="false">` — wraps a single trigger element as its slot child. `content` is auto-translated.
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
- **Dense business forms.** Two-column by default: `grid gap-6 md:grid-cols-2`. Separate logical groups with `<atom:separator>` and short titles (e.g. "Address", "Registration & Tax").
- **Section separation.** Prefer `<atom:separator>` over ad-hoc `<hr>` or border classes.
@endverbatim
- **Volt method order** (top of class to bottom):
    1. Validation (`$rules`, `$messages`, `#[Rule]` properties)
    2. `mount()`
    3. `breadcrumbs()`
    4. `#[Computed]` properties
    5. Livewire lifecycle hooks (`updated*`, `hydrate`, …)
    6. Custom methods
- **PHPDoc.** Every public and private method has a one-line PHPDoc describing what it does or returns. Skip inline `//` comments unless the logic is non-obvious.
