<?php

use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\DisabledBehaviourFixture;
use Jiannius\Atom\Tests\Fixtures\DisabledChildFixture;
use Livewire\Livewire;

beforeEach(function () {
    // the served e2e app registers these in E2EServiceProvider; a Livewire::test() renders a field that reads the error bag
    Livewire::component('atom-e2e-disabled-child', DisabledChildFixture::class);
    view()->share('errors', new ViewErrorBag);
});

/**
 * The guard behind the disabled-state rig (tests/e2e/disabled-behaviour.spec.js).
 *
 * The browser specs loop tests/Fixtures/disabled-controls-cases.json, so a control with a row is covered by a gesture probe in both routes
 * (its own `disabled`, and a disabled `<atom:form>`). This file keeps the corpus honest: a new component that holds a value must get a row,
 * or be listed below with the reason it is not one. LIMIT: it forces a row, and cannot prove a handler is guarded; a write path no gesture
 * of the row exercises escapes it.
 */

/**
 * Component files that match the value-control signature and are not a control for a record's data. Each says why. A new entry needs a reason a reviewer can check.
 * (`tiptap/menu/link` draws `<atom:input>` and `<atom:checkbox>` inside the editor's bubble menu; the signature does not see those, and the tiptap rows cover the menu.)
 *
 * @var array<string, string>
 */
const NOT_VALUE_CONTROLS = [
    'calendar/index' => 'a grid of events; the signature is a message about the removed `x-model`, and it has no value of its own to disable',
    'command/index' => 'the palette\'s search box: it filters a list and writes no model',
    'pagination' => '"Go to page" box in a navigation popover: moves the page, changes no record data',
    'table/pagination' => '"Go to page" box in a navigation popover: moves the page, changes no record data',
    'table/views' => '`x-modelable` carries which saved view is open: navigation, not record data',
    'tabs/index' => '`x-modelable` carries which tab is open: navigation, not record data',
    'tiptap/toolbar/font-size' => 'an input inside the tiptap toolbar, not a value of its own: the tiptap rows drive the toolbar (its menus, bold, the keyboard walk) disabled, and record that it is live today',
    'tiptap/toolbar/image' => 'an input inside the tiptap toolbar, not a value of its own: the tiptap rows drive the toolbar (its menus, bold, the keyboard walk) disabled, and record that it is live today',
    'tiptap/toolbar/youtube' => 'an input inside the tiptap toolbar, not a value of its own: the tiptap rows drive the toolbar (its menus, bold, the keyboard walk) disabled, and record that it is live today',
    'tiptap/chat/upload' => 'the chat composer\'s attach input: the chat rows drive the composer (Enter, the buttons, the keyboard walk) disabled',
];

/**
 * The corpus.
 *
 * @return list<array<string, mixed>>
 */
function disabledCorpus(): array
{
    return DisabledBehaviourFixture::corpus();
}

/**
 * Every file under components/ that looks like it holds a value, as the name a corpus row gives it (`input/general`, `textarea`).
 *
 * @return list<string>
 */
function valueControlFiles(): array
{
    $root = realpath(__DIR__.'/../../components');
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        if (preg_match('/x-modelable|<input|<select|<textarea|contenteditable/', file_get_contents($file->getPathname()))) {
            $found[] = substr($file->getPathname(), strlen($root) + 1, -strlen('.blade.php'));
        }
    }

    sort($found);

    return $found;
}

it('reads a corpus that is well formed', function () {
    $ids = [];

    foreach (disabledCorpus() as $case) {
        $id = $case['id'] ?? '(no id)';

        expect($case)->toHaveKeys(['id', 'components', 'blade', 'gestures', 'walk', 'tabStops'], $id);
        expect(in_array($id, $ids, true))->toBeFalse("{$id} is used twice");
        $ids[] = $id;

        // a standard case with no {disabled} could not be disabled by the prop route, and its rows would test an enabled control twice (a special case,
        // a Livewire child, is a component of its own that the form route disables from outside)
        if (empty($case['special'])) {
            expect(str_contains($case['blade'], '{disabled}'))->toBeTrue("{$id} must carry {disabled} for the prop route");

            if (($case['model'] ?? 'v') === 'v') {
                expect(str_contains($case['blade'], '{model}'))->toBeTrue("{$id} binds its value through {model}");
            }
        }

        expect($case['components'])->toBeArray()->not->toBeEmpty();
        expect($case['tabStops'])->toHaveKeys(['prop', 'form'], $id);

        foreach ($case['gestures'] as $gesture) {
            expect($gesture)->toHaveKeys(['name', 'steps', 'effect'], "{$id}: a gesture names what it must do enabled");
            expect($gesture['effect'])->not->toBeEmpty("{$id} / {$gesture['name']}: a gesture that is not asked to change anything is not a probe");
            expect($gesture['steps'])->not->toBeEmpty();
        }
    }
});

it('has a corpus row for every component that holds a value', function () {
    $covered = collect(disabledCorpus())->pluck('components')->flatten()->unique()->all();

    $missing = array_values(array_diff(valueControlFiles(), $covered, array_keys(NOT_VALUE_CONTROLS)));

    expect($missing)->toBe([], 'add a case to tests/Fixtures/disabled-controls-cases.json (or list the file in NOT_VALUE_CONTROLS with the reason it is not a value control)');
});

it('lists nothing stale: every exemption and every corpus component is a real value-control file', function () {
    $files = valueControlFiles();

    foreach (array_keys(NOT_VALUE_CONTROLS) as $name) {
        expect(in_array($name, $files, true))->toBeTrue("{$name} no longer matches the value-control signature: drop it from NOT_VALUE_CONTROLS");
    }

    foreach (collect(disabledCorpus())->pluck('components')->flatten()->unique() as $name) {
        expect(file_exists(__DIR__."/../../components/{$name}.blade.php"))->toBeTrue("{$name} is not a component file");
        expect(array_key_exists($name, NOT_VALUE_CONTROLS))->toBeFalse("{$name} is a corpus component and also listed in NOT_VALUE_CONTROLS");
    }
});

/**
 * Every x-data expression in the rendered case, in document order.
 *
 * @return list<string>
 */
function renderedXData(string $caseId, string $route, bool $off): array
{
    $component = Livewire::withQueryParams(['case' => $caseId, 'route' => $route, 'off' => $off ? '1' : '0'])->test(DisabledBehaviourFixture::class);

    return collect(domQuery($component->html(), '//*[@x-data]'))
        ->map(fn (DOMElement $el) => preg_replace('/\s+/', ' ', $el->getAttribute('x-data')))
        ->all();
}

it('keeps the disabled state out of x-data, in both routes', function (string $caseId, string $route) {
    // x-data must not hold a per-render value (an Alpine data object is built once, and a morph does not rebuild it): a disabled control
    // reads the state from the DOM at event time, so the expression is the same enabled and disabled
    $enabled = renderedXData($caseId, $route, false);
    $disabled = renderedXData($caseId, $route, true);

    // a disabled render may leave a part out (the native select draws no clear button), but never write an expression the enabled one lacks
    expect(array_values(array_diff($disabled, $enabled)))->toBe([], 'a disabled render wrote an x-data the enabled render does not have');
})->with(function () {
    foreach (disabledCorpus() as $case) {
        foreach (['prop', 'form'] as $route) {
            if (isset($case['routes']) && ! in_array($route, $case['routes'], true)) {
                continue;
            }

            yield "{$case['id']} ({$route})" => [$case['id'], $route];
        }
    }
});

it('renders an x-data for most of the corpus, so the comparison above is not an empty one', function () {
    $with = collect(disabledCorpus())->filter(fn ($case) => empty($case['special']) && renderedXData($case['id'], 'prop', false) !== [])->count();

    expect($with)->toBeGreaterThan(20);
});

// Left to PR 2, which adds the helper these name: nothing to assert before it exists.
it('Attributes::disabledBy answers self, inherited or null for every route')->todo('PR 2: needs Attributes::disabledBy()');
it('keeps pointer-events-none off the wrapper of every control, so the keyboard and the mouse meet the same guard')->todo('PR 2: the listbox and date wrappers carry it today');
it('lets a control\'s own :disabled="false" beat a disabled form')->todo('PR 2: the helper decides it for every control');
it('keeps the internal controls of a panel enabled inside a disabled control')->todo('PR 2/6: needs the panels\' :disabled="false"');
