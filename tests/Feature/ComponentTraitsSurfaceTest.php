<?php

use Illuminate\Support\Facades\Blade;
use App\Actions\Spy;
use Jiannius\Atom\Tests\Fixtures\ComponentHelpersFixture;
use Jiannius\Atom\Tests\Fixtures\HookNamedPropertiesFixture;
use Jiannius\Atom\Tests\Fixtures\TraitGuardActionFixture;
use Jiannius\Atom\Tests\Fixtures\TraitGuardChildFixture;
use Jiannius\Atom\Tests\Fixtures\TraitGuardParentFixture;
use Jiannius\Atom\Tests\Fixtures\TraitGuardTableChildFixture;
use Jiannius\Atom\Traits\WithBreadcrumbs;
use Jiannius\Atom\Traits\WithEditor;
use Jiannius\Atom\Traits\WithRecaptcha;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\WithPagination;

/**
 * The atom traits are mixed into every consuming app's Livewire components, and a
 * class method silently beats a trait method of the same name — no error, the
 * feature behind it just stops working. So the set of names each trait occupies
 * is API: adding one is a decision, not a side effect of writing a helper.
 *
 * If this fails, the name may well be fine to add — update the list here and the
 * places that document it (README "Names the traits occupy", and the Boost
 * guidelines, which propagate into every host app's CLAUDE.md).
 *
 * Reflection flattens `use`d traits into the declaring class, so WithEditor's list
 * includes what WithFileUploads brings along.
 */
function publicNamesOf(string $class): array
{
    return collect((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn ($method) => $method->getName())
        ->sort()
        ->values()
        ->all();
}

/** The helpers that are macros on Livewire\Component rather than trait methods. */
const COMPONENT_MACROS = ['toast', 'modal', 'alert', 'confirm', 'command', 'wirekey', 'action'];

it('WithTable occupies only the names it documents', function () {
    expect(publicNamesOf(WithTable::class))->toBe([
        'clearTableSelectAll',
        'getTableCheckboxes',
        'isTableSelectAll',
        'isTableShowSelected',
        'isTableShowTrashed',
        'resetTableCheckboxes',
        'selectAllTableMatching',
        'tableRowsQuery',
        'tableSelection',
        'tableSelectionQuery',
        'toggleTableShowSelected',
        'updatedWithTable',
        'updatingWithTable',
    ]);
});

it('WithBreadcrumbs, WithRecaptcha and WithEditor occupy only the names they document', function () {
    expect(publicNamesOf(WithBreadcrumbs::class))->toBe(['mountWithBreadcrumbs']);
    expect(publicNamesOf(WithRecaptcha::class))->toBe(['verifyRecaptcha']);
    expect(publicNamesOf(WithEditor::class))->toBe([
        // WithFileUploads
        '_finishUpload',
        '_removeUpload',
        '_startUpload',
        '_uploadErrored',

        // atom
        'updatedWithEditor',
    ]);
});

it('keeps the old aggregate trait gone', function () {
    expect(trait_exists('Jiannius\Atom\Traits\AtomComponent'))->toBeFalse();
});

/**
 * The short helpers are documented as safe for a host component to shadow, which
 * is only true while atom never calls them on the component itself: shadowing one
 * then swaps out sugar the host was calling anyway, rather than breaking machinery
 * the host can't see. The moment something in the package starts calling
 * `$this->toast(...)`, that promise is void — so pin it.
 */
it('never calls its own host-facing helpers, so shadowing them stays harmless', function () {
    // glob()'s ** matches a single level in PHP, which would quietly skip the
    // deeper components — walk the trees instead
    $walk = function (string $dir, string $suffix) {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if (str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    };

    $files = collect([
        ...$walk(__DIR__.'/../../src', '.php'),
        ...$walk(__DIR__.'/../../components', '.blade.php'),
    ])->reject(fn ($path) => str_contains($path, '/src/Commands/'));   // console Commands have their own confirm()

    // a walk that finds nothing would pass every assertion below it
    expect($files->count())->toBeGreaterThan(300);

    $helpers = ['modal', 'command', 'toast', 'alert', 'confirm', 'action', 'wirekey', 'verifyRecaptcha'];

    foreach ($files as $path) {
        $contents = file_get_contents($path);

        foreach ($helpers as $helper) {
            expect($contents)->not->toContain(
                '$this->'.$helper.'(',
                basename($path).' calls $this->'.$helper.'() — that name is documented as safe for a host to shadow',
            );
        }
    }
});

it('claims no name a host component is likely to want for its own data', function () {
    // the computed property feeding <atom:table :paginate="..."> is the host's to
    // name — atom must never define or look for one of these
    foreach ([WithTable::class, WithBreadcrumbs::class, WithEditor::class, WithRecaptcha::class] as $trait) {
        $reflection = new ReflectionClass($trait);

        foreach (['items', 'rows', 'records', 'data', 'results', 'list', 'query', 'filters'] as $name) {
            expect($reflection->hasMethod($name))->toBeFalse("{$trait} must not define {$name}()");
            expect($reflection->hasProperty($name))->toBeFalse("{$trait} must not declare \${$name}");
        }
    }
});

it('declares its own state under an underscore prefix, one bucket per trait', function () {
    $state = fn (string $trait) => collect((new ReflectionClass($trait))->getProperties())
        ->map(fn ($property) => $property->getName())
        ->all();

    expect($state(WithTable::class))->toBe(['_table']);
    expect($state(WithBreadcrumbs::class))->toBe(['_breadcrumbs']);
    expect($state(WithEditor::class))->toBe(['_editor']);
    expect($state(WithRecaptcha::class))->toBe(['_recaptcha']);
});

/**
 * Livewire exposes every public, non-static method a component declares, and
 * reflection reports a trait method's declaring class as the USING class — so a
 * public trait method is callable from the browser as $wire.<name>() on every
 * component in every host app. The helpers are therefore macros, which the
 * reflected list never contains.
 *
 * action() forwards to the unrestricted Atom::action(), which honours a
 * caller-controlled `method` param: the hole v3.19.0 closed on POST /atom/action
 * (WebAction contract, method stripped, authorize() honoured), had it stayed
 * open through Livewire.
 */
it('registers every helper as a macro on Livewire\Component, not as a trait method', function () {
    foreach (COMPONENT_MACROS as $name) {
        expect(Component::hasMacro($name))->toBeTrue("{$name} must be a macro");

        foreach ([WithTable::class, WithBreadcrumbs::class, WithEditor::class, WithRecaptcha::class] as $trait) {
            expect((new ReflectionClass($trait))->hasMethod($name))->toBeFalse("{$trait} must not declare {$name}()");
        }
    }
});

it('keeps every helper macro off the browser-callable surface, whatever traits are used', function () {
    $combinations = [
        new class extends Component {},
        new class extends Component { use WithTable; },
        new class extends Component { use WithTable, WithPagination; },
        new class extends Component { use WithBreadcrumbs, WithEditor, WithRecaptcha; },
        new ComponentHelpersFixture,
    ];

    foreach ($combinations as $component) {
        $surface = Utils::getPublicMethodsDefinedBySubClass($component);

        foreach (COMPONENT_MACROS as $name) {
            expect($surface)->not->toContain($name);
        }
    }
});

it('puts the table methods on the browser surface only with WithTable', function () {
    $tableMethods = ['resetTableCheckboxes', 'clearTableSelectAll', 'selectAllTableMatching', 'toggleTableShowSelected', 'tableSelection', 'tableRowsQuery'];

    $without = Utils::getPublicMethodsDefinedBySubClass(new class extends Component { use WithPagination, WithEditor, WithBreadcrumbs, WithRecaptcha; });
    $with = Utils::getPublicMethodsDefinedBySubClass(new class extends Component { use WithTable; });

    foreach ($tableMethods as $name) {
        expect($without)->not->toContain($name);
        expect($with)->toContain($name);
    }
});

it('refuses a browser call to any helper macro with a real update request', function (string $name) {
    $test = Livewire::test(ComponentHelpersFixture::class);

    expect(fn () => $test->call($name, 'x'))->toThrow(MethodNotFoundException::class);
})->with(COMPONENT_MACROS);

it('refuses a crafted calls payload naming a helper macro, and nothing runs', function () {
    $test = Livewire::test(ComponentHelpersFixture::class);

    foreach (COMPONENT_MACROS as $name) {
        Spy::$ran = 0;

        $params = $name === 'action' ? ['spy', ['method' => 'handle']] : ['x'];

        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($test->snapshot),
                'updates' => [],
                'calls' => [['method' => $name, 'params' => $params, 'metadata' => []]],
            ]],
        ]);

        // refused by Livewire's own allow-list, before the macro could be reached
        expect($response->status())->toBeGreaterThanOrEqual(400);
        expect($response->getContent())->toContain("Public method [{$name}] not found");
        expect($response->getContent())->not->toContain('atom-toast-show');
        expect(Spy::$ran)->toBe(0, "{$name} must not have run an action");
    }
});

it('runs an action only from PHP, and the spy proves the browser path never reaches it', function () {
    Spy::$ran = 0;

    Livewire::test(ComponentHelpersFixture::class)->call('runSpy')->assertSet('result', ['ran' => 'spy']);

    expect(Spy::$ran)->toBe(1);
});

it('still lets the same component call its helpers from PHP', function () {
    $test = Livewire::test(ComponentHelpersFixture::class)
        ->call('notify')
        ->assertDispatched('atom-toast-show');

    $test->call('runAction')->assertSet('result', ['ran' => 'secret']);
});

it('binds $this to the component inside a macro, so action() can skip the render', function () {
    $test = Livewire::test(ComponentHelpersFixture::class)->call('runAction');

    // action() called skipRender() on the component: no html in the response
    expect($test->effects)->not->toHaveKey('html');
});

it('resolves helpers from a Blade view through $this', function () {
    Livewire::test(ComponentHelpersFixture::class)
        ->assertSeeHtml(md5(json_encode(['row', 0])));
});

it('lets a class method of the same name win over the macro', function () {
    $component = new class extends Component {
        public function toast(...$args): string
        {
            return 'host';
        }
    };

    expect($component->toast('x'))->toBe('host');
});

/**
 * A component that renders something needing a trait it does not use fails with a
 * message naming the trait and the class, instead of a silent dead control.
 */
it('names WithTable and the component when <atom:table> is rendered without it', function () {
    $component = new class extends Component {
        public function render() { return '<div><atom:table :paginate="null"><x-slot:rows></x-slot:rows></atom:table></div>'; }
    };

    expect(fn () => Livewire::test($component::class))
        ->toThrow(Exception::class, 'needs the WithTable trait: add `use \Jiannius\Atom\Traits\WithTable;` to '.$component::class);
});

it('names WithTable for each table part that reads _table', function (string $blade) {
    $component = new class($blade) extends Component {
        public static string $blade = '';

        public function __construct(string $blade = '') { if ($blade) { static::$blade = $blade; } }

        public function render() { return '<div>'.static::$blade.'</div>'; }
    };

    $component::$blade = $blade;

    expect(fn () => Livewire::test($component::class))->toThrow(Exception::class, 'WithTable');
})->with([
    'column with sort' => '<table><thead><tr><atom:table.column sort="name">Name</atom:table.column></tr></thead></table>',
    'column with checkbox' => '<table><thead><tr><atom:table.column checkbox /></tr></thead></table>',
    'cell with checkbox' => '<table><tbody><tr><atom:table.cell checkbox="1" /></tr></tbody></table>',
    'trashed toggle' => '<atom:table.trashed />',
]);

it('also asks a paginated table for WithPagination', function () {
    $component = new class extends Component {
        use WithTable;

        public function render()
        {
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator([1], 200, 10, 1);

            return view('atom-test::trait-guard-pager', ['paginate' => $paginator]);
        }
    };

    expect(fn () => Livewire::test($component::class))->toThrow(Exception::class, 'needs the WithPagination trait');
});

it('does not complain about a table outside a Livewire component', function () {
    expect(Blade::render('<atom:table><x-slot:rows></x-slot:rows></atom:table>'))->toContain('data-atom-table');
});

it('names WithEditor for the tiptap image toolbar, and WithRecaptcha for <atom:form recaptcha>', function () {
    $toolbar = new class extends Component {
        public function render() { return '<div><atom:tiptap.toolbar.image /></div>'; }
    };
    $form = new class extends Component {
        public function render() { return '<div><atom:form recaptcha wire:submit="save">x</atom:form></div>'; }
    };

    expect(fn () => Livewire::test($toolbar::class))->toThrow(Exception::class, 'needs the WithEditor trait');
    expect(fn () => Livewire::test($form::class))->toThrow(Exception::class, 'needs the WithRecaptcha trait');
});

it('names WithBreadcrumbs when a component defines breadcrumbs() without it', function () {
    $component = new class extends Component {
        public function breadcrumbs($breadcrumbs) { return $breadcrumbs->home('Home', '/'); }

        public function render() { return '<div></div>'; }
    };

    expect(fn () => Livewire::test($component::class))
        ->toThrow(Exception::class, 'defines breadcrumbs() but nothing calls it: add `use \Jiannius\Atom\Traits\WithBreadcrumbs;`');
});

it('lets a component with every trait render all of it', function () {
    Livewire::test(ComponentHelpersFixture::class)->assertOk();
});

it('echoes an editor image upload back as temporary preview urls', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    config()->set('livewire.temporary_file_upload.disk', 'local');

    $test = Livewire::test(ComponentHelpersFixture::class)
        ->set('_editor.images', [\Illuminate\Http\UploadedFile::fake()->image('a.jpg')]);

    $images = $test->get('_editor.images');

    expect($images)->toHaveCount(1);
    expect($images[0])->toBeString()->toContain('preview-file');
});


/**
 * The missing-trait check follows Livewire's render stack: the component whose view is
 * rendering. mount(), an action and a static page have none, so rendering table markup
 * to a string there (an export, a PDF, a mail body) is not an error.
 */
it('does not throw for table markup rendered in mount() or an action of a component without WithTable', function () {
    $test = Livewire::test(TraitGuardActionFixture::class);

    expect($test->get('html'))->toContain('data-atom-table');

    $test->set('html', '')->call('export');

    expect($test->get('html'))->toContain('data-atom-table');
});

it('checks the child that is rendering, not its parent', function () {
    // a parent with WithTable cannot cover a child without it
    expect(fn () => Livewire::test(TraitGuardParentFixture::class, ['child' => TraitGuardChildFixture::class]))
        ->toThrow(Exception::class, 'to '.TraitGuardChildFixture::class);

    // and the child's own trait is enough, whatever the parent uses
    Livewire::test(TraitGuardParentFixture::class, ['child' => TraitGuardTableChildFixture::class])
        ->assertSeeHtml('data-atom-table');
});

it('checks a lazy component when it renders, not when its placeholder does', function () {
    foreach ([TraitGuardChildFixture::class => false, TraitGuardTableChildFixture::class => true] as $class => $hasTrait) {
        // the placeholder has no table and needs no trait
        $test = Livewire::test($class, ['lazy' => true])->assertDontSeeHtml('data-atom-table');

        preg_match('/__lazyLoad\(&#039;([^&]+)&#039;\)/', $test->html(), $match);

        expect($match)->not->toBeEmpty('the lazy placeholder must carry its load call');

        if ($hasTrait) {
            $test->call('__lazyLoad', $match[1])->assertSeeHtml('data-atom-table');
        }
        else {
            expect(fn () => $test->call('__lazyLoad', $match[1]))->toThrow(Exception::class, 'WithTable');
        }
    }
});

it('checks an update render of the same component', function () {
    $test = Livewire::test(TraitGuardTableChildFixture::class)->call('$refresh')->assertSeeHtml('data-atom-table');

    expect($test->effects['html'] ?? $test->html())->toContain('data-atom-table');
});

/**
 * Livewire calls a trait's hook for a property of the same name too, with the value
 * where the path would be; an array value was a TypeError (500).
 */
it('survives a host property named like a trait', function (string $property) {
    $test = Livewire::test(HookNamedPropertiesFixture::class)
        ->set($property, ['a'])
        ->assertOk()
        ->assertSet($property, ['a']);

    $test->set($property, [])->assertOk();
})->with(['withTable', 'withEditor', 'withBreadcrumbs', 'withUrlFilters']);

it('still refuses a raw: sort when the property named like the trait is set in the same request', function () {
    Livewire::test(HookNamedPropertiesFixture::class)
        ->set('_table.sort.column', 'raw:(SELECT 1)')
        ->assertForbidden();
});

/**
 * A component with a breadcrumbs() method and no WithBreadcrumbs throws at mount, except
 * where the method is plainly not WithBreadcrumbs' convention.
 */
it('ignores a #[Computed] property that happens to be called breadcrumbs', function () {
    $component = new class extends Component {
        #[\Livewire\Attributes\Computed]
        public function breadcrumbs() { return ['Home']; }

        public function render() { return '<div>{{ count($this->breadcrumbs) }}</div>'; }
    };

    Livewire::test($component::class)->assertOk()->assertSee('1');
});

it('ignores a component that lives in vendor/', function () {
    $dir = base_path('vendor/atom-test-package');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/VendorBreadcrumbsComponent.php', '<?php
        namespace AtomTestPackage;
        class VendorBreadcrumbsComponent extends \Livewire\Component {
            public function breadcrumbs() { return []; }
            public function render() { return "<div>vendor</div>"; }
        }');

    try {
        require_once $dir.'/VendorBreadcrumbsComponent.php';

        Livewire::test(\AtomTestPackage\VendorBreadcrumbsComponent::class)->assertOk()->assertSee('vendor');
    }
    finally {
        @unlink($dir.'/VendorBreadcrumbsComponent.php');
        @rmdir($dir);
    }
});

it('ignores a breadcrumbs() inherited from a vendor/ class', function () {
    $dir = base_path('vendor/atom-test-package');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/VendorBase.php', '<?php
        namespace AtomTestPackage;
        abstract class VendorBase extends \Livewire\Component {
            public function breadcrumbs() { return []; }
        }');

    try {
        require_once $dir.'/VendorBase.php';

        $component = new class extends \AtomTestPackage\VendorBase {
            public function render() { return '<div>inherited</div>'; }
        };

        Livewire::test($component::class)->assertOk()->assertSee('inherited');
    }
    finally {
        @unlink($dir.'/VendorBase.php');
        @rmdir($dir);
    }
});

it('still throws for a host component with its own plain breadcrumbs() method', function () {
    $component = new class extends Component {
        public function breadcrumbs() { return []; }

        public function render() { return '<div></div>'; }
    };

    expect(fn () => Livewire::test($component::class))->toThrow(Exception::class, 'WithBreadcrumbs');
});
