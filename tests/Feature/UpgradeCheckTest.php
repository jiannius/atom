<?php

use Illuminate\Support\Facades\Artisan;
use Jiannius\Atom\Services\UpgradeCheck;

/**
 * The scanner is read-only and text based: these tests hold it to a fixture
 * host (tests/Fixtures/UpgradeHost) that has one known instance of every check
 * and the look-alikes that must not count (a PHP empty() in Blade, ->local() on
 * $this, a comment, a native Date method).
 */
function upgradeFixture(): string
{
    return realpath(__DIR__.'/../Fixtures/UpgradeHost');
}

/**
 * Copy a directory tree
 */
function upgradeCopy(string $from, string $to): void
{
    @mkdir($to, 0777, true);

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);

        $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
    }
}

/**
 * A fresh copy of the fixture host in a temp directory
 */
function upgradeHost(): string
{
    $dir = sys_get_temp_dir().'/atom-upgrade-check-'.bin2hex(random_bytes(6));
    upgradeCopy(upgradeFixture(), $dir);

    return $dir;
}

function upgradeRemove(string $dir): void
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
 * Every file in a tree with its hash, to prove nothing changed
 *
 * @return array<string, string>
 */
function upgradeSnapshot(string $dir): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[substr($file->getPathname(), strlen($dir) + 1)] = md5_file($file->getPathname());
    }

    ksort($files);

    return $files;
}

/**
 * file:line of a check's findings in one scope
 *
 * @return array<int, string>
 */
function upgradeAt(array $result, string $id, string $scope = 'app'): array
{
    return array_values(array_map(
        fn ($finding) => $finding['file'].':'.$finding['line'],
        array_filter($result['checks'][$id]['findings'], fn ($finding) => $finding['scope'] === $scope)
    ));
}

beforeEach(function () {
    $this->hosts = [];
    $this->host = function () {
        return $this->hosts[] = upgradeHost();
    };
});

afterEach(function () {
    foreach ($this->hosts as $dir) {
        upgradeRemove($dir);
    }
});

describe('atom:upgrade-check: the findings', function () {
    beforeEach(function () {
        $this->result = (new UpgradeCheck)->scan(upgradeFixture());
    });

    it('finds exactly the known instance of every check, and none of the look-alikes', function () {
        $orders = 'resources/views/livewire/orders.blade.php';
        $profile = 'resources/views/livewire/profile.blade.php';
        $widget = 'resources/views/components/widget.blade.php';
        $php = 'app/Livewire/Orders.php';
        $vendorScreen = 'resources/views/livewire/app/vendor/index.blade.php';

        $expected = [
            'css-atom-source' => ['resources/css/app.css:2'],
            // the fixture has no skeleton theme block: atom:upgrade's own test lays one over a copy
            'css-skeleton-theme' => [],
            'css-border-color-rule' => ['resources/css/app.css:14'],
            'css-root-font-size' => ['resources/css/app.css:9'],
            'css-old-font' => ['resources/css/app.css:4', 'vite.config.js:2'],
            'navlist-group-class' => [$orders.':17'],
            'shell-dropdown-menu-wrap' => [$orders.':19'],
            'html-editor-prop' => [$profile.':13'],
            'button-implicit-icon' => [$orders.':5', $orders.':7'],
            'button-variant-undefined' => [$orders.':8'],
            'button-variant-brand' => [$orders.':9'],
            'button-variant-primary' => [$orders.':10'],
            'button-size-prop' => [$orders.':8'],
            'badge-color-name' => [$orders.':11'],
            'chart-color-unknown' => [$orders.':14'],
            'link-colour-override' => [$orders.':16'],
            // $vendorScreen is the host's own code in a directory called vendor: it is read
            'use-atom-component' => ['app/Livewire/Aliased.php:10', $php.':11', $vendorScreen.':7', $profile.':7'],
            'wire-helper-browser' => [$widget.':21', $widget.':22'],
            // the $listeners entry, the getListeners() return array and the #[On] method: not the options() array
            // one a line, whatever the shape: [..], array(..), array_merge(parent::getListeners(), [..]), $l + [..], #[On([..])], stacked attributes
            'wire-helper-listener' => [
                'app/Livewire/Listens.php:12', 'app/Livewire/Listens.php:15',
                'app/Livewire/Listens2.php:11', 'app/Livewire/Listens2.php:17', 'app/Livewire/Listens2.php:25', 'app/Livewire/Listens2.php:30', 'app/Livewire/Listens2.php:34', 'app/Livewire/Listens2.php:57', 'app/Livewire/Listens2.php:65',
                $php.':13',
            ],
            'trait-hook-name' => [$php.':27'],
            'table-state-in-url' => ['app/Http/UrlTable.php:7'],
            'date-use-line' => ['app/Providers/AppServiceProvider.php:1'],
            'date-pretty' => [$php.':38'],
            'date-local' => [$php.':39'],
            'date-recent' => [$php.':40'],
            'date-range-string' => [$php.':41'],
            'date-printed-bare' => [$orders.':26'],
            'carbon-import' => [$php.':5'],
            'carbon-typed' => [$php.':31', $php.':31', $php.':33'],
            'macro-request' => [$php.':45', $php.':46'],
            'macro-str' => [$php.':47'],
            'macro-builder' => [$php.':48'],
            'macro-attribute-bag' => ['resources/views/components/field.blade.php:2'],
            'js-currency' => [$widget.':15'],
            'js-array-helper' => ['resources/js/app.js:6', $widget.':16', $widget.':16', $widget.':20', $widget.':27'],
            'js-string-helper' => ['resources/js/app.js:6', $widget.':16'],
            'js-date-helper' => ['resources/js/app.js:4', 'resources/js/app.js:5', $widget.':16'],
            'js-from-now' => [$widget.':16'],
            'js-empty' => [$widget.':15', $widget.':28'],
            'js-dd' => ['resources/js/app.js:3', 'resources/js/app.js:8', $widget.':15'],
            'editor-cast' => ['app/Models/Article.php:12'],
            'editor-tag' => [$profile.':15', $profile.':16', $profile.':17', $profile.':19'],
            'editor-content-static' => [$profile.':17'],
            // it reads tests/ only: app/Http/Styles.php names bg-white and rounded-lg and is not a finding
            'test-old-classes' => [],
            'test-heading-weight' => [],
        ];

        expect(array_keys($expected))->toEqualCanonicalizing(array_keys($this->result['checks']));

        foreach ($expected as $id => $at) {
            expect(upgradeAt($this->result, $id))->toBe($at, $id);
            expect($this->result['checks'][$id]['count'])->toBe(count($at), $id);
        }

        expect(upgradeAt($this->result, 'test-old-classes', 'tests'))->toBe(['tests/Feature/OrdersTest.php:8', 'tests/Feature/OrdersTest.php:8', 'tests/Feature/OrdersTest.php:10', 'tests/Feature/OrdersTest.php:49'])
            // a heading line with font-medium (h1.font-medium, HEADING.font-medium) or with font-semibold and a negation
            // (line 25: the headingHtml('sm') ->not; 36: assertDontSeeHtml; 42-46: doesNotContain, doesntContain, NotContains, assertFalse, toBeFalse; 49: ->not->toContain('font-medium'), flagged
            // although it passes, an accepted over-report; the spec.js line: .not.). Not 47-48 (->notify, ->notes). Not line 24, a positive font-semibold on a heading (lg and xl
            // were semibold already); not 26 (no heading word); not .SUBHEADING.font-medium, sh1, h1x, font-medium-x, text-font-medium
            ->and(upgradeAt($this->result, 'test-heading-weight', 'tests'))->toBe([...array_map(fn ($line) => 'tests/Feature/OrdersTest.php:'.$line, [19, 25, 35, 36, 42, 43, 44, 45, 46, 49]), 'tests/e2e/heading.spec.js:2'])
            ->and(upgradeAt($this->result, 'date-pretty', 'tests'))->toBe(['tests/Feature/OrdersTest.php:9'])
            ->and(upgradeAt($this->result, 'use-atom-component', 'tests'))->toBe(['tests/Feature/OrdersTest.php:15']);
    });

    it('does not count the look-alikes', function () {
        // $this->local(), app('livewire')->isLivewireRequest(), a commented ->pretty(), PHP empty() in Blade,
        // atom.currency(), atom.arr.pluck(), classList.toggle(), this.toggle(), atom.empty(), a JS comment, a Blade comment
        $all = collect($this->result['checks'])->flatMap(fn ($check) => collect($check['findings'])->map(fn ($f) => $check['id'].' '.$f['file'].':'.$f['line']));

        foreach ([
            'date-local app/Livewire/Orders.php:43',
            'date-pretty app/Livewire/Orders.php:42',
            'macro-request app/Livewire/Orders.php:44',
            'js-empty resources/views/components/widget.blade.php:5',
            'js-empty resources/views/components/widget.blade.php:7',
            'js-empty resources/views/components/widget.blade.php:9',
            'js-currency resources/views/components/widget.blade.php:17',
            'js-dd resources/views/components/widget.blade.php:26',
            'js-array-helper resources/views/components/widget.blade.php:18',
            'js-array-helper resources/views/components/widget.blade.php:19',
            'editor-tag resources/views/livewire/orders.blade.php:24',
            'css-root-font-size resources/css/app.css:22',
        ] as $lookAlike) {
            expect($all->contains($lookAlike))->toBeFalse($lookAlike);
        }
    });

    it('marks a native Date method name as a candidate and a bare date print as review', function () {
        $native = collect($this->result['checks']['js-date-helper']['findings'])->firstWhere('line', 5);

        expect($native['note'])->toContain('native Date method');
        expect($this->result['checks']['date-printed-bare']['severity'])->toBe(UpgradeCheck::REVIEW);
    });

    it('gives every check an id, a title, a section, a severity, a codemod verdict and how it is detected', function () {
        foreach (UpgradeCheck::checks() as $id => $check) {
            expect($check['title'])->toBeString()->not->toBeEmpty()
                ->and($check['section'])->toBeString()->not->toBeEmpty()
                ->and($check['severity'])->toBeIn([UpgradeCheck::BREAKS, UpgradeCheck::VISUAL, UpgradeCheck::REVIEW])
                ->and($check['codemod'])->toBeIn(['auto', 'report'])
                ->and($check['detect'])->toBeString()->not->toBeEmpty();
        }

        // what UPGRADE.md says the codemod fixes: the CSS import, the traits, the Date::use line, the editor tags, the editor prop
        $auto = collect(UpgradeCheck::checks())->filter(fn ($c) => $c['codemod'] === 'auto')->keys()->all();

        expect($auto)->toEqualCanonicalizing(['css-atom-source', 'css-skeleton-theme', 'css-border-color-rule', 'html-editor-prop', 'use-atom-component', 'date-use-line', 'editor-tag']);
    });

    it('infers the traits each component needs from its view, its partials and its methods', function () {
        $components = collect($this->result['components'])->where('scope', 'app')->keyBy('file');

        expect($components)->toHaveCount(4)
            ->and($components['resources/views/livewire/app/vendor/index.blade.php']['traits'])->toBe([])
            ->and($components['app/Livewire/Aliased.php']['traits'])->toBe([])
            ->and($components['app/Livewire/Orders.php']['traits'])->toBe(['WithTable', 'WithBreadcrumbs', 'WithPagination', 'WithFileUploads'])
            ->and($components['app/Livewire/Orders.php']['partials'])->toBe(['resources/views/partials/extra.blade.php'])
            ->and($components['app/Livewire/Orders.php']['line'])->toBe(11)
            ->and($components['resources/views/livewire/profile.blade.php']['traits'])->toBe(['WithEditor', 'WithRecaptcha']);
    });

    it('counts the atom tags in use, per name, with the section that restyled them', function () {
        $tags = $this->result['tags'];

        expect($tags['button']['count'])->toBe(6)
            ->and($tags['button']['restyled'])->toBe('Controls')
            ->and($tags['editor.content']['count'])->toBe(2)
            ->and($tags['table']['restyled'])->toBe('Data display')
            ->and($tags['menu']['restyled'])->toBe('Overlays and navigation')
            ->and($tags['form']['count'])->toBe(1)
            ->and($tags)->not->toHaveKey('editor.content ')
            ->and(array_key_first($tags))->toBe('button');
    });

    it('reports test findings apart from the app ones', function () {
        expect($this->result['scanned'])->toBe(['app' => 19, 'tests' => 2])
            ->and($this->result['checks']['date-pretty']['count'])->toBe(1)
            ->and($this->result['checks']['date-pretty']['tests_count'])->toBe(1)
            ->and($this->result['summary']['tests_findings'])->toBe(17);
    });
});

describe('atom:upgrade-check: what is scanned', function () {
    it('skips node_modules, storage and .git at any depth', function () {
        $dir = ($this->host)();

        foreach (['node_modules/pkg', 'storage/framework', 'resources/node_modules/pkg', 'app/node_modules/pkg', '.git/hooks', 'app/.git/hooks'] as $path) {
            @mkdir($dir.'/'.$path, 0777, true);
            file_put_contents($dir.'/'.$path.'/leak.php', "<?php\n\$d->pretty();\n");
        }

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'date-pretty'))->toBe(['app/Livewire/Orders.php:38']);
    });

    it('skips Composer\'s vendor: the root one and a nested one that holds an install', function () {
        $dir = ($this->host)();

        // the root vendor/ is not in the scanned directories at all; the nested ones are what walk() has to tell from the host's own
        foreach ([
            'vendor/pkg' => null,
            'modules/Shop/vendor/pkg' => 'modules/Shop/vendor/autoload.php',
            'modules/Blog/vendor/pkg' => 'modules/Blog/vendor/composer/installed.json',
            'app/Support/vendor/pkg' => 'app/Support/vendor/autoload.php',
        ] as $path => $marker) {
            @mkdir($dir.'/'.$path, 0777, true);
            file_put_contents($dir.'/'.$path.'/leak.php', "<?php\n\$d->pretty();\n");

            if ($marker) {
                @mkdir(dirname($dir.'/'.$marker), 0777, true);
                file_put_contents($dir.'/'.$marker, "<?php\n");
            }
        }

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'date-pretty'))->toBe(['app/Livewire/Orders.php:38']);
    });

    it('reads a directory called vendor that is the host\'s own code', function () {
        $dir = ($this->host)();

        // a supplier screen, a controllers folder in either case, a module folder with no Composer install
        foreach (['resources/views/livewire/app/vendor', 'app/vendor', 'app/Http/Controllers/Vendor', 'modules/Shop/vendor'] as $path) {
            @mkdir($dir.'/'.$path, 0777, true);
            file_put_contents($dir.'/'.$path.'/Supplier.php', "<?php\n\$d->pretty();\n");
        }

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'date-pretty'))->toEqualCanonicalizing([
            'app/Http/Controllers/Vendor/Supplier.php:2',
            'app/Livewire/Orders.php:38',
            'app/vendor/Supplier.php:2',
            'modules/Shop/vendor/Supplier.php:2',
            'resources/views/livewire/app/vendor/Supplier.php:2',
        ]);
    });

    it('skips third-party scripts and styles in resources/js|css/vendor, one entry a directory; any other vendor path is read', function () {
        $dir = ($this->host)();

        foreach ([
            'resources/js/vendor/sub', 'resources/css/vendor', 'modules/Shop/resources/js/vendor', 'resources/js/pages/vendor',
            'resources/js/vendors', 'resources/views/vendor/atom', 'resources/views/livewire/app/vendor',
        ] as $path) {
            @mkdir($dir.'/'.$path, 0777, true);
        }

        $call = "dd(1);\n";

        foreach ([
            'resources/js/vendor/lib.js', 'resources/js/vendor/sub/b.js', 'modules/Shop/resources/js/vendor/m.js',
            'resources/js/pages/vendor/list.js', 'resources/js/vendor.js', 'resources/js/vendors/a.js', 'resources/views/vendor/atom/pub.js',
        ] as $file) {
            file_put_contents($dir.'/'.$file, $call);
        }

        file_put_contents($dir.'/resources/css/vendor/lib.css', "html { font-size: 14px; }\n");
        file_put_contents($dir.'/resources/js/vendor/Lib.php', "<?php\n\$d->pretty();\n");
        file_put_contents($dir.'/resources/views/livewire/app/vendor/Supplier.php', "<?php\n\$d->pretty();\n");

        $result = (new UpgradeCheck)->scan($dir);
        $reason = 'third-party scripts or styles under resources/js|css/vendor';

        // one entry per directory (lib.js and sub/b.js share resources/js/vendor), the module's and the stylesheet's own
        expect(array_values(array_filter($result['skipped'], fn ($s) => $s['reason'] === $reason)))->toEqualCanonicalizing([
            ['path' => 'resources/js/vendor', 'reason' => $reason],
            ['path' => 'resources/css/vendor', 'reason' => $reason],
            ['path' => 'modules/Shop/resources/js/vendor', 'reason' => $reason],
        ]);

        // read: resources/js/pages/vendor, vendor.js, vendors/, the published views, and PHP wherever it is
        expect(array_values(array_diff(upgradeAt($result, 'js-dd'), upgradeAt((new UpgradeCheck)->scan(upgradeFixture()), 'js-dd'))))->toEqualCanonicalizing([
            'resources/js/pages/vendor/list.js:1', 'resources/js/vendor.js:1', 'resources/js/vendors/a.js:1', 'resources/views/vendor/atom/pub.js:1',
        ])
            ->and(upgradeAt($result, 'css-root-font-size'))->not->toContain('resources/css/vendor/lib.css:1')
            ->and(upgradeAt($result, 'date-pretty'))->toContain('resources/js/vendor/Lib.php:2', 'resources/views/livewire/app/vendor/Supplier.php:2');
    });

    it('finds the AtomComponent users under a directory called vendor', function () {
        $components = collect((new UpgradeCheck)->scan(upgradeFixture())['components'])->pluck('file');

        expect($components)->toContain('resources/views/livewire/app/vendor/index.blade.php');
    });

    it('reads modules and Modules once', function () {
        $dir = ($this->host)();
        @mkdir($dir.'/Modules/Shop/resources/views', 0777, true);
        file_put_contents($dir.'/Modules/Shop/resources/views/index.blade.php', "<div>{{ \$x->created_at }}</div>\n");

        $result = (new UpgradeCheck)->scan($dir);

        $found = upgradeAt($result, 'date-printed-bare');

        // one finding for the module (its directory is named however the disk answers first), not two
        expect($found)->toHaveCount(2)
            ->and(strtolower(implode(',', $found)))->toContain('modules/shop/resources/views/index.blade.php:1');
    });

    it('drops the date-use-line finding once Date::use is called in a provider', function () {
        $dir = ($this->host)();
        $file = $dir.'/app/Providers/AppServiceProvider.php';
        file_put_contents($file, str_replace('    {', "    {\n        \\Illuminate\\Support\\Facades\\Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);", file_get_contents($file)));

        expect(upgradeAt((new UpgradeCheck)->scan($dir), 'date-use-line'))->toBe([]);

        // a commented call does not count
        file_put_contents($file, "<?php\nclass P { function boot() { // Date::use(Carbon::class);\n } }\n");

        expect(upgradeAt((new UpgradeCheck)->scan($dir), 'date-use-line'))->toBe(['app/Providers/AppServiceProvider.php:1']);
    });

    it('does not mistake a closure use, an import or a mention for the AtomComponent trait', function () {
        $dir = sys_get_temp_dir().'/atom-upgrade-traits-'.bin2hex(random_bytes(4));
        $this->hosts[] = $dir;
        @mkdir($dir.'/app', 0777, true);
        file_put_contents($dir.'/app/A.php', <<<'PHP'
<?php

use Jiannius\Atom\Traits\AtomComponent;

/** use AtomComponent; in a docblock */
class A
{
    // use AtomComponent;
    public function run($x)
    {
        return fn () => (function () use ($x) { return $x; })();
    }
}

class B
{
    use \Jiannius\Atom\Traits\AtomComponent, Other;
}

class C
{
    use Other, AtomComponent { toast as protected; }
}
PHP);

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'use-atom-component'))->toBe(['app/A.php:17', 'app/A.php:22']);
    });
});

describe('atom:upgrade-check: the command', function () {
    it('prints the report, grouped by check, with counts and file:line', function () {
        Artisan::call('atom:upgrade-check', ['--path' => upgradeFixture()]);
        $out = Artisan::output();

        expect($out)->toContain('BREAKS AT RUNTIME')
            ->toContain('[date-pretty] 1 in app, 1 in tests')
            ->toContain('app/Livewire/Orders.php:38')
            ->toContain('(tests) tests/Feature/OrdersTest.php:9')
            ->toContain('needs WithTable, WithBreadcrumbs, WithPagination, WithFileUploads')
            ->toContain('TAGS IN USE')
            ->toContain('SUMMARY')
            ->toContain('the codemod: fixes it');
    });

    it('prints JSON the codemod can read', function () {
        Artisan::call('atom:upgrade-check', ['--path' => upgradeFixture(), '--json' => true]);
        $json = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect(array_keys($json))->toBe(['root', 'guide', 'atom_version', 'scanned', 'checks', 'components', 'tags', 'published_views', 'css_files', 'extended_classes', 'host_atom_components', 'skipped', 'date_use', 'guideline', 'summary'])
            ->and($json['guide'])->toBe('vendor/jiannius/atom/UPGRADE.md')
            ->and(array_keys($json['checks']['date-pretty']))->toBe(['id', 'title', 'section', 'severity', 'codemod', 'detect', 'count', 'tests_count', 'findings'])
            ->and($json['checks']['date-pretty']['findings'][0])->toBe(['file' => 'app/Livewire/Orders.php', 'line' => 38, 'text' => '$a = $order->placed_at->pretty();', 'scope' => 'app'])
            ->and($json['checks']['date-pretty']['severity'])->toBe('breaks at runtime')
            ->and($json['checks']['editor-tag']['codemod'])->toBe('auto')
            ->and(array_keys($json['components'][0]))->toBe(['file', 'line', 'scope', 'traits', 'also_present', 'evidence', 'partials', 'notes', 'class', 'classes_in_file'])
            ->and(array_keys($json['summary']))->toBe(['checks', 'checks_with_findings', 'findings', 'tests_findings', 'by_severity', 'by_codemod', 'components', 'tags', 'editor_casts', 'editor_tags'])
            ->and($json['summary']['editor_casts'])->toBe(1)
            ->and($json['summary']['editor_tags'])->toBe(4)
            ->and($json['tags']['button'])->toBe(['count' => 6, 'files' => 1, 'tests' => 0, 'restyled' => 'Controls']);
    });

    it('limits the report to 10 findings a check unless --all', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/app/Many.php', "<?php\n".str_repeat("\$a->toDateRangeString();\n", 14));

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        expect(Artisan::output())->toContain('... and 5 more (use --all)');

        Artisan::call('atom:upgrade-check', ['--path' => $dir, '--all' => true]);
        expect(Artisan::output())->not->toContain('more (use --all)')->toContain('app/Many.php:15');
    });

    it('never writes: not into the project, not anywhere, unless --output names a file', function () {
        $dir = ($this->host)();
        $before = upgradeSnapshot($dir);

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        Artisan::call('atom:upgrade-check', ['--path' => $dir, '--json' => true]);
        Artisan::call('atom:upgrade-check', ['--path' => $dir, '--all' => true]);

        expect(upgradeSnapshot($dir))->toBe($before);

        $out = sys_get_temp_dir().'/atom-upgrade-out-'.bin2hex(random_bytes(4)).'.json';
        Artisan::call('atom:upgrade-check', ['--path' => $dir, '--json' => true, '--output' => $out]);

        expect(upgradeSnapshot($dir))->toBe($before)
            ->and(json_decode(file_get_contents($out), true)['checks'])->toHaveKey('date-pretty')
            ->and(file_get_contents($out))->toBe(Artisan::output());

        unlink($out);
    });

    it('is a command the service provider registers', function () {
        expect(array_keys(Artisan::all()))->toContain('atom:upgrade-check');
    });

    it('refuses a path that is not a directory', function () {
        $this->artisan('atom:upgrade-check', ['--path' => '/no/such/directory'])->assertFailed();
    });
});

describe('atom:upgrade-check: the CLAUDE.md atom section', function () {
    /**
     * The block boost:install would write: the heading line, a blank line, the rendered guideline
     */
    function upgradeBlock(): string
    {
        return "=== jiannius/atom/core rules ===\n\n".trim((new UpgradeCheck)->renderedGuideline())."\n";
    }

    function upgradeGuidelineState(string $dir): array
    {
        return (new UpgradeCheck)->guideline($dir);
    }

    it('is in sync when the host holds what the installed version renders, between other sections', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/CLAUDE.md', "<laravel-boost-guidelines>\n=== foundation rules ===\n\nOther rules.\n\n".upgradeBlock()."\n=== acme/other-package/core rules ===\n\nOther package rules.\n</laravel-boost-guidelines>\n");

        expect(upgradeGuidelineState($dir))->toBe(['state' => 'in_sync', 'files' => ['CLAUDE.md' => ['state' => 'in_sync']]]);
    });

    it('ignores blank lines, trailing spaces and CRLF', function () {
        $dir = ($this->host)();
        $block = str_replace("\n", " \r\n\r\n", upgradeBlock());
        file_put_contents($dir.'/AGENTS.md', "<laravel-boost-guidelines>\n".$block."</laravel-boost-guidelines>\n");

        expect(upgradeGuidelineState($dir)['files']['AGENTS.md']['state'])->toBe('in_sync');
    });

    it('is diverged when a line differs, with a short summary of what', function () {
        $dir = ($this->host)();
        $edited = str_replace('is a Tailwind + Alpine', 'is a Bootstrap + Alpine', upgradeBlock());
        $edited .= "- A rule the host added itself.\n";
        file_put_contents($dir.'/CLAUDE.md', "=== foundation rules ===\n\nx\n\n".$edited);

        $state = upgradeGuidelineState($dir);

        expect($state['state'])->toBe('diverged')
            ->and($state['files']['CLAUDE.md'])->toMatchArray(['state' => 'diverged', 'host_only' => 2, 'installed_only' => 1])
            ->and($state['files']['CLAUDE.md']['host_only_examples'])->toContain('- A rule the host added itself.')
            ->and($state['files']['CLAUDE.md']['installed_only_examples'][0])->toContain('is a Tailwind + Alpine');
    });

    it('does not count the next section as part of the atom block', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/CLAUDE.md', upgradeBlock()."\n=== pest/core rules ===\n\nA lot of unrelated rules.\n");

        expect(upgradeGuidelineState($dir)['state'])->toBe('in_sync');
    });

    it('is not found when there is no atom block, or no file at all', function () {
        $dir = ($this->host)();

        expect(upgradeGuidelineState($dir))->toBe(['state' => 'not_found', 'files' => []]);

        file_put_contents($dir.'/CLAUDE.md', "# My project\n\nNo atom section here. jiannius/atom is mentioned.\n");

        expect(upgradeGuidelineState($dir))->toBe(['state' => 'not_found', 'files' => ['CLAUDE.md' => ['state' => 'not_found']]]);
    });

    it('reads both files, and is diverged when either is', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/CLAUDE.md', upgradeBlock());
        file_put_contents($dir.'/AGENTS.md', str_replace('Atom', 'Atoms', upgradeBlock()));

        $state = upgradeGuidelineState($dir);

        expect($state['state'])->toBe('diverged')
            ->and($state['files']['CLAUDE.md']['state'])->toBe('in_sync')
            ->and($state['files']['AGENTS.md']['state'])->toBe('diverged');
    });

    it('prints the state in the report', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/CLAUDE.md', upgradeBlock());

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        expect(Artisan::output())->toContain('CLAUDE.md: in sync with the installed version');

        file_put_contents($dir.'/CLAUDE.md', str_replace('Tag syntax', 'Tag syntax changed', upgradeBlock()));
        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        expect(Artisan::output())->toContain('CLAUDE.md: diverged')->toContain('boost:install');
    });
});

describe('atom:upgrade-check: read only, and what it will not follow', function () {
    it('writes nothing anywhere in the host, storage/ included: Blade compiles in memory', function () {
        $dir = ($this->host)();
        @mkdir($dir.'/storage/framework/views', 0777, true);
        file_put_contents($dir.'/CLAUDE.md', "=== jiannius/atom/core rules ===\n\nx\n");

        // point Blade's cache at the host, the way a real run on base_path() would
        config(['view.compiled' => $dir.'/storage/framework/views']);

        foreach (['blade.compiler', 'view.engine.resolver', 'view'] as $abstract) {
            $this->app->forgetInstance($abstract);
            Illuminate\Support\Facades\Blade::clearResolvedInstance($abstract);
        }

        $before = upgradeSnapshot($dir);

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        Artisan::call('atom:upgrade-check', ['--path' => $dir, '--json' => true]);

        expect(upgradeSnapshot($dir))->toBe($before)
            ->and(glob($dir.'/storage/framework/views/*'))->toBe([]);
    });

    it('does not read a symlinked file or directory, nor a symlinked vite.config, and says so', function () {
        $dir = ($this->host)();
        $outside = sys_get_temp_dir().'/atom-upgrade-outside-'.bin2hex(random_bytes(4));
        $this->hosts[] = $outside;
        @mkdir($outside.'/dir', 0777, true);
        file_put_contents($outside.'/leak.php', "<?php\n\$d->pretty();\n");
        file_put_contents($outside.'/dir/leak.php', "<?php\n\$d->pretty();\n");
        file_put_contents($outside.'/vite.config.js', "import 'vite-plugin-bunny-fonts'\n");
        symlink($outside.'/leak.php', $dir.'/app/Linked.php');
        symlink($outside.'/dir', $dir.'/app/LinkedDir');
        symlink($outside.'/vite.config.js', $dir.'/vite.config.mjs');

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'date-pretty'))->toBe(['app/Livewire/Orders.php:38'])
            ->and(upgradeAt($result, 'css-old-font'))->toBe(['resources/css/app.css:4', 'vite.config.js:2'])
            ->and($result['skipped'])->toBe([
                ['path' => 'app/Linked.php', 'reason' => 'symlink'],
                ['path' => 'app/LinkedDir', 'reason' => 'symlink'],
                ['path' => 'vite.config.mjs', 'reason' => 'symlink'],
            ]);
    });

    it('reports a directory it cannot read instead of failing the scan', function () {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root reads every directory');
        }

        $dir = ($this->host)();
        @mkdir($dir.'/app/Locked', 0777, true);
        file_put_contents($dir.'/app/Locked/x.php', "<?php\n\$d->pretty();\n");
        chmod($dir.'/app/Locked', 0000);

        try {
            $result = (new UpgradeCheck)->scan($dir);
            Artisan::call('atom:upgrade-check', ['--path' => $dir]);
            $output = Artisan::output();
        } finally {
            chmod($dir.'/app/Locked', 0755);
        }

        expect($result['skipped'])->toBe([['path' => 'app/Locked', 'reason' => 'unreadable']])
            ->and(upgradeAt($result, 'date-pretty'))->toBe(['app/Livewire/Orders.php:38'])
            ->and($output)->toContain('NOT READ (1)')->toContain('app/Locked  (unreadable)');
    });

    it('counts and lists a file over 2 MB and a minified one, which it does not read', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/resources/js/big.js', str_repeat("dd(x)\n", 400000));
        file_put_contents($dir.'/resources/js/bundle.js', 'var a=1;'.str_repeat('dd(x);', 30000));

        $result = (new UpgradeCheck)->scan($dir);

        expect($result['skipped'])->toBe([
            ['path' => 'resources/js/big.js', 'reason' => 'over 2 MB'],
            ['path' => 'resources/js/bundle.js', 'reason' => 'looks minified'],
        ])->and(upgradeAt($result, 'js-dd'))->toBe(['resources/js/app.js:3', 'resources/js/app.js:8', 'resources/views/components/widget.blade.php:15']);
    });

    it('scans atom\'s published views and lists them as a section; other vendor views are skipped', function () {
        $dir = ($this->host)();
        @mkdir($dir.'/resources/views/vendor/atom/button', 0777, true);
        @mkdir($dir.'/resources/views/vendor/other', 0777, true);
        file_put_contents($dir.'/resources/views/vendor/atom/button/index.blade.php', "<div>{{ \$x->created_at }}</div>\n");
        file_put_contents($dir.'/resources/views/vendor/other/x.blade.php', "<div>{{ \$x->created_at }}</div>\n");

        $result = (new UpgradeCheck)->scan($dir);

        expect($result['published_views'])->toBe(['resources/views/vendor/atom/button/index.blade.php'])
            ->and(upgradeAt($result, 'date-printed-bare'))->toContain('resources/views/vendor/atom/button/index.blade.php:1')
            ->and(upgradeAt($result, 'date-printed-bare'))->not->toContain('resources/views/vendor/other/x.blade.php:1');

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        expect(Artisan::output())->toContain('PUBLISHED ATOM VIEWS (1)');
    });

    it('treats a module\'s resources/views/vendor like the root one: only atom/ is read', function () {
        $dir = ($this->host)();
        @mkdir($dir.'/modules/Shop/resources/views/vendor/atom', 0777, true);
        @mkdir($dir.'/modules/Shop/resources/views/vendor/pagination', 0777, true);
        file_put_contents($dir.'/modules/Shop/resources/views/vendor/atom/b.blade.php', "<div>{{ \$x->created_at }}</div>\n");
        file_put_contents($dir.'/modules/Shop/resources/views/vendor/pagination/a.blade.php', "<div>{{ \$x->created_at }}</div>\n");

        $result = (new UpgradeCheck)->scan($dir);

        expect($result['published_views'])->toBe(['modules/Shop/resources/views/vendor/atom/b.blade.php'])
            ->and(upgradeAt($result, 'date-printed-bare'))->toContain('modules/Shop/resources/views/vendor/atom/b.blade.php:1')
            ->and(upgradeAt($result, 'date-printed-bare'))->not->toContain('modules/Shop/resources/views/vendor/pagination/a.blade.php:1');
    });

    it('prints a matching line verbatim: <info> in the host\'s source is not a console style', function () {
        $dir = ($this->host)();
        file_put_contents($dir.'/app/Tagged.php', "<?php\n\$d->toDateRangeString('<info>x</info> <error>y</error>');\n");

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);

        expect(Artisan::output())->toContain("toDateRangeString('<info>x</info> <error>y</error>');");
    });
});

describe('atom:upgrade-check: Date::use and the date checks', function () {
    $covered = ['date-pretty', 'date-local', 'date-recent', 'date-range-string', 'carbon-typed'];

    it('keeps the date checks at "breaks at runtime" without a Date::use call, and carbon-import at review always', function () use ($covered) {
        $result = (new UpgradeCheck)->scan(upgradeFixture());

        foreach ($covered as $id) {
            expect($result['checks'][$id]['severity'])->toBe(UpgradeCheck::BREAKS, $id);
        }

        expect($result['checks']['carbon-import']['severity'])->toBe(UpgradeCheck::REVIEW)
            ->and($result['checks']['date-use-line']['count'])->toBe(1)
            ->and($result['date_use'])->toBeNull();
    });

    it('downgrades them to review, with a note, once a provider calls Date::use', function () use ($covered) {
        $dir = ($this->host)();
        $file = $dir.'/app/Providers/AppServiceProvider.php';
        file_put_contents($file, str_replace('    {', "    {\n        \\Illuminate\\Support\\Facades\\Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);", file_get_contents($file)));

        $result = (new UpgradeCheck)->scan($dir);

        foreach ($covered as $id) {
            expect($result['checks'][$id]['severity'])->toBe(UpgradeCheck::REVIEW, $id)
                ->and($result['checks'][$id]['count'])->toBeGreaterThan(0, $id)
                ->and($result['checks'][$id]['findings'][0]['note'])->toContain('covered by the Date::use line (app/Providers/AppServiceProvider.php)');
        }

        // what Date::use does not cover stays what it was
        expect($result['checks']['macro-request']['severity'])->toBe(UpgradeCheck::BREAKS)
            ->and($result['checks']['js-currency']['severity'])->toBe(UpgradeCheck::BREAKS)
            ->and($result['checks']['carbon-import']['severity'])->toBe(UpgradeCheck::REVIEW)
            ->and($result['checks']['date-use-line']['count'])->toBe(0)
            ->and($result['date_use'])->toBe('app/Providers/AppServiceProvider.php')
            ->and($result['summary']['by_severity'][UpgradeCheck::BREAKS])->toBeLessThan((new UpgradeCheck)->scan(upgradeFixture())['summary']['by_severity'][UpgradeCheck::BREAKS]);
    });

    it('finds the call in a module provider, in bootstrap/, and not in a comment or a test', function () {
        $dir = ($this->host)();
        @mkdir($dir.'/modules/Shop/Providers', 0777, true);
        file_put_contents($dir.'/modules/Shop/Providers/ShopServiceProvider.php', "<?php\nclass ShopServiceProvider { function boot() { Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); } }\n");

        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBe('modules/Shop/Providers/ShopServiceProvider.php');

        upgradeRemove($dir.'/modules');
        @mkdir($dir.'/bootstrap', 0777, true);
        file_put_contents($dir.'/bootstrap/app.php', "<?php\n// Date::use(X::class)\nreturn 1;\n");
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBeNull();

        file_put_contents($dir.'/bootstrap/app.php', "<?php\nDate::use(\\Jiannius\\Atom\\Services\\Carbon::class);\n");
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBe('bootstrap/app.php');

        unlink($dir.'/bootstrap/app.php');
        file_put_contents($dir.'/tests/Feature/TestServiceProvider.php', "<?php\nclass TestServiceProvider { function boot() { Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); } }\n");
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBeNull();
    });

    it('counts only a Date::use of atom\'s Carbon: not another date class, not another Carbon', function () {
        $dir = ($this->host)();
        $file = $dir.'/app/Providers/AppServiceProvider.php';
        $write = fn (string $imports, string $call) => file_put_contents($file, "<?php\n".$imports."\nclass P { function boot() { ".$call." } }\n");
        $date = fn () => (new UpgradeCheck)->scan($dir)['date_use'];

        $write('use Carbon\CarbonImmutable;', 'Date::use(CarbonImmutable::class);');
        expect($date())->toBeNull();

        $write('use Illuminate\Support\Carbon;', 'Date::use(Carbon::class);');
        expect($date())->toBeNull();

        $write('', 'Date::use(\\Carbon\\Carbon::class);');
        expect($date())->toBeNull();

        $write('use Jiannius\Atom\Services\Carbon;', 'Date::use(Carbon::class);');
        expect($date())->toBe('app/Providers/AppServiceProvider.php');

        $write('use Jiannius\Atom\Services\Carbon as AtomCarbon;', '\\Illuminate\\Support\\Facades\\Date::use( AtomCarbon::class );');
        expect($date())->toBe('app/Providers/AppServiceProvider.php');

        $write('', 'Date::use(\\Jiannius\\Atom\\Services\\Carbon::class);');
        expect($date())->toBe('app/Providers/AppServiceProvider.php');

        // an alias that is not atom's is not it, even when atom's is imported as another name
        $write("use Jiannius\Atom\Services\Carbon as AtomCarbon;\nuse Carbon\CarbonImmutable as Carbon;", 'Date::use(Carbon::class);');
        expect($date())->toBeNull();
    });

    it('looks in every file of app/Providers and every *ServiceProvider.php, but not in any other file', function () {
        $dir = ($this->host)();
        $call = "<?php\nclass D { function boot() { Date::use(\\Jiannius\\Atom\\Services\\Carbon::class); } }\n";

        file_put_contents($dir.'/app/Http/Dates.php', $call);
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBeNull();

        file_put_contents($dir.'/app/Providers/Dates.php', $call);
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBe('app/Providers/Dates.php');

        unlink($dir.'/app/Providers/Dates.php');
        @mkdir($dir.'/app/Providers/Support', 0777, true);
        file_put_contents($dir.'/app/Providers/Support/Dates.php', $call);
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBe('app/Providers/Support/Dates.php');

        unlink($dir.'/app/Providers/Support/Dates.php');
        file_put_contents($dir.'/app/Http/RouteServiceProvider.php', $call);
        expect((new UpgradeCheck)->scan($dir)['date_use'])->toBe('app/Http/RouteServiceProvider.php');
    });

    it('says "no AppServiceProvider found" when there is none, rather than pointing at a directory', function () {
        $dir = ($this->host)();
        unlink($dir.'/app/Providers/AppServiceProvider.php');

        $finding = (new UpgradeCheck)->scan($dir)['checks']['date-use-line']['findings'][0];

        expect($finding)->toMatchArray(['file' => '', 'line' => 0, 'text' => 'no AppServiceProvider found']);

        Artisan::call('atom:upgrade-check', ['--path' => $dir]);
        expect(Artisan::output())->toContain('(app)  no AppServiceProvider found')->not->toContain('app/Providers:1');
    });
});

describe('atom:upgrade-check: the docs', function () {
    it('is documented in the README, the Boost skill and CLAUDE.md', function () {
        $readme = file_get_contents(__DIR__.'/../../README.md');
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
        $claude = file_get_contents(__DIR__.'/../../CLAUDE.md');

        foreach (['atom:upgrade-check', '--json', '--all', '--output', '--path', 'Moving an app to v4', 'vendor/jiannius/atom/UPGRADE.md', 'treat the file like source code'] as $needle) {
            expect($readme)->toContain($needle);
        }

        foreach (['atom:upgrade-check', '--json', '--all', '--output'] as $needle) {
            expect($skill)->toContain($needle);
        }

        expect($claude)->toContain('atom:upgrade-check')->toContain('UpgradeCheck::checks()');
    });
});
