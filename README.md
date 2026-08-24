# Atom

A Laravel UI component library built on **Tailwind + Alpine + Livewire 4**. Atom ships ~50 anonymous Blade components, a Livewire trait, an `atom()` runtime singleton for dispatching modals/toasts/alerts/confirms from PHP, a set of Laravel macros, and a pre-built JS/CSS bundle that the package serves itself — there is nothing to `npm install` in your host app.

- Composer: `jiannius/atom`
- Namespace: `Jiannius\Atom\`
- Custom Blade syntax: `<atom:button>`, `<atom:input.text>`, `<atom:button.group>` …

---

## Requirements

| Dependency        | Version  |
| ----------------- | -------- |
| PHP               | `^8.3`   |
| Laravel (illuminate/support) | `^13.0`  |
| Livewire          | `^4.0`   |
| Intervention Image | `^3.0`  |

---

## Installation

```bash
composer require jiannius/atom
```

The service provider (`Jiannius\Atom\AtomServiceProvider`) auto-registers via Laravel's package discovery. It will:

- Mount its routes under `/atom/*` (asset serving + action endpoint).
- Register all components under the `atom` view namespace.
- Install the `<atom:...>` Blade tag compiler.
- Swap Laravel's `Date` facade for `Jiannius\Atom\Services\Carbon`.
- Mix macros onto `Eloquent\Builder`, `Query\Builder`, `ComponentAttributeBag`, `Request`, `Str`, `Stringable`, and `Arr`.
- Register the artisan command `atom:purge-editor-images`.

No publishing step is required to get started.

### Page boilerplate

Use `<atom:html>` once at the top of any full-page Blade view. It writes the `<html>`, `<head>`, meta tags, Tailwind base, atom's bundled CSS/JS, and your host app's Vite entry.

```blade
<atom:html
    title="My Awesome App"
    description="Hello world"
    :fonts="'inter'"
    :vite="['resources/css/app.css', 'resources/js/app.js']"
    :gtm="config('services.gtm.id')"
    dark>
    <livewire:layout.sidebar>
        {{ $slot }}
    </livewire:layout.sidebar>
</atom:html>
```

Useful `<atom:html>` props (all optional):

| Prop          | Purpose                                                             |
| ------------- | ------------------------------------------------------------------- |
| `title`       | `<title>` and OG tag (falls back to `config('page.title')`).        |
| `description` | Meta description / OG description.                                  |
| `image`       | OG image (defaults to `storage/img/logo.png`).                      |
| `canonical`   | Canonical URL.                                                      |
| `hreflang`    | Array of locale alternates.                                         |
| `jsonld`      | Raw JSON-LD payload.                                                |
| `gtm` / `ga` / `fbp` | Tracking IDs for GTM, GA, Facebook Pixel.                   |
| `fonts`       | Google Font family to preload (default `inter`).                    |
| `dark`        | Opts the page into dark mode (off by default, on every layout too). Adds `class="dark"` to `<html>` and emits the `window.darkmode()` bootstrap, which then follows the stored preference or, with none, the visitor's `prefers-color-scheme`. Required for `<atom:darkmode-toggle>` to work. |
| `styles`      | Additional stylesheet URLs.                                         |
| `scripts`     | Additional script URLs.                                             |
| `editor`      | Loads the editor CSS chunk (for pages using `<atom:editor>`).       |
| `vite`        | Vite entries from your host app.                                    |
| `noindex`     | Sets `robots` to `noindex,nofollow`.                                |

The package's own JS/CSS bundle is served from `/atom/{file}` (immutable, hashed via `dist/manifest.json`); you do **not** need to add it to your Vite config.

### Component directory

With the package installed and `APP_ENV=local`, visit **`/atom/docs`** in your app for a browsable directory of every component: live previews, copyable code snippets, auto-generated prop tables, and searchable icon/logo galleries. The routes are not registered outside the local environment.

### Service provider entry points (reference)

If something feels magic, the answer is almost always in `src/AtomServiceProvider.php` (boot order: routes → migrations → translations → views → components → tag compiler → date facade swap → macros → asset routes → `/atom/action/{name}` POST endpoint).

---

## The `<atom:...>` tag syntax

`src/Services/TagCompiler.php` rewrites tags before Blade compiles:

| You write                       | Resolves to                          | File                                              |
| ------------------------------- | ------------------------------------ | ------------------------------------------------- |
| `<atom:button>...</atom:button>` | `<x-atom::button>...</x-atom::button>` | `components/button/index.blade.php`              |
| `<atom:icon.check/>`            | `<x-atom::icon.check/>`              | `components/icon/check.blade.php`                |
| `<atom:button.group>...`        | `<x-atom::button.group>...`          | `components/button/group.blade.php`              |
| `<atom:input.text/>`            | `<x-atom::input.text/>`              | `components/input/text.blade.php`                |

Dot paths map to subdirectories. The classic `<x-atom::...>` form still works — `<atom:...>` is preferred for terseness.

---

## Quickstart example

A typical Livewire 4 form using Atom:

```php
// app/Livewire/Customers/Create.php
namespace App\Livewire\Customers;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Create extends Component
{
    use AtomComponent;

    public $name;
    public $email;
    public $bio;

    public function breadcrumbs($crumbs)
    {
        return $crumbs
            ->home('Dashboard', route('home'))
            ->push('Customers', route('customers.index'))
            ->push('New Customer');
    }

    public function save()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);

        // ... persist

        $this->toast('Customer created', variant: 'success');
        return redirect()->route('customers.index');
    }

    public function render()
    {
        return view('livewire.customers.create');
    }
}
```

```blade
{{-- resources/views/livewire/customers/create.blade.php --}}
<div>
    <atom:breadcrumbs/>

    <atom:form wire:submit="save">
        <atom:input label="Name" wire:model="name" required/>
        <atom:input type="email" label="Email" wire:model="email" required/>
        <atom:editor label="Bio" wire:model="bio"/>

        <atom:button.group>
            <atom:button href="{{ route('customers.index') }}">Cancel</atom:button>
            <atom:button type="submit">Save</atom:button>
        </atom:button.group>
    </atom:form>
</div>
```

---

## Helpers

### The `atom()` singleton

`app('atom')` (aliased) is a single entry point for runtime UI dispatch. Inside a Livewire component, prefer the trait methods (`$this->toast(...)`) — they delegate here but are shorter. Use `app('atom')->...` from controllers, jobs, or anywhere outside the component class.

```php
app('atom')->modal('confirm-delete')->show();
app('atom')->modal('details')->slide('right');   // slide-over
app('atom')->modal('details')->close();

app('atom')->toast('Saved.', variant: 'success', delay: 4000);

app('atom')->alert(
    heading: 'Heads up',
    message: 'Your subscription expires tomorrow.',
    variant: 'warning',
    button: 'Got it',
);

app('atom')->confirm(
    heading: 'Delete customer?',
    message: 'This cannot be undone.',
    buttonConfirm: 'Delete',
    password: true,                       // require password re-entry
    onAccepted: 'reallyDelete',           // calls $wire.reallyDelete()
    onRejected: 'cancelDelete',
);

app('atom')->action('Foo.Bar', ['method' => 'doThing', ...$params]);

app('atom')->mail(
    to: $user->email,
    subject: 'Welcome',
    content: '<p>Hello!</p>',
    cta: ['label' => 'Open dashboard', 'url' => route('home')],
    queue: true,
);

$breadcrumbs = app('atom')->breadcrumbs()
    ->home('Home', '/')
    ->push('Customers', route('customers.index'))
    ->push('Edit')
    ->build();

app('atom')->asset()->version('atom.js');     // → /atom/atom-{hash}.js
app('atom')->sitemap();
app('atom')->broadcast();
```

All `heading`, `subheading`, and `message` strings are auto-passed through `t()` for translation.

### Global functions (`src/Helpers.php`)

| Function | Purpose |
| -------- | ------- |
| `t($key, $count = 1, $params = [])` | Translation shim. Number → `trans_choice`, array → `__($key, $array)`, scalar → `__($key, $params)`. Used internally by every component. |
| `num($value)` | Wraps Laravel's `Number` helper. Adds `->currency($iso, $rounding, $bracket, $abbreviate)` and `->filesize($precision)`. All other `Number::*` methods proxy through. |
| `carbon(...$args)` | Returns a `Jiannius\Atom\Services\Carbon` instance. |
| `js($value)` | Alias for `Js::from()`. |
| `is_enum($value)` | True for `UnitEnum` / `BackedEnum`. |
| `is_using_trait($class, $trait)` | True if `$class` (recursively) uses `$trait`. |

Examples:

```php
t('Welcome :name', ['name' => $user->name]);
t('item.count', 5);                       // → trans_choice
num(1234.5)->currency('USD');             // → "USD 1,234.50"
num(2048)->filesize();                    // → "2 MB"
num(1500000)->currency('USD', abbreviate: true);  // → "USD 1.5M"
```

### The `AtomComponent` Livewire trait

`use Jiannius\Atom\Traits\AtomComponent;` on any Livewire component to get:

- `WithPagination` + `WithFileUploads` automatically.
- Reserved state buckets:
  - `$_breadcrumbs` — auto-populated from your optional `breadcrumbs($crumbs)` method.
  - `$_table` — sort, checkboxes, max rows, show-trashed (consumed by `<atom:table>`).
  - `$_editor.images` — temporary upload URLs for the rich text editor.
- Short methods that delegate to `app('atom')`:

```php
$this->modal('name-of-modal')->show();
$this->toast('Saved!');
$this->alert(message: 'Done.');
$this->confirm(message: 'Sure?', onAccepted: 'doIt');
$this->action('Foo.Bar', $params);
$this->wirekey('row', $id);     // stable md5 key for wire:key
```

### The `Enum` trait

`use Jiannius\Atom\Traits\Enum;` on backed enums to get the convention used by Atom's status badges and selects:

```php
enum OrderStatus: string {
    use \Jiannius\Atom\Traits\Enum;

    case NEW = 'new';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}

OrderStatus::all();              // collection of cases (filters out TRASHED)
OrderStatus::get('paid');        // → OrderStatus::PAID
OrderStatus::PAID->label();      // → "Paid" (headline of value)
OrderStatus::PAID->color();      // → "green" (sensible mapping by value)
OrderStatus::PAID->toArray();    // → ['value' => ..., 'label' => ..., 'color' => ...]
OrderStatus::PAID->is('paid', OrderStatus::NEW);
```

---

## Macros

### Eloquent / Query `Builder` (`src/Macros/Builder.php`)

```php
User::query()->whereDateBetween('created_at', '2025-01-01 to 2025-12-31');
$q->toPage(2, 50);                       // paginate to page 2, 50 per page
$q->toTable();                           // paginate using $_table state from <atom:table>
$q->filter(['search' => 'tj', 'status:!=' => 'archived']);
$q->breakdown($diff, $start);            // group-by year/month/day for charts
$q->randomCode(8, 'code');               // unique random code
User::query()->tableColumns();           // cached SHOW COLUMNS
User::query()->tableHasColumn('email');
User::query()->tableColumnType('created_at');
```

`filter()` knows about: named scopes (`search`, `byFoo`), enum casts, JSON columns, date columns, and the `key:operator` syntax (`'price:>=' => 100`).

### `Request` (`src/Macros/Request.php`)

```php
request()->portal();                     // → 'auth' | 'admin' | 'app' | etc., derived from route name
request()->portal('admin');              // → boolean
request()->subdomain();                  // → "client" from "client.app.test"
request()->hostWithoutSubdomain();
request()->isLivewireRequest();
```

### `Str` / `Stringable` (`src/Macros/Str.php`)

```php
str('foo.bar')->namespace();             // → "Foo\Bar"
str('App\Models\User')->dotpath();       // → "App.Models.User"
str('3 months')->interval();             // → "Quarterly"
str()->initials('Tan Joon Long');        // → "TJ"
```

### `Arr` (`src/Macros/Arr.php`)

```php
Arr::pick(['xs' => false, 'sm' => true, 'lg' => false]);  // → "sm"
```

### `ComponentAttributeBag` (`src/Macros/ComponentAttributeBag.php`)

Used inside component templates:

```blade
{{-- inside components/button/index.blade.php style code --}}
$attributes->size('md')                  {{-- → "xs"|"sm"|"md"|"lg"|... from `sm`/`md`/`lg` modifiers or size= --}}
$attributes->modifier()                  {{-- → "lazy", "live", etc. from wire:model.X --}}
$attributes->modifier('live')            {{-- → boolean --}}
$attributes->field()                     {{-- → field name from field=, for=, or wire:model --}}
$attributes->hasLike('wire:click*')
$attributes->getLike('x-on:click*')
$attributes->getAny('alt', 'title', 'aria-label')
$attributes->classes()->add('foo')->add($condition && 'bar')
$attributes->styles()->add('width', '100px')
```

---

## Component catalog

All components live in `components/`. Open `components/<name>/index.blade.php` (or `components/<name>.blade.php`) to see the canonical `@props([...])` list. The tables below list the most-used props per component.

### Form inputs

| Tag | Notable props / subcomponents |
| --- | ------------------------------ |
| `<atom:input>` | `name`, `type` (`text`, `email`, `password`, `number`, `tel`, `color`), `label`, `caption`, `prefix`, `suffix`, `required`, `error`. Subs: `<atom:input.text>`, `<atom:input.email>`, `<atom:input.tel>`, `<atom:input.color>`, `<atom:input.field>`, `<atom:input.prefix>`. |
| `<atom:textarea>` | `name`, `label`, `caption`, `rows` (default 3), `autoresize`, `variant="transparent"`. |
| `<atom:select>` | `options` — an **array** for a static list, or a **string** naming a `GetOptions` set to fetch remotely. Plus `name` (field name, not the option set), `label`, `caption`, `variant` (`native` (default), `listbox`, `filter`), `required`, `error`, `prefix`, `suffix`, `inline`; `filters`, `multiple`, `searchable`, `clearable` on `listbox`. Children: `<atom:select.option>`, `<atom:select.group>`. |
| `<atom:checkbox>` | `name`, `label`, `caption`, `align` (`start`, `center`, `end`). Group with `<atom:checkbox.group>`. |
| `<atom:radio>` | Same as checkbox. Group with `<atom:radio.group>`. |
| `<atom:toggle>` | `name`, `label`, `caption`. Group with `<atom:toggle.group>`. |
| `<atom:slider>` | Range slider on a native `<input type=range>`. `name`, `label`, `caption`, `min` (0), `max` (100), `step` (1), `value`, `bubble` (value shown on interaction), `labels` (min/max at track ends), `required`, `disabled`. `wire:model` binds via `x-modelable`. |
| `<atom:rating>` | Star rating input + display. `name`, `label`, `caption`, `count` (5), `value`, `half` (half-step selection), `readonly` (display a fixed/average value), `clearable` (re-click resets to 0), `icon` (swap the star for any atom icon). `wire:model` binds via `x-modelable`. |
| `<atom:date-picker>` | `name`, `variant` (`date`, `range`, `calendar`), `label`, `caption`, `inline`, `prefix`, `suffix`. Subs: `<atom:date-picker.date>`, `<atom:date-picker.range>`, `<atom:date-picker.calendar>`. |
| `<atom:time-picker>` | `name`, `label`, `caption`, `invalid`, `inline`. |
| `<atom:uploader>` | `label` (default `Upload`), `variant`, `size`. Drop variant: `<atom:uploader.dropzone>`. |
| `<atom:editor>` | Tiptap rich text. `name`, `label`, `caption`, `readonly`, `autofocus`, `variant="transparent"`, `placeholder`, `toolbar`, `mention`. Many sub-buttons under `<atom:editor.button.*>` and contextual menus under `<atom:editor.menu.*>`. Requires `<atom:html editor>` on the page. |

### Buttons & links

| Tag | Notable props |
| --- | ------------- |
| `<atom:button>` | `type` (`submit`, `delete`), `variant` (`primary`, `danger`, `accent`, `ghost`, `link`, `facebook`, `google`, `linkedin`, `whatsapp`, `telegram`), `size` (`xs`, `sm`, `md`, `lg`), `block`, `href`, `icon`, `iconSuffix`, `inverted`, `newtab`. Wraps `wire:click`, dispatches `confirmed` for `type="delete"` (auto-confirmed → `$wire.delete()`). |
| `<atom:button.group>` | Layout helper for adjacent buttons. |
| `<atom:link>` | `href`, `icon`, `iconSuffix`, `variant="accent"`, `newtab`, `rel`. |

### Display & typography

| Tag | Notable props |
| --- | ------------- |
| `<atom:heading>` | `size` (`xs`, `sm`, `default`, `lg`, `xl`, or `<n>px`) — each size sets both a font size and a weight. `level` (`1`..`6`) renders a real `<hN>` instead of the default `<div>`; see **Heading levels** below. |
| `<atom:subheading>` | Same as heading, smaller / muted. |
| `<atom:caption>` | Small muted text. |
| `<atom:label>` | `icon`, `align`. |
| `<atom:kbd>` | Keyboard-key caps. `keys` (space/`+`-separated, maps `cmd`→⌘, `shift`→⇧, `alt`→⌥, `ctrl`→⌃, `enter`, `esc`, arrows, …); or a single cap from the slot: `<atom:kbd>Esc</atom:kbd>`. |
| `<atom:avatar>` | `src`, `name`, `initial`, `square` (default `true`), `size` (`xs`..`xl`). Stack: `<atom:avatar.group>`. |
| `<atom:badge>` | `status` (enum-aware), `size` (`xs`, `default`, `lg`), `icon`, `color`, `label`. Group: `<atom:badge.group>`. |
| `<atom:card>` | `inset`, `subtle`, `divided`, `variant` (`stats`, `chart`), `heading`, `data`, `indicator`, `trend`, `color`. |
| `<atom:chart>` | Standalone ApexCharts chart. `type` (`bar`, `area`, `trend`), `data` (bar/area: `label`/`value`/`tooltip` rows; trend: plain number array), `color` (`red`/`green`/`orange`/`gray` or `#hex`), `max` (`['value'=>, 'label'=>]` goal line), `min`. Default height `h-64` (`h-16` for trend), override with a `h-*` class. |
| `<atom:callout>` | `icon`, `heading`, `content`, `variant` (`info`, `success`, `warning`, `danger`, `error`), `closeable`. |
| `<atom:skeleton>` | Animated loading block. |
| `<atom:placeholder-bar>` | `size="100%x20px"`. |
| `<atom:empty>` | `icon` (default `inbox`), `size`, `subtle`, `heading`, `subheading`. |
| `<atom:profile>` | `name`, `avatar`, `email`, `size`. |
| `<atom:icon.*>` | 200+ icons. Examples: `<atom:icon.check/>`, `<atom:icon.arrow-left/>`, `<atom:icon.delete/>`. Browse `components/icon/`. |
| `<atom:logo.*>` | Payment / brand marks: `apple-pay`, `fpx`, `google-pay`, `ipay88`, `master`, `senangpay`, `stripe`, `tng`, `visa`. |

#### Heading levels

`<atom:heading>` renders a `<div>` unless you pass `level`, because most headings in an app UI are card titles, stat labels and table captions — visual, not structural. `size` and `level` are deliberately independent: size is how big it looks, level is where it sits in the document outline.

`<atom:layouts.sidebar>` supplies the page's `<h1>` itself, from its `title` prop — visually hidden, since the visible title comes from `<atom:breadcrumbs>`, which is a `nav` landmark and emits no heading element. So you do **not** need an `<h1>` in the page body; start at `level="2"`.

Pass `level` where a section genuinely nests, and leave it off elsewhere:

```blade
<atom:layouts.sidebar title="Checklists">   {{-- gives the page its <h1> --}}
    <atom:heading size="lg" level="2">Vehicle details</atom:heading>
    <atom:heading size="default" level="3">Tyres</atom:heading>

    <atom:card>
        <atom:heading size="lg">Revenue</atom:heading>   {{-- a card title: no level --}}
    </atom:card>
</atom:layouts.sidebar>
```

A suggested mapping when a heading *is* structural: `xl` → `level="1"` (only outside the sidebar layout), `lg` → `2`, `default` → `3`, `sm`/`xs` → `4`.


### Feedback & overlays

| Tag | Notable props |
| --- | ------------- |
| `<atom:modal>` | `name` (required for `atom()->modal($name)` to find it), `inset`, `dismissible`, `closeable`. Trigger via `<atom:modal.trigger name="...">` or `$this->modal('name')->show()` from PHP. |
| `<atom:alert>` | Window-bound. Triggered by `atom()->alert(...)`. Config keys: `heading`, `subheading`, `message`, `variant`, `button`, `onDismissed`. |
| `<atom:toast>` | Window-bound. Triggered by `atom()->toast(...)`. Config keys: `message`, `variant` (`success`, `warning`, `danger`), `delay` (default 3000), `position` (`top`, `bottom`, `center`), `align`. |
| `<atom:confirm>` | Window-bound. Triggered by `atom()->confirm(...)`. Supports `password`, `passphrase`, optional reason field. Wires `onAccepted` / `onRejected` to Livewire methods. |
| `<atom:tooltip>` | `interactive`, `position` (`top`, `bottom`, `left`, `right`), `align` (`start`, `center`, `end`), `content`, `kbd`, `toggleable`. |
| `<atom:dropdown>` | `position` (`bottom`, `top`), `align` (`start`, `end`), `locked`. |
| `<atom:context-menu>` | Right-click menu opened at the cursor. `locked`. Default slot = the right-clickable target; `<x-slot:menu>` holds `<atom:menu.item>`s. Reuses `<atom:menu>`. |
| `<atom:lightbox>` | Image lightbox; click any `<img>` inside to zoom. |

The four window-level overlays (`alert`, `toast`, `confirm`) are usually dropped **once** in your root layout — drop them in `<atom:layouts.sidebar>` or near `<atom:html>` and dispatch from anywhere.

### Layout & navigation

| Tag | Notable props |
| --- | ------------- |
| `<atom:form>` | `inset`. Wraps form, handles auto loading state on submit. |
| `<atom:table>` | `empty`, `paginate`, `maxRows` (array of row options). Children: `<atom:table.column>`, `<atom:table.row>`, `<atom:table.cell>`, `<atom:table.checkbox>`, `<atom:table.pagination>`. Driven by `$_table` state on the Livewire component. |
| `<atom:tabs>` | `tabs` (array), `size` (`sm`), `variant` (`button`, `border`). Child: `<atom:tabs.item>`. |
| `<atom:list>` | `heading`, `scrollable` (default `true`). Child: `<atom:list.item>`. |
| `<atom:menu>` | `popover`. Child: `<atom:menu.item>`. |
| `<atom:navlist>` | Sidebar nav container. Children: `<atom:navlist.item>`, `<atom:navlist.group>`, `<atom:navlist.badge>`. |
| `<atom:breadcrumbs>` | `heading` (default `true`). Reads `$_breadcrumbs` populated by your `breadcrumbs()` method. |
| `<atom:calendar>` | `name`, `modes` (`calendar`, `timeline`), `periods` (`month`, `week`, `day`). |
| `<atom:separator>` | `align` (`left`, `center`, `right`). Slot becomes the label. |
| `<atom:layouts.auth>` | Centered auth layout (login, register, forgot password). Props: `title`, `noindex`, `dark`, `vite`. |
| `<atom:layouts.sidebar>` | App layout with sidebar + top bar. Props: `title`, `noindex`, `dark`, `editor`, `styles`, `scripts`, `vite`. Pass `dark` to get the darkmode bootstrap and the header's switcher. |

### Miscellaneous

| Tag | Notable props |
| --- | ------------- |
| `<atom:copy>` | `value` — copy-to-clipboard button. |
| `<atom:darkmode-toggle>` | Dark-mode switcher. Needs the page to pass `dark` to `<atom:html>` or its layout — without it `window.darkmode()` is never defined. The sidebar layout renders its own toggle when `dark` is set. |
| `<atom:dd>` | Definition list. Child: `<atom:dd.group>`. |
| `<atom:embed>` | `src`, `icon`, `file` — embeds image / video / YouTube / file preview. |
| `<atom:error>` | Plain error message slot. |
| `<atom:html>` | Page boilerplate (see [Page boilerplate](#page-boilerplate)). |
| `<atom:sharer>` | `sites` (array), `url`, `title` — social share buttons. |
| `<atom:whatsapp>` | `number`, `text` — floating WhatsApp button. |

---

## The `AsEditorContent` cast

Use this when you want a column to behave as Tiptap rich-text content with automatic image persistence:

```php
use Jiannius\Atom\Casts\AsEditorContent;

class Article extends Model
{
    protected $casts = [
        'body' => AsEditorContent::class,
    ];
}
```

What it does on save:

1. Scans the HTML for Livewire temporary preview URLs (`/livewire-{hash}/preview-file/...`).
2. Resizes each via Intervention Image (max width 1000, quality 80).
3. Persists to `Storage::disk(env('FILESYSTEM_DISK'))` under `<configured folder>/editor/`.
4. Rewrites the URLs back into the HTML, serializes the result.

`get()` lazily `unserialize()`s, falling back to the raw value if it isn't serialized.

Pair with the scheduled command to clean up images no longer referenced:

```bash
php artisan atom:purge-editor-images          # dry-clean (move to editor-purged/)
php artisan atom:purge-editor-images --force  # delete the editor-purged/ backup
```

---

## Actions

Named PHP classes you can invoke from PHP or, if they opt in, from the browser.

Resolution order:
1. `App\Actions\{Name}` (host app — wins).
2. `Jiannius\Atom\Actions\{Name}` (package fallback).

### From PHP

Any action, any public method:

```php
atom()->action('Customer.Search', ['q' => 'jane']);
atom()->action('Customer.Search', ['method' => 'byEmail', 'email' => 'jane@acme.test']);
```

### From the browser

Atom mounts `POST /atom/action/{name}` and the front-end exposes `window.atom.action(name, params)`. **That endpoint is public** — no auth, reachable by anyone who can load the app — so it only runs actions that opt in by implementing `Jiannius\Atom\Contracts\WebAction`:

```php
// app/Actions/Customer/Search.php
namespace App\Actions\Customer;

use Jiannius\Atom\Contracts\WebAction;

class Search implements WebAction
{
    public function handle($params)
    {
        return \App\Models\Customer::query()
            ->where('name', 'like', '%'.$params['q'].'%')
            ->take(10)
            ->get(['id', 'name']);
    }
}
```

```js
const result = await window.atom.action('Customer.Search', { q: 'jane' });
```

The endpoint:

- **Answers 404 for anything that did not opt in** — the same answer an unknown action gets, so it cannot be used to enumerate your action classes.
- **Only ever calls `handle()`.** `method` is a PHP-side convenience; over HTTP it is stripped from the params and ignored.
- **Encodes the return value straight to the caller.** Return the columns you mean to expose, not whole models.
- **Does not rate-limit.**

### Gating who may call it

Declare `authorize()` on the action. The endpoint calls it before `handle()` and answers 403 when it returns false:

```php
class Search implements WebAction
{
    public function authorize($params) : bool
    {
        return auth()->check();
    }

    public function handle($params) { /* ... */ }
}
```

Actions without `authorize()` are callable by anyone, including guests — which is right for something like `GetOptions` (country and dial-code lists on public forms) and wrong for almost everything else. An action inheriting from an opted-in parent inherits the contract.

### Upgrading to 3.20

3.20 closes three holes in `GetOptions`, the one action the package ships web-callable without authentication. All three were reachable by an anonymous caller:

- **The option name picked a method.** `{"name":"purge-cache"}` invoked `purgeCache()` on your subclass. `method_exists()` matches private, protected and inherited methods, and the call was made from inside the class, so all of them were reachable.
- **The option name built a file path.** `{"name":"../../composer"}` escaped `resources/json/` and read the file.
- **An unknown name threw**, returning 500 with the absolute path in the log — an unauthenticated log flooder that also fired on an honest typo (`country` instead of `countries`).

**What you must do**, in every app with its own `App\Actions\GetOptions`: declare each option set in `$auth` (needs a signed-in caller) or `$guest` (readable by anyone). Undeclared sets return `[]` — the select goes empty — and log a warning naming the set. See [`GetOptions`](#getoptions-shared-option-lists) above.

Assume `$auth` unless you can say why a stranger may read every row. Sets like `contacts`, `users`, `documents` or `taxes` were guest-readable before this release; declaring them `$guest` keeps them that way.

`$auth` gates *who is signed in*, not *which rows they get*. Scope the queries too.

### Upgrading to 3.19

Before 3.19 the endpoint ran **any** class in `App\Actions\` — unauthenticated, and with the caller choosing which public method to invoke. It is now closed by default, so any action your JS calls goes dark until you opt it in.

1. Find them: `grep -rn "atom.action(" resources/` (and any `.js` outside `resources/`). Each name maps to a class — `Foo.Bar` → `App\Actions\Foo\Bar`.
2. For each, add `implements \Jiannius\Atom\Contracts\WebAction`. Nothing else changes.
3. While you are in each file, decide whether it should have been public at all. Add `authorize()` to anything that reads or writes user data — before 3.19 it had no gate, so assume none of them do.
4. If you have your own `App\Actions\GetOptions`, make it `extends \Jiannius\Atom\Actions\GetOptions` — otherwise it shadows the package class without the contract and every remote-option select (`<atom:select options="users">`) in the app 404s.
5. If any JS passed `method` in the params, split that method into its own action — the endpoint ignores `method` now.
6. Check the app's `CLAUDE.md`/`AGENTS.md`. Atom's guidelines are **copied** into it rather than read from `vendor/`, and Boost rewrites that file on `composer update` — so the new rules arrive on their own, but any hand-edits you made to the atom section are overwritten at the same time. Re-apply them, or keep them outside that section.

Anything you do *not* opt in stays fully callable from PHP; only the browser path is affected.

**If you miss one, the log says so.** A refused action that exists writes a warning naming the class and the endpoint:

```
[atom] Refused POST /atom/action/customer.search: App\Actions\Customer\Search does not
implement Jiannius\Atom\Contracts\WebAction. ...
```

Requests for actions that don't exist stay silent, so this doesn't turn into noise from probing.

### `GetOptions`: shared option lists

`Jiannius\Atom\Actions\GetOptions` loads option arrays from JSON files. It merges:

- Package JSON at `<package>/json/{name}.json`
- App JSON at `resource_path('json/{name}.json')`

with the app-side values taking precedence. Results are cached under `_options`.

Built-in JSON sets: `countries`, `postcodes`, `colors`. Override any of them by creating `resources/json/colors.json` in your host app.

To serve options from your database instead, create `App\Actions\GetOptions` **extending** the package class (it shadows it, and extending is what carries the `WebAction` contract that remote-option selects need). Each option set needs two things: a camelCase method, and its name **declared** in `$auth` or `$guest`.

Reach it by passing the set name as a **string** on `options` — that is what makes the select fetch rather than render a static list:

```blade
<atom:select variant="listbox" options="users" wire:model="user_id" searchable />
```

`:options="[...]"` (an array) is a static list; `name` is the field name and never selects an option set.

```php
namespace App\Actions;

class GetOptions extends \Jiannius\Atom\Actions\GetOptions
{
    /** Readable only by a signed-in caller. */
    protected array $auth = ['users'];

    /** Readable by anyone, guests included. */
    protected array $guest = ['brands'];

    public function users() : array
    {
        return \App\Models\User::query()
            ->where('team_id', auth()->user()?->team_id)
            ->get()
            ->map(fn ($user) => ['value' => $user->id, 'label' => $user->name])
            ->all();
    }
}
```

Why the declaration: the option name arrives in the request body, and it used to be turned straight into a method call. Anything zero-arg on your subclass was reachable from a browser, authenticated or not. Now the name only selects among the sets you listed, and a name in neither list returns an empty array.

- **`$auth`** — needs a signed-in caller. Everything backed by app data belongs here. A guest gets 403.
- **`$guest`** — readable by anyone. Only for sets where every row a stranger could pull back is safe to hand over.
- **Undeclared** — returns `[]`. If a method of that name exists, a warning is logged naming the class and the set, so a select that has gone empty is diagnosable.

The package's own sets (`countries`, `states`, `dialcodes`, `currencies`, `colors`, `postcodes`) are always readable — guest address and phone forms need them — and you don't re-declare them.

`$auth` is a coarse gate: signed in or not. It does **not** scope rows. A signed-in user of tenant A calling a set that returns every tenant's rows still gets every tenant's rows, so scope the query itself as well.

---

## Translation

`t('Some string', $countOrParams, $params)` is the translation shim. Almost every UI string in components passes through it, so to translate your app you just drop standard Laravel translation files under `lang/{locale}/`.

```php
t('Save changes');                      // → __('Save changes')
t('item.count', 5);                     // → trans_choice('item.count', 5)
t('Hello :name', ['name' => $user]);    // → __('Hello :name', ['name' => $user])
```

---

## Front-end JS API

`resources/js/atom.js` is built to `dist/` and served by the package; it boots automatically when `<atom:html>` renders. It exposes:

- `window.atom.action(name, params)` — POST to `/atom/action/{name}` (actions implementing `WebAction` only; see [Actions](#actions)).
- `window.dd(...args)` — `console.log` dump.
- `window.empty(value)` — truthy-empty helper.
- Alpine factories: `modal`, `editor`, `select`, `tooltip`, `dropdown`, `lightbox`, `telInput`, `emailInput`, `breadcrumbs`, `datePicker`, `timePicker`, `dateRange`, `calendar`, plus chart variants.
- `$clipboard` Alpine magic.
- Prototype additions on `Array`, `Number`, `String` (see `resources/js/prototypes/`).
- Alpine plugins loaded: `@alpinejs/intersect`, `@marcreichel/alpine-autosize`.

---

## Conventions worth knowing

- Window-level Livewire events are prefixed `atom-` (`atom-modal-show`, `atom-modal-close`, `atom-toast-show`, `atom-alert-show`, `atom-confirm-show`). Grep by this prefix when tracing overlay state.
- `<atom:button type="delete">` auto-dispatches `confirmed` on accept, which (unless overridden) calls `$wire.delete()`.
- Date handling everywhere goes through `Jiannius\Atom\Services\Carbon` because of the `Date::use()` swap in the service provider.
- Components prefer `Arr::toCssClasses([...])` and `match` over conditional class strings. See `components/button/index.blade.php` for the canonical pattern.
- The bundled `dist/` directory is **committed**; the package serves it itself. If you fork and edit JS/CSS sources, run `npm run build` and commit `dist/`.

---

## Artisan commands

| Command | Purpose |
| ------- | ------- |
| `atom:purge-editor-images` | Walks `App\Models\*`, finds columns cast as `AsEditorContent`, and moves any unreferenced editor image to `editor-purged/` on the local disk before removing from the configured disk. |
| `atom:purge-editor-images --force` | Empties the `editor-purged/` backup folder. |

---

## License

MIT. See `LICENSE.md`.
