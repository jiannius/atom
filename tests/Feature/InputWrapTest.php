<?php

use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Pins what `<atom:input>` prints for every type, with and without a label or caption and a prefix or suffix, against
 * tests/Snapshots/input-wrap.json (a hash of the render with its whitespace collapsed, per case), so the way the control is
 * wrapped in the field and the affix box can be restructured without the markup moving. Set UPDATE_INPUT_WRAP=1 to rewrite it.
 */

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * The tags the types are drawn with: the opening tag's attributes (`{extra}` takes the case's label, caption and affix) and the
 * affixes the type honours (the others never print one).
 *
 * @return array<string, string>
 */
function inputWrapTypes(): array
{
    return [
        'text' => '<atom:input type="text" wire:model="a" placeholder="Name" {extra} />',
        'password' => '<atom:input type="password" wire:model="a" {extra} />',
        'number' => '<atom:input type="number" wire:model="a" {extra} />',
        'default type' => '<atom:input wire:model="a" {extra} />',
        'text with actions' => '<atom:input wire:model="a" {extra}>Body<x-slot:actions><button type="button">Go</button></x-slot:actions></atom:input>',
        'tel' => '<atom:input type="tel" wire:model="a" {extra} />',
        'color' => '<atom:input type="color" wire:model="a" {extra} />',
        'email' => '<atom:input type="email" wire:model="a" {extra} />',
        'email multiple' => '<atom:input type="email" multiple wire:model="a" {extra} />',
        'email options' => '<atom:input type="email" :options="[]" wire:model="a" {extra} />',
        'file' => '<atom:input type="file" wire:model="a" {extra} />',
        'file with end' => '<atom:input type="file" wire:model="a" {extra}><x-slot:end>Tail</x-slot:end></atom:input>',
        'unknown type' => '<atom:input type="url" wire:model="a" {extra} />',
    ];
}

/**
 * The label, caption, error and affix combinations.
 *
 * @return array<string, string>
 */
function inputWrapExtras(): array
{
    $none = [
        'bare' => '',
        'label' => 'label="Name"',
        'caption' => 'caption="Hint"',
        'label and caption' => 'label="Name" caption="Hint" required error="Taken"',
    ];

    $affixes = [
        '' => '',
        'prefix' => 'prefix="RM"',
        'suffix' => 'suffix="kg"',
        'prefix and suffix' => 'prefix="RM" suffix="kg"',
    ];

    $extras = [];

    foreach ($none as $field => $fieldAttrs) {
        foreach ($affixes as $affix => $affixAttrs) {
            $extras[trim($field.' / '.$affix, ' /')] = trim($fieldAttrs.' '.$affixAttrs);
        }
    }

    return $extras;
}

/**
 * Collapse whitespace so indentation does not count, but a missing gap does.
 */
function inputWrapNormalise(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', $html));
}

it('prints the same markup for every type, label, caption and affix', function () {
    $path = __DIR__.'/../Snapshots/input-wrap.json';
    $cases = [];

    foreach (inputWrapTypes() as $type => $template) {
        foreach (inputWrapExtras() as $extra => $attrs) {
            $html = inputWrapNormalise(renderBlade(str_replace('{extra}', $attrs, $template)));
            $cases["{$type} / {$extra}"] = strlen($html).':'.sha1($html);
        }
    }

    if (getenv('UPDATE_INPUT_WRAP')) {
        file_put_contents($path, json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    expect(file_exists($path))->toBeTrue("no snapshot at {$path}: run with UPDATE_INPUT_WRAP=1 to write it");
    expect($cases)->toBe(json_decode(file_get_contents($path), true));
});

it('keeps every type of the matrix distinct from its neighbours, so the snapshot pins something', function () {
    $html = fn (string $type, string $extra) => inputWrapNormalise(renderBlade(str_replace('{extra}', $extra, inputWrapTypes()[$type])));

    expect($html('text', 'label="Name"'))->not->toBe($html('text', ''));
    expect($html('text', 'prefix="RM"'))->toContain('RM')->not->toBe($html('text', 'suffix="kg"'));
    expect($html('email multiple', 'label="Name"'))->toContain('data-atom-input-email');
    expect($html('unknown type', 'label="Name"'))->not->toContain('<input');
});

/**
 * The markers a Livewire component prints for the given Blade: `@if` is followed by a BLOCK marker and `@endif` by an ENDBLOCK
 * one, and Alpine's morph throws when the two do not pair.
 */
function inputWrapMarkers(string $blade): array
{
    $component = new class extends Component
    {
        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;

    $html = Livewire::test($component::class)->html();

    return [
        'block' => substr_count($html, '<!--[if BLOCK]><![endif]-->'),
        'end' => substr_count($html, '<!--[if ENDBLOCK]><![endif]-->'),
        'html' => $html,
    ];
}

it('prints as many end markers as start markers under Livewire, for every type and wrapping', function () {
    foreach (inputWrapTypes() as $type => $template) {
        foreach (inputWrapExtras() as $extra => $attrs) {
            $markers = inputWrapMarkers(str_replace('{extra}', $attrs, $template));

            expect($markers['end'])->toBe($markers['block'], "{$type} / {$extra}: {$markers['block']} BLOCK, {$markers['end']} ENDBLOCK");
        }
    }
});

it('prints nothing for a type nothing draws, with or without a label, under Livewire as well', function (string $attrs) {
    $html = inputWrapMarkers('<atom:input type="url" wire:model="a" '.$attrs.' />')['html'];

    expect($html)->not->toContain('<input')->not->toContain('<label')->not->toContain('Name')->not->toContain('Hint');
})->with([
    'bare' => '',
    'label' => 'label="Name"',
    'caption' => 'caption="Hint"',
    'label and caption' => 'label="Name" caption="Hint"',
]);
