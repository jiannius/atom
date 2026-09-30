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
  - `$_table` — sort, checkboxes, select-all, max rows, show-trashed (consumed by `<atom:table>` — see [Table selection](#table-selection)).
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

#### Names the trait occupies

A trait method loses to a method of the same name on the class itself — with no error. So a
component that defines one of these shadows atom's version. Whether that matters depends
entirely on **who calls the name**, and the two halves are not equally risky.

**Safe to shadow** — nothing in atom ever calls these on your component. They're sugar that
delegates to `app('atom')`, there for you to call and no one else. Define your own `toast()`
and you get yours; you'll know, because you wrote it.

> `modal`, `command`, `toast`, `alert`, `confirm`, `action`, `wirekey`, `verifyRecaptcha`

`action` is `protected` (see [Upgrading to 3.25](#upgrading-to-325)) — shadow it if you
like, but define yours `protected` too. A `public function action()` on your component is
callable from the browser as `$wire.action(...)`, which is the exposure 3.25 removed.

**Silent if shadowed** — atom or Livewire invokes these by name, so redefining one kills the
feature behind it with nothing to show for it. All of them are prefixed for exactly this
reason; the prefix *is* the protection.

| | Invoked by | Breaks |
| --- | --- | --- |
| `$_breadcrumbs`, `$_table`, `$_editor`, `$_recaptcha` | the `toTable()` macro and the blades | sort, pagination, checkboxes |
| `mountAtomComponent`, `updatedAtomComponent`, `updatingAtomComponent` | Livewire, by convention | breadcrumbs, editor uploads, trashed-toggle clear, the `raw:` table-sort signature gate |
| `resetTableCheckboxes`, `selectAllTableMatching`, `toggleTableShowSelected`, `clearTableSelectAll` | atom's markup, by name | the checked-bar buttons no-op |
| `tableSelection`, `tableSelectionQuery`, `tableRowsQuery`, `getTableCheckboxes`, `isTableSelectAll`, `isTableShowSelected`, `isTableShowTrashed` | each other, and your own `items()` | bulk actions target the wrong rows |
| `$paginators`, `getPage`, `gotoPage`, `nextPage`, `previousPage`, `resetPage`, `setPage`, `queryStringHandlesPagination` | Livewire's `WithPagination` | pagination |
| `_startUpload`, `_finishUpload`, `_removeUpload`, `_uploadErrored` | Livewire's `WithFileUploads` | file uploads |

**Yours to define:** `breadcrumbs` (optional), `tableQuery` (required for `tableSelection()`),
`tableSelectionQuery` (override for sticky selection).

Nothing else is reserved. The computed property feeding `<atom:table :paginate="...">` is
yours to name: atom neither defines nor looks for `items`, `rows`, `records`, `data`, or
anything like them. `tests/Feature/AtomComponentSurfaceTest.php` pins this list, so it can't
drift from the trait without a failing test.

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
| `<atom:select>` | `options` — an **array** for a static list, or a **string** naming a `GetOptions` set to fetch remotely. Plus `name` (field name, not the option set), `label`, `caption`, `variant` (`native` (default), `listbox`, `filter`), `required`, `error`, `prefix`, `suffix`, `inline`; `filters`, `multiple`, `searchable`, `clearable` on `listbox`; `table-filter` on `native`/`listbox` registers the field as a chip in `<atom:table.filters>` (key = `wire:model`, chip name = `label`). Children: `<atom:select.option>`, `<atom:select.group>`. |
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
| `<atom:table>` | `empty`, `paginate`, `maxRows` (array of row options), `skeleton`, `trashed`, `selectAll`, `stickySelection`. Slots: `columns`, `rows`, `header`, `checked` (bulk-action bar), `footer`. Children: `<atom:table.column>`, `<atom:table.row>`, `<atom:table.cell>`, `<atom:table.checkbox>`, `<atom:table.search>`, `<atom:table.filters>`, `<atom:table.trashed>`, `<atom:table.actions>`, `<atom:table.pagination>`. Driven by `$_table` state on the Livewire component. See [Table selection](#table-selection). |
| `<atom:tabs>` | `tabs` (array), `size` (`sm`), `variant` (`button`, `border`). Child: `<atom:tabs.item>`. |
| `<atom:list>` | `heading`, `scrollable` (default `true`). Child: `<atom:list.item>`. |
| `<atom:menu>` | `popover`. Child: `<atom:menu.item>`. |
| `<atom:navlist>` | Sidebar nav container. Children: `<atom:navlist.item>`, `<atom:navlist.group>`, `<atom:navlist.badge>`. |
| `<atom:navlist.group>` | `heading`, `expandable`, `expanded` (default `true`), `hiddenIfEmpty` (default `true`), `persistKey`. See [Remembering collapsed groups](#remembering-collapsed-groups). |
| `<atom:breadcrumbs>` | `heading` (default `true`). Reads `$_breadcrumbs` populated by your `breadcrumbs()` method. |
| `<atom:calendar>` | `name`, `modes` (`calendar`, `timeline`), `periods` (`month`, `week`, `day`). |
| `<atom:separator>` | `align` (`left`, `center`, `right`). Slot becomes the label. |
| `<atom:layouts.auth>` | Centered auth layout (login, register, forgot password). Props: `title`, `noindex`, `dark`, `vite`. |
| `<atom:layouts.sidebar>` | App layout with sidebar + top bar. Props: `title`, `noindex`, `dark`, `editor`, `styles`, `scripts`, `vite`. Pass `dark` to get the darkmode bootstrap and the header's switcher. |

#### Remembering collapsed groups

An expandable group's open state lives in Alpine, so it resets on every page load. Give it a
`persist-key` and it's remembered in `localStorage` instead:

```blade
<atom:navlist.group heading="Purchase" expandable persist-key="nav.purchase">
```

| prop | default | behaviour |
| --- | --- | --- |
| `persistKey` | `null` | When `null`, behaves exactly as before — no storage read, no write. |

**`expanded` is only the starting state.** A stored value always wins, so a group rendered
`:expanded="false"` that the user has expanded stays open across reloads. That precedence is
the point of the feature, and the answer to "why is my `:expanded="false"` ignored?"

Three things worth knowing:

- **The key is namespaced** under `atom:navlist-group:`, so passing `"sidebar"` can't collide
  with your app's own `localStorage` entries.
- **Pass a key that's stable across pages** — never one derived from the current route, or the
  group forgets itself the moment the user navigates.
- **Two groups sharing a key sync.** Not guarded, because that's usually what you want when the
  same nav renders in more than one layout.

Storage access is wrapped both ways: hardened browsers and Safari private mode throw on
`localStorage`, and an uncaught throw would take the Alpine component down and stop the group
toggling at all. It degrades to today's behaviour instead. Persistence is per-browser only —
there's no server-side or cross-device state.

### Miscellaneous

| Tag | Notable props |
| --- | ------------- |
| `<atom:copy>` | `value` — copy-to-clipboard button. |
| `<atom:darkmode-toggle>` | Dark-mode switcher. Needs the page to pass `dark` to `<atom:html>` or its layout — without it `window.darkmode()` is never defined. The sidebar layout renders its own toggle when `dark` is set. |
| `<atom:dd>` | Definition list. Child: `<atom:dd.group>`. |
| `<atom:embed>` | `src`, `icon`, `file` — embeds image / video / YouTube / file preview. |
| `<atom:error>` | Plain error message slot. |
| `<atom:html>` | Page boilerplate (see [Page boilerplate](#page-boilerplate)). |
| `<atom:sharer>` | `sites` (array), `url`, `title` — social share buttons. atom does not bundle [sharer.js](https://ellisonleao.github.io/sharer.js): load it on the page (`<script src="https://cdn.jsdelivr.net/npm/sharer.js@0.5.4/sharer.js"></script>`) or the share buttons do nothing (the copy-link button works without it). The default sites (`facebook`, `twitter-x`, `linkedin`, `whatsapp`, `telegram`, `email`) were checked against sharer.js 0.5.4; a site needs an atom icon of the same name. |
| `<atom:whatsapp>` | `number`, `text` — floating WhatsApp button. |

---

## Table selection

Add `checkbox` to a header column and `:checkbox="$item->id"` to the matching cell:

```blade
<atom:table.column checkbox />
...
<atom:table.cell :checkbox="$item->id" />
```

Ticked ids collect in `$_table.checkboxes` and **survive pagination and sorting**, so a
selection can span pages. The header checkbox selects or deselects the current page only.
Bulk-action buttons go in the `checked` slot — that bar replaces the table header while
anything is selected and shows the running count.

### Cross-page "Select all N" — `:select-all`

```blade
<atom:table :paginate="$this->items" :select-all>
```

Once a subset is ticked, a **Select all N** button appears. It sets a `$_table.select_all`
flag rather than materialising an id list, so it costs the same for 20 rows or 200,000.
Ticking an individual row or the header exits the mode.

Requires a `tableQuery()` on the component — the full scoped **and** filtered query:

```php
public function tableQuery()
{
    return Invoice::query()->filter($this->filters);
}

#[Computed]
public function items()
{
    return $this->tableQuery()->toTable();
}
```

Bulk actions then run off `tableSelection()`, which covers both modes:

```php
public function deleteSelected(): void
{
    $this->tableSelection()->delete();
    $this->resetTableCheckboxes();
}
```

### Selection that survives a filter — `:sticky-selection`

By default a filter change, a search, or the trashed toggle **clears the selection** — the
ticked rows may no longer be on screen, and a bulk action over invisible rows is a nasty
surprise. Pass `:sticky-selection` for the opposite trade: keep the ids so a user can build
one batch across several searches (tick 2, search, tick 1 more, act on all 3).

```blade
<atom:table :paginate="$this->items" :sticky-selection>
```

`select_all` still drops on a filter change — it means "everything matching *this* query",
which stops being true the moment the query changes. The trashed toggle still clears
everything, since live and soft-deleted rows aren't one result set. A persistent **Clear
selection** appears in the checked bar, because part of the selection is off-screen and the
user can't untick what they can't see.

The `header` slot also stays on screen instead of being swapped out for the checked bar —
the two stack. Searching *while holding a selection* is the whole flow, and a table that
hides its own search box the moment you tick a row can't support it.

#### Reviewing the batch — "Show selected"

A search hides part of the selection, so the checked bar carries a **Show selected** toggle
that lists the selection instead of the filtered rows, search ignored. Flipping back
restores the search results. Render the rows from `tableRowsQuery()` to wire it up:

```php
#[Computed]
public function items()          // your name — atom never defines or looks for it
{
    return $this->tableRowsQuery()->toTable();   // the selection when the toggle is on
}
```

The computed property is yours to name; `<atom:table :paginate="$this->items">` is the only
thing that has to agree with it. What atom owns is listed under
[Names the trait occupies](#names-the-trait-occupies).

Ticked rows stay unticked-able while the toggle is on, which is how a user prunes a batch
they can no longer find by searching. The flag clears with the selection, and falls back to
the filtered rows if the last row is unticked while it's on — otherwise the table would
empty out and take its own toggle with it.

Note this is a *filter to* the selection, not a union with it: the search keeps returning
what actually matches, so a 500-row batch never floods a result set and the paginator total
stays honest.

**This prop requires `tableSelectionQuery()`** — the scoped but *unfiltered* base the checked
ids resolve against:

```php
public function tableSelectionQuery()
{
    return Invoice::query();                                      // scoped, no filters
}

public function tableQuery()
{
    return $this->tableSelectionQuery()->filter($this->filters);  // + the live filters
}
```

Without it, ids ticked under an earlier filter get silently intersected away by the current
one: the bar reads *3 selected* and the delete hits 1. It defaults to `tableQuery()`, so a
table that doesn't opt in is unaffected. It deliberately does **not** default to a bare
`newQuery()` on the model — that would make the prop work with no override, but stays
tenant-safe only where scoping is a global scope, and the consuming app is the only party
that can say what "unfiltered but still scoped" means.

### Clearing from a custom filter control

`<atom:table.search>`, `<atom:select.filter>` and `<atom:date-picker.range>` already dispatch
the event. A hand-rolled control opts in the same way:

```blade
<input x-on:input="$dispatch('table-filter:changed')" />
```

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
php artisan atom:purge-editor-images            # dry-clean (move to editor-purged/)
php artisan atom:purge-editor-images --dry-run  # list what it would delete, change nothing
php artisan atom:purge-editor-images --force    # delete the editor-purged/ backup
```

A file counts as referenced when its file name (or its percent-encoded form) appears anywhere in a stored editor value, whatever the format around it. The scan splits each form of a value (raw, decoded, percent-decoded) into tokens once, so its cost still follows the size of your data, not the number of files, and it prints a progress line every 1000 rows. A file whose name holds a space, quote, bracket or non-ASCII character (a macOS `Screenshot ... .png` stored through `tiptapStoreImage()`, say) is searched for as a plain substring instead, which is slower when there are many such files.

---

## Editor and chat HTML is untrusted

`<atom:tiptap.chat>` (the chat composer) hands the host **HTML**: `tiptap.getHTML()`, in an `input` event carrying `{ body, files }`. Nothing checks it on the way in. A client that skips the editor and calls `$wire.submit(...)` (or sets the bound property) with its own string stores whatever it likes, and stored and printed as-is that is stored XSS. atom can't see your storage code, so the rule for a host that keeps chat HTML is:

- **Clean it before you store it:** `Jiannius\Atom\Tiptap\Content::sanitize()`.
- **Print it through atom:** `<atom:tiptap.content :content="$message->body"/>` or `Content::render($message->body)`. Both parse the value through the editor's schema and print only what the schema allows.
- **Never** print it with `x-html`, `{!! $body !!}` or `->html()` straight from the column or from the request.

```php
use Jiannius\Atom\Tiptap\Content;

// the chat composer dispatches `input` with { body, files }
public function submit(array $message): void
{
    $raw = $message['body'] ?? '';
    $body = Content::sanitize($raw);

    // '' covers three cases: empty, refused (too big) and failed. Ask which if the user should be told.
    if ($body === '') {
        if (Content::sanitizeRefuses($raw)) {
            $this->addError('body', 'That message is too long.');
        }

        return;
    }

    $this->task->messages()->create(['body' => $body, 'user_id' => auth()->id()]);
}
```

```blade
{{-- reads back through the same schema --}}
<atom:tiptap.content :content="$message->body"/>
```

**This is for HTML, not for the JSON editor.** `<atom:tiptap>` (and the `AsTiptapContent` cast) store **Tiptap JSON**, and `sanitize()` returns HTML. Don't pass JSON editor content through it before storing: that would turn the column into HTML and drop YouTube embeds. JSON is already printed safely by `<atom:tiptap.content>` / `Content::render()`, so store it as JSON and print it through those.

**Rows stored before you adopt `sanitize()` stay unclean.** `sanitize()` only cleans what passes through it from now on. A chat message already in your table keeps whatever it was saved with until you render it through `<atom:tiptap.content>` / `Content::render()` (which cleans on the way out, so old rows print safely), or backfill the column by running each row through `Content::sanitize()`. A host that prints stored HTML with `x-html` is exposed to every unclean row it already holds.

`Content::sanitize(mixed $html, int $maxBytes = 131072, int $maxTags = 5000): string` parses the value through the same schema and the same hardened extensions as `render()`, and returns the schema's own serialisation, so nothing of the input's markup survives except what the editor itself can write. It also accepts a Tiptap JSON document (a string or array with a `type` key) and a `Stringable`; any other string, `'42'` and `'null'` included, is text.

It returns `''` when the input is empty, when it is refused, and when it fails. It refuses (silently, so a client can't flood your logs) HTML over `$maxBytes`, HTML with more than `$maxTags` tags (or a document with more than `$maxTags` nodes and marks), a document that is not a Tiptap document, HTML that carries tiptap-php's reserved `MINIFYHTML` placeholder, and the `<pre>` inputs the parser can't survive (below). Output over four times `$maxBytes` is refused too, so a small `$maxBytes` also caps the output at four times that. Only an unexpected failure is reported through Laravel's `report()`. `Content::sanitizeRefuses($html)` tells a refusal from an empty message.

The limits exist because parsing cost tracks the tag count, not the byte count: about 3 KB of memory per tag or document node in the worst case, so an input of 65,000 `<p>a` (260 KB) needs over 200 MB, and 95,000 `{"type":"paragraph"}` nodes (2 MB of JSON) needs about 290 MB. Measured at the sanitize defaults, the worst shape costs about 16 MB and 0.2 s; a long chat message is a few KB and a few dozen tags. Raise the limits only for a field that holds long documents. The parser also has inputs it takes minutes on or crashes on, which are normalised where real content could have them and refused only where it can't:

- **Long whitespace runs are collapsed, not refused.** Outside a `<pre>`, a run of more than 32 whitespace characters (Unicode spaces included) becomes one space before parsing. HTML collapses whitespace anyway, so nothing a reader sees changes, and content with no such run is passed on byte for byte. Pretty-printed or indented legacy HTML and pasted Word markup render normally (120 KB of spaces used to take 98 s and now takes about 0 s). Inside a `<pre>` nothing is touched: a code block with 300-space indentation, or 120 KB of spaces, is kept exactly.
- **Unclosed `<pre>` tags are closed, not refused.** A few are left alone; once they would make the parser quadratic (5000 in 130 KB took 8 s), one `</pre>` is appended, which is what the HTML parser does at the end of the input anyway.
- **Refused:** a `<pre>` block over 500,000 characters (the parser's regex fails past about 1M), a very large number of `<pre>` blocks in a long input (about 250 code blocks in a 2 MB document), and many `<pre` tags with no `>` after them. Real content has none of these.
- **Repaired, not refused:** a Tiptap document with an attribute the renderer throws on keeps rendering. A heading with no usable `level` becomes level 1, and a `colwidth`, a mention `label` or an `attrs` of the wrong shape is dropped. Only a document that is not a document at all (wrong `type`, `content` not a list, and so on) is refused.

| Kept (as the editor writes it) | Dropped |
| --- | --- |
| Paragraphs, headings, lists, blockquotes, code blocks, line breaks, rules, tables | `<script>`, `<style>`, `<svg>`, `<form>`, `<object>`, `<embed>`, `<link>`, `<meta>`, `<base>`, comments, CDATA and any tag the editor has no node for |
| Bold, italic, strike, underline, code, sub/superscript | Every event handler (`onclick`, `onerror`, ...) and every attribute the schema doesn't define, `id` and `srcdoc` included |
| Links to `http`, `https`, `mailto`, `tel` (and the other protocols Tiptap allows, such as `ftp` and `sms`) and relative URLs, with atom's own `target` / `rel` | Links whose href is `javascript:`, `vbscript:`, `data:` or another scheme (the text stays, the link goes); a link's own `class`, `rel` or `target` |
| Images with an `http(s)`, relative or raster `data:image/` source | An image whose source is `javascript:`, `data:image/svg+xml` or another scheme (the whole image goes) |
| `color`, `background-color`, `font-size`, `text-align`, image `width` / `float` / `align` when the value passes the allow-list | `style` declarations that fail it (`position`, `url(...)`, `expression(...)`, out-of-range sizes), and every `class` except `mention`, a font-size preset and `language-*` |
| Mentions (`<span class="mention" data-type="mention" data-id data-label>`), escaped | The `data-mention-suggestion-char` marker the JS adds |
| YouTube embeds in a JSON document, rebuilt from the video id | Any `<iframe>` in HTML input, and any iframe that isn't YouTube |
| Text that looks like markup (`&lt;script&gt;`), as escaped text | Invalid UTF-8 (scrubbed, the text around it kept) |

Two things it does not do:

- **It doesn't check a mention's `data-id`.** It is escaped, but it is whatever the client sent. If a mention does anything (a notification, a link to a record), look the id up on the server, scoped to what the current user may mention, and ignore the rest.
- **It doesn't check that the caller may post.** Authorise the request as you would any other write.

### Upgrading: `render()` and `<atom:tiptap.content>` refuse what they can't parse safely

`Content::render()` (and so `<atom:tiptap.content>`) reads stored content, and it now renders **empty, and logs a warning**, for stored content that is over its limits, has a `<pre>` block or count of `<pre>` tags the parser can't survive (see above; long whitespace runs and unclosed `<pre>` tags are normalised, not refused), carries the minifier's placeholder token (`MINIFYHTML`), or is not a Tiptap document. Nothing is reported through `report()`. The warning (`Log::warning`, once per value per process) carries the reason, the size and the limits, so check your log after upgrading if a page that used to show content is blank.

The defaults are 2 MB and 20,000 tags (HTML tags, or nodes and marks of a JSON document; worst case about 64 MB of memory for HTML and up to about 82 MB for a JSON document). Raise or lower them for your app in `config/atom.php`:

```php
return [
    'editor' => [
        'render_max_bytes' => 4 * 1024 * 1024,
        'render_max_tags' => 40000,
    ],
];
```

Memory grows with the tag limit (about 3 KB per tag or node), so raising `render_max_tags` needs a matching increase in PHP's `memory_limit`: at the defaults a page that renders one such document can peak near 64 MB (HTML) or 82 MB (JSON) above baseline, and doubling the limit roughly doubles that.

A long legitimate document (a 1,000-row price table, a 3,000-paragraph article) is within the defaults. A value that is not a Tiptap document at all (one only a hostile client can send) renders empty without a warning.

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

### Upgrading to 3.29.12

`<atom:sharer>` printed `title` and `url`, and `<atom:embed>` printed an image `src`, into HTML attributes without escaping, so a value containing a quote could add attributes to the element. They are escaped now. `<atom:embed>` also refuses a `src` whose scheme is not `http` or `https` (relative and protocol-relative URLs still work) and shows the file icon instead.

- **Nothing to do** if you pass plain text and normal URLs. `&`, quotes and CJK text reach `sharer.js` as typed; it reads the attributes with `getAttribute()` and URL-encodes them itself.
- **A `title` or `url` that is already HTML-encoded** (`Tom &amp; Jerry`) is now shown literally. Pass the raw text (`Tom & Jerry`).
- **An embed `src` on another scheme** (`javascript:`, `data:`, `blob:`) no longer renders. Nothing in atom's own `file->url` produces one.
- **`embed`'s `icon` prop is trusted markup when it starts with `<svg`.** Only pass developer-written SVG; never a database or user value.
- **`<atom:sharer>` needs [sharer.js](https://ellisonleao.github.io/sharer.js) on the page.** atom has never bundled it. Without it the component used to throw `Sharer is not defined` in the console; it now stays quiet, but the share buttons do nothing until you load it, for example `<script src="https://cdn.jsdelivr.net/npm/sharer.js@0.5.4/sharer.js"></script>`. The copy-link button does not need it.
- **Two share buttons that never worked are fixed** (checked against sharer.js 0.5.4). The X button sent `data-sharer="twitter-x"`, a key sharer.js does not have, so it did nothing; it now sends `x` and keeps its icon and label. The email button opened a blank popup for its `mailto:` link; it now carries `data-link="true"`, so the mail client opens. sharer.js opens its popups without `noopener` and has no option for it, and atom does not patch it: the popups only ever go to the social sites' own addresses.
- **`Content::sanitize()` and `Content::render()` no longer report an error for input with nothing to render.** A script-only, style-only, comment-only, whitespace-only, `<html></html>` or head-only value, or one with the same mark twice on a node (`<code><code>c`), used to hit `report()` on every call (a `TypeError` or `ErrorException` from tiptap-php). They now return `''` (or the content, once the duplicate mark is collapsed) without a report or a log line. If you filtered these out of your error tracker, you can drop the filter.

### Upgrading to 3.29.9

Remote and static select options used to be turned into HTML without escaping `label`, `caption` or `color`, so a value a user typed (a contact name, say) rendered as markup. They are escaped now, including the native select's `<option>` text.

- **Nothing to do** if your option sets return plain `label`/`caption` text.
- **A label that already contains HTML entities** (`Tom &amp; Jerry`) is now shown literally. Return the raw text (`Tom & Jerry`).
- **The `html` key stays trusted.** If a set builds its own `html`, atom still passes it through untouched, and you must wrap every interpolated user or database field in `e()`. Grep your `App\Actions\GetOptions` (and any code that sets `html` or `selected_html` on an option) for interpolated model fields. See [Option text is escaped](#option-text-is-escaped-the-html-key-is-trusted).

### Upgrading to 3.25

3.25 makes the trait's `action()` helper `protected`. It was public, and Livewire exposes
every public method a component declares — a trait method included, because reflection
reports the *using class* as the declaring class. So on every component using
`AtomComponent`, in every app:

```js
$wire.action('SomeAction', { method: 'whicheverMethod', ...params })
```

reached any `App\Actions\*` class and any method on it, with no `WebAction` contract, no
`authorize()`, and no allowlist. That is the hole 3.19 closed on `POST /atom/action`,
standing open through Livewire the whole time. On a guest-facing page it was reachable
without signing in.

**What breaks:** JS that calls the helper on the wire proxy. Find it with **both** spellings
— Livewire proxies an exposed method under its own name, so the obvious grep misses most of
it:

```bash
grep -rn "\$wire\.action(\|@this\.action(\|call('action'\|call(\"action\"" resources/
```

**Migration**, per hit: move the call to the public route, which has been the gated path
since 3.19 — `window.atom.action(name, params)` — and opt the action in with
`implements \Jiannius\Atom\Contracts\WebAction` plus an `authorize()`. See
[From the browser](#from-the-browser). If the call passed `method`, split that method into
its own action; the route ignores `method`.

**What does not change:** server-side `$this->action(...)` inside a component, and
`atom()->action(...)` from anywhere. Both still reach any action and any method — being
protected only closes the browser door.

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

#### Option text is escaped; the `html` key is trusted

The listbox renders each option's markup with `x-html`. An option is a `value` plus a `label`, and optionally `caption`, `avatar` and `color`. Atom builds the markup from those fields and **escapes every one of them**, both on the server (`GetOptions::getOptionHtml()`) and in the client-side fallback (`select.js`). A contact name a user typed renders as text, whatever it contains. Return the raw text in `label` and `caption`; don't pre-escape it, or it is escaped twice. `color` is checked as well as escaped: only `#hex`, `rgb()`/`rgba()`, `hsl()`/`hsla()` or a plain colour name draws a swatch, and anything else (`red; position: fixed`) draws none.

An option may instead carry its own `html` (and `selected_html`, the version shown in the closed trigger). That key is the escape hatch for a custom layout, and it is **trusted**: atom passes it to the browser untouched, so you own escaping it. Wrap every user or database value you put in it with `e()`:

```php
['value' => $doc->id, 'label' => $doc->name, 'html' => '<div>'.e($doc->name).' <small>'.e($doc->number).'</small></div>']
```

An unescaped field in `html` is a stored XSS for everyone who opens that select. Keep `label` plain even when `html` is set: the label is what search filters on and what the chips show.

The native variant prints `label` into `<option>`, and escapes it.

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
- `atom.css` defines `[x-cloak] { display: none !important }`, so `x-cloak` works out of the box in any app loading atom's stylesheet — Tailwind ships no such rule, and without one the attribute is inert. The `!important` matters: `[x-cloak]` and a utility like `.flex` have equal specificity, so otherwise the winner is whichever stylesheet loads last. Alpine strips the attribute on init, so the rule only ever hides pre-boot markup — which also means **`x-cloak` must sit on an element Alpine will initialise** (one carrying `x-data`, or inside one), or it stays hidden forever. `tests/Feature/XCloakTest.php` enforces both halves.

---

## Artisan commands

| Command | Purpose |
| ------- | ------- |
| `atom:purge-editor-images` | Walks `App\Models\*`, finds columns cast as `AsEditorContent` or `AsTiptapContent`, and moves any unreferenced editor image to `editor-purged/` on the local disk before removing from the configured disk. A file is referenced if its name appears anywhere in a stored value. |
| `atom:purge-editor-images --dry-run` | Lists what it would delete and changes nothing. |
| `atom:purge-editor-images --force` | Empties the `editor-purged/` backup folder. |

---

## License

MIT. See `LICENSE.md`.
