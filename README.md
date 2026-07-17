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
| `dark`        | Adds `class="dark"` to `<html>`.                                    |
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
| `<atom:select>` | `name`, `label`, `caption`, `variant` (`native` (default), `listbox`, `filter`), `required`, `error`, `prefix`, `suffix`, `inline`. Children: `<atom:select.option>`, `<atom:select.group>`. |
| `<atom:checkbox>` | `name`, `label`, `caption`, `align` (`start`, `center`, `end`). Group with `<atom:checkbox.group>`. |
| `<atom:radio>` | Same as checkbox. Group with `<atom:radio.group>`. |
| `<atom:toggle>` | `name`, `label`, `caption`. Group with `<atom:toggle.group>`. |
| `<atom:slider>` | Range slider on a native `<input type=range>`. `name`, `label`, `caption`, `min` (0), `max` (100), `step` (1), `value`, `bubble` (value shown on interaction), `labels` (min/max at track ends), `required`, `disabled`. `wire:model` binds via `x-modelable`. |
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
| `<atom:heading>` | `size` (`sm`, `default`, `lg`, `xl`, or `<n>px`), `level` (`h1`..`h6`). |
| `<atom:subheading>` | Same as heading, smaller / muted. |
| `<atom:caption>` | Small muted text. |
| `<atom:label>` | `icon`, `align`. |
| `<atom:avatar>` | `src`, `name`, `initial`, `square` (default `true`), `size` (`xs`..`xl`). Stack: `<atom:avatar.group>`. |
| `<atom:badge>` | `status` (enum-aware), `size` (`xs`, `default`, `lg`), `icon`, `color`, `label`. Group: `<atom:badge.group>`. |
| `<atom:card>` | `inset`, `subtle`, `divided`, `variant` (`stats`, `chart`), `heading`, `data`, `indicator`, `trend`, `color`. |
| `<atom:callout>` | `icon`, `heading`, `content`, `variant` (`info`, `success`, `warning`, `danger`, `error`), `closeable`. |
| `<atom:skeleton>` | Animated loading block. |
| `<atom:placeholder-bar>` | `size="100%x20px"`. |
| `<atom:empty>` | `icon` (default `inbox`), `size`, `subtle`, `heading`, `subheading`. |
| `<atom:profile>` | `name`, `avatar`, `email`, `size`. |
| `<atom:icon.*>` | 200+ icons. Examples: `<atom:icon.check/>`, `<atom:icon.arrow-left/>`, `<atom:icon.delete/>`. Browse `components/icon/`. |
| `<atom:logo.*>` | Payment / brand marks: `apple-pay`, `fpx`, `google-pay`, `ipay88`, `master`, `senangpay`, `stripe`, `tng`, `visa`. |

### Feedback & overlays

| Tag | Notable props |
| --- | ------------- |
| `<atom:modal>` | `name` (required for `atom()->modal($name)` to find it), `inset`, `dismissible`, `closeable`. Trigger via `<atom:modal.trigger name="...">` or `$this->modal('name')->show()` from PHP. |
| `<atom:alert>` | Window-bound. Triggered by `atom()->alert(...)`. Config keys: `heading`, `subheading`, `message`, `variant`, `button`, `onDismissed`. |
| `<atom:toast>` | Window-bound. Triggered by `atom()->toast(...)`. Config keys: `message`, `variant` (`success`, `warning`, `danger`), `delay` (default 3000), `position` (`top`, `bottom`, `center`), `align`. |
| `<atom:confirm>` | Window-bound. Triggered by `atom()->confirm(...)`. Supports `password`, `passphrase`, optional reason field. Wires `onAccepted` / `onRejected` to Livewire methods. |
| `<atom:tooltip>` | `interactive`, `position` (`top`, `bottom`, `left`, `right`), `align` (`start`, `center`, `end`), `content`, `kbd`, `toggleable`. |
| `<atom:dropdown>` | `position` (`bottom`, `top`), `align` (`start`, `end`), `locked`. |
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
| `<atom:layouts.auth>` | Centered auth layout (login, register, forgot password). |
| `<atom:layouts.sidebar>` | App layout with sidebar + top bar. |

### Miscellaneous

| Tag | Notable props |
| --- | ------------- |
| `<atom:copy>` | `value` — copy-to-clipboard button. |
| `<atom:darkmode-toggle>` | Dark-mode switcher (works with `class="dark"` on `<html>`). |
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

JS-callable PHP classes. Atom mounts `POST /atom/action/{name}` automatically, and the front-end exposes `window.atom.action('Foo.Bar', params)`.

```js
// in Alpine or any component
const result = await window.atom.action('Customer.Search', { q: 'jane' });
```

```php
// app/Actions/Customer/Search.php
namespace App\Actions\Customer;

class Search
{
    public function handle($params)
    {
        return \App\Models\Customer::query()
            ->where('name', 'like', '%'.$params['q'].'%')
            ->take(10)
            ->get();
    }
}
```

Resolution order:
1. `App\Actions\{Name}` (host app — wins).
2. `Jiannius\Atom\Actions\{Name}` (package fallback).

Pass `method` in `$params` to invoke something other than `handle`.

You can also call from PHP: `atom()->action('Customer.Search', ['q' => 'jane'])`.

### `GetOptions`: shared option lists

`Jiannius\Atom\Actions\GetOptions` loads option arrays from JSON files. It merges:

- Package JSON at `<package>/json/{name}.json`
- App JSON at `resource_path('json/{name}.json')`

with the app-side values taking precedence. Results are cached under `_options`.

Built-in JSON sets: `countries`, `postcodes`, `colors`. Override any of them by creating `resources/json/colors.json` in your host app.

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

- `window.atom.action(name, params)` — POST to `/atom/action/{name}`.
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
