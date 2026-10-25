<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Commands\MigrateTiptapContent;
use Jiannius\Atom\Services\Upgrade;
use Jiannius\Atom\Services\UpgradeCheck;
use Jiannius\Atom\Services\UpgradeCss;
use Jiannius\Atom\Services\UpgradePhp;

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

/**
 * UPGRADE.md by `## ` heading (backticks dropped), each with its text
 *
 * @return array<string, string>
 */
function upgradeGuideSections(): array
{
    $guide = (string) file_get_contents(__DIR__.'/../../UPGRADE.md');
    $sections = [];

    foreach (preg_split('/^## /m', $guide) as $index => $chunk) {
        if ($index === 0) {
            continue;
        }

        [$heading, $body] = explode("\n", $chunk, 2) + [1 => ''];
        $sections[str_replace('`', '', trim($heading))] = $body;
    }

    return $sections;
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
@@ -2,13 +2,10 @@

 namespace App\Livewire;

-use Jiannius\Atom\Traits\AtomComponent as Atom;
 use Livewire\Component;

 class Aliased extends Component
 {
-    use Atom;
-
     public function render()
     {
         return view('partials.blank');
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

    it('keeps a yellow-500 accent that stands alone in a v4 entry file, and a second run finds nothing to do', function (string $theme) {
        $dir = ($this->host)(false);
        $done = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n".$theme."}\n";
        file_put_contents($dir.'/resources/css/yellow.css', $done);

        upgradeCodemodRun($dir);
        upgradeCodemodRun($dir);

        $found = array_filter((new UpgradeCheck)->scan($dir)['checks']['css-skeleton-theme']['findings'], fn ($finding) => $finding['file'] === 'resources/css/yellow.css');

        expect(file_get_contents($dir.'/resources/css/yellow.css'))->toBe($done)
            ->and($found)->toBe([])
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->not->toContain('yellow.css:1')->not->toContain('skeleton value')->not->toContain('left alone');
    })->with([
        'the accent' => ["    --color-accent: var(--color-yellow-500);\n"],
        'the accent and its content colour' => ["    --color-accent: var(--color-yellow-500);\n    --color-accent-content: var(--color-yellow-500);\n"],
    ]);

    it('keeps a lone yellow-500 accent in .dark too, and still removes the whole block of a first run', function () {
        $css = new UpgradeCss;
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-accent: var(--color-yellow-500);\n}\n\n@layer theme {\n    .dark {\n        --color-accent: var(--color-yellow-500);\n    }\n}\n";

        expect($css->rewrite($v4, 'resources/css/app.css')['css'])->toBe($v4)
            ->and($css->skeletonDeclarations($v4))->toBe([])
            ->and($css->residue($v4))->toBe([]);

        $skeleton = (string) file_get_contents(__DIR__.'/../Fixtures/skeleton-app.css');
        $first = $css->rewrite($skeleton, 'resources/css/app.css');

        expect($first['removed'])->toContain('--color-accent', '--color-accent-content')
            ->and($first['css'])->not->toContain('yellow-500')
            ->and($css->rewrite($first['css'], 'resources/css/app.css')['changed'])->toBeFalse();
    });

    it('removes the accent while another skeleton declaration is still beside it, and a yellow accent is not a brand to add', function () {
        $css = new UpgradeCss;
        $beside = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n";
        $result = $css->rewrite($beside, 'resources/css/app.css');

        expect($result['css'])->not->toContain('yellow-500')->not->toContain('--color-muted');

        // a customised primary is not copied over an accent the host keeps
        $kept = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: #c0392b;\n    --color-accent: var(--color-yellow-500);\n}\n";
        $result = $css->rewrite($kept, 'resources/css/app.css');

        expect($result['css'])->toContain('--color-accent: var(--color-yellow-500);')->toContain('--color-primary: #c0392b;')
            ->and($result['brand'])->toBeNull();
    });

    it('keeps a deliberate yellow accent beside a customised primary, run after run, and reports nothing for it', function () {
        $dir = ($this->host)(false);
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-accent: var(--color-yellow-500);\n    --color-primary: #c0392b;\n    --color-primary-content: var(--color-zinc-50);\n    --color-primary-foreground: var(--color-zinc-50);\n}\n\n@layer theme {\n    .dark {\n        --color-primary: #e74c3c;\n        --color-primary-content: var(--color-zinc-900);\n        --color-primary-foreground: var(--color-zinc-900);\n    }\n}\n";
        file_put_contents($dir.'/resources/css/red.css', $v4);

        upgradeCodemodRun($dir);
        upgradeCodemodRun($dir);

        $lines = array_column(array_filter((new UpgradeCheck)->scan($dir)['checks']['css-skeleton-theme']['findings'], fn ($finding) => $finding['file'] === 'resources/css/red.css'), 'line');

        // the accent is on line 5; what the report lists is the kept primary's leftovers, which atom defines now
        expect(file_get_contents($dir.'/resources/css/red.css'))->toBe($v4)
            ->and(array_filter(array_column((new UpgradeCss)->skeletonDeclarations($v4), 1), fn ($name) => str_starts_with($name, '--color-accent')))->toBe([])
            ->and($lines)->not->toContain(5);
    });

    it('counts only a clean skeleton declaration as proof that the block is the skeleton\'s', function () {
        $css = new UpgradeCss;
        $head = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n";

        // a commented line is not removed, so it is no anchor: the lone accent stays
        $commented = $head."    --color-muted: var(--color-zinc-500); /* mine */\n    --color-accent: var(--color-yellow-500);\n}\n";
        expect($css->rewrite($commented, 'resources/css/app.css')['css'])->toContain('--color-accent: var(--color-yellow-500);')->toContain('--color-muted')
            ->and($css->ownsAccent($commented))->toBeTrue();

        // the same line, clean: the accent goes with it
        $clean = $head."    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n";
        expect($css->rewrite($clean, 'resources/css/app.css')['css'])->not->toContain('--color-accent')->not->toContain('--color-muted')
            ->and($css->ownsAccent($clean))->toBeFalse();

        // a kept primary and accent-foreground are not proof either
        $primary = $head."    --color-primary: #c0392b;\n    --color-primary-content: var(--color-zinc-50);\n    --color-accent: var(--color-yellow-500);\n    --color-accent-foreground: var(--color-white);\n}\n";
        expect($css->ownsAccent($primary))->toBeTrue();

        // a file that has atom's import has been through a run: whatever sits in it is the host's, a clean anchor or not
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n";
        expect($css->ownsAccent($v4))->toBeTrue()
            ->and($css->rewrite($v4, 'resources/css/app.css')['css'])->toContain('--color-accent: var(--color-yellow-500);')->not->toContain('--color-muted')
            ->and(array_column($css->skeletonDeclarations($v4), 1))->toBe(['--color-muted']);
    });

    it('adds no brand over a yellow accent that is kept only in .dark', function () {
        $css = new UpgradeCss;
        $dark = "@layer theme {\n    .dark {\n        --color-accent: var(--color-yellow-500);\n    }\n}\n";

        // a v4 file, and a first run with only a customised primary and the dark accent
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-primary: #c0392b;\n}\n".$dark;
        $first = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: #c0392b;\n}\n".$dark;

        foreach ([$v4, $first] as $input) {
            $result = $css->rewrite($input, 'resources/css/app.css');

            expect($result['brand'])->toBeNull()
                ->and($result['css'])->toContain('.dark')->toContain('--color-accent: var(--color-yellow-500);')
                ->and(substr_count($result['css'], '--color-accent:'))->toBe(1)
                ->and($css->rewrite($result['css'], 'resources/css/app.css')['changed'])->toBeFalse();
        }
    });

    it('adds no brand colour while any accent is left in the file, wherever it sits', function (string $css, int $accents) {
        $codemod = new UpgradeCss;
        $head = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n";
        $result = $codemod->rewrite($head.$css, 'resources/css/app.css');
        $again = $codemod->rewrite($result['css'], 'resources/css/app.css');

        expect($result['brand'])->toBeNull()
            ->and($result['added'])->each->not->toContain('--color-accent: #c0392b')
            ->and(preg_match_all('/(?<![\w-])--color-accent(?:-content)?\s*:/', $result['css']))->toBe($accents)
            ->and($again['changed'])->toBeFalse();
    })->with([
        // a skeleton accent on a line with a comment is not removed, so it is the one accent: no second line above it
        'a commented accent' => ["@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500); /* brand */\n}\n", 1],
        'a kept accent-content beside a customised primary' => ["@theme {\n    --color-primary: #c0392b;\n    --color-accent-content: var(--color-yellow-500);\n}\n", 1],
        'a top-level .dark rule' => ["@theme {\n    --color-primary: #c0392b;\n}\n\n.dark {\n    --color-accent: var(--color-yellow-500);\n}\n", 1],
        'a .dark rule with two selectors' => ["@theme {\n    --color-primary: #c0392b;\n}\n\n@layer theme {\n    .dark, [data-theme=dark] {\n        --color-accent: var(--color-yellow-500);\n    }\n}\n", 1],
        'a custom accent in .dark of a first run' => ["@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n\n@layer theme {\n    .dark {\n        --color-accent: #ff0;\n    }\n}\n", 1],
        'a commented accent in .dark' => ["@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n\n@layer theme {\n    .dark {\n        --color-accent: var(--color-yellow-500); /* keep */\n    }\n}\n", 1],
        'a kept accent-content in .dark' => ["@theme {\n    --color-primary: #c0392b;\n}\n\n@layer theme {\n    .dark {\n        --color-accent-content: var(--color-yellow-500);\n    }\n}\n", 1],
    ]);

    it('still moves the brand when the skeleton accent is the only accent and goes with the rest', function () {
        $result = (new UpgradeCss)->rewrite("@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n/* --color-accent: red; an old try, commented out */\n@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n    --color-accent-content: var(--color-yellow-500);\n}\n", 'resources/css/app.css');

        expect($result['brand'])->toBe('#c0392b')
            ->and(preg_match_all('/^[ \t]+--color-accent\s*:/m', $result['css']))->toBe(1)
            ->and($result['css'])->toContain('--color-accent: #c0392b;')->not->toContain('yellow-500');
    });

    it('still moves the brand when the only other mention of an accent is not a declaration', function (string $extra) {
        $result = (new UpgradeCss)->rewrite("@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n".$extra, 'resources/css/app.css');

        expect($result['brand'])->toBe('#c0392b')
            ->and($result['brand_skipped'])->toBeNull()
            ->and($result['css'])->toContain('--color-accent: #c0392b;')->not->toContain('yellow-500');
    })->with([
        'a string' => [".x::before { content: \"--color-accent: red\"; }\n"],
        'an unquoted url()' => [".x { background: url(data:image/svg+xml;--color-accent:x); }\n"],
        'an attribute selector' => ["[style*=\"--color-accent:\"] { outline: 1px solid; }\n"],
        'another token that ends in the name' => [".x { --brand--color-accent: 1; --tw-color-accent: 1; }\n"],
        'another token that starts with the name' => [".x { --color-accent-hover: red; }\n"],
        'a use of the token as a value' => [".x { color: var(--color-accent); }\n"],
    ]);

    it('says why no brand was added, and does not claim the accent is unset', function () {
        $dir = ($this->host)(false);
        $css = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n\n.marketing {\n    --color-accent: #0af;\n}\n";
        file_put_contents($dir.'/resources/css/scoped.css', $css);

        $result = (new UpgradeCss)->rewrite($css, 'resources/css/scoped.css');

        expect($result['brand'])->toBeNull()
            ->and($result['brand_skipped'])->toBe(['line' => 9, 'name' => '--color-accent', 'value' => '#c0392b']);

        foreach ([1, 2] as $run) {
            upgradeCodemodRun($dir);
            $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

            expect($todo)->not->toContain('`resources/css/scoped.css` sets no `--color-accent`')
                ->and($todo)->toContain('`resources/css/scoped.css:');
        }

        expect($todo)->toContain('sets `--color-accent`, and `@theme` sets no `--color-accent`');
    });

    it('names the surviving accent and the primary that was not copied, in the run that skipped it', function () {
        $dir = ($this->host)(false);
        file_put_contents($dir.'/resources/css/scoped.css', "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-primary: #c0392b;\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n\n.marketing {\n    --color-accent: #0af;\n}\n");

        [, $output] = upgradeCodemodRun($dir, dry: true);

        expect($output)->toContain('`resources/css/scoped.css:9` sets `--color-accent`, so your `--color-primary: #c0392b` was not copied as the accent; set `--color-accent` in `@theme` if it is your brand.');
    });

    it('takes an import in a string continued over a line for no import, and a path that only ends in vendor/ for none', function () {
        $css = new UpgradeCss;
        $escaped = "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n.x::after {\n    content: \"see \\\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\";\n}\n";
        $result = $css->rewrite($escaped, 'resources/css/app.css');

        expect($css->hasEntryImport($escaped))->toBeFalse()
            ->and($result['added'])->toBe(["@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css'"])
            ->and($result['css'])->toContain("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n")
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@import '../../myvendor/jiannius/atom/resources/css/atom.tailwind.css';\n"))->toBeFalse()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@import 'vendor/jiannius/atom/resources/css/atom.tailwind.css';\n"))->toBeTrue()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@IMPORT '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n"))->toBeTrue()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@import url(vendor/jiannius/atom/resources/css/atom.tailwind.css);\n"))->toBeTrue();
    });

    it('reads atom\'s import in the other spellings, and adds no second one', function (string $line) {
        $css = new UpgradeCss;
        $input = "@import 'tailwindcss';\n".$line."@source '../../vendor/jiannius/atom/resources/**/*.blade.php';\n\n@theme {\n    --color-muted: var(--color-zinc-500);\n    --color-accent: var(--color-yellow-500);\n}\n";
        $result = $css->rewrite($input, 'resources/css/app.css');

        expect($css->hasEntryImport($input))->toBeTrue()
            ->and(substr_count($result['css'], 'atom.tailwind.css'))->toBe(1)
            ->and($result['css'])->toContain('--color-accent: var(--color-yellow-500);')->not->toContain('--color-muted')
            ->and($css->rewrite($result['css'], 'resources/css/app.css')['changed'])->toBeFalse();
    })->with([
        'url()' => ["@import url('../../vendor/jiannius/atom/resources/css/atom.tailwind.css');\n"],
        'url() with double quotes' => ["@import url(\"../../vendor/jiannius/atom/resources/css/atom.tailwind.css\");\n"],
        'url() without quotes' => ["@import url(../../vendor/jiannius/atom/resources/css/atom.tailwind.css);\n"],
        'a layer()' => ["@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css' layer(base);\n"],
        'a media query' => ["@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css' screen;\n"],
    ]);

    it('does not take atom\'s import from a comment or a string, and takes one with no `;` at the end of the file', function () {
        $css = new UpgradeCss;
        $path = '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';

        expect($css->hasEntryImport("@import 'tailwindcss';\n/* @import '".$path."'; */\n"))->toBeFalse()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n/*\n@import url('".$path."');\n*/\n"))->toBeFalse()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n.x { content: \"@import '".$path."';\"; }\n"))->toBeFalse()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@import '".$path."'"))->toBeTrue()
            ->and($css->hasEntryImport("@import 'tailwindcss';\n@import '../other.css';\n"))->toBeFalse();
    });

    it('still notes a kept yellow accent-content in `@theme inline`, which the codemod does not rewrite', function () {
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme inline {\n    --color-accent-content: var(--color-yellow-500);\n}\n";
        $notes = (new UpgradeCss)->residue($v4);

        expect($notes)->toHaveCount(1)
            ->and($notes[0]['text'])->toContain('@theme inline sets --color-accent-content');
    });

    it('keeps a yellow accent-content in .dark of a v4 file, and says nothing about it', function () {
        $css = new UpgradeCss;
        $v4 = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@layer theme {\n    .dark {\n        --color-accent-content: var(--color-yellow-500);\n    }\n}\n";

        expect($css->rewrite($v4, 'resources/css/app.css')['css'])->toBe($v4)
            ->and($css->residue($v4))->toBe([])
            ->and($css->skeletonDeclarations($v4))->toBe([]);
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

describe('atom:upgrade: the guide', function () {
    it('states, in every section, which entries atom:upgrade rewrites and which it reports, exactly as checks() says', function () {
        $sections = upgradeGuideSections();
        $expected = [];

        foreach (UpgradeCheck::checks() as $id => $check) {
            $expected[$check['section']][$check['codemod'] === 'auto' ? 'Automated' : 'Reported'][] = $id;
        }

        expect(array_keys($expected))->each->toBeIn(array_keys($sections));

        foreach ($sections as $heading => $body) {
            if (in_array($heading, ['Installing a pre-release', 'What is not restyled', 'The order'], true)) {
                continue;
            }

            expect(preg_match('/\*\*atom:upgrade:\*\* Automated: ([^.]*)\. Reported: ([^.]*)\./', $body, $line))->toBe(1, 'no atom:upgrade line in "'.$heading.'"');

            foreach (['Automated' => $line[1], 'Reported' => $line[2]] as $verdict => $text) {
                preg_match_all('/`([a-z][a-z0-9-]+)`/', $text, $ids);

                expect(collect($ids[1])->sort()->values()->all())->toBe(collect($expected[$heading][$verdict] ?? [])->sort()->values()->all(), $heading.' / '.$verdict);
            }
        }
    });

    it('no longer says the codemod is yet to come', function () {
        $guide = (string) file_get_contents(__DIR__.'/../../UPGRADE.md');

        expect($guide)->not->toContain('when 4.0 ships')
            ->not->toContain('will rewrite')
            ->not->toContain('will only report')
            ->not->toContain('are written as the work lands')
            ->not->toContain('V4-4d');
    });

    it('gives the ordered checklist, with every command a host runs', function () {
        $order = upgradeGuideSections()['The order'];

        foreach (['atom:upgrade-check', 'atom:tiptap-migrate', '--force', '--compare', '--status --for-v4', 'atom:upgrade --dry-run', 'php artisan atom:upgrade`', 'ATOM-UPGRADE-TODO.md'] as $needle) {
            expect($order)->toContain($needle);
        }

        // the steps run in this order
        $at = array_map(fn ($needle) => strpos($order, $needle), ['atom:upgrade-check', 'atom:tiptap-migrate', 'Require v4', 'atom:upgrade --dry-run', 'php artisan atom:upgrade`', 'Work through']);

        expect($at)->toBe(collect($at)->sort()->values()->all())->and($at)->each->not->toBeFalse();
    });

    it('keeps the internal milestone labels out of the public skill, and agrees with the chart about its height', function () {
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
        $readme = file_get_contents(__DIR__.'/../../README.md');

        expect($skill)->not->toMatch('/V4-\d[a-z]?\b/')
            ->and($skill)->toContain('a chart\'s height goes on a wrapper that the chart fills')
            ->and($readme)->toContain('<div class="h-48"><atom:chart class="h-full" .../></div>')
            ->and($skill)->toContain('<atom:chart class="h-full" .../></div>')
            ->and(file_get_contents(__DIR__.'/../../UPGRADE.md'))->toContain('<atom:chart class="h-full" .../></div>')
            ->and(file_get_contents(__DIR__.'/../../resources/views/docs/demos/chart/trend.blade.php'))->toContain('class="h-full"')
            ->and($readme)->not->toContain('override with a `h-*` class')
            ->and(file_get_contents(__DIR__.'/../../resources/views/docs/demos/chart/trend.blade.php'))->not->toContain('class="h-24"/>');
    });

    it('does not hand the atom:upgrade-check options to atom:upgrade in the skill', function () {
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');

        expect($skill)->toContain('`atom:upgrade-check` alone takes `--json`')
            ->and($skill)->toContain('`atom:upgrade` takes `--dry-run` and `--path=`');
    });

    it('is described in the README, the Boost skill and CLAUDE.md', function () {
        $readme = file_get_contents(__DIR__.'/../../README.md');
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
        $claude = file_get_contents(__DIR__.'/../../CLAUDE.md');

        foreach (['atom:upgrade --dry-run', 'ATOM-UPGRADE-TODO.md', 'refuses to write', 'atom:tiptap-migrate --status'] as $needle) {
            expect($readme)->toContain($needle);
        }

        expect($skill)->toContain('atom:upgrade --dry-run')->toContain('ATOM-UPGRADE-TODO.md');
        expect($claude)->toContain('atom:upgrade')->toContain('Upgrade::REWRITES')->toContain('UpgradeCheck::checks()');
    });
});

/**
 * A fresh copy of one or more shape trees (tests/Fixtures/UpgradeShapes/<name>), merged into one host
 */
function upgradeShapeHost(string ...$shapes): string
{
    $dir = sys_get_temp_dir().'/atom-upgrade-shape-'.bin2hex(random_bytes(6));
    @mkdir($dir, 0777, true);

    foreach ($shapes as $shape) {
        $from = realpath(__DIR__.'/../Fixtures/UpgradeShapes/'.$shape);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $target = $dir.'/'.substr($item->getPathname(), strlen($from) + 1);
            $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
    }

    return $dir;
}

/**
 * Whether the braces of a stylesheet balance, comments and strings aside
 */
function upgradeBalanced(string $css): bool
{
    $css = preg_replace('/([\'"])(?:\\\\.|(?!\1)[^\\\\\n])*\1|\/\*.*?\*\//s', '', $css);
    $depth = 0;

    foreach (str_split($css) as $c) {
        $depth += $c === '{' ? 1 : ($c === '}' ? -1 : 0);

        if ($depth < 0) {
            return false;
        }
    }

    return $depth === 0;
}

/**
 * The PHP of a file (the leading block of a single-file Blade component), parsed: a ParseError fails the test
 */
function upgradeParses(string $path): bool
{
    $source = (string) file_get_contents($path);

    if (str_ends_with($path, '.blade.php')) {
        if (! str_starts_with(ltrim($source), '<?php') || ($end = strpos($source, '?>')) === false) {
            return true;
        }

        $source = substr($source, 0, $end + 2);
    }

    try {
        token_get_all($source, TOKEN_PARSE);

        return true;
    } catch (ParseError) {
        return false;
    }
}

describe('atom:upgrade: a rewrite it cannot be sure of is declined, not guessed', function () {
    it('cuts only a declaration\'s own text when it shares its line with a brace', function () {
        $dir = upgradeShapeHost('css-one-line');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/one.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n.btn { color: red; }\n")
            ->and(file_get_contents($dir.'/resources/css/tail.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-brand: #e11d48;\n}\n\n.btn { color: red; }\n")
            ->and(file_get_contents($dir.'/resources/css/pair.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n@theme { --color-brand: #e11d48; }\n.btn { color: red; }\n");
    });

    it('does not take the rule before it, or the layer around it, when a rule shares its line', function () {
        $dir = upgradeShapeHost('css-same-line');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/app.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n.keep { color: red; }\n.after { color: blue; }\n");
    });

    it('does not add an import a grouped or multiple import already makes', function () {
        $dir = upgradeShapeHost('php-grouped');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $grouped = file_get_contents($dir.'/app/Livewire/Grouped.php');

        expect($grouped)->toContain("use Livewire\\{Component, WithPagination};\n")
            ->and(substr_count($grouped, 'WithPagination'))->toBe(2)
            ->and($grouped)->toContain("use Jiannius\\Atom\\Traits\\WithTable;\n")
            ->and($grouped)->toContain('use WithTable, WithPagination;')
            ->and(file_get_contents($dir.'/app/Livewire/Multi.php'))->toContain("use Livewire\\Component, Livewire\\WithPagination;\n")
            ->and(substr_count(file_get_contents($dir.'/app/Livewire/Multi.php'), 'Livewire\\WithPagination'))->toBe(1)
            ->and(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toContain("use Illuminate\\Support\\Facades\\{DB, Date};\n")
            ->and(substr_count(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'), 'Facades\\Date'))->toBe(0)
            ->and(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toContain("    {\n        Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);\n\n        DB::prohibit");
    });

    it('declines a file with a braced namespace', function () {
        $dir = upgradeShapeHost('php-braced');
        $this->hosts[] = $dir;
        $before = file_get_contents($dir.'/app/Livewire/Braced.php');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Braced.php'))->toBe($before)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`app/Livewire/Braced.php:10`: not changed, it uses a braced `namespace { }`');
    });

    it('counts a trait as present only when the class uses it, not when the file imports it', function () {
        $dir = upgradeShapeHost('php-present');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/ImportOnly.php'))->toContain('    use WithTable, WithPagination;')
            ->and(file_get_contents($dir.'/app/Livewire/Mixed.php'))->toContain('    use WithTable, Paginates;');
    });

    it('keeps WithPagination for a table that only draws <atom:table>, and says it may be unneeded', function () {
        $dir = upgradeShapeHost('php-table-only');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/TableOnly.php'))->toContain('use WithTable, WithPagination;')
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('WithPagination (only <atom:table> in the view: it may be unneeded when the table never paginates)');
    });

    it('declines a file with several classes in it', function () {
        $dir = upgradeShapeHost('php-several');
        $this->hosts[] = $dir;
        $before = file_get_contents($dir.'/app/Livewire/Two.php');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Two.php'))->toBe($before)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('the file declares several classes');
    });

    it('keeps the line, and says why, for an abstract class, a trait, a parent class and a class it cannot read the view of', function () {
        $dir = upgradeShapeHost('php-declined');
        $this->hosts[] = $dir;
        $before = upgradeCodemodTree($dir);

        upgradeCodemodRun($dir);

        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');
        $after = upgradeCodemodTree($dir);
        unset($after['ATOM-UPGRADE-TODO.md']);

        expect($after)->toBe($before)
            ->and($todo)->toContain('`app/Livewire/Base.php:10`: not changed, it is an abstract class')
            ->and($todo)->toContain('`app/Livewire/Concerns/Shared.php:9`: not changed, it is a trait')
            ->and($todo)->toContain('`app/Livewire/Parented.php:10`: not changed, another class extends Parented')
            ->and($todo)->toContain('`app/Livewire/NoRender.php:10`: not changed, the traits cannot be inferred (no view() call found')
            ->and($todo)->toContain('`app/Livewire/Missing.php:10`: not changed, the traits cannot be inferred (view livewire.nowhere not found');
    });

    it('finds the class of a single-file component that has an attribute between new and class', function () {
        $dir = upgradeShapeHost('blade-attributes');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $file = file_get_contents($dir.'/resources/views/livewire/sites.blade.php');

        expect($file)->toContain("use Jiannius\\Atom\\Traits\\WithTable;\n")
            ->and($file)->toContain("use Livewire\\WithPagination;\n")
            ->and($file)->toContain('    use WithTable, WithPagination;')
            ->and($file)->not->toContain('AtomComponent');
    });

    it('declines a use statement with a comment in its trait list', function () {
        $dir = upgradeShapeHost('php-comment');
        $this->hosts[] = $dir;
        $before = file_get_contents($dir.'/app/Livewire/Commented.php');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Commented.php'))->toBe($before)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('the `use` statement has a comment in it');
    });

    it('puts Date::use in the provider\'s own boot(), not in a class nested in another method', function () {
        $dir = upgradeShapeHost('php-nested-boot');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $provider = file_get_contents($dir.'/app/Providers/AppServiceProvider.php');

        expect($provider)->toContain("    public function boot(): void\n    {\n        Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);\n\n        //\n    }")
            ->and(substr_count($provider, 'Date::use'))->toBe(1);
    });

    it('leaves the Blade under tests/ alone, and renames the app\'s', function () {
        $dir = upgradeShapeHost('blade-scopes');
        $this->hosts[] = $dir;
        $test = file_get_contents($dir.'/tests/views/fixture.blade.php');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/tests/views/fixture.blade.php'))->toBe($test)
            ->and(file_get_contents($dir.'/resources/views/page.blade.php'))->toBe("<atom:html>\n    <atom:tiptap wire:model=\"body\" />\n</atom:html>\n");
    });

    it('renames a tag in an HTML comment, which Blade compiles, and none in a Blade comment or @verbatim', function () {
        $dir = upgradeShapeHost('blade-comments');
        $this->hosts[] = $dir;

        // the scan agrees: the HTML comment's tag and the real one are findings, the @verbatim and Blade comment ones are not
        expect(array_map(fn ($f) => $f['file'].':'.$f['line'], (new UpgradeCheck)->scan($dir)['checks']['editor-tag']['findings']))->toBe(['resources/views/c.blade.php:2', 'resources/views/c.blade.php:7']);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/views/c.blade.php'))->toBe("<div>\n<!-- old: <atom:tiptap wire:model=\"body\"/> -->\n@verbatim\n<atom:editor wire:model=\"doc\"/>\n@endverbatim\n{{-- <atom:editor wire:model=\"gone\"/> --}}\n<atom:tiptap wire:model=\"real\"/>\n</div>\n");
    });

    it('does not copy a primary that refers to the accent into the accent', function () {
        $dir = upgradeShapeHost('css-brand-cycle');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $css = file_get_contents($dir.'/resources/css/app.css');

        expect($css)->not->toContain('--color-accent: var(--color-accent)')
            ->and($css)->toContain('--color-primary: var(--color-accent);')
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`--color-primary` refers to the accent');
    });

    it('leaves every file of every shape tree parsing, balanced, and unchanged by a second run', function (string $shape) {
        $dir = $shape === 'UpgradeHost' ? ($this->host)() : upgradeShapeHost($shape);
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);
        $after = upgradeCodemodTree($dir);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, '.php')) {
                expect(upgradeParses($path))->toBeTrue(substr($path, strlen($dir)));
            }

            if (str_ends_with($path, '.css')) {
                expect(upgradeBalanced((string) file_get_contents($path)))->toBeTrue(substr($path, strlen($dir)));
            }
        }

        [$code, $output] = upgradeCodemodRun($dir, dry: true);

        expect($code)->toBe(0)->and($output)->not->toContain('--- a/');
        upgradeCodemodRun($dir);
        expect(upgradeCodemodTree($dir))->toBe($after);
    })->with(fn () => array_merge(['UpgradeHost'], array_map('basename', glob(__DIR__.'/../Fixtures/UpgradeShapes/*', GLOB_ONLYDIR))));
});

describe('atom:upgrade: the smaller guards', function () {
    it('puts atom\'s import on a line of its own when Tailwind\'s is the last line with no newline', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/resources/css', 0777, true);
        file_put_contents($dir.'/resources/css/order.css', "@source '../../vendor/jiannius/atom/components/**/*.blade.php';\n@import 'tailwindcss';");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/order.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';");
    });

    it('reads a stylesheet that starts with a byte order mark, and keeps the mark', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/resources/css', 0777, true);
        file_put_contents($dir.'/resources/css/bom.css', "\xEF\xBB\xBF@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/components/**/*.blade.php';\n\n.a { color: red; }\n");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/bom.css'))->toBe("\xEF\xBB\xBF@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n.a { color: red; }\n");
    });

    it('leaves a declaration that has a comment on its line, and says so', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/resources/css', 0777, true);
        file_put_contents($dir.'/resources/css/commented.css', "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/components/**/*.blade.php';\n\n@theme {\n    --color-zinc-50: #fafafa; /* keep */\n    --color-zinc-100: #f5f5f5;\n}\n");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/commented.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-zinc-50: #fafafa; /* keep */\n}\n")
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`resources/css/commented.css:5`: @theme sets --color-zinc-50: #fafafa')
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->not->toContain('declarations that are the skeleton\'s were left');
    });

    it('reads the accent out of @theme inline, and does not rewrite that block', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/resources/css', 0777, true);
        $css = "@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme inline {\n    --color-accent: #0d9488;\n    --color-zinc-50: #fafafa;\n}\n";
        file_put_contents($dir.'/resources/css/inline.css', $css);

        upgradeCodemodRun($dir);

        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect(file_get_contents($dir.'/resources/css/inline.css'))->toBe($css)
            ->and($todo)->toContain('`resources/css/inline.css:5` sets `--color-accent: #0d9488;`')
            ->and($todo)->not->toContain('sets no `--color-accent`')
            ->and($todo)->toContain('@theme inline sets --color-zinc-50');
    });

    it('indents the Date::use line the way the provider does', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/app/Providers', 0777, true);
        file_put_contents($dir.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n\tpublic function boot(): void\n\t{\n\t}\n}\n");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Providers/AppServiceProvider.php'))->toContain("\t{\n\t\tDate::use(\\Jiannius\\Atom\\Services\\Carbon::class);\n\t}");
    });

    it('sees an upload through $wire.upload(), a livewire-upload event or temporaryUrl(), and not through an import', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/app/Livewire', 0777, true);
        mkdir($dir.'/resources/views/livewire', 0777, true);

        foreach (['wire' => '<button x-on:click="$wire.upload(\'photo\', file)">x</button>', 'event' => '<div x-on:livewire-upload-progress="p = $event.detail.progress">x</div>', 'plain' => '<div>x</div>'] as $name => $markup) {
            $class = ucfirst($name);
            file_put_contents($dir.'/app/Livewire/'.$class.'.php', "<?php\n\nnamespace App\\Livewire;\n\nuse Jiannius\\Atom\\Traits\\AtomComponent;\nuse Livewire\\Component;\nuse Livewire\\WithFileUploads;\n\nclass {$class} extends Component\n{\n    use AtomComponent;\n\n    public function render()\n    {\n        return view('livewire.{$name}');\n    }\n}\n");
            file_put_contents($dir.'/resources/views/livewire/'.$name.'.blade.php', $markup."\n");
        }

        file_put_contents($dir.'/app/Livewire/Url.php', "<?php\n\nnamespace App\\Livewire;\n\nuse Jiannius\\Atom\\Traits\\AtomComponent;\nuse Livewire\\Component;\n\nclass Url extends Component\n{\n    use AtomComponent;\n\n    public function render()\n    {\n        \$u = \$this->photo->temporaryUrl();\n\n        return view('livewire.plain');\n    }\n}\n");

        $components = collect((new UpgradeCheck)->scan($dir)['components'])->keyBy('file');

        expect($components['app/Livewire/Wire.php']['traits'])->toBe(['WithFileUploads'])
            ->and($components['app/Livewire/Event.php']['traits'])->toBe(['WithFileUploads'])
            ->and($components['app/Livewire/Url.php']['traits'])->toBe(['WithFileUploads'])
            ->and($components['app/Livewire/Plain.php']['traits'])->toBe([]);
    });

    it('does not say "nothing" while an automated finding is left, and lists it', function () {
        $dir = upgradeShapeHost('php-declined');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $left = substr(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'), strpos(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'), '## Left for you'));
        $left = substr($left, 0, strpos($left, '## Accent'));

        expect($left)->not->toContain('(nothing')
            ->and($left)->toContain('- **use-atom-component**: 5 findings are still in the app');
    });

    it('says "1 theme declaration that was", not "were"', function () {
        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/resources/css', 0777, true);
        file_put_contents($dir.'/resources/css/one.css', "@import 'tailwindcss';\n@source '../../vendor/jiannius/atom/components/**/*.blade.php';\n\n@theme {\n    --color-zinc-50: #fafafa;\n}\n");

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('removed 1 atom `@source` line; removed 1 theme declaration that was the skeleton\'s');
    });

    it('lists a later run\'s rewrite of a file in place of the earlier line for it, not beside it', function () {
        $dir = ($this->host)();

        upgradeCodemodRun($dir);

        // the host puts AtomComponent back, three lines further down
        $orders = file_get_contents($dir.'/app/Livewire/Orders.php');
        file_put_contents($dir.'/app/Livewire/Orders.php', str_replace('    protected $listeners', "    use \\Jiannius\\Atom\\Traits\\AtomComponent;\n\n    protected \$listeners", $orders));
        upgradeCodemodRun($dir);

        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect(preg_match_all('/^- `app\/Livewire\/Orders\.php:\d+`: `use/m', $todo))->toBe(1);
    });
});

describe('atom:upgrade: writing', function () {
    it('checks every file and the TODO can be written before it writes the first one', function () {
        $dir = ($this->host)();
        mkdir($dir.'/ATOM-UPGRADE-TODO.md');
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir);

        expect($code)->not->toBe(0)
            ->and($output)->toContain('Cannot write ATOM-UPGRADE-TODO.md')
            ->and(upgradeCodemodTree($dir))->toBe($before);
    });

    it('refuses before writing anything when a file cannot be written', function () {
        $dir = ($this->host)();
        chmod($dir.'/app/Providers/AppServiceProvider.php', 0444);
        $before = upgradeCodemodTree($dir);

        [$code, $output] = upgradeCodemodRun($dir);

        chmod($dir.'/app/Providers/AppServiceProvider.php', 0644);

        expect($code)->not->toBe(0)
            ->and($output)->toContain('Cannot write app/Providers/AppServiceProvider.php')
            ->and(upgradeCodemodTree($dir))->toBe($before);
    })->skip(fn () => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can write a read-only file');

    it('says which files it wrote and which it did not when a write fails part way, and keeps modes', function () {
        $dir = ($this->host)();
        chmod($dir.'/app/Livewire/Orders.php', 0640);
        $upgrade = new class extends Upgrade
        {
            public int $writes = 0;

            protected function write(string $path, ?string $before, string $after): void
            {
                if (++$this->writes === 3) {
                    throw new RuntimeException('disk full');
                }

                parent::write($path, $before, $after);
            }
        };
        $plan = $upgrade->plan($dir);
        $files = array_keys($plan['changes']);

        try {
            $upgrade->apply($plan, 'todo');
            $message = '';
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        expect($message)->toContain('Could not write '.$files[2].' (disk full)')
            ->and($message)->toContain('Written: '.$files[0].', '.$files[1].'.')
            ->and($message)->toContain('Not written: '.$files[2])
            ->and($message)->toContain('ATOM-UPGRADE-TODO.md')
            ->and(file_get_contents($dir.'/'.$files[0]))->toBe($plan['changes'][$files[0]]['after'])
            ->and(file_get_contents($dir.'/'.$files[2]))->toBe($plan['changes'][$files[2]]['before'])
            ->and(decoct(fileperms($dir.'/app/Livewire/Orders.php') & 0777))->toBe('640');
    });

    it('writes in place, so the file keeps its inode (owner, ACL, attributes), and refuses a file with another hard link', function () {
        $dir = ($this->host)();
        $path = $dir.'/app/Livewire/Orders.php';
        $inode = fileinode($path);

        (new Upgrade)->apply((new Upgrade)->plan($dir));

        clearstatcache();

        expect(fileinode($path))->toBe($inode)
            ->and(file_get_contents($path))->toContain('use WithTable, WithBreadcrumbs');

        $other = upgradeCodemodHost();
        $this->hosts[] = $other;
        link($other.'/app/Livewire/Orders.php', $other.'/linked.php');
        $before = upgradeCodemodTree($other);

        [$code, $output] = upgradeCodemodRun($other);

        expect($code)->not->toBe(0)
            ->and($output)->toContain('has other hard links')
            ->and(upgradeCodemodTree($other))->toBe($before);
    });

    it('stops with nothing written when a file changed after the plan was made', function () {
        $dir = ($this->host)();
        $upgrade = new Upgrade;
        $plan = $upgrade->plan($dir);
        $files = array_keys($plan['changes']);
        $edited = file_get_contents($dir.'/'.$files[1])."\n// edited meanwhile\n";
        file_put_contents($dir.'/'.$files[1], $edited);
        $first = file_get_contents($dir.'/'.$files[0]);

        try {
            $upgrade->apply($plan, 'todo');
            $message = '';
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        expect($message)->toContain('Cannot write '.$files[1].' (it changed since the plan was made): nothing was changed')
            ->and(file_get_contents($dir.'/'.$files[0]))->toBe($first)
            ->and(file_get_contents($dir.'/'.$files[1]))->toBe($edited);
    });

    it('still catches a file that changes between the check and its write, and reports the partial write', function () {
        $dir = ($this->host)();
        $upgrade = new class extends Upgrade
        {
            public int $writes = 0;

            protected function write(string $path, ?string $before, string $after): void
            {
                if (++$this->writes === 2) {
                    file_put_contents($path, $before."\n// raced\n");
                }

                parent::write($path, $before, $after);
            }
        };
        $plan = $upgrade->plan($dir);
        $files = array_keys($plan['changes']);

        try {
            $upgrade->apply($plan, 'todo');
            $message = '';
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        expect($message)->toContain('Could not write '.$files[1].' (it changed since the plan was made: run again)')
            ->and($message)->toContain('Written: '.$files[0].'.');
    });
});

describe('atom:upgrade: round two', function () {
    it('does not take a host trait called AtomComponent for atom\'s, and says so', function () {
        $dir = upgradeShapeHost('php-host-trait');
        $this->hosts[] = $dir;
        $before = upgradeCodemodTree($dir);
        $scan = (new UpgradeCheck)->scan($dir);

        // not atom users: only the host traits themselves (which use atom's) are
        expect(array_column($scan['components'], 'file'))->toBe(['app/Livewire/AtomComponent.php', 'app/Traits/AtomComponent.php'])
            ->and(array_map(fn ($host) => $host['file'].' '.$host['name'], $scan['host_atom_components']))->toBe(['app/Livewire/Imported.php App\\Traits\\AtomComponent', 'app/Livewire/Local.php App\\Livewire\\AtomComponent']);

        upgradeCodemodRun($dir);

        $after = upgradeCodemodTree($dir);
        unset($after['ATOM-UPGRADE-TODO.md']);
        $todo = file_get_contents($dir.'/ATOM-UPGRADE-TODO.md');

        expect($after)->toBe($before)
            ->and($todo)->toContain('`app/Livewire/Imported.php:10`: not changed, `use AtomComponent` is a host trait named AtomComponent (App\Traits\AtomComponent), not atom\'s')
            ->and($todo)->toContain('`app/Livewire/Local.php:9`: not changed, `use AtomComponent` is a host trait named AtomComponent (App\Livewire\AtomComponent), not atom\'s')
            ->and($todo)->toContain('it is a trait');
    });

    it('does not let a new import take over a trait the file already means by that name, or list a trait twice', function () {
        $dir = upgradeShapeHost('php-shadow');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $shadowed = file_get_contents($dir.'/app/Livewire/Shadowed.php');

        expect($shadowed)->toContain('    use \\Jiannius\\Atom\\Traits\\WithTable, WithPagination, WithTable;')
            ->and($shadowed)->not->toContain("use Jiannius\\Atom\\Traits\\WithTable;\n")
            ->and($shadowed)->toContain("use Livewire\\WithPagination;\n")
            ->and(file_get_contents($dir.'/app/Livewire/Dup.php'))->toContain('    use WithPagination, WithTable;');
    });

    it('finds a component whose import it cannot read, and declines it with the reason', function () {
        $source = "<?php\n\nnamespace App\\Livewire;\n\nuse App\\Foo as;\nuse Jiannius\\Atom\\Traits\\AtomComponent;\n\nclass Broken\n{\n    use AtomComponent;\n}\n";
        $structure = UpgradePhp::structure($source);

        expect($structure['ok'])->toBeFalse()
            ->and($structure['classes'])->toHaveCount(1)
            ->and(UpgradeCheck::atomComponentUses($source, $structure))->toHaveCount(1);

        $dir = upgradeShapeHost();
        $this->hosts[] = $dir;
        mkdir($dir.'/app/Livewire', 0777, true);
        file_put_contents($dir.'/app/Livewire/Broken.php', $source);

        $scan = (new UpgradeCheck)->scan($dir);

        expect(array_column($scan['components'], 'file'))->toBe(['app/Livewire/Broken.php']);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Broken.php'))->toBe($source)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`app/Livewire/Broken.php:10`: not changed, it has an import it cannot read');
    });

    it('reads a function in a mixed group as no class, and rewrites the component beside it', function () {
        $dir = upgradeShapeHost('php-mixed-group');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $mixed = file_get_contents($dir.'/app/Livewire/Mixed.php');

        expect($mixed)->toContain("use App\\Support\\{function helper, Foo};\n")
            ->and($mixed)->toContain('    use WithTable, WithPagination;');
    });

    it('reads the traits a view needs from a tag in an HTML comment too, since Blade compiles it', function () {
        $dir = upgradeShapeHost('blade-comment-table');
        $this->hosts[] = $dir;

        $scan = (new UpgradeCheck)->scan($dir);

        expect($scan['components'][0]['traits'])->toContain('WithTable', 'WithEditor');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/views/livewire/page.blade.php'))->toContain('    use WithTable, WithEditor, WithPagination;');
    });

    it('does not cut a declaration out of the inside of a string', function () {
        $dir = upgradeShapeHost('css-string');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/app.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --font-display: \"Weird; --color-zinc-50: #fafafa; x\", serif;\n}\n");
    });

    it('renames a tag after @@verbatim, which Blade escapes and so compiles', function () {
        $dir = upgradeShapeHost('blade-escaped-verbatim');
        $this->hosts[] = $dir;

        expect(array_map(fn ($f) => $f['line'], (new UpgradeCheck)->scan($dir)['checks']['editor-tag']['findings']))->toBe([2]);

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/views/v.blade.php'))->toBe("@@verbatim\n<atom:tiptap wire:model=\"a\"/>\n@endverbatim\n");
    });

    it('keeps the indent of what is left on a line when a declaration is cut from it', function () {
        $dir = upgradeShapeHost('css-shared-line');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/app.css'))->toBe("@import 'tailwindcss';\n@import '../../vendor/jiannius/atom/resources/css/atom.tailwind.css';\n\n@theme {\n    --color-brand: #f00;\n}\n");
    });

    it('leaves no space behind when it removes one import from a line of two', function () {
        $dir = upgradeShapeHost('php-import-line');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Inline.php'))->toContain("namespace App\\Livewire;\n\nuse Livewire\\Component;\n\nclass Inline");
    });

    it('says why it left a stylesheet whose @source lines it does not recognise', function () {
        $dir = upgradeShapeHost('css-skipped');
        $this->hosts[] = $dir;
        $before = file_get_contents($dir.'/resources/css/two.css');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/resources/css/two.css'))->toBe($before)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('`resources/css/two.css:2`: not changed, a `@source` line naming jiannius/atom was left: the codemod removes only atom\'s own three paths');
    });
});

describe('atom:upgrade: round three', function () {
    it('reads names the way PHP does, whatever their case', function () {
        $dir = upgradeShapeHost('php-lowercase');
        $this->hosts[] = $dir;

        expect(array_column((new UpgradeCheck)->scan($dir)['components'], 'file'))->toBe(['app/Livewire/Lower.php', 'app/Livewire/Upper.php']);

        upgradeCodemodRun($dir);

        $lower = file_get_contents($dir.'/app/Livewire/Lower.php');
        $upper = file_get_contents($dir.'/app/Livewire/Upper.php');

        expect($lower)->toContain('    use WithTable, WithPagination;')
            ->and(stripos($lower, 'atomcomponent'))->toBeFalse()
            ->and($upper)->toContain('    use WithTable, WithPagination;')
            ->and(stripos($upper, 'atomcomponent'))->toBeFalse();
    });

    it('resolves namespace\\Foo into the file\'s own namespace', function () {
        expect(UpgradePhp::resolve('namespace\\Foo\\Bar', [], 'App\\Livewire'))->toBe('App\\Livewire\\Foo\\Bar')
            ->and(UpgradePhp::resolve('NAMESPACE\\Foo', [], ''))->toBe('Foo');
    });

    it('does not take a name in a comment or a string for one the file uses', function () {
        $dir = upgradeShapeHost('php-comment-name');
        $this->hosts[] = $dir;

        upgradeCodemodRun($dir);

        $file = file_get_contents($dir.'/app/Livewire/Commented.php');

        expect($file)->toContain('    use WithTable, WithPagination;')
            ->and($file)->toContain("use Jiannius\\Atom\\Traits\\WithTable;\n")
            ->and($file)->not->toContain('\\Jiannius\\Atom\\Traits\\WithTable,');
    });

    it('declines a file with several namespace statements', function () {
        $dir = upgradeShapeHost('php-two-namespaces');
        $this->hosts[] = $dir;
        $before = file_get_contents($dir.'/app/Livewire/Two.php');

        upgradeCodemodRun($dir);

        expect(file_get_contents($dir.'/app/Livewire/Two.php'))->toBe($before)
            ->and(file_get_contents($dir.'/ATOM-UPGRADE-TODO.md'))->toContain('it has several namespace statements');
    });
});
