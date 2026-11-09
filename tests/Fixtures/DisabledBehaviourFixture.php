<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Support\Facades\Blade;
use Jiannius\Atom\Traits\WithEditor;
use Livewire\Component;

/**
 * Renders ONE case of tests/Fixtures/disabled-controls-cases.json per page (`?case=<id>`), so a Livewire morph on one control can never
 * detach the locator of the next (a test that walks many controls on one page loses its next locator to a `.live` morph).
 *
 * Query: `case` (an id), `route` (`prop`: the control carries `:disabled="$off"`; `form`: it sits in `<atom:form :disabled="$off">` and
 * carries nothing), `off=1` (start disabled: the static render a morph is compared with), `bind=x` (the value lives in the page's Alpine
 * data instead of in Livewire, to prove an `x-model` parent still shows a push), `value` (a JSON value instead of the case's own).
 */
class DisabledBehaviourFixture extends Component
{
    use WithEditor;

    public mixed $v = null;

    public bool $off = false;

    public string $case = '';

    public string $route = 'prop';

    public bool $xmodel = false;

    public mixed $file = null;

    public int $renders = 0;

    public int $submits = 0;

    /**
     * Start from the case's own initial value, unless the query overrides it.
     */
    public function mount(): void
    {
        $case = static::find(request()->query('case'));

        abort_unless($case, 404);

        $this->case = $case['id'];
        $this->route = request()->query('route') === 'form' ? 'form' : 'prop';
        $this->off = request()->query('off') === '1';
        $this->xmodel = request()->query('bind') === 'x';
        $this->v = request()->has('value') ? json_decode(request()->query('value'), true) : ($case['value'] ?? null);
    }

    /**
     * The corpus, as PHP and the specs both read it.
     *
     * @return list<array<string, mixed>>
     */
    public static function corpus(): array
    {
        return json_decode(file_get_contents(__DIR__.'/disabled-controls-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * One case of the corpus by id, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function find(mixed $id): ?array
    {
        return collect(static::corpus())->firstWhere('id', $id);
    }

    /**
     * Flip disabled through a real Livewire morph.
     */
    public function flip(): void
    {
        $this->off = ! $this->off;
    }

    /**
     * The server pushes a new value, as a host's `$this->field = ...` does.
     */
    public function push(mixed $value): void
    {
        $this->v = $value;
    }

    /**
     * Make the component render again, with nothing else changed.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * What the server holds, read after a round trip (the return value is sent back and nothing is re-rendered).
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $this->skipRender();

        return [
            'v' => $this->v,
            'file' => is_object($this->file) ? $this->file->getClientOriginalName() : null,
            'renders' => $this->renders,
            'submits' => $this->submits,
        ];
    }

    /**
     * The form route's submit target: a disabled form must never reach it.
     */
    public function submit(): void
    {
        $this->submits++;
    }

    /**
     * Render the case's snippet, in the route being tested.
     */
    public function render()
    {
        $case = static::find($this->case);
        $level = ob_get_level();

        $options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent'], ['value' => 'c', 'label' => 'Paid']];

        $snippet = str_replace(
            ['{disabled}', '{model}'],
            [$this->route === 'prop' ? ':disabled="$off"' : '', $this->xmodel ? 'x-model="pv"' : 'wire:model.live="v"'],
            $case['blade'],
        );

        if ($this->route === 'form') {
            $snippet = '<atom:form :disabled="$off" wire:submit="submit">'.$snippet.'</atom:form>';
        }

        try {
            $html = Blade::render($snippet, ['off' => $this->off, 'options' => $options]);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        return view('atom-test::disabled-behaviour', ['html' => $html, 'def' => $case]);
    }
}
