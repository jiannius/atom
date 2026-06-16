# `<atom:tiptap>` — Phase 3 Implementation Plan (SSR display + migration)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]` checkboxes.

**Goal:** Render stored Tiptap JSON to HTML server-side (`<atom:tiptap.content>`) faithfully — including atom's custom nodes/attrs — via `ueberdosis/tiptap-php`, and provide an optional one-shot `atom:tiptap-migrate` command that backfills legacy HTML rows to JSON.

**Architecture:** A single source of truth — `Jiannius\Atom\Tiptap\Content` — exposes `extensions(): array` (the full PHP extension set mirroring the JS engine) and `render($value): string` (JSON|HTML → HTML). `<atom:tiptap.content>` and the migrate command both use it, so JS↔PHP fidelity is defined in one place. tiptap-php ships almost everything; only **4 custom extensions** are needed: `AtomImage` (float/align/width), `FontSize`, `Youtube`, `AtomMention`.

**Tech Stack:** `ueberdosis/tiptap-php` (^2.1, installed Phase 0), Blade, Pest + Testbench.

**Reference:** spec `docs/superpowers/specs/2026-06-15-atom-tiptap-design.md`; the JS engine `resources/js/tiptap.js` (the custom JS extensions to mirror); the v2 `src/Commands/PurgeEditorImages.php` (model-scanning pattern for the migrate command).

## Discovery (already done — build on this, don't re-spike from scratch)
tiptap-php's **default** `new Editor()` drops most nodes/marks — you MUST pass an explicit extension array. tiptap-php SHIPS: `StarterKit` (Document, Paragraph, Text, Heading, Blockquote, BulletList, OrderedList, ListItem, CodeBlock, HardBreak, HorizontalRule, Bold, Italic, Strike, Code), and separately `Underline, Subscript, Superscript, Highlight (multicolor opt), Link, TextStyle` (marks), `Table, TableRow, TableHeader, TableCell, Image, Mention` (nodes), `Color, TextAlign, FontFamily` (extensions). It does NOT ship: **FontSize**, **Youtube**, image **float/align/width**, and its **Mention** renders `<span data-type=mention data-id>` (atom wants `<span class="mention">@label</span>`). `HTML::mergeAttributes` merges `class`/`style` fragments (verified). Extension base API: subclass `Tiptap\Core\Node`/`Mark`/`Extension` with `public static $name`, `addAttributes()` (per-attr `parseHTML`/`renderHTML` closures), `renderHTML($node/$mark, $HTMLAttributes=[])`; register via `new Tiptap\Editor(['extensions' => [...]])`.

## File map (Phase 3)
```
Create:
  src/Tiptap/Content.php                       # extensions() + render() — single source of truth
  src/Tiptap/Extensions/AtomImage.php          # extends Tiptap\Nodes\Image: float/align/width
  src/Tiptap/Extensions/FontSize.php           # textStyle fontSize attr
  src/Tiptap/Extensions/Youtube.php            # youtube node -> iframe embed
  src/Tiptap/Extensions/AtomMention.php        # extends Tiptap\Nodes\Mention: label + class=mention
  components/tiptap/content.blade.php          # <atom:tiptap.content :content="..."/>
  src/Commands/MigrateTiptapContent.php        # atom:tiptap-migrate
  tests/Feature/TiptapContentTest.php          # Content::render fidelity + component render
  tests/Feature/TiptapMigrateTest.php          # migrate HTML->JSON
Modify:
  src/AtomServiceProvider.php                  # register the atom:tiptap-migrate command
  resources/views/docs/demos/tiptap.blade.php  # add a "Rendered output" example (optional)
```

---

### Task 3.1: PHP custom extensions + `Content` helper

**Files:** Create `src/Tiptap/Content.php` + `src/Tiptap/Extensions/{AtomImage,FontSize,Youtube,AtomMention}.php`; Test `tests/Feature/TiptapContentTest.php`

- [ ] **Step 1: Write `src/Tiptap/Extensions/AtomImage.php`**

```php
<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Nodes\Image;

class AtomImage extends Image
{
    /**
     * Add atom's float / align / width attributes (data-* + inline style),
     * mirroring the JS ImageExtended. HTML::mergeAttributes merges the style
     * fragments, so per-attribute renderHTML is safe.
     */
    public function addAttributes()
    {
        return array_merge(parent::addAttributes(), [
            'float' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-float') ?: null,
                'renderHTML' => fn ($attributes) => empty($attributes->float)
                    ? null
                    : ['data-float' => $attributes->float, 'style' => "float: {$attributes->float}"],
            ],
            'align' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-align') ?: null,
                'renderHTML' => function ($attributes) {
                    if (empty($attributes->align)) {
                        return null;
                    }

                    $style = match ($attributes->align) {
                        'left' => 'margin-right: auto',
                        'center' => 'margin-left: auto; margin-right: auto',
                        'right' => 'margin-left: auto',
                        default => null,
                    };

                    return $style ? ['data-align' => $attributes->align, 'style' => $style] : null;
                },
            ],
            'width' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-width') ?: null,
                'renderHTML' => fn ($attributes) => empty($attributes->width)
                    ? null
                    : ['data-width' => $attributes->width, 'style' => "width: {$attributes->width}"],
            ],
        ]);
    }
}
```

- [ ] **Step 2: Write `src/Tiptap/Extensions/FontSize.php`**

```php
<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Core\Extension;

class FontSize extends Extension
{
    public static $name = 'fontSize';

    public function addOptions()
    {
        return ['types' => ['textStyle']];
    }

    /**
     * Mirror the JS FontSize: preset keys (xs..xl) render a Tailwind text-*
     * class; any other value renders an inline font-size (e.g. "1.25rem").
     */
    public function addGlobalAttributes()
    {
        return [[
            'types' => $this->options['types'],
            'attributes' => [
                'fontSize' => [
                    'default' => null,
                    'parseHTML' => fn ($node) => $node->getAttribute('data-font-size') ?: null,
                    'renderHTML' => function ($attributes) {
                        if (empty($attributes->fontSize)) {
                            return null;
                        }

                        $sizes = ['xs' => 'text-xs', 'sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'];
                        $value = $attributes->fontSize;

                        return isset($sizes[$value])
                            ? ['data-font-size' => $value, 'class' => $sizes[$value]]
                            : ['data-font-size' => $value, 'style' => "font-size: {$value}"];
                    },
                ],
            ],
        ]];
    }
}
```

- [ ] **Step 3: Write `src/Tiptap/Extensions/Youtube.php`**

```php
<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

class Youtube extends Node
{
    public static $name = 'youtube';

    public function addOptions()
    {
        return ['HTMLAttributes' => []];
    }

    public function addAttributes()
    {
        return [
            'src' => [
                'parseHTML' => fn ($node) => $node->getAttribute('src') ?: null,
            ],
            'start' => ['default' => null],
        ];
    }

    public function parseHTML()
    {
        return [['tag' => 'div[data-youtube-video] iframe']];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        $src = $node->attrs->src ?? '';
        $embed = static::embedUrl($src);

        return [
            'div',
            ['data-youtube-video' => true],
            ['iframe', HTML::mergeAttributes(
                ['src' => $embed, 'frameborder' => '0', 'allowfullscreen' => 'true'],
                $this->options['HTMLAttributes'],
            ), 0],
        ];
    }

    /**
     * Convert any YouTube URL form to an /embed/ URL.
     */
    public static function embedUrl(string $url): string
    {
        if (preg_match('/(?:youtu\.be\/|v=|\/embed\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return $url;
    }
}
```

- [ ] **Step 4: Write `src/Tiptap/Extensions/AtomMention.php`**

```php
<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Nodes\Mention;
use Tiptap\Utils\HTML;

class AtomMention extends Mention
{
    /**
     * Add the `label` attribute (the JS mention stores id + label) on top of
     * the built-in `id`.
     */
    public function addAttributes()
    {
        return array_merge(parent::addAttributes(), [
            'label' => [
                'parseHTML' => fn ($node) => $node->getAttribute('data-label') ?: null,
                'renderHTML' => fn ($attributes) => empty($attributes->label)
                    ? null
                    : ['data-label' => $attributes->label],
            ],
        ]);
    }

    /**
     * Render atom's mention markup: <span class="mention" ...>@Label</span>.
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $label = $node->attrs->label ?? $node->attrs->id ?? '';

        return [
            'span',
            HTML::mergeAttributes(['class' => 'mention'], $this->options['HTMLAttributes'], $HTMLAttributes),
            '@'.$label,
        ];
    }
}
```

- [ ] **Step 5: Write `src/Tiptap/Content.php` (single source of truth)**

```php
<?php

namespace Jiannius\Atom\Tiptap;

use Jiannius\Atom\Tiptap\Extensions\AtomImage;
use Jiannius\Atom\Tiptap\Extensions\AtomMention;
use Jiannius\Atom\Tiptap\Extensions\FontSize;
use Jiannius\Atom\Tiptap\Extensions\Youtube;
use Tiptap\Editor;

class Content
{
    /**
     * The full PHP extension set mirroring the JS engine. Used for both SSR
     * rendering and the HTML->JSON migration so fidelity is defined once.
     *
     * @return array<int, object>
     */
    public static function extensions(): array
    {
        return [
            new \Tiptap\Extensions\StarterKit,
            new \Tiptap\Marks\Underline,
            new \Tiptap\Marks\Subscript,
            new \Tiptap\Marks\Superscript,
            new \Tiptap\Marks\Highlight(['multicolor' => true]),
            new \Tiptap\Marks\Link,
            new \Tiptap\Marks\TextStyle,
            new \Tiptap\Extensions\Color(['types' => ['textStyle']]),
            new FontSize(['types' => ['textStyle']]),
            new \Tiptap\Extensions\TextAlign(['types' => ['heading', 'paragraph']]),
            new \Tiptap\Nodes\Table,
            new \Tiptap\Nodes\TableRow,
            new \Tiptap\Nodes\TableHeader,
            new \Tiptap\Nodes\TableCell,
            new AtomImage,
            new Youtube,
            new AtomMention,
        ];
    }

    /**
     * Render stored content (Tiptap JSON string/array, or legacy HTML) to HTML.
     */
    public static function render(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return (new Editor(['extensions' => static::extensions()]))
            ->setContent($value)
            ->getHTML();
    }
}
```

- [ ] **Step 6: Write `tests/Feature/TiptapContentTest.php` (fidelity)** — assert the custom nodes round-trip. Build a doc with image(float/align/width), fontSize, youtube, mention, highlight, textAlign, link, table:

```php
<?php

use Jiannius\Atom\Tiptap\Content;

function renderDoc(array $content): string
{
    return Content::render(json_encode(['type' => 'doc', 'content' => $content]));
}

describe('Content::render', function () {
    it('renders image float/align/width as data-attrs + style', function () {
        $html = renderDoc([['type' => 'image', 'attrs' => ['src' => 'a.png', 'float' => 'left', 'width' => '50%']]]);

        expect($html)
            ->toContain('data-float="left"')
            ->toContain('data-width="50%"')
            ->toContain('float: left')
            ->toContain('width: 50%');
    });

    it('renders fontSize preset as a class and custom as inline style', function () {
        $preset = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'textStyle', 'attrs' => ['fontSize' => 'lg']]], 'text' => 'x']]]]);
        $custom = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'textStyle', 'attrs' => ['fontSize' => '1.25rem']]], 'text' => 'x']]]]);

        expect($preset)->toContain('text-lg');
        expect($custom)->toContain('font-size: 1.25rem');
    });

    it('renders youtube as an embed iframe', function () {
        $html = renderDoc([['type' => 'youtube', 'attrs' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']]]);

        expect($html)
            ->toContain('data-youtube-video')
            ->toContain('youtube.com/embed/dQw4w9WgXcQ');
    });

    it('renders mention as span.mention with the label', function () {
        $html = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'mention', 'attrs' => ['id' => '1', 'label' => 'Alice']]]]]);

        expect($html)
            ->toContain('class="mention"')
            ->toContain('@Alice');
    });

    it('renders highlight, link, text-align and tables', function () {
        $html = renderDoc([
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'H']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'marks' => [['type' => 'highlight', 'attrs' => ['color' => '#ff0']]], 'text' => 'hl'],
                ['type' => 'text', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://x.com']]], 'text' => 'lnk'],
            ]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'c']]]]]]]],
        ]);

        expect($html)
            ->toContain('text-align: center')
            ->toContain('background-color: #ff0')
            ->toContain('href="https://x.com"')
            ->toContain('<table>')
            ->toContain('<td');
    });

    it('round-trips legacy HTML (renders it as-is structurally)', function () {
        expect(Content::render('<p>legacy <strong>bold</strong></p>'))
            ->toContain('<strong>bold</strong>');
    });

    it('renders empty for empty input', function () {
        expect(Content::render(''))->toBe('');
        expect(Content::render(null))->toBe('');
    });
});
```

- [ ] **Step 7: Run** — `vendor/bin/pest tests/Feature/TiptapContentTest.php`. DEBUG failures against the actual tiptap-php output (run `php` ad-hoc to print `Content::render($doc)` and inspect). Likely adjustments: exact attribute key casing, whether `Link` needs option config, whether `Table` wraps differently — fix the EXTENSIONS or the test expectations to match real faithful output (prefer matching the JS engine's HTML; if tiptap-php differs cosmetically e.g. `<table>` vs `<table class>`, relax the assertion to the meaningful part). Re-run until green.

- [ ] **Step 8: Full suite** — `vendor/bin/pest` → green.

- [ ] **Step 9: Commit** — `git add src/Tiptap/ tests/Feature/TiptapContentTest.php && git commit -m "feat(tiptap): PHP custom extensions + Content::render (SSR fidelity)"`

---

### Task 3.2: `<atom:tiptap.content>` component

**Files:** Create `components/tiptap/content.blade.php`; Test (append to `tests/Feature/TiptapContentTest.php`)

- [ ] **Step 1: Write `components/tiptap/content.blade.php`**

```blade
@props([
    'content' => null,
])

<link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

<div {{ $attributes->class(['editor-content']) }}>
    {!! \Jiannius\Atom\Tiptap\Content::render($content ?? $slot) !!}
</div>
```

Note: accepts a `:content` prop (the stored JSON/HTML value) OR slot content (so `<atom:tiptap.content>{{ $post->body }}</atom:tiptap.content>` also works — when no `content` prop, fall back to the slot's string). If `$slot` is a ComponentSlot, cast to string before passing; adjust to `Content::render($content ?? (string) $slot)` if needed.

- [ ] **Step 2: Append a component render test**

```php
it('the <atom:tiptap.content> component renders stored JSON', function () {
    $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]]]]);
    $html = renderBlade('<atom:tiptap.content :content="$c" />', ['c' => $json]);

    expect($html)
        ->toContain('editor-content')
        ->toContain('<p>hello</p>');
});
```
(Use `renderBlade` from `tests/Pest.php`; share `errors` in `beforeEach` if the component pulls it in — likely not, but add if needed.)

- [ ] **Step 3: Run** — `vendor/bin/pest tests/Feature/TiptapContentTest.php` → green. Then full suite.

- [ ] **Step 4: Commit** — `git add components/tiptap/content.blade.php tests/Feature/TiptapContentTest.php && git commit -m "feat(tiptap): <atom:tiptap.content> SSR render component"`

---

### Task 3.3: `atom:tiptap-migrate` command

**Files:** Create `src/Commands/MigrateTiptapContent.php`; Modify `src/AtomServiceProvider.php`; Test `tests/Feature/TiptapMigrateTest.php`

Walk `App\Models\*`, find columns cast as `AsTiptapContent` (and legacy `AsEditorContent`), and for any row whose stored value is legacy HTML (not JSON), convert HTML→JSON via `Content` extensions and re-save. Model-scanning mirrors `src/Commands/PurgeEditorImages.php::getModels()`.

- [ ] **Step 1: Write `src/Commands/MigrateTiptapContent.php`**

```php
<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Jiannius\Atom\Casts\AsEditorContent;
use Jiannius\Atom\Casts\AsTiptapContent;
use Jiannius\Atom\Tiptap\Content;
use Tiptap\Editor;

class MigrateTiptapContent extends Command
{
    protected $signature = 'atom:tiptap-migrate {--dry}';
    protected $description = 'Convert legacy editor HTML columns to Tiptap JSON';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $editor = new Editor(['extensions' => Content::extensions()]);
        $count = 0;

        foreach ($this->models() as $class) {
            $model = app($class);
            $columns = collect($model->getCasts())
                ->filter(fn ($cast) => in_array($cast, [AsTiptapContent::class, AsEditorContent::class]))
                ->keys();

            if ($columns->isEmpty()) {
                continue;
            }

            foreach ($model->newQuery()->withoutGlobalScopes()->cursor() as $row) {
                $dirty = false;

                foreach ($columns as $column) {
                    $value = $row->getRawOriginal($column);
                    $html = @unserialize($value);          // legacy AsEditorContent stored serialize()'d HTML
                    if ($html === false && $value !== 'b:0;') {
                        $html = $value;                    // raw
                    }

                    if (! is_string($html) || $html === '' || $this->isJson($html)) {
                        continue;                          // already JSON or empty
                    }

                    $json = $editor->setContent($html)->getJSON();
                    if (! $this->option('dry')) {
                        $row->{$column} = $json;           // AsTiptapContent::set stores it
                    }
                    $dirty = true;
                    $count++;
                }

                if ($dirty && ! $this->option('dry')) {
                    $row->saveQuietly();
                }
            }
        }

        $this->info(($this->option('dry') ? '[dry] ' : '').'Migrated '.$count.' column value(s) to Tiptap JSON.');
    }

    /**
     * Is this string already a Tiptap JSON document?
     */
    protected function isJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    /**
     * All App\Models\* class names.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function models()
    {
        $path = app_path('Models');
        $models = collect();

        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $models->push('App\\Models\\'.pathinfo($file, PATHINFO_FILENAME));
                }
            }
        }

        return $models;
    }
}
```

Note: the migrate command sets `$row->{$column} = $json` where `$json` is already a JSON string; `AsTiptapContent::set()` will `json_decode` it, find no temp images, and re-encode — harmless. The column must be cast as `AsTiptapContent` post-migration (consumer changes the cast from `AsEditorContent` to `AsTiptapContent` as part of adopting v3.6.0). For columns STILL cast as `AsEditorContent`, the migrate writes JSON but the old cast would `serialize()` it — so document that consumers switch the cast to `AsTiptapContent` BEFORE running migrate, or the command only targets `AsTiptapContent` columns. SIMPLER + SAFER: only target `AsTiptapContent` columns (drop `AsEditorContent` from the filter); consumers switch the cast first, then migrate. Update the filter to `[AsTiptapContent::class]` only and note this in the command description + Phase-6 docs.

- [ ] **Step 2: Register in `src/AtomServiceProvider.php`** — find where commands are registered (search for `commands(` / `PurgeEditorImages`) and add `MigrateTiptapContent::class` to the `$this->commands([...])` array (in the `boot()` console-only block).

- [ ] **Step 3: Write `tests/Feature/TiptapMigrateTest.php`** — unit-test the conversion logic without a DB: assert the command's HTML→JSON via `Content::extensions()` produces valid JSON, and `isJson()` detection. (A full model-walk integration test needs a sqlite model + migration — if the harness supports it like `tests/Fixtures/Item.php`, add one; else test the conversion helper directly.)

```php
<?php

use Jiannius\Atom\Tiptap\Content;
use Tiptap\Editor;

it('converts legacy HTML to a tiptap JSON doc', function () {
    $editor = new Editor(['extensions' => Content::extensions()]);
    $json = $editor->setContent('<p>hello <strong>world</strong></p>')->getJSON();
    $doc = json_decode($json, true);

    expect($doc['type'])->toBe('doc')
        ->and($doc['content'][0]['type'])->toBe('paragraph');
});

it('round-trips custom nodes through HTML->JSON->HTML', function () {
    // an HTML img with atom data-attrs should parse back into JSON attrs
    $editor = new Editor(['extensions' => Content::extensions()]);
    $json = $editor->setContent('<img src="a.png" data-float="left" data-width="50%">')->getJSON();
    $doc = json_decode($json, true);

    // find the image node attrs
    $img = collect($doc['content'])->firstWhere('type', 'image');
    expect($img['attrs']['float'])->toBe('left')
        ->and($img['attrs']['width'])->toBe('50%');
});
```

- [ ] **Step 4: Run** — `vendor/bin/pest tests/Feature/TiptapMigrateTest.php` → green; then full suite. Also smoke-run the command in Testbench if a fixture model exists: `vendor/bin/testbench atom:tiptap-migrate --dry` (only if the e2e/testbench harness makes this easy; otherwise rely on the unit tests).

- [ ] **Step 5: Commit** — `git add src/Commands/MigrateTiptapContent.php src/AtomServiceProvider.php tests/Feature/TiptapMigrateTest.php && git commit -m "feat(tiptap): atom:tiptap-migrate command (legacy HTML -> JSON backfill)"`

---

## Self-review notes
- `Content::extensions()` is the single fidelity source; `<atom:tiptap.content>` + migrate both use it. Keep them in sync with the JS engine if extensions change later.
- The 4 custom extensions mirror the JS: AtomImage (float/align/width), FontSize (preset class | inline rem), Youtube (embed iframe), AtomMention (span.mention + @label). Verify each against real tiptap-php output in Task 3.1 Step 7 — adjust extension code, not just tests, if output is wrong.
- Migrate targets **`AsTiptapContent` columns only**; consumers switch the cast from `AsEditorContent`→`AsTiptapContent` before running it. Document in Phase 6.
- Image-orphan **purge** (the v2 `atom:purge-editor-images` equivalent walking JSON image nodes) is NOT in this phase — fold into Phase 6 cleanup or a follow-up; the existing purge command still works on `AsEditorContent` columns meanwhile.

## Done when
- `vendor/bin/pest` green: TiptapContentTest (8) + TiptapMigrateTest (2), no regressions.
- `<atom:tiptap.content :content="$json"/>` renders faithful HTML incl. custom nodes (image float/align/width, fontSize, youtube, mention, highlight, text-align, tables, links).
- `atom:tiptap-migrate` converts legacy HTML rows to JSON.

**Next:** Phase 4 (chat: `<atom:tiptap.chat>`), Phase 5 (mention), Phase 6 (shim + cutover → tag v3.6.0).
