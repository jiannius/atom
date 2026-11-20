<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A multiple select stays open while values are ticked (#280). Modelled on a host's dashboard filter: a searchable
 * multiple Client filter on `wire:model.live`, with a Branch select that only shows while exactly one client is ticked.
 */
class SelectStayOpenFixture extends Component
{
    /** @var array<int, string> */
    public array $clients = [];

    public ?string $branch = null;

    /** @var array<int, string> */
    public array $regions = [];

    /** @var array<int, string> */
    public array $tags = [];

    /** @var array<int, string> */
    public array $sizes = [];

    /** @var array<int, string> */
    public array $codes = [];

    /** @var array<int, string> */
    public array $countries = [];

    public ?string $status = null;

    public ?string $priority = null;

    public int $renders = 0;

    /**
     * Render the fixture view.
     */
    public function render()
    {
        // counted, so a test can tell a tick went to the server
        $this->renders++;

        $options = fn (array $labels) => collect($labels)->map(fn ($label) => ['value' => str($label)->slug()->toString(), 'label' => $label])->all();

        return view('atom-test::select-stay-open', [
            'clientOptions' => $options(['Alpha Trading', 'Alpine Foods', 'Altair Labs', 'Beta Works', 'Bravo Studio', 'Charlie Supplies']),
            'regionOptions' => [
                ['group' => 'North', 'options' => $options(['Kedah', 'Penang', 'Perak'])],
                ['group' => 'South', 'options' => $options(['Johor', 'Melaka'])],
            ],
            'tagOptions' => $options(['Red', 'Rose', 'Ruby', 'Green', 'Blue']),
            'sizeOptions' => $options(['Small', 'Medium', 'Large', 'Huge']),
            'statusOptions' => $options(['Draft', 'Sent', 'Paid']),
            // a list taller than its 400px cap
            'codeOptions' => collect(range(1, 60))->map(fn ($n) => ['value' => 'code-'.$n, 'label' => 'Code '.$n])->all(),
        ]);
    }
}
