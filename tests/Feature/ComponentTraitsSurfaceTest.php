<?php

use Illuminate\Support\Facades\Blade;
use Jiannius\Atom\Tests\Fixtures\ComponentHelpersFixture;
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
        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($test->snapshot),
                'updates' => [],
                'calls' => [['method' => $name, 'params' => $name === 'action' ? ['open', ['method' => 'secret']] : ['x'], 'metadata' => []]],
            ]],
        ]);

        // refused: no 2xx, and the component never dispatched or ran anything
        expect($response->status())->toBeGreaterThanOrEqual(400);
        expect($response->getContent())->not->toContain('atom-toast-show')->not->toContain('"secret"');
    }
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
        public function render() { return Blade::render('<div><atom:table :paginate="null"><x-slot:rows></x-slot:rows></atom:table></div>'); }
    };

    expect(fn () => Livewire::test($component::class))
        ->toThrow(Exception::class, 'needs the WithTable trait: add `use \Jiannius\Atom\Traits\WithTable;` to '.$component::class);
});

it('names WithTable for each table part that reads _table', function (string $blade) {
    $component = new class($blade) extends Component {
        public static string $blade = '';

        public function __construct(string $blade = '') { if ($blade) { static::$blade = $blade; } }

        public function render() { return Blade::render('<div>'.static::$blade.'</div>'); }
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

            return Blade::render('<div><atom:table.pagination :paginate="$paginate" /></div>', ['paginate' => $paginator]);
        }
    };

    expect(fn () => Livewire::test($component::class))->toThrow(Exception::class, 'needs the WithPagination trait');
});

it('does not complain about a table outside a Livewire component', function () {
    expect(Blade::render('<atom:table><x-slot:rows></x-slot:rows></atom:table>'))->toContain('data-atom-table');
});

it('names WithEditor for the tiptap image toolbar, and WithRecaptcha for <atom:form recaptcha>', function () {
    $toolbar = new class extends Component {
        public function render() { return Blade::render('<div><atom:tiptap.toolbar.image /></div>'); }
    };
    $form = new class extends Component {
        public function render() { return Blade::render('<div><atom:form recaptcha wire:submit="save">x</atom:form></div>'); }
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
