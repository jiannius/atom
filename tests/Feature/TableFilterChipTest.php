<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;

// The five controls that report to a table's filter bar as a chip (the two form selects, the bar select, the range and the
// search) resolve their key the same way, name the chip, and print the same registration and clear hooks. This pins what each
// prints with a key (a wire:model, then data-filter-key) and without one, so a change to how they share it shows up here.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** One tag per control, `%s` taking the attributes that key it. */
function tableFilterChipTags(): array
{
    return [
        'select native' => ['<atom:select variant="native" label="Paint colour" table-filter :options="[[\'value\' => \'red\', \'label\' => \'Red\']]" %s />', "'Paint colour'", false],
        'select listbox' => ['<atom:select variant="listbox" label="Paint colour" table-filter :options="[[\'value\' => \'red\', \'label\' => \'Red\']]" %s />', "'Paint colour'", false],
        'select filter' => ['<atom:select.filter label="Paint colour" :options="[[\'value\' => \'red\', \'label\' => \'Red\']]" %s />', "'Paint colour'", true],
        'date range' => ['<atom:date-picker.range placeholder="Due date" %s />', "'Due date'", true],
        'search' => ['<atom:table.search placeholder="Find colours..." %s />', "'Find colours'", true],
    ];
}

/** The attributes of the element that carries the chip hooks, and whether any element does. */
function tableFilterChipRoot(string $html): array
{
    $roots = domQuery($html, '//*[@*[name()="x-on:table-filter:do-clear.window"]]');

    return $roots === [] ? [] : [
        'clear' => $roots[0]->getAttribute('x-on:table-filter:do-clear.window'),
        'init' => $roots[0]->getAttribute('x-init'),
        'data' => $roots[0]->getAttribute('x-data'),
        'key' => $roots[0]->getAttribute('data-atom-filter-key'),
    ];
}

it('keys the chip by the wire:model, naming it after the control', function (string $name) {
    [$tag, $label, $printsKey] = tableFilterChipTags()[$name];
    $root = tableFilterChipRoot(renderBlade(sprintf($tag, 'wire:model="filters.paint_colour"')));

    expect($root)->not->toBeEmpty()
        ->and($root['clear'])->toContain("'filters.paint_colour'")
        ->and($root['init'].$root['data'])->toContain("'filters.paint_colour'")->toContain($label)
        ->and($root['key'])->toBe($printsKey ? 'filters.paint_colour' : '');
})->with(array_keys(tableFilterChipTags()));

it('takes data-filter-key when there is no wire:model', function (string $name) {
    [$tag, , $printsKey] = tableFilterChipTags()[$name];
    $root = tableFilterChipRoot(renderBlade(sprintf($tag, 'x-model="q" data-filter-key="filters.q"')));

    expect($root)->not->toBeEmpty()
        ->and($root['clear'])->toContain("'filters.q'")
        ->and($root['init'].$root['data'])->toContain("'filters.q'")
        ->and($root['key'])->toBe($printsKey ? 'filters.q' : '');
})->with(array_keys(tableFilterChipTags()));

it('prefers the wire:model over data-filter-key', function (string $name) {
    [$tag] = tableFilterChipTags()[$name];
    $root = tableFilterChipRoot(renderBlade(sprintf($tag, 'wire:model="filters.a" data-filter-key="filters.b"')));

    expect($root['clear'])->toContain("'filters.a'")->not->toContain('filters.b');
})->with(array_keys(tableFilterChipTags()));

it('registers nothing without a key', function (string $name) {
    [$tag] = tableFilterChipTags()[$name];
    $html = renderBlade(sprintf($tag, ''));

    expect(tableFilterChipRoot($html))->toBeEmpty()
        ->and($html)->not->toContain('data-atom-filter-key')
        ->and($html)->not->toContain('table-filter:do-clear')
        ->and($html)->not->toContain('table-filter:set');
})->with(array_keys(tableFilterChipTags()));

it('falls back to the key for the chip name on the form selects', function (string $variant) {
    $html = renderBlade('<atom:select variant="'.$variant.'" wire:model="filters.paint_colour" table-filter :options="[]" />');

    expect(tableFilterChipRoot($html)['init'])->toContain("'Paint Colour'");
})->with(['native', 'listbox']);

it('prints as many end markers as start markers under Livewire, with a key and without', function (string $name, string $attrs) {
    [$tag] = tableFilterChipTags()[$name];

    $component = new class extends Component
    {
        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };
    $component::$blade = sprintf($tag, $attrs);

    $html = Livewire::test($component::class)->html();

    expect(substr_count($html, '<!--[if ENDBLOCK]><![endif]-->'))->toBe(substr_count($html, '<!--[if BLOCK]><![endif]-->'));
})->with(array_keys(tableFilterChipTags()))->with(['wire:model="filters.x"', 'data-filter-key="filters.x"', '']);
