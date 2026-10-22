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
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
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

        $expected = [
            'css-atom-source' => ['resources/css/app.css:2'],
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
            'use-atom-component' => [$php.':11', $profile.':7'],
            'wire-helper-browser' => [$widget.':21', $widget.':22'],
            'wire-helper-listener' => [$php.':13'],
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
            'macro-attribute-bag' => [],
            'js-currency' => [$widget.':15'],
            'js-array-helper' => ['resources/js/app.js:6', $widget.':16', $widget.':16', $widget.':20', $widget.':27'],
            'js-string-helper' => ['resources/js/app.js:6', $widget.':16'],
            'js-date-helper' => ['resources/js/app.js:4', 'resources/js/app.js:5', $widget.':16'],
            'js-from-now' => [$widget.':16'],
            'js-empty' => [$widget.':15', $widget.':28'],
            'js-dd' => ['resources/js/app.js:3', $widget.':15'],
            'editor-cast' => ['app/Models/Article.php:12'],
            'editor-tag' => [$profile.':15', $profile.':16', $profile.':17', $profile.':19'],
            'editor-content-static' => [$profile.':17'],
            // it reads tests/ only: app/Http/Styles.php names bg-white and rounded-lg and is not a finding
            'test-old-classes' => [],
        ];

        expect(array_keys($expected))->toEqualCanonicalizing(array_keys($this->result['checks']));

        foreach ($expected as $id => $at) {
            expect(upgradeAt($this->result, $id))->toBe($at, $id);
            expect($this->result['checks'][$id]['count'])->toBe(count($at), $id);
        }

        expect(upgradeAt($this->result, 'test-old-classes', 'tests'))->toBe(['tests/Feature/OrdersTest.php:8', 'tests/Feature/OrdersTest.php:8', 'tests/Feature/OrdersTest.php:10'])
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

        // what UPGRADE.md says the codemod fixes: the traits, the Date::use line, the editor tags, the editor prop
        $auto = collect(UpgradeCheck::checks())->filter(fn ($c) => $c['codemod'] === 'auto')->keys()->all();

        expect($auto)->toEqualCanonicalizing(['html-editor-prop', 'use-atom-component', 'date-use-line', 'editor-tag']);
    });

    it('infers the traits each component needs from its view, its partials and its methods', function () {
        $components = collect($this->result['components'])->where('scope', 'app')->keyBy('file');

        expect($components)->toHaveCount(2)
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
        expect($this->result['scanned'])->toBe(['app' => 12, 'tests' => 1])
            ->and($this->result['checks']['date-pretty']['count'])->toBe(1)
            ->and($this->result['checks']['date-pretty']['tests_count'])->toBe(1)
            ->and($this->result['summary']['tests_findings'])->toBe(5);
    });
});

describe('atom:upgrade-check: what is scanned', function () {
    it('skips vendor, node_modules, storage and .git at any depth', function () {
        $dir = ($this->host)();

        foreach (['vendor/pkg', 'node_modules/pkg', 'storage/framework', 'resources/node_modules/pkg', 'app/vendor/pkg', '.git/hooks'] as $path) {
            @mkdir($dir.'/'.$path, 0777, true);
            file_put_contents($dir.'/'.$path.'/leak.php', "<?php\n\$d->pretty();\n");
        }

        $result = (new UpgradeCheck)->scan($dir);

        expect(upgradeAt($result, 'date-pretty'))->toBe(['app/Livewire/Orders.php:38']);
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

        expect(array_keys($json))->toBe(['root', 'atom_version', 'scanned', 'checks', 'components', 'tags', 'guideline', 'summary'])
            ->and(array_keys($json['checks']['date-pretty']))->toBe(['id', 'title', 'section', 'severity', 'codemod', 'detect', 'count', 'tests_count', 'findings'])
            ->and($json['checks']['date-pretty']['findings'][0])->toBe(['file' => 'app/Livewire/Orders.php', 'line' => 38, 'text' => '$a = $order->placed_at->pretty();', 'scope' => 'app'])
            ->and($json['checks']['date-pretty']['severity'])->toBe('breaks at runtime')
            ->and($json['checks']['editor-tag']['codemod'])->toBe('auto')
            ->and(array_keys($json['components'][0]))->toBe(['file', 'line', 'scope', 'traits', 'also_present', 'evidence', 'partials', 'notes'])
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
        file_put_contents($dir.'/CLAUDE.md', "<laravel-boost-guidelines>\n=== foundation rules ===\n\nOther rules.\n\n".upgradeBlock()."\n=== jiannius/playbook/core rules ===\n\nPlaybook.\n</laravel-boost-guidelines>\n");

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

describe('atom:upgrade-check: the docs', function () {
    it('is documented in the README, the Boost skill and CLAUDE.md', function () {
        $readme = file_get_contents(__DIR__.'/../../README.md');
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
        $claude = file_get_contents(__DIR__.'/../../CLAUDE.md');

        foreach (['atom:upgrade-check', '--json', '--all', '--output', '--path', 'Preparing for v4'] as $needle) {
            expect($readme)->toContain($needle);
        }

        foreach (['atom:upgrade-check', '--json', '--all', '--output'] as $needle) {
            expect($skill)->toContain($needle);
        }

        expect($claude)->toContain('atom:upgrade-check')->toContain('UpgradeCheck::checks()');
    });
});
