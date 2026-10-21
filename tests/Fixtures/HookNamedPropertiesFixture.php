<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Jiannius\Atom\Traits\WithEditor;
use Jiannius\Atom\Traits\WithTable;
use Jiannius\Atom\Traits\WithUrlFilters;
use Livewire\Component;

/**
 * A host that happens to name its properties like a trait: Livewire calls a trait's
 * hook for a property of that name too, with the value where the path would be.
 */
class HookNamedPropertiesFixture extends Component
{
    use WithBreadcrumbs;
    use WithEditor;
    use WithTable;
    use WithUrlFilters;

    /** @var array<string, mixed> */
    public array $filters = ['search' => ''];

    /** @var array<int, mixed> */
    public array $withTable = [];

    /** @var array<int, mixed> */
    public array $withEditor = [];

    /** @var array<int, mixed> */
    public array $withBreadcrumbs = [];

    /** @var array<int, mixed> */
    public array $withUrlFilters = [];

    /**
     * The filters that live in the URL
     *
     * @return array<string, mixed>
     */
    protected function urlFilters(): array
    {
        return ['search' => ''];
    }

    /**
     * Render the fixture
     */
    public function render()
    {
        return '<div>ok</div>';
    }
}
