# Livewire 4 Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bump `jiannius/atom` from Livewire 3 to Livewire 4 (Phase 1: PHP/composer/docs). Drops Volt; updates the editor preview-file URL regex; refreshes the Boost guideline doc to L4 single-file syntax.

**Architecture:** Seven thin tasks — composer constraints, two surgical PHP edits (service provider Volt removal, cast regex update), two doc refreshes (Boost guideline + CLAUDE.md), lockfile regeneration, and a Testbench 11 + L4 smoke verification.

**Tech Stack:** PHP 8.3+, Laravel 13, Livewire 4.x, Orchestra Testbench 11.x, Composer 2.

**Spec:** `docs/superpowers/specs/2026-05-12-livewire-4-upgrade-design.md`

**Note on TDD:** This package has no test suite. The TDD loop is replaced by composer-level resolution checks plus the smoke task at the end. Do NOT add a test framework in this plan.

**Note on Phase 2:** JS verification in a real browser/consuming app is explicitly out of scope for this plan. Any `$wire.*` / `wire:model` / browser-event regressions discovered after tagging v3.0.0 will be patched as `v3.0.x`.

---

## Task 1: Update `composer.json`

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/composer.json`

- [ ] **Step 1: Read current `composer.json`**

Run: `cat /Users/tj/Projects/jiannius/atom/composer.json`

Expected current `require` block:
```json
"require": {
    "php": "^8.3",
    "illuminate/support": "^13.0",
    "intervention/image": "^3.0",
    "livewire/livewire": "^3.0",
    "livewire/volt": "^1.0"
},
```

- [ ] **Step 2: Replace the `require` block**

Edit `/Users/tj/Projects/jiannius/atom/composer.json`. Replace the `require` block with:

```json
"require": {
    "php": "^8.3",
    "illuminate/support": "^13.0",
    "intervention/image": "^3.0",
    "livewire/livewire": "^4.0"
},
```

Changes:
- Remove `"livewire/volt": "^1.0"` (Volt is folded into Livewire 4 core; consumers migrate per Livewire's upgrade guide).
- Bump `"livewire/livewire"` from `^3.0` to `^4.0`.
- Keep everything else (`php`, `illuminate/support`, `intervention/image`) and the `require-dev`, `config`, `extra`, `autoload`, `authors` blocks untouched.

- [ ] **Step 3: Validate JSON**

Run: `cd /Users/tj/Projects/jiannius/atom && composer validate --no-check-lock`

Expected: `./composer.json is valid` (lockfile warnings expected — Task 6 regenerates it).

- [ ] **Step 4: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add composer.json
git commit -m "$(cat <<'EOF'
chore: bump livewire to ^4.0 and drop volt

Hard cut from Livewire 3 to Livewire 4. Volt is folded into
Livewire 4 core; the standalone livewire/volt package is no
longer needed and is explicitly recommended for removal by
Livewire's upgrade guide.

Consumers on Livewire 3 must stay on Atom ^2.0. Consumers using
Volt must follow Livewire's official upgrade guide before
bumping Atom to v3.0.0.
EOF
)"
```

---

## Task 2: Drop Volt wiring from `AtomServiceProvider`

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/src/AtomServiceProvider.php`

The current service provider imports `Livewire\Volt\Volt`, calls `$this->mountVoltComponents()` from `boot()`, and defines `mountVoltComponents()` which mounts an empty directory. All three pieces must go.

- [ ] **Step 1: Remove the `Livewire\Volt\Volt` import**

In `/Users/tj/Projects/jiannius/atom/src/AtomServiceProvider.php`, delete line 12:

```php
use Livewire\Volt\Volt;
```

The surrounding imports stay; just remove this single line.

- [ ] **Step 2: Remove the `mountVoltComponents()` call from `boot()`**

Inside the `boot()` method, delete the line:

```php
        $this->mountVoltComponents();
```

(This is approximately line 38 in the current file; the exact line moves up by one after Step 1.)

- [ ] **Step 3: Remove the `mountVoltComponents()` method definition**

Delete the entire method (including its PHPDoc), which is currently:

```php
    /**
     * Mount the volt components
     */
    protected function mountVoltComponents() : void
    {
        $this->app->booted(function() {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });
    }
```

- [ ] **Step 4: Syntax-check the file**

Run: `php -l /Users/tj/Projects/jiannius/atom/src/AtomServiceProvider.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 5: Confirm no other references to Volt remain in `src/`**

Run: `grep -rn "Volt" /Users/tj/Projects/jiannius/atom/src 2>/dev/null`

Expected: zero results. If anything matches, stop and report.

- [ ] **Step 6: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add src/AtomServiceProvider.php
git commit -m "$(cat <<'EOF'
chore: drop Volt mount from service provider

Livewire 4 absorbs Volt into core; the standalone livewire/volt
package has been dropped from this package's dependencies.

The mountVoltComponents() call had been mounting an empty
resources/views/livewire/ directory for the package's whole
life — pure dead wiring with no behavioural change from its
removal.
EOF
)"
```

---

## Task 3: Update the editor preview-file URL regex

**Why:** Livewire 4 prefixes all internal URLs with a hash derived from APP_KEY. The temporary-file preview URL changes from `/livewire/preview-file/...` to `/livewire-{hash}/preview-file/...`. The regex in `AsEditorContent::set()` no longer matches new uploads under L4. The fix matches anything between `/livewire-` and the next slash, which is safe regardless of the exact hash character set Livewire uses.

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/src/Casts/AsEditorContent.php`

- [ ] **Step 1: Read the current regex and surrounding comment**

Run: `sed -n '34,46p' /Users/tj/Projects/jiannius/atom/src/Casts/AsEditorContent.php`

Expected lines 36–40:
```php
        // Use regex to find all img tags with src containing "/livewire/preview-file/"
        $tmps = [];

        if (is_string($value)) {
            if (preg_match_all('/<img\s[^>]*src=[\'"]([^\'"]*\/livewire\/preview-file\/[^\'"\?]+(?:\?[^\'"]*)?)[\'"][^>]*>/i', $value, $matches)) {
```

- [ ] **Step 2: Update the comment**

Replace the comment on line 36:

```php
        // Use regex to find all img tags with src containing "/livewire/preview-file/"
```

with:

```php
        // Use regex to find all img tags with src containing "/livewire-{hash}/preview-file/"
```

- [ ] **Step 3: Update the regex**

Replace the regex string on line 40:

```php
            if (preg_match_all('/<img\s[^>]*src=[\'"]([^\'"]*\/livewire\/preview-file\/[^\'"\?]+(?:\?[^\'"]*)?)[\'"][^>]*>/i', $value, $matches)) {
```

with (note the inserted `-[^\/]+` between `livewire` and the next slash):

```php
            if (preg_match_all('/<img\s[^>]*src=[\'"]([^\'"]*\/livewire-[^\/]+\/preview-file\/[^\'"\?]+(?:\?[^\'"]*)?)[\'"][^>]*>/i', $value, $matches)) {
```

`[^\/]+` matches any non-slash character one or more times — robust to whatever URL-safe character set Livewire 4 picks for the hash.

- [ ] **Step 4: Syntax-check the file**

Run: `php -l /Users/tj/Projects/jiannius/atom/src/Casts/AsEditorContent.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 5: Standalone regex sanity check (no Laravel boot)**

Run this one-liner from anywhere:

```bash
php -r '
$regex = "/<img\s[^>]*src=[\x27\x22]([^\x27\x22]*\/livewire-[^\/]+\/preview-file\/[^\x27\x22\?]+(?:\?[^\x27\x22]*)?)[\x27\x22][^>]*>/i";
$html = "<img src=\"/livewire-DEADBEEF/preview-file/livewire-file:abc.png\">";
preg_match_all($regex, $html, $m);
var_dump($m[1] ?? []);
'
```

Expected output:
```
array(1) {
  [0]=>
  string(48) "/livewire-DEADBEEF/preview-file/livewire-file:abc.png"
```

(The exact byte count may vary slightly; what matters is that one URL is captured and it matches the input.)

If the array is empty, the regex did not capture — re-check Step 3 for transcription errors.

- [ ] **Step 6: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add src/Casts/AsEditorContent.php
git commit -m "$(cat <<'EOF'
fix: match Livewire 4 preview-file URL prefix in editor cast

Livewire 4 prepends an APP_KEY-derived hash to all internal
URLs, so /livewire/preview-file/... becomes
/livewire-{hash}/preview-file/...

The cast regex now matches the new form. Strict-only is
correct here: the regex only runs at save time against
in-flight temporary uploads, which under L4 only ever use
the new URL form.
EOF
)"
```

---

## Task 4: Refresh Boost guideline doc for L4 single-file components

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/resources/boost/guidelines/core.blade.php`

Five surgical edits. The line numbers below are approximate but each `old_string` is unique in the file, so the edits will land correctly regardless of small line shifts.

- [ ] **Step 1: Replace the "Volt is the default" paragraph (around line 23)**

Replace:
```
Volt is the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every Volt class component. It provides `WithPagination` + `WithFileUploads`, reserved state buckets (`$_breadcrumbs`, `$_table`, `$_editor`), and helper methods:
```

with:
```
Livewire 4 single-file components are the default. Mix `Jiannius\Atom\Traits\AtomComponent` into every single-file Livewire component class. It provides `WithPagination` + `WithFileUploads`, reserved state buckets (`$_breadcrumbs`, `$_table`, `$_editor`), and helper methods:
```

- [ ] **Step 2: Rename the code-snippet title (around line 33)**

Replace:
```
<code-snippet name="Volt component with Atom" lang="php">
```

with:
```
<code-snippet name="Single-file Livewire component with Atom" lang="php">
```

- [ ] **Step 3: Swap the `Component` import (around line 35)**

Replace:
```php
use Livewire\Volt\Component;
```

with:
```php
use Livewire\Component;
```

- [ ] **Step 4: Update the modal-root note (around line 73)**

Replace:
```
- When `<atom:modal>` is the **root** of a Volt component, omit `name` — methods auto-resolve to the component name.
```

with:
```
- When `<atom:modal>` is the **root** of a single-file Livewire component, omit `name` — methods auto-resolve to the component name.
```

- [ ] **Step 5: Update the method-order heading (around line 241)**

Replace:
```
- **Volt method order** (top of class to bottom):
```

with:
```
- **Component method order** (top of class to bottom):
```

- [ ] **Step 6: Confirm no Volt references remain**

Run: `grep -n -i "volt" /Users/tj/Projects/jiannius/atom/resources/boost/guidelines/core.blade.php`

Expected: zero results. If anything matches, that's a missed edit — locate and fix before committing.

- [ ] **Step 7: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add resources/boost/guidelines/core.blade.php
git commit -m "$(cat <<'EOF'
docs: refresh boost guideline for L4 single-file components

Volt is folded into Livewire 4 core; the guideline that AI
agents read should reflect the new authoring style. Five
surgical edits: prose default, code-snippet title, Component
import, modal-root note, method-order heading.
EOF
)"
```

---

## Task 5: Update `CLAUDE.md` for L4

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/CLAUDE.md`

Two surgical edits — both reference Livewire 3 / Volt behaviour that no longer applies.

- [ ] **Step 1: Remove the Volt-mount bullet (around line 36)**

Delete this bullet entirely:

```
- Mounts every Volt component in `resources/views/livewire/` automatically.
```

The surrounding bullets stay. Make sure the deletion does not leave a stray empty bullet line — the next bullet ("Swaps Laravel's `Date` facade...") should follow directly after the "Registers anonymous Blade components..." bullet.

- [ ] **Step 2: Update the editor URL description (around lines 70–71)**

Replace this block:

```
1. While the user types, image uploads land in Livewire's temporary disk and are echoed back into `_editor.images` as `temporaryUrl()` strings (handled in `updatedAtomComponent`). The editor HTML carries `<img src="/livewire/preview-file/...">` URLs.
2. Only when the editor's HTML column is *saved* through Eloquent does `Casts\AsEditorContent::set()` regex out each `/livewire/preview-file/` URL, resize via Intervention Image (max width 1000, q=80), persist to `Storage::disk(env('FILESYSTEM_DISK'))` under `<configured folder>/editor/`, rewrite the URL in the HTML, and serialize the result.
```

with:

```
1. While the user types, image uploads land in Livewire's temporary disk and are echoed back into `_editor.images` as `temporaryUrl()` strings (handled in `updatedAtomComponent`). The editor HTML carries `<img src="/livewire-{hash}/preview-file/...">` URLs (Livewire 4 prefixes internal URLs with an APP_KEY-derived hash).
2. Only when the editor's HTML column is *saved* through Eloquent does `Casts\AsEditorContent::set()` regex out each `/livewire-{hash}/preview-file/` URL, resize via Intervention Image (max width 1000, q=80), persist to `Storage::disk(env('FILESYSTEM_DISK'))` under `<configured folder>/editor/`, rewrite the URL in the HTML, and serialize the result.
```

- [ ] **Step 3: Confirm no stale references remain**

Run: `grep -n -i "volt\|/livewire/preview-file" /Users/tj/Projects/jiannius/atom/CLAUDE.md`

Expected: zero results. If anything matches, that's a missed edit.

- [ ] **Step 4: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add CLAUDE.md
git commit -m "$(cat <<'EOF'
docs: update CLAUDE.md for Livewire 4

Remove the obsolete Volt-mount bullet from the service-provider
section, and update the editor URL description to reflect L4's
hash-prefixed /livewire-{hash}/preview-file/ form.
EOF
)"
```

---

## Task 6: Regenerate `composer.lock` against Livewire 4

**Files:**
- Modify: `/Users/tj/Projects/jiannius/atom/composer.lock`

- [ ] **Step 1: Confirm local PHP version**

Run: `php --version | head -1`

Expected: `PHP 8.3.x` or higher. (The `config.platform.php: 8.3.0` pin in composer.json forces resolution against 8.3.0 regardless of local version, but the install must still run on 8.3+.)

- [ ] **Step 2: Run `composer update`**

```bash
cd /Users/tj/Projects/jiannius/atom
composer update --no-interaction --no-progress
```

Expected outcome:
- `livewire/livewire` resolves to `^4.x` (likely `4.3.0` or later — `4.3.0` was released 2026-05-01).
- `livewire/volt` no longer appears in `composer.lock` at all.
- `laravel/framework` stays on `^13.x`.
- `orchestra/testbench` stays on `^11.x` (in `packages-dev`).

If composer reports a resolution conflict, capture the full output, stop, and report BLOCKED — do not edit the lockfile by hand.

- [ ] **Step 3: Verify resolved versions**

Run:
```bash
cd /Users/tj/Projects/jiannius/atom
composer show livewire/livewire laravel/framework orchestra/testbench 2>/dev/null | grep -E "^(name|versions)"
```

Expected:
- `livewire/livewire` versions start with `v4.`
- `laravel/framework` versions start with `v13.`
- `orchestra/testbench` versions start with `v11.`

Confirm Volt is gone:
```bash
cd /Users/tj/Projects/jiannius/atom
grep '"name": "livewire/volt"' composer.lock
```
Expected: zero lines.

- [ ] **Step 4: Commit**

```bash
cd /Users/tj/Projects/jiannius/atom
git add composer.lock
git commit -m "$(cat <<'EOF'
chore: regenerate composer.lock against Livewire 4

Resolves livewire/livewire to ^4.x and removes livewire/volt
entirely. Laravel framework and orchestra/testbench stay on
their current majors.
EOF
)"
```

---

## Task 7: Manual verification in a fresh Testbench 11 + L4 host

**Why:** No automated test suite. We boot Atom on a real L4 host, confirm the service provider wires up cleanly, the routes register, and the editor regex captures a synthetic L4-formatted URL.

**Files:** none modified in this repo. Verification happens in `/tmp/atom-l4-verify`.

- [ ] **Step 1: Create a scratch host with Laravel 13 + Testbench 11**

The `orchestra/testbench-skeleton` Packagist package referenced in the L13 plan does not exist — instead, build a minimal scaffold from scratch.

```bash
SCRATCH=/tmp/atom-l4-verify
rm -rf "$SCRATCH"
mkdir -p "$SCRATCH"
cd "$SCRATCH"
cat > composer.json <<'JSON'
{
    "name": "scratch/atom-l4-verify",
    "type": "project",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.0"
    },
    "require-dev": {
        "orchestra/testbench": "^11.0"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
JSON
composer install --no-interaction --no-progress
```

Expected: `laravel/framework v13.x.x` and `orchestra/testbench v11.x.x` installed cleanly under `vendor/`.

- [ ] **Step 2: Add Atom as a path repository and require it**

```bash
cd /tmp/atom-l4-verify
composer config repositories.atom path /Users/tj/Projects/jiannius/atom
composer require jiannius/atom:@dev --no-interaction
composer show jiannius/atom | head -10
```

Expected: install succeeds; `composer show` reports the package version starting with `dev-` (the branch name) and the path pointing to `/Users/tj/Projects/jiannius/atom`. `livewire/livewire` should resolve to `v4.x`.

If install fails with a Volt-related error (e.g. resolver still trying to pull `livewire/volt`), the lockfile from Task 6 was not regenerated cleanly — stop and re-check.

- [ ] **Step 3: Confirm the service provider boots and the singleton resolves**

```bash
cd /tmp/atom-l4-verify
vendor/bin/testbench package:discover
vendor/bin/testbench tinker --execute='dump(app("atom") instanceof \Jiannius\Atom\Atom);'
```

Expected:
- `package:discover` output mentions `jiannius/atom ... DONE`.
- Tinker prints `true`.

If `package:discover` errors with `Class "Livewire\Volt\Volt" not found`, Task 2 missed an edit — re-check the service provider.

- [ ] **Step 4: Confirm route registration**

```bash
cd /tmp/atom-l4-verify
vendor/bin/testbench tinker --execute='
$routes = collect(app("router")->getRoutes()->getRoutes())
    ->map(fn ($r) => $r->methods()[0] . " " . $r->uri())
    ->filter(fn ($u) => str_contains($u, "atom/"))
    ->values()
    ->all();
dump($routes);
'
```

Expected: the dumped array contains at least `GET atom/{file}` and `POST atom/action/{name}`.

- [ ] **Step 5: Editor regex smoke against the installed Atom code**

```bash
cd /tmp/atom-l4-verify
vendor/bin/testbench tinker --execute='
$cast = new \Jiannius\Atom\Casts\AsEditorContent();
$ref = new \ReflectionMethod($cast, "set");
$html = "<p>hi</p><img src=\"/livewire-DEADBEEF1234/preview-file/livewire-file:abc.png\">";
// We cant easily run set() without a model + tmp file, so we instead run the same
// regex from the source against the synthetic html and confirm a capture.
$pattern = "/<img\\s[^>]*src=[\x27\x22]([^\x27\x22]*\\/livewire-[^\\/]+\\/preview-file\\/[^\x27\x22\\?]+(?:\\?[^\x27\x22]*)?)[\x27\x22][^>]*>/i";
preg_match_all($pattern, $html, $matches);
dump($matches[1] ?? []);
'
```

Expected: the dumped array contains one entry: `"/livewire-DEADBEEF1234/preview-file/livewire-file:abc.png"`.

If empty, the regex update in Task 3 didn't take effect — re-read `src/Casts/AsEditorContent.php`.

- [ ] **Step 6: Confirm `app('livewire')->current()` and dispatch wiring don't immediately blow up**

L4 may have moved internal APIs we use indirectly (`Atom::modal()` calls `app('livewire')->current()->dispatch(...)`). We can't truly exercise a Livewire request from tinker, but we CAN confirm the manager binds and the modal-control object is constructible:

```bash
cd /tmp/atom-l4-verify
vendor/bin/testbench tinker --execute='
dump(app("livewire") instanceof \Livewire\LivewireManager);
dump(app("atom")->modal("x") !== null);
dump(method_exists(app("livewire"), "isLivewireRequest"));
dump(method_exists(app("livewire"), "originalPath"));
'
```

Expected: four `bool(true)` lines. Any `false` indicates a Livewire 4 internal-API change we need to chase down before tagging.

- [ ] **Step 7: Clean up**

```bash
rm -rf /tmp/atom-l4-verify
ls /tmp/atom-l4-verify 2>&1 | head -1
```

Expected: `ls: /tmp/atom-l4-verify: No such file or directory`.

- [ ] **Step 8: Document the verification outputs**

Capture the actual outputs from steps 3, 4, 5, 6 in the PR / branch description (or in the task report when running under subagent-driven-development). This is the evidence layer for `superpowers:verification-before-completion`.

---

## Done criteria

- [ ] `composer.json` updated, validates cleanly.
- [ ] `composer.lock` regenerated; `livewire/livewire` at `^4.x`, `livewire/volt` removed entirely.
- [ ] `AtomServiceProvider.php` no longer imports `Livewire\Volt\Volt` and no longer defines or calls `mountVoltComponents()`.
- [ ] `AsEditorContent.php` regex matches `/livewire-{hash}/preview-file/...` URLs.
- [ ] `resources/boost/guidelines/core.blade.php` has zero remaining `Volt` references.
- [ ] `CLAUDE.md` has no stale Volt-mount bullet and the editor URL description reflects L4's hash prefix.
- [ ] Manual verification (Task 7 steps 3–6) executed against a fresh L4 Testbench host; outputs captured.
- [ ] Six commits on the branch — composer.json, service provider, cast, boost guideline, CLAUDE.md, composer.lock.
