<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Commands\MigrateTiptapContent;
use Jiannius\Atom\Services\Upgrade;
use Jiannius\Atom\Services\UpgradeCheck;
use Jiannius\Atom\Services\UpgradeCss;

/**
 * atom:upgrade rewrites what text can fix and reports the rest. These tests run it on
 * a copy of the fixture host (tests/Fixtures/UpgradeHost, the one atom:upgrade-check is
 * held to), with the CSS shape skeleton-project ships laid over a copy (the fixture
 * tests/Fixtures/skeleton-app.css is that file, pinned).
 */
function upgradeCodemodHost(bool $skeleton = true): string
{
    $dir = sys_get_temp_dir().'/atom-upgrade-codemod-'.bin2hex(random_bytes(6));
    $from = realpath(__DIR__.'/../Fixtures/UpgradeHost');
    @mkdir($dir, 0777, true);

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $target = $dir.'/'.substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
    }

    if ($skeleton) {
        copy(__DIR__.'/../Fixtures/skeleton-app.css', $dir.'/resources/css/skeleton.css');
    }

    return $dir;
}

/**
 * A diff written in a test: an empty line is a blank context line, which a diff prints as one space
 */
function upgradeCodemodDiff(string $diff): string
{
    return implode("\n", array_map(fn ($line) => $line === '' ? ' ' : $line, explode("\n", $diff)));
}

function upgradeCodemodRemove(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

/**
 * @return array<string, string>
 */
function upgradeCodemodTree(string $dir): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[substr($file->getPathname(), strlen($dir) + 1)] = md5_file($file->getPathname());
    }

    ksort($files);

    return $files;
}

/**
 * Run the command and give back [exit code, output]
 *
 * @return array{0: int, 1: string}
 */
function upgradeCodemodRun(string $dir, bool $dry = false): array
{
    $code = Artisan::call('atom:upgrade', array_filter(['--path' => $dir, '--dry-run' => $dry]));

    return [$code, Artisan::output()];
}

/**
 * Make the gate say what a test needs
 *
 * @param  array<string, mixed>|Throwable  $status
 */
function upgradeCodemodGate(array|Throwable $status): void
{
    app()->bind(MigrateTiptapContent::class, fn () => new class($status) extends MigrateTiptapContent
    {
        public function __construct(protected array|Throwable $stub)
        {
            parent::__construct();
        }

        public function status(array $paths = []): array
        {
            if ($this->stub instanceof Throwable) {
                throw $this->stub;
            }

            return $this->stub;
        }
    });
}

/**
 * @return array<string, mixed>
 */
function upgradeCodemodReady(bool $ready = true): array
{
    return $ready
        ? ['legacy_casts' => [], 'rows' => [], 'unmigrated' => 0, 'flagged' => 0, 'refused' => 0, 'problems' => [], 'ready' => true]
        : ['legacy_casts' => ['App\\Models\\Article' => ['body']], 'rows' => [], 'unmigrated' => 3, 'flagged' => 1, 'refused' => 1, 'problems' => [], 'ready' => false];
}

beforeEach(function () {
    $this->hosts = [];
    $this->host = function (bool $skeleton = true) {
        return $this->hosts[] = upgradeCodemodHost($skeleton);
    };
    upgradeCodemodGate(upgradeCodemodReady());
});

afterEach(function () {
    foreach ($this->hosts as $dir) {
        upgradeCodemodRemove($dir);
    }
});

describe('atom:upgrade: a dry run', function () {
    it('writes nothing, whatever it would change', function () {
        $dir = ($this->host)();
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir, dry: true);

        expect($code)->toBe(0)
            ->and(upgradeCodemodTree($dir))->toBe($before)
            ->and($output)->toContain('--- a/app/Livewire/Orders.php')->toContain('+++ b/app/Livewire/Orders.php')
            ->and($output)->toContain('=== ATOM-UPGRADE-TODO.md (would be written) ===')
            ->and(file_exists($dir.'/ATOM-UPGRADE-TODO.md'))->toBeFalse();
    });

    it('prints a unified diff with relative paths and the exact change of each rewrite', function () {
        [, $output] = upgradeCodemodRun(($this->host)(), dry: true);

        // the traits, with their imports in alphabetical place and the old import gone
        expect($output)->toContain(upgradeCodemodDiff(<<<'DIFF'
--- a/app/Livewire/Orders.php
+++ b/app/Livewire/Orders.php
@@ -3,12 +3,15 @@
 namespace App\Livewire;

 use Jiannius\Atom\Services\Carbon;
-use Jiannius\Atom\Traits\AtomComponent;
+use Jiannius\Atom\Traits\WithBreadcrumbs;
+use Jiannius\Atom\Traits\WithTable;
 use Livewire\Component;
+use Livewire\WithFileUploads;
+use Livewire\WithPagination;

 class Orders extends Component
 {
-    use AtomComponent;
+    use WithTable, WithBreadcrumbs, WithPagination, WithFileUploads;

     protected $listeners = ['saved' => 'toast'];

DIFF));

        // an alias for the trait, and a component that needs none: the statement and the import go
        expect($output)->toContain(upgradeCodemodDiff(<<<'DIFF'
--- a/app/Livewire/Aliased.php
+++ b/app/Livewire/Aliased.php
@@ -2,10 +2,8 @@

 namespace App\Livewire;

-use Jiannius\Atom\Traits\AtomComponent as Atom;
 use Livewire\Component;

 class Aliased extends Component
 {
-    use Atom;
 }
DIFF));

        // the Date::use line, first in boot(), with its import
        expect($output)->toContain(upgradeCodemodDiff(<<<'DIFF'
--- a/app/Providers/AppServiceProvider.php
+++ b/app/Providers/AppServiceProvider.php
@@ -2,11 +2,13 @@

 namespace App\Providers;

+use Illuminate\Support\Facades\Date;
 use Illuminate\Support\ServiceProvider;

 class AppServiceProvider extends ServiceProvider
 {
     public function boot(): void
     {
+        Date::use(\Jiannius\Atom\Services\Carbon::class);
     }
 }
DIFF));

        // a single-file component: its trait, the editor tags (opening, closing, self-closing, dotted, both spellings) and the dead prop
        expect($output)->toContain(upgradeCodemodDiff(<<<'DIFF'
--- a/resources/views/livewire/profile.blade.php
+++ b/resources/views/livewire/profile.blade.php
@@ -1,21 +1,22 @@
 <?php

-use Jiannius\Atom\Traits\AtomComponent;
+use Jiannius\Atom\Traits\WithEditor;
+use Jiannius\Atom\Traits\WithRecaptcha;
 use Livewire\Component;

 new class extends Component {
-    use AtomComponent;
+    use WithEditor, WithRecaptcha;

     public $name;
 };
 ?>

-<atom:html editor>
+<atom:html>
     <atom:form recaptcha>
-        <atom:editor wire:model="bio" />
-        <atom:editor.chat />
-        <atom:editor.content>Some <b>static</b> prose</atom:editor.content>
+        <atom:tiptap wire:model="bio" />
+        <atom:tiptap.chat />
+        <atom:tiptap.content>Some <b>static</b> prose</atom:tiptap.content>
         <atom:tiptap.content :content="$bio" />
-        <x-atom::editor.content :content="$bio" />
+        <x-atom::tiptap.content :content="$bio" />
     </atom:form>
 </atom:html>
DIFF));

        // the CSS: atom's @source line is replaced by atom's import, and the copied border rule goes; the host's own rules stay
        expect($output)->toContain(upgradeCodemodDiff(<<<'DIFF'
--- a/resources/css/app.css
+++ b/resources/css/app.css
@@ -1,5 +1,5 @@
 @import 'tailwindcss';
-@source '../../vendor/jiannius/atom/resources/**/*.blade.php';
+@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';

 @font-face {
     font-family: 'Inter';
@@ -8,14 +8,6 @@

 html {
     font-size: 14px;
-}
-
-@layer base {
-    *,
-    ::after,
-    ::before {
-        border-color: var(--color-gray-200, currentColor);
-    }
 }

 /* html { font-size: 20px } in a comment is not a rule */
DIFF));
    });

    it('prints the TODO it would write, and the gate result last', function () {
        [$code, $output] = upgradeCodemodRun(($this->host)(), dry: true);

        expect($code)->toBe(0)
            ->and($output)->toContain('# atom 4 upgrade: what is left')
            ->and(rtrim($output))->toEndWith('Ready: atom:tiptap-migrate has nothing left, so atom:upgrade will write.');

        upgradeCodemodGate(upgradeCodemodReady(false));

        [$code, $output] = upgradeCodemodRun(($this->host)(), dry: true);

        expect($code)->toBe(0)
            ->and($output)->toContain('--- a/app/Livewire/Orders.php')
            ->and(strpos($output, '=== Gate ==='))->toBeGreaterThan(strpos($output, '# atom 4 upgrade'))
            ->and($output)->toContain('Not ready: atom:upgrade will refuse to write.')
            ->and($output)->toContain('App\Models\Article: body still cast as AsEditorContent')
            ->and($output)->toContain('atom:tiptap-migrate --status');
    });
});

describe('atom:upgrade: the Tailwind entry file', function () {
    it('rewrites the skeleton-project shape to atom\'s import and keeps nothing else', function () {
        $dir = ($this->host)();
        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/skeleton.css'))->toBe(<<<'CSS'
@import 'tailwindcss';
@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

CSS);
    });

    it('holds the names it removes to the pinned skeleton file, declaration by declaration', function () {
        $css = (string) file_get_contents(__DIR__.'/../Fixtures/skeleton-app.css');
        $found = collect((new UpgradeCss)->skeletonDeclarations($css));

        // every declaration in the skeleton's @theme and .dark is one the codemod knows, and the other way round
        preg_match_all('/^\s+(--[\w-]+)\s*:/m', $css, $declared);

        expect($found)->toHaveCount(count(UpgradeCss::THEME) + count(UpgradeCss::DARK))
            ->and($declared[1])->toHaveCount(count(UpgradeCss::THEME) + count(UpgradeCss::DARK))
            ->and($found->pluck(1)->unique()->sort()->values()->all())->toBe(collect(array_keys(UpgradeCss::THEME))->sort()->values()->all());
    });

    it('reads a brand colour off a customised --color-primary into --color-accent, and keeps primary', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/resources/css/skeleton.css', str_replace('--color-primary: var(--color-zinc-900);', '--color-primary: #4c55c9;', file_get_contents($dir.'/resources/css/skeleton.css')));
        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/skeleton.css'))->toBe(<<<'CSS'
@import 'tailwindcss';
@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --color-accent: #4c55c9;
    --color-primary: #4c55c9;
    --color-primary-content: var(--color-zinc-50);
    --color-primary-foreground: var(--color-zinc-50);
}

@layer theme {
    .dark {
        --color-primary: var(--color-zinc-50);
        --color-primary-content: var(--color-zinc-900);
        --color-primary-foreground: var(--color-zinc-900);
    }
}

CSS);

        expect(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`resources/css/skeleton.css:10` sets `--color-accent: #4c55c9;`');
    });

    it('leaves a host\'s own rules, other @source lines, @plugin and its own tokens byte for byte', function () {
        $dir = ($this->host)();
        $extra = <<<'CSS'

/* sortable */
.sortable-ghost {
    @apply outline-1 outline-offset-2 outline-zinc-300;

    @variant dark {
        @apply outline-zinc-500;
    }
}
CSS;
        $css = (string) file_get_contents(__DIR__.'/../Fixtures/skeleton-app.css');
        $css = str_replace("@import 'tailwindcss';\n", "@import 'tailwindcss';\n@plugin \"@tailwindcss/typography\";\n", $css);
        $css = str_replace("@source '../../vendor/jiannius/atom/src/Actions/*.php';\n", "@source '../../vendor/jiannius/atom/src/Actions/*.php';\n@source '../../vendor/jiannius/filesystem/components/**/*.blade.php';\n", $css);
        $css = str_replace('    --color-primary: var(--color-zinc-900);', "    --breakpoint-3xl: 120rem;\n\n    --color-primary: var(--color-zinc-900);", $css);
        $css = str_replace('        --color-primary: var(--color-zinc-50);', "        /* flip the muted pair */\n        --color-muted: var(--color-zinc-400);\n\n        --color-primary: var(--color-zinc-50);", $css);
        file_put_contents($dir.'/resources/css/skeleton.css', $css.$extra);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/skeleton.css'))->toBe(<<<'CSS'
@import 'tailwindcss';
@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';
@plugin "@tailwindcss/typography";

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/jiannius/filesystem/components/**/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --breakpoint-3xl: 120rem;
}

@layer theme {
    .dark {
        /* flip the muted pair */
        --color-muted: var(--color-zinc-400);
    }
}

/* sortable */
.sortable-ghost {
    @apply outline-1 outline-offset-2 outline-zinc-300;

    @variant dark {
        @apply outline-zinc-500;
    }
}
CSS);

        // what it left of the skeleton's names is said, with its line
        expect(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`resources/css/skeleton.css:18`: .dark sets --color-muted: var(--color-zinc-400) (not the skeleton value');
    });

    it('does not touch a file that is not a Tailwind entry for atom, and says why', function () {
        $dir = ($this->host)(false);
        $legacy = "/* no tailwind import here */\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: var(--color-zinc-900);\n}\n";
        $other = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom-livewire/resources/**/*.blade.php';\n@source '../../vendor/jiannius/atom/components/**/*.blade.php';\n";
        file_put_contents($dir.'/resources/css/legacy.css', $legacy);
        file_put_contents($dir.'/resources/css/other.css', $other);

        upgradeCodemodRun($dir);

        // no plain `@import 'tailwindcss'`: not a file the import can be placed in
        expect(file_get_contents($dir.'/resources/css/legacy.css'))->toBe($legacy)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain("`resources/css/legacy.css:1`: not changed, no plain @import 'tailwindcss'");

        // another package's @source line stays; atom's own goes
        expect(file_get_contents($dir.'/resources/css/other.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n@source '../../vendor/jiannius/atom-livewire/resources/**/*.blade.php';\n");
    });

    it('finds nothing to do in a file that has atom\'s import already', function () {
        $dir = ($this->host)(false);
        $done = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-accent: #c0392b;\n}\n";
        file_put_contents($dir.'/resources/css/done.css', $done);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/done.css'))->toBe($done);
    });

    it('computes the import path from the file\'s own depth', function () {
        $css = new UpgradeCss;

        expect($css->relativeEntry('resources/css/app.css'))->toBe('../../vendor/jiannius/atom/resources/css/atom.tailwind.css')
            ->and($css->relativeEntry('resources/css/admin/app.css'))->toBe('../../../vendor/jiannius/atom/resources/css/atom.tailwind.css')
            ->and($css->relativeEntry('app.css'))->toBe('vendor/jiannius/atom/resources/css/atom.tailwind.css');
    });

    it('keeps a CRLF file CRLF', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/resources/css/skeleton.css', str_replace("\n", "\r\n", file_get_contents($dir.'/resources/css/skeleton.css')));

        upgradeCodemodRun($dir);

        $css = file_get_contents($dir.'/resources/css/skeleton.css');

        expect(substr_count($css, "\r\n"))->toBe(substr_count($css, "\n"))
            ->and($css)->toContain("@import 'tailwindcss';\r\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\r\n");
    });
});

describe('atom:upgrade: the PHP and Blade rewrites', function () {
    it('declines a use statement it cannot rewrite safely, and the Date::use line when there is nowhere to put it', function () {
        $dir = ($this->host)(false);
        file_put_contents($dir.'/app/Livewire/Adapted.php', "<?php\n\nnamespace App\\Livewire;\n\nuse Jiannius\\Atom\\Traits\\AtomComponent;\nuse Livewire\\Component;\n\nclass Adapted extends Component\n{\n    use AtomComponent { toast as protected; }\n}\n");
        unlink($dir.'/app/Providers/AppServiceProvider.php');

        upgradeCodemodRun($dir);

        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect(file_get_contents($dir.'/app/Livewire/Adapted.php'))->toContain('use AtomComponent { toast as protected; }')
            ->and($todo)->toContain('`app/Livewire/Adapted.php:10`: not changed, the `use` statement has a `{ ... }` block of conflict rules')
            ->and($todo)->toContain('`app/Providers/AppServiceProvider.php`: not changed, there is no AppServiceProvider.php')
            ->and(file_exists($dir.'/app/Providers/AppServiceProvider.php'))->toBeFalse();
    });

    it('leaves a provider that already calls Date::use with another class, or has no boot()', function () {
        $dir = ($this->host)(false);
        $provider = "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\Facades\\Date;\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function boot(): void\n    {\n        Date::use(\\Carbon\\CarbonImmutable::class);\n    }\n}\n";
        file_put_contents($dir.'/app/Providers/AppServiceProvider.php', $provider);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toBe($provider)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('already calls Date::use() with another class');

        file_put_contents($dir.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n    }\n}\n");
        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('it has no boot() method');
    });

    it('puts the call first in a boot() that has code, with a blank line after it', function () {
        $dir = ($this->host)(false);
        file_put_contents($dir.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function boot()\n    {\n        Model::shouldBeStrict();\n    }\n}\n");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toContain("use Illuminate\\Support\\Facades\\Date;\nuse Illuminate\\Support\\ServiceProvider;")
            ->toContain("    {\n        Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);\n\n        Model::shouldBeStrict();\n    }");
    });

    it('renames editor tags only: not a comment, not another tag, not a name that only starts with editor', function () {
        $dir = ($this->host)(false);
        file_put_contents($dir.'/resources/views/tags.blade.php', <<<'BLADE'
{{-- <atom:editor> in a Blade comment --}}
<atom:editor
    wire:model="a"
/>
<atom:editor-toolbar />
<atom:editor.content>x</atom:editor.content>
<x-atom::editor.chat />
<atom:html
    editor
    title="x"
>
<atom:html :editor="true">
<atom:layouts.sidebar editor="true" />
BLADE);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/views/tags.blade.php'))->toBe(<<<'BLADE'
{{-- <atom:editor> in a Blade comment --}}
<atom:tiptap
    wire:model="a"
/>
<atom:editor-toolbar />
<atom:tiptap.content>x</atom:tiptap.content>
<x-atom::tiptap.chat />
<atom:html
    title="x"
>
<atom:html>
<atom:layouts.sidebar />
BLADE);
    });
});

describe('atom:upgrade: the gate', function () {
    it('refuses to write while the editor columns are not migrated, and writes nothing', function () {
        upgradeCodemodGate(upgradeCodemodReady(false));
        $dir = ($this->host)();
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->not->toBe(0)
            ->and(upgradeCodemodTree($dir))->toBe($before)
            ->and($output)->toContain('Not writing anything')
            ->and($output)->toContain('App\Models\Article: body still cast as AsEditorContent')
            ->and($output)->toContain('3 value(s) not yet migrated (1 flagged, 1 refused)')
            ->and($output)->toContain('php artisan atom:tiptap-migrate --status')
            ->and($output)->toContain('Moving an app to v4')
            ->and(file_exists($dir.'/ATOM-UPGRADE-TODO.md'))->toBeFalse();
    });

    it('refuses when the status cannot be read, for instance without a database', function () {
        upgradeCodemodGate(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'));
        $dir = ($this->host)();
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->not->toBe(0)
            ->and(upgradeCodemodTree($dir))->toBe($before)
            ->and($output)->toContain('Could not read the editor columns')->toContain('Connection refused');
    });

    it('refuses on a problem the status reports even when no value is left', function () {
        upgradeCodemodGate([...upgradeCodemodReady(false), 'legacy_casts' => [], 'unmigrated' => 0, 'problems' => ['App\Models\Post: no primary key column']]);

        [$code, $output] = upgradeCodemodRun(($this->host)());

        expect($code)->not->toBe(0)->and($output)->toContain('Could not read: App\Models\Post: no primary key column');
    });

    it('writes when the status is ready', function () {
        $dir = ($this->host)();

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->toBe(0)
            ->and($output)->toContain('changed  app/Livewire/Orders.php  (traits)')
            ->and($output)->toContain('Wrote      ATOM-UPGRADE-TODO.md')
            ->and(file_get_contents($dir.'/app/Livewire/Orders.php'))->toContain('use WithTable, WithBreadcrumbs, WithPagination, WithFileUploads;')
            ->and(file_exists($dir.'/ATOM-UPGRADE-TODO.md'))->toBeTrue();
    });

    it('reads the real status: an AsEditorContent cast left, then nothing left', function () {
        app()->bind(MigrateTiptapContent::class, fn () => new MigrateTiptapContent);
        $this->app->useAppPath(__DIR__.'/../Fixtures/UpgradeGate');
        require_once __DIR__.'/../Fixtures/UpgradeGate/Models/UpgradeGateArticle.php';
        Schema::create('upgrade_gate_articles', function (Blueprint $t) {
            $t->id();
            $t->text('body')->nullable();
        });
        $dir = ($this->host)();
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->not->toBe(0)
            ->and($output)->toContain('App\Models\UpgradeGateArticle: body still cast as AsEditorContent')
            ->and(upgradeCodemodTree($dir))->toBe($before);

        $empty = sys_get_temp_dir().'/atom-upgrade-app-'.bin2hex(random_bytes(4));
        $this->hosts[] = $empty;
        mkdir($empty.'/Models', 0777, true);
        $this->app->useAppPath($empty);

        [$code] = upgradeCodemodRun($dir);

        expect($code)->toBe(0)->and(file_get_contents($dir.'/app/Livewire/Orders.php'))->toContain('use WithTable');
    });
});

describe('atom:upgrade: running it twice', function () {
    it('changes nothing the second time and prints no diff', function () {
        $dir = ($this->host)();

        upgradeCodemodRun($dir);
        $after = upgradeCodemodTree($dir);
        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        [$code, $output] = upgradeCodemodRun($dir, dry: true);

        expect($code)->toBe(0)
            ->and($output)->toContain('No file would change.')
            ->and($output)->not->toContain('--- a/')
            ->and($output)->toContain('=== ATOM-UPGRADE-TODO.md (unchanged) ===');

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->toBe(0)
            ->and($output)->toContain('No file needed changing.')
            ->and($output)->toContain('Unchanged  ATOM-UPGRADE-TODO.md')
            ->and(upgradeCodemodTree($dir))->toBe($after)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toBe($todo);
    });

    it('keeps what the first run changed in the TODO, and lists it once', function () {
        $dir = ($this->host)();

        upgradeCodemodRun($dir);
        upgradeCodemodRun($dir);
        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect(substr_count($todo, '`app/Livewire/Orders.php:11`: `use AtomComponent;` became `use WithTable, WithBreadcrumbs, WithPagination, WithFileUploads;`'))->toBe(1)
            ->and($todo)->toContain('`resources/css/skeleton.css`: added atom\'s import after `@import \'tailwindcss\'`; removed 3 atom `@source` lines; removed 26 theme declarations');
    });

    it('rewrites a tree in the PHP it can still parse', function () {
        $dir = ($this->host)();
        upgradeCodemodRun($dir);

        foreach (['app/Livewire/Orders.php', 'app/Livewire/Aliased.php', 'app/Providers/AppServiceProvider.php'] as $file) {
            expect(token_get_all(file_get_contents($dir.'/'.$file), TOKEN_PARSE))->not->toBeEmpty($file);
        }
    });
});

describe('atom:upgrade: the TODO', function () {
    it('lists what is left by UPGRADE.md section with file:line, and the rest it needs', function () {
        $dir = ($this->host)();
        upgradeCodemodRun($dir);
        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect($todo)
            // what it changed, with the trait evidence
            ->toContain('- `app/Livewire/Orders.php:11`: `use AtomComponent;` became `use WithTable, WithBreadcrumbs, WithPagination, WithFileUploads;`. Check the traits: WithTable (<atom:table> in the view); WithBreadcrumbs (defines breadcrumbs($b))')
            ->toContain('- `resources/views/livewire/profile.blade.php`: 4 `<atom:editor*>` tags renamed to `<atom:tiptap*>`')
            ->toContain("- `app/Providers/AppServiceProvider.php`: added `Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);` to boot()")
            // the sections of UPGRADE.md, in the findings
            ->toContain('### Tokens')->toContain('### Livewire traits')->toContain('### JS globals')->toContain('### PHP globals: dates and macros')
            // JS globals, $wire helpers, listeners
            ->toContain('  - `resources/js/app.js:3` dd(rows)')
            ->toContain('**js-currency** (breaks at runtime, 1)')
            ->toContain('  - `resources/views/components/widget.blade.php:22` <button @click="$wire.alert(\'Hi\')">hi</button>')
            ->toContain('**wire-helper-listener** (breaks at runtime, 10)')
            ->toContain('  - `app/Livewire/Orders.php:16` protected $listeners = [\'saved\' => \'toast\'];')
            // an automated check whose only findings left are in tests
            ->toContain('**use-atom-component** (breaks at runtime, 1): AtomComponent is deleted: use only the traits the component needs (automated for the app, not for tests/: change these by hand)')
            ->toContain('  - `tests/Feature/OrdersTest.php:15` (tests) use AtomComponent;')
            // a restyled component with a class on it
            ->toContain('  - `resources/views/livewire/orders.blade.php:16` <atom:link href="/x" class="text-sky-600">Link</atom:link> <- the link is the accent ink now')
            // the accent choice and the content gate
            ->toContain('## Accent')->toContain('sets no `--color-accent`')
            ->toContain('php artisan atom:tiptap-migrate --status')
            ->toContain('## Restyled components in use')->toContain('- Controls: button x6');

        // the date findings are marked as covered now the line is there
        expect($todo)->toContain('<- covered by the Date::use line (app/Providers/AppServiceProvider.php)');
    });

    it('has no line the rewrites fixed', function () {
        $dir = ($this->host)();
        upgradeCodemodRun($dir);
        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect($todo)->not->toContain('**css-atom-source**')
            ->not->toContain('**css-skeleton-theme**')
            ->not->toContain('**css-border-color-rule**')
            ->not->toContain('**editor-tag**')
            ->not->toContain('**html-editor-prop**')
            ->not->toContain('**date-use-line**');
    });
});

describe('atom:upgrade: the codemod verdict', function () {
    it('rewrites exactly the checks UpgradeCheck::checks() marks auto, and every rewrite answers for one', function () {
        $auto = collect(UpgradeCheck::checks())->filter(fn ($check) => $check['codemod'] === 'auto')->keys()->sort()->values()->all();
        $answered = collect(Upgrade::REWRITES)->flatten()->sort()->values()->all();

        expect($answered)->toBe($auto);

        // on the fixture each of them has findings, the codemod changes the files they are in, and none is left in the app after
        $dir = ($this->host)();
        $before = (new UpgradeCheck)->scan($dir);
        $plan = (new Upgrade)->plan($dir);

        foreach ($auto as $id) {
            expect($before['checks'][$id]['count'])->toBeGreaterThan(0, $id);
            expect($plan['scan']['checks'][$id]['count'])->toBe(0, $id);
        }

        $kinds = collect($plan['changes'])->flatMap(fn ($change) => $change['kinds'])->unique()->sort()->values()->all();

        expect($kinds)->toBe(collect(array_keys(Upgrade::REWRITES))->sort()->values()->all());

        // the files changed are the ones the auto checks found (and no other)
        $found = collect($auto)->flatMap(fn ($id) => array_column($before['checks'][$id]['findings'], 'file'))
            ->filter(fn ($file) => $file !== '' && ! str_starts_with($file, 'tests/'))
            ->unique()->sort()->values()->all();

        expect(array_keys($plan['changes']))->toBe($found);
    });

    it('is registered', function () {
        expect(array_key_exists('atom:upgrade', Artisan::all()))->toBeTrue();
    });

    it('prints a diff that patch can apply', function () {
        $dir = ($this->host)();
        [, $output] = upgradeCodemodRun($dir, dry: true);
        $diff = substr($output, 0, strpos($output, "\n=== ATOM-UPGRADE-TODO.md"));
        file_put_contents($dir.'/changes.diff', $diff);
        exec('cd '.escapeshellarg($dir).' && patch -p1 --dry-run -s < changes.diff 2>&1', $lines, $status);

        expect($status)->toBe(0, implode("\n", $lines));
    });
});

describe('atom:upgrade: the diff', function () {
    it('shows only the changed lines with three lines of context and merges hunks that touch', function () {
        $upgrade = new Upgrade;
        $before = implode('', array_map(fn ($n) => "line $n\n", range(1, 30)));
        $after = str_replace(["line 5\n", "line 9\n", "line 25\n"], ["five\n", "nine\n", "twenty-five\n"], $before);

        expect($upgrade->unifiedDiff('a.txt', $before, $after))->toBe(<<<'DIFF'
--- a/a.txt
+++ b/a.txt
@@ -2,11 +2,11 @@
 line 2
 line 3
 line 4
-line 5
+five
 line 6
 line 7
 line 8
-line 9
+nine
 line 10
 line 11
 line 12
@@ -22,7 +22,7 @@
 line 22
 line 23
 line 24
-line 25
+twenty-five
 line 26
 line 27
 line 28

DIFF);
    });

    it('marks a file with no newline at the end, and an equal pair has no diff', function () {
        $upgrade = new Upgrade;

        expect($upgrade->unifiedDiff('a', "a\nb", "a\nc"))->toBe("--- a/a\n+++ b/a\n@@ -1,2 +1,2 @@\n a\n-b\n\\ No newline at end of file\n+c\n\\ No newline at end of file\n")
            ->and($upgrade->unifiedDiff('a', "a\n", "a\n"))->toBe('')
            ->and($upgrade->unifiedDiff('a', '', "x\n"))->toBe("--- a/a\n+++ b/a\n@@ -0,0 +1 @@\n+x\n");
    });
});
