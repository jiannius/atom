<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\Livewire;

// Livewire's morph-aware precompiler prints `<!--[if BLOCK]-->` after every `@if` and `<!--[if ENDBLOCK]-->` after every `@endif`. A `//` comment
// holding an apostrophe inside a `{{ }}` echo made it lose the marker of the next `@if`, so the pager printed one end marker too many and Alpine
// morph threw when a table went from one page to several (see TableCellMarkersTest for the first such case).

/** The markers a Livewire component prints for a pager over $total rows, 20 a page. */
function pagerMarkers(string $blade, int $page, int $total = 200): array
{
    $component = new class extends Component {
        use WithTable;

        public static string $blade = '';

        public static int $page = 1;

        public static int $total = 200;

        public function pager(): LengthAwarePaginator
        {
            return new LengthAwarePaginator(collect(range(1, min(20, static::$total))), static::$total, 20, static::$page, ['path' => '/']);
        }

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;
    $component::$page = $page;
    $component::$total = $total;

    $html = Livewire::test($component::class)->html();

    return [
        'block' => substr_count($html, '<!--[if BLOCK]><![endif]-->'),
        'end' => substr_count($html, '<!--[if ENDBLOCK]><![endif]-->'),
    ];
}

it('prints as many end markers as start markers for a pager', function (string $blade, int $page, int $total) {
    $markers = pagerMarkers($blade, $page, $total);

    expect($markers['block'])->toBeGreaterThan(0)
        ->and($markers['end'])->toBe($markers['block']);
})->with([
    'standalone' => ['<atom:pagination :paginator="$this->pager()" />'],
    'standalone, simple' => ['<atom:pagination :paginator="$this->pager()" simple />'],
    'standalone, no summary' => ['<atom:pagination :paginator="$this->pager()" :summary="false" />'],
    'table' => ['<atom:table.pagination :paginate="$this->pager()" />'],
    'table, no rows menu' => ['<atom:table.pagination :paginate="$this->pager()" :max-rows="false" />'],
    'table, not sticky' => ['<atom:table.pagination :paginate="$this->pager()" :sticky="false" />'],
])->with([
    'first page' => [1, 200],
    'middle page' => [5, 200],
    'last page' => [10, 200],
    'single page' => [1, 3],
]);
