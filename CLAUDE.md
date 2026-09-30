# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jiannius/atom` is a Laravel package (composer name `jiannius/atom`, PSR-4 namespace `Jiannius\Atom\`) that ships a Tailwind + Alpine + Livewire UI component library. It is consumed by other Laravel apps via `composer require`; this repo is the library itself, not a host app. There is no `.env`, no app boot, no test suite wired up — Orchestra Testbench is in dev deps but unused.

## Common commands

```bash
# Build front-end assets (Vite) → dist/assets + dist/manifest.json
npm run build

# PHP deps
composer install
```

There are no lint, test, or dev-server scripts defined. `vite.config.js` uses `laravel-vite-plugin`, but the package has no host Laravel app to serve from — `npm run build` produces a static `dist/` that is committed and served at runtime via the package's own `/atom/{file}` route.

The package's own artisan command (available in any consuming app):

```bash
php artisan atom:purge-editor-images        # dry-cleans unreferenced editor images
php artisan atom:purge-editor-images --dry-run # lists what it would delete, changes nothing
php artisan atom:purge-editor-images --grace=48 --path=modules/Notes/Models # keep files under 48h old; also scan a models directory
php artisan atom:purge-editor-images --force # empties the editor-purged backup folder
```

## Architecture

### Service-provider wiring (`src/AtomServiceProvider.php`)

`boot()` registers everything; read it first when something seems to come from nowhere:

- Loads `routes/web.php`, `database/migrations` (no directory exists yet — referenced for future use), `lang/`, and `resources/views/` under the `atom` view namespace.
- Registers anonymous Blade components in `components/` under the `atom` namespace, so `components/button/index.blade.php` is reachable as `<x-atom::button>` **or** as `<atom:button>` (see Tag compiler below).
- Swaps Laravel's `Date` facade to use `Jiannius\Atom\Services\Carbon`.
- Mixes in macros onto Eloquent `Builder`, Query `Builder`, `ComponentAttributeBag`, `Request`, `Str`, `Stringable`, `Arr` (`src/Macros/*`). These macros are how component blade files get methods like `$attributes->modifier()`, `$attributes->size()`, etc. — if you see an unfamiliar method on an attribute bag in a component, check `src/Macros/ComponentAttributeBag.php` before assuming it's framework.
- Boots `Services\Asset`, which exposes the public route `GET /atom/{file}` serving files from `dist/assets/` with `Cache-Control: immutable`. `atom()->asset()->version($name)` looks up the hashed filename in `dist/manifest.json`. Consuming apps reference assets by name, not path.
- Mounts a public `POST /atom/action/{name}` endpoint that the JS uses to invoke actions remotely — unauthenticated, and gated per-action by the `WebAction` contract (see "Actions" below).

### The `<atom:...>` tag syntax (`src/Services/TagCompiler.php`)

Custom Blade precompiler (inspired by livewire/flux) translates `<atom:button>`, `<atom:icon.add/>`, `<atom:button.group>` into the corresponding `<x-atom::...>` component invocations before the normal Blade compile runs. Dot-paths map to subdirectories: `<atom:button.group>` → `components/button/group.blade.php`. This syntax is preferred throughout `components/` over `<x-atom::...>`.

### The `Atom` singleton (`src/Atom.php`, aliased as `app('atom')` / via the `atom()` helper indirectly)

Single entry point for cross-cutting UI operations that need to dispatch Livewire events from PHP:

- `atom()->modal($name)->show()/slide()/close()` — dispatches `atom-modal-show` / `atom-modal-close` on the *current* Livewire component.
- `atom()->toast(...)`, `atom()->alert(...)`, `atom()->confirm(...)` — dispatch the matching `atom-toast-show` / `atom-alert-show` / `atom-confirm-show` events. All `heading`, `subheading`, `message` strings are passed through `t()` (translation helper).
- `atom()->action($name, $params)` — resolves an Action class from `App\Actions\{Name}` *or* `Jiannius\Atom\Actions\{Name}` and invokes `handle($params)` (or `$params['method']`). Its sibling `webAction()` is the gated version behind the public endpoint (see "Actions" below).
- `atom()->mail(...)`, `atom()->breadcrumbs()`, `atom()->broadcast()`, `atom()->sitemap()`, `atom()->asset()`.

The `Atom` class is registered both by FQN and by the `'atom'` container alias — both work.

### Livewire integration: `Traits\AtomComponent`

Consuming Livewire components mix this in to get `WithPagination + WithFileUploads`, plus reserved state buckets:
- `$_breadcrumbs` — populated by an optional `breadcrumbs()` method during `mountAtomComponent()`.
- `$_table` — sort, checkboxes, max_rows, show_trashed (consumed by `<atom:table>`).
- `$_editor.images` — buffered temporary upload URLs from Tiptap editor (see Editor flow below).

Helper methods on the trait (`modal()`, `toast()`, `alert()`, `confirm()`, `action()`) all delegate to `app('atom')`. `action()` is `protected` — see "Actions" below.

### Signed `raw:` table sort

`$_table.sort.column` is a plain public property (no `#[Locked]`), so a client can `$wire.set()` it to anything. A plain column name is safe either way — `toTable()`'s `orderBy()` path lets Laravel quote it — but a `raw:` value (declared as `<atom:table.column sort="raw:some_sql_expr">`) goes straight into `orderByRaw()`, so a tampered one is SQL injection (issue #54: boolean-blind exfiltration via row order). `components/table/column.blade.php` signs a `raw:` value before it ever reaches the browser — `Services\TableSort::sign($expression)` HMACs it (against `config('app.key')`) into `raw:<64-hex-hmac>:<expression>` — and that signed string is what the Alpine click handler round-trips through `$wire.set('_table.sort.column', ...)`. `AtomComponent::updatingAtomComponent()` is the gate: it inspects `_table.sort.column`, `_table.sort`, and `_table` wholesale (Livewire's `updating` hook fires once per dotted path a `$wire.set()` targets, so all three shapes need checking) and refuses (403) any client update that would leave an unsigned or tampered `raw:` value there. The gate only runs for a client-originated update — a host mounting `_table['sort']['column'] = 'raw:...'` directly in PHP never touches Livewire's `updating` hook, so an *unsigned* `raw:` value reaching `toTable()` can only have come from PHP and stays trusted; `TableSort::expression()` verifies a signed one and strips the signature before it reaches `orderByRaw()`, returning `null` (no sort applied) on a bad signature. The sort direction on that branch is also clamped to `asc`/`desc` there (anything else defaults to `asc`) since it used to be spliced into the raw SQL unvalidated too.

### Editor content lifecycle (Tiptap + Livewire uploads)

A subtle, two-phase flow worth understanding before touching the editor:

1. While the user types, image uploads land in Livewire's temporary disk and are echoed back into `_editor.images` as `temporaryUrl()` strings (handled in `updatedAtomComponent`). The editor HTML carries `<img src="/livewire-{hash}/preview-file/...">` URLs (Livewire 4 prefixes internal URLs with an APP_KEY-derived hash).
2. Only when the editor's HTML column is *saved* through Eloquent does `Casts\AsEditorContent::set()` regex out each `/livewire-{hash}/preview-file/` URL, resize via Intervention Image (max width 1000, q=80), persist to `Storage::disk(env('FILESYSTEM_DISK'))` under `<configured folder>/editor/`, rewrite the URL in the HTML, and serialize the result.
3. Stored values are `serialize()`d strings; `get()` unserializes lazily, falling back to the raw value if it isn't serialized. Every unserialize (both casts, `atom:tiptap-migrate`) goes through `Tiptap\Content::unserialize()`, which passes `allowed_classes => false`: a payload holding an object (at any depth) reads back as `null`, never as an instance. `AsTiptapContent::set()` also refuses a client-sent serialized string (a serialized HTML string is unwrapped, any other serialized value is dropped). Stored content is untrusted on the render side too: `Content::render()` reports and renders empty on a document it can't handle, and every value that reaches `style`, `class`, `target`/`rel` or an iframe `src` is checked against an allowlist — `Tiptap\StyleValue` plus the `Atom*` extensions in `src/Tiptap/Extensions/` (`AtomColor`, `AtomHighlight`, `AtomTextAlign`, `AtomLink`, `AtomCodeBlock`, `AtomImage`, `FontSize`, `Youtube`). A new extension that renders a stored attribute must validate it the same way.
4. **What the browser sends is untrusted too, and atom can't intercept the host's storage.** The chat composer (`<atom:tiptap.chat>`) hands the host raw `tiptap.getHTML()` in an `input` event (`{ body, files }`), and any client can call `$wire.submit()` with its own string. `Content::sanitize(mixed $html, int $maxBytes = 131072, int $maxTags = 5000): string` is the host's gate **for HTML (chat mode)**: it parses the value through the same `Content::extensions()` as `render()` (one list, so the two can't drift) and returns the schema's own serialisation, so a script, handler, failing `style`/`class`, `javascript:`/`data:` href, non-YouTube iframe or unknown tag can't come back out. It is not for the JSON editor: `<atom:tiptap>` / `AsTiptapContent` store JSON, which `render()` already prints safely, and sanitising it would turn the column into HTML and drop YouTube. A host must `sanitize()` chat HTML before storing, or print through `<atom:tiptap.content>` / `Content::render()`, and never through `x-html` or `{!! !!}` straight from the column; rows stored before it adopts `sanitize()` stay unclean until it renders them through `render()` or backfills them. It returns `''` for empty, refused and failed input alike (`Content::sanitizeRefuses()` tells a refusal apart). Refusals are silent, so a client can't flood the log; only an unexpected exception is `report()`ed. Input that is not a string, a `Stringable`, or an array with a `type` key is `''`; any string that is not a JSON document (`'42'`, `'null'`) is text. **The cost limits are the DoS defence, and they live in the shared `convert()`.** Parsing cost tracks the tag count, not the bytes (about 3 KB of memory per tag worst case; 65,536 `<p>a` was 212 MB and 2.7 s, a fatal at 128M), so `inspect()` refuses HTML over `maxBytes` (128 KB), over `maxTags` `<` characters (5000: worst shape ~16 MB and ~0.2 s), or carrying tiptap-php's `MINIFYHTML` placeholder, and `convert()` refuses output over 4x `maxBytes` (a small `maxBytes` caps the output at 4x that). A JSON document is bounded the same way by node count (`documentProblem()` counts nodes and marks against `maxTags`; a `{` count spares `json_decode()` a document that is over it anyway): `{"type":"paragraph"}` x 95,000 is 2 MB and used to fatal at 128M (~3 KB per node). `inspect()` also validates the document shape: a value that is not a document (root not a `doc` with a `content` list, a non-string `type`, `content`/`marks` not lists, `text` not a string) is a silent refusal, and `report()` is only left for a failure nobody expected. **Where real content could trip a guard, it is normalised, not refused.** Attributes tiptap-php throws on are repaired in `normaliseAttributes()` so the rest of the document renders: a heading with no usable `level` becomes 1, and a `colwidth` that is not a list of scalars, a mention `label` or `id` that is an array, or an `attrs` that is not a keyed array is dropped. Minify's own regexes are quadratic or fail on some input, so `inspect()` also handles those: `collapseWhitespace()` collapses a whitespace run over 32 characters, outside a `<pre>`, to one space (Unicode spaces included, since PHP compiles `\s` with `u`; `^\s+|\s+$` rescans a run from every position, so 120 KB of spaces took 98 s; HTML collapses whitespace itself, content with no such run is passed on byte for byte, and the `<pre>` alternative in its regex is Minify's own pattern so exactly the blocks Minify sets aside are left intact, since Minify swaps those out before it trims and a run inside one costs nothing); `normalisePre()` (a block ends at the literal `</pre>`, as in Minify's regex, so `</pre ` or `</prex>` leaves it unclosed) closes unclosed `<pre>` tags by appending one `</pre>` once they would cost over `MAX_UNCLOSED_PRE_COST` (5000 in 130 KB took 8 s; the HTML parser closes them at the end anyway, and a few are left alone), and refuses only what it can't fix: a `<pre>` block over 500,000 characters (past ~1M `pcre.backtrack_limit` returns null, a TypeError on every view), more than `MAX_PRE_COST` (blocks times input length; the placeholders are `str_replace()`d back over the whole input: 10000 blocks in 120 KB took 2.3 s; the budget is ~250 blocks in 2 MB), and many `<pre` with no `>` after them. `render()` reads stored rows through the same `convert()` with looser, configurable limits (`atom.editor.render_max_bytes` / `render_max_tags`, defaults `RENDER_MAX_BYTES` 2 MB and `RENDER_MAX_TAGS` 20000: worst case ~64 MB for HTML, up to ~82 MB for a JSON document: raising them needs a matching `memory_limit`), so a stored payload can't down every page that prints it. Because that blanks stored content, `render()` logs every refusal with `Log::warning` (not `report()`) with the reason, the size and the limits, once per value per process (`$logged`, capped at 100); `sanitize()` refusals stay silent, and so does a malformed document. `atom:tiptap-migrate` skips a row that `Content::carriesPlaceholder()`. The placeholder: `Tiptap\Utils\Minify` swaps every `<pre>` for `%MINIFYHTML<md5(REQUEST_TIME)>N%` and `str_replace()`s them back in order; the hash is guessable and a placeholder written inside a `<pre>` expands again per nesting level (12 levels x 10 fatals 128M from ~4 KB). Only HTML reaches Minify, so a JSON document's text can carry the token harmlessly. Invalid UTF-8 is scrubbed first because Minify's `/u` regexes return null on it (a TypeError). It does not validate a mention's `data-id` (escaped, but client-chosen), so a host acting on mentions looks the id up itself. `AtomImage` drops the whole image when its `src` isn't `http(s)`, relative or a raster `data:image/` URI (`isSafeSource()`), and `AtomMention` parses `data-id="0"` as `"0"`, not as no id. HTML `<iframe>` (YouTube included) is dropped on parse — the `div[data-youtube-video] iframe` selector never matches under tiptap-php — so an embed survives only in a JSON document. `tests/Feature/ContentSanitizeTest.php` checks the output structurally (allow-lists of tags, attributes per tag, style properties, classes per tag and URL shapes) rather than grepping for known-bad strings, holds the worst-case shapes at the limits to a memory and time bound, and its chat corpus is real `getHTML()` output captured from the composer.
5. `atom:purge-editor-images` reads columns cast as `AsEditorContent` **or** `AsTiptapContent` and moves anything in the editor folder that isn't referenced to `editor-purged/` on the local disk before deleting it. Rules worth knowing before touching it:
   - **What is inside a column is not assumed from its cast.** `<atom:editor>` aliases `<atom:tiptap>` and emits Tiptap JSON, so a host still on `AsEditorContent` stores `serialize(<JSON>)` (humblebear's `Task::description`), and a Tiptap column can still hold legacy serialized HTML. `AsTiptapContent::imageSources()` therefore reads every value both ways (HTML: quoted/unquoted `src`, `srcset`, `href`; JSON: image nodes plus any `src`/`href`/`srcset`, e.g. link marks) and never treats invalid JSON as an error.
   - **The deciding rule is a substring backstop, not that extraction.** A listed file is referenced if its file name (or its `rawurlencode`d / `urlencode`d form, or, for a name JSON writes differently, its `json_encode`d form in both hex cases: a truncated document cannot be decoded, so `tr-ünï.jpg` is stored as `tr-\u00fcn\u00ef.jpg`) appears in a stored value's raw text, its decoded text, or their percent-decoded forms (three levels). Names are unique (`random-timestamp.ext`), so this can only over-keep, and it is immune to format (CSS `url()`, `poster=`, a truncated document, `%2520` double-encoding, a src with or without the disk folder). Never replace it with a full-path or parsed-only match: that is exactly how Tiptap images, and JSON inside legacy columns, were once deleted.
   - **How the backstop stays fast.** A plain `str_contains()` of every unfound file against every row costs files x rows x value size, and orphans are never found, so they stay in the search for every row (4000 files x 10000 rows of 6 KB took 49 s, a large host would take hours). `findNamesIn()` instead splits each form of a value once into tokens (`TOKEN_SPLIT`: any run of characters that is not an ASCII letter, digit or `. _ - % +`; **every non-ASCII byte splits**, so a name beside a fullwidth colon, a curly quote, an NBSP, U+3000 or an emoji is still a token) and looks each token up in a hash of the needles, so the cost follows the length of the data. The forms are the raw and decoded value and three levels of percent-decoding, each level read with both `rawurldecode()` and `urldecode()` (a whole-value `urlencode()` writes a space as `+`). A name glued to a prefix or suffix is still found: a token is also looked up as every span that begins at its start or after a `-`, `_`, `.` or `+`, and ends at its end or before one (`thumb-NAME`, `NAME.webp`, `NAME-2x`, `a+NAME`); and a variant of each form with every JSON escape (`\uXXXX`, `\n \t \r \b \f`) turned into `/` is scanned too, so `\u002fNAME` or `\nNAME` is not glued (`JSON_ESCAPES`; a variant PCRE cannot make is skipped, the form itself is still searched). A needle that itself holds a delimiter cannot be a token, so those alone are searched as substrings: a name with a space, a quote, a bracket or **any non-ASCII character** (`日本語.png`, `tr-ünï.jpg`; their `rawurlencode`d forms are ASCII and stay on the fast path), the `json_encode`d needles (they hold a backslash), and a host-named file such as a macOS `Screenshot 2026-09-30 at 10.00.00.png` stored through `tiptapStoreImage()`. Those are slower (needles x forms x value size), so a host whose files are mostly like that gets the old cost. A value that cannot be split (PCRE limit) falls back to the substring search. **This deliberately relaxes the old "can only over-keep" invariant.** The two agree on every reference a delimiter, a non-ASCII byte, a `- _ . +` boundary or the start/end of the value bounds. The token rule no longer keeps a name that is glued to ASCII letters, digits or `%` with no `- _ . +` or delimiter between: `xNAME`, `NAMEx` (`NAME.jpgx`), `NAME` after a `%09`/`%20` that the three decode levels did not reach, or after any other alphanumeric run. The old rule kept those by accident (`photo.jpg` was "referenced" by the text `myZphoto.jpg`). None can be a reference atom itself writes: it stores a name only as the last segment of a URL or `src`, preceded by `/` (`\/`, `\u002f` and `%2F` included) and followed by a quote, `?`, `#`, whitespace, `)` or the end, and a host's own decoration (`thumb-NAME`, `thumb_NAME`, `NAME.webp`, `NAME-2x`, `a+NAME`) is bounded by one of `- _ . +`. A host that concatenates a name straight onto ASCII alphanumerics (`img123NAME`) is not seen. **A needle stands for a list of files** (`needle => names`): `a b.jpg` urlencodes to `a+b.jpg`, which can be another file's name, and a hit keeps every file the needle could stand for (over-keeping is the accepted direction; last-owner-wins used to delete the other one). `forgetNames()` removes only the found name from a shared needle. `tests/Feature/PurgeEditorImagesMatcherTest.php` holds the matcher to the old rule: it keeps the old substring matcher (as `purgeReferenceMatch()`, never in `src/`) and runs it and the production one over a seeded random corpus (HTML, JSON, CSS `url()`, `srcset`, `poster`, CSV, query strings, prefixes, suffixes, CJK punctuation, NBSP, U+3000, emoji, JSON control escapes, every encoding, truncation, whole-value `rawurlencode`/`urlencode` up to three levels, noise and near-misses). On that corpus the two keep the same set, value by value and over a whole run, when the old matcher is given the same forms; on a second corpus that adds names glued onto letters and digits and four levels of encoding, the production matcher keeps a superset of the ORIGINAL matcher's set except for names with no bounded occurrence in any form (`purgeHasBoundedOccurrence()`), and that loss is non-zero, so the test is not vacuous. The corpus draws from its own seeded `\Random\Randomizer`, so it never reseeds the global generator; its generated names start with a letter that no percent escape can supply, or a near-miss completed by the `%20` of an encoded joiner is an accident the old rule kept. If you change a delimiter, a boundary or a needle form, rerun it with more seeds. Progress is printed every `PROGRESS_EVERY` rows per model. **Memory is bounded by the value, not by its shape.** A value is tokenised `TOKEN_CHUNK` (256 KB) at a time, cut at the first delimiter past the chunk so no token spans a cut; a token's spans are walked with a sliding window of starts within the longest needle, not arrays of every boundary; and a value over `URLDECODE_MAX_BYTES` (1 MB) is not also read with `urldecode()` (the `rawurldecode()` levels still are). A 3.2 MB value of `%2B%2B+a` peaked at 79 MB (26x its size) before, 9 MB (3x) after; `PurgeEditorImagesMatcherTest` holds three shapes to under 8x. **The scan finishes before the first deletion** (`handle()` reads every model, then walks the files), so a memory fatal in it can never follow a deletion: keep every move and delete below the `getImagesFromModels()` call.
   - **Each cast names its own disk and folder** (`disk()`/`diskName()`/`folder()`, used by both the cast and the command), so the two can differ. Every disk's file list is taken BEFORE the database is read, so an image saved mid-scan is never called unreferenced.
   - **`--grace=<hours>` (default 24)**: a file modified more recently is kept. The casts write the file on attribute assignment, not on save, so an unsaved or failed form leaves a file that is about to be referenced. `--grace=0` disables it. The grace period uses the file's **mtime**, so a file restored from backup or rsynced with an old mtime is not grace-protected.
   - **Which models are scanned:** every class declared under `app/Models` (subdirectories included; every declaration in a file counts, so an abstract base beside a model is fine; files declaring no class are skipped; an empty `--path=` is refused), plus any directory given with `--path=` (repeatable, absolute or relative to `base_path()`, e.g. a `modules/*/Models` folder). A model outside those directories is invisible, so its images look unreferenced: list its directory with `--path`. The run prints the models and columns it found; check that list before trusting a run.
   - **`tiptapStoreImage()`:** a model that defines this hook stores its images wherever the hook says. Anything outside `<disk>/editor/` is never listed, so it is never purged; anything the hook puts inside that folder is only safe if the model has an editor column the command can see.
   - **Fails safe:** a model, column, `--path` directory, model directory or disk it cannot read (a failed unserialize, a query error, an unlistable disk, an unreadable subdirectory) aborts the run before any file is touched (never "unreadable = unreferenced"); with no editor column found it deletes nothing; a file it cannot back up is kept. `--dry-run` lists what would go.

If you change the cast, also update the purge command's scanning logic — they are coupled.

### Actions pattern

Two entry points into the same `App\Actions\*` / `Jiannius\Atom\Actions\*` classes, sharing `Atom::resolveAction()` (dotted name → namespace via the `str()->namespace()` macro; app-level class wins over the package's):

- **`Atom::action()`** — the PHP entry point (`atom()->action()`, `$this->action()` on the trait). Unrestricted: any action, and `method` in `$params` picks the method (default `handle`). The trait's helper is `protected` for that reason (v3.25.0): Livewire exposes every public method a component declares, and reflection reports a trait method's declaring class as the *using* class, so a public one was callable as `$wire.action('Any', {method: 'any'})` on every component in every host app — the same caller-picks-the-method hole v3.19.0 closed on the route, open through Livewire. `tests/Feature/AtomComponentSurfaceTest.php` asserts it stays off `Utils::getPublicMethodsDefinedBySubClass()`.
- **`Atom::webAction()`** — behind the public `POST /atom/action/{name}` endpoint. Runs the action only if it implements `Contracts\WebAction`; 404s otherwise, with the same body an unknown action gets so the endpoint can't enumerate an app's actions. Always calls `handle()` — `method` is stripped, never honoured. Calls the action's `authorize($params)` first if it has one, 403 on false. Denials are JSON so `ajax.js`'s `res.json()` can read them. A refusal of a class that *exists* is logged (the 404 is otherwise indistinguishable from a typo, and the failure mode in a consuming app is a silently dark front-end feature); a refusal of a class that doesn't is silent, so probing can't flood the log.

The endpoint is unauthenticated by design (guest-facing remote-option selects — `<atom:select options="countries">`, a *string* `options` prop — hit `GetOptions`), which is why the gate is per-action rather than route middleware.

`GetOptions` is the one action the package ships web-callable, so it carries a second gate of its own (v3.20.0). The requested option name used to be camelCased into a method call and interpolated into two file paths, both unvalidated — so a name could invoke any zero-arg method on a host app's subclass, or read any `.json` outside `resources/json/`. Now a name must appear in `PUBLIC_OPTIONS` (the package's own sets), `$guest` (subclass, anyone may read) or `$auth` (subclass, signed-in only, enforced by `authorize()`); anything else returns `[]`. `isOptionName()` (slug regex) guards both the dispatch and the file reads, `getFromJson()` guards the packaged-file read the same way the local one was already guarded, and it no longer writes a cache entry for a name with no file behind it (unknown names would otherwise grow the shared `_options` entry without bound). Note `authorize()` runs on the HTTP path only — `atom()->action('get-options', ...)` from a blade (`input/tel`, `select/native`) skips it, which is why the allowlist lives in `handle()` and only the auth check lives in `authorize()`. `tests/Feature/ActionEndpointTest.php` + `ActionTest.php` cover both paths; fixtures live in `tests/Fixtures/Actions/` under a dev-only `App\Actions\` PSR-4 mapping.

`Actions\GetOptions` is the in-package example. It also demonstrates the JSON lookup convention: `getFromJson($name)` reads `resource_path('json/'.$name.'.json')` from the consuming app and merges it (recursively) over `json/{$name}.json` from this package. Results are cached under `_options` in the default cache store. Adding a new option set means adding a JSON file in both places (or just one).

**Option output is escaped; the `html` key is trusted.** The listbox/filter variants render an option through `x-html="getOptionHtml(option)"`, so anything concatenated into that string is markup. `GetOptions::getOptionHtml()` therefore `e()`s the `label` and `caption` it builds (the `avatar` goes through `{{ }}` in `Blade::render`), and the client-side fallback in `resources/js/alpinejs/select.js` `escapeHtml()`s `label`, and lets `color` through `safeColor()` first (only `#hex`, `rgb(a)()`, `hsl(a)()` or a named colour — escaping alone stops attribute breakout but not `red; position: fixed`; the listbox chip template uses the same `safeColor()`); `select/native.blade.php` prints labels with `{{ }}`. An option carrying its own `html` (or `selected_html`) is returned untouched — that key is a **trusted pass-through**: the host builds it and owns escaping every interpolated field with `e()`. The raw `label` stays on the option (search filters on it, chips render it with `x-text`), so never pre-escape a label. Any new `x-html` / `{!! !!}` fed by option, label or request data needs the same treatment; `tests/e2e/select-xss.spec.js` + `GetOptionsTest` cover it (fixtures: `tests/Fixtures/XssOptions.php`, aliased to `App\Actions\GetOptions` only inside the served e2e app by `E2EServiceProvider` — never add an `App\Actions\GetOptions` file under `tests/Fixtures/Actions/`, it would shadow the package class in every Pest test — and `resources/views/e2e/select-xss.blade.php`).

### Component directory (`/atom/docs`)

Local-env-only routes (registered in `routes/web.php`) serve a browsable component directory. `Services\Docs` scans `components/` (excluding `docs/`), parses `@props` blocks for prop tables, and lists icon/logo glyphs. Docs chrome lives in `components/docs/` (layout, example, props); pages and demo partials live in `resources/views/docs/`. Each example partial is BOTH rendered live AND displayed as its own source — when editing a demo, remember the file text is the documentation. Undocumented components automatically get a fallback page, so new components need no docs work to appear.

### Front-end (`resources/js/atom.js`)

Entry point bundled by Vite. It:
- Extends `Array`, `Number`, `String` prototypes (`prototypes/*`) — `window.atom`, `window.dd`, `window.empty` are also set.
- Registers Alpine data factories (`modal`, `editor`, `select`, `tooltip`, `dropdown`, `lightbox`, `telInput`, `emailInput`, `breadcrumbs`, `datePicker`, `timePicker`, `dateRange`, `calendar`, chart variants) and the `$clipboard` magic.
- Loads `@alpinejs/intersect` and `@marcreichel/alpine-autosize` plugins.

The Vite config builds `resources/css/atom.css`, `resources/css/editor.css`, `resources/css/calendar.css`, `resources/js/atom.js` to `dist/`, with the calendar package split into its own chunk. The build output is committed and served by the package itself (not by the consuming app), so **`npm run build` must be run and the resulting `dist/` committed whenever JS/CSS sources change**.

## Conventions worth knowing

- `t('Some string', $count, $params)` is the package's translation shim — accepts a plain string, number, or array; routes through `trans_choice` or `__()` appropriately. Almost every UI string in components passes through it.
- Date handling everywhere goes through `Jiannius\Atom\Services\Carbon` because of the `Date::use()` swap.
- Components prefer `Arr::toCssClasses([...])` over conditional class strings; conditional/utility classes are grouped by variant in plain `match` expressions (see `components/button/index.blade.php` for the canonical pattern).
- Many components dispatch and listen for window-level Livewire events prefixed `atom-` (`atom-modal-show`, `atom-toast-show`, `atom-confirm-show`, `atom-alert-show`). Search by this prefix when tracing UI state changes.
- The `confirm` flow for `<atom:button type="delete">` is auto-wired in the button component: it dispatches `confirmed` on accept and that translates to `$wire.delete()` unless the caller overrides `wire:click` or `x-on:click`.
- **Icons come from [heroicons.com](https://heroicons.com/) or [lucide.dev/icons](https://lucide.dev/icons/)** — never hand-draw a glyph or invent path data. One file per glyph at `components/icon/<name>.blade.php`: `<atom:icon._wrapper :attributes="$attributes">` wrapping a single-line 24×24 SVG. Normalise lucide's `stroke-width` from `2` to the set's `1.5`; keep its `class="lucide lucide-x-icon lucide-x"` (recent additions do). `Services\Docs::glyphs()` globs the directory and the Boost guidelines point at it rather than listing names, so **adding an icon needs no docs work** — it lists itself in `/atom/docs`.

## Development guidelines

Curated from the jiannius package skeleton (`skeleton-package`) — the subset that applies to atom. The skeleton's Pest/Testbench, Models & data, and Pint guidelines are intentionally omitted: atom has no test suite or Pint config (Orchestra Testbench is a dev dep but unused) and ships no models. Front-end changes still require `npm run build` + committing `dist/` (see Front-end above).

### Conventions

- Follow the existing code conventions; when creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods (`isRegisteredForDiscounts`, not `discount()`).
- Stick to the existing directory structure — don't create new base folders without approval.
- Don't change the package's dependencies without approval.
- Only create documentation files if explicitly requested.
- Be concise in explanations — focus on what's important rather than obvious details.

### PHP style

- Always use curly braces for control structures, even single-line bodies.
- Use PHP 8 constructor property promotion (`public function __construct(public GitHub $github) {}`); no empty zero-parameter constructors unless private.
- Explicit return types and type hints on all parameters: `function isAccessible(User $user, ?string $path = null): bool`.
- Prefer PHPDoc over inline comments; every public/private method gets a one-line PHPDoc. Use array-shape definitions in PHPDoc where useful.
- Backed enums mix in `Jiannius\Atom\Traits\Enum`; cases are `FULL_UPPERCASE`. The trait provides `all()`, `option()`, `label()`, `get()`, `is()`/`isNot()`, plus `color()`, `str()`, `snake()`, `slug()`.

### Workflow

- Always squash-merge when exiting a worktree, then remove the worktree.
- Plan mode: no need to use the superpowers skills.
- Session close: when closing or clearing the session, save important gotchas/findings to memory and clear any stale data pieces from it.

## Working Guidelines

Behavioral guidelines to reduce common LLM coding mistakes. For trivial tasks, use judgment.

### 1. Think Before Coding

Don't assume. Don't hide confusion. Surface tradeoffs. State assumptions explicitly; if multiple interpretations exist, present them rather than picking silently. If something is unclear, stop, name what's confusing, and ask.

### 2. Simplicity First

Minimum code that solves the problem. No features beyond what was asked, no abstractions for single-use code, no "configurability" that wasn't requested, no error handling for impossible scenarios.

### 3. Surgical Changes

Touch only what you must. Don't "improve" adjacent code or refactor things that aren't broken. Match existing style. Remove imports/variables your change orphaned; leave pre-existing dead code unless asked.

### 4. Goal-Driven Execution

Transform the task into a verifiable goal ("write a test that reproduces the bug, then make it pass") and loop until verified.

### 5. Caveman

Talk normally in discussion; talk like a caveman (caveman skill) during coding work.
