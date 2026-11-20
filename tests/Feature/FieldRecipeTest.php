<?php

use Illuminate\Support\ViewErrorBag;

/**
 * Pins the class set of every text control, per state, against tests/Snapshots/field-recipe-classes.json.
 *
 * The control classes are written out in each control (atom's utilities are found by a host's Tailwind scan of components/, so
 * they stay next to the markup), which is how they drifted. This file is what holds them to one recipe: the snapshot shows a
 * change to any control's classes as a diff, and the rules below say what every control must share. Set UPDATE_FIELD_RECIPE=1 to
 * rewrite the snapshot.
 */

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * Each control: a template (`{attrs}` takes the state's attributes) and the XPath of the element that carries the recipe.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function fieldRecipeControls(): array
{
    return [
        'input' => ['<atom:input type="text" wire:model="a" {attrs} />', '//input[@type="text"]'],
        'input-email-single' => ['<atom:input type="email" wire:model="a" {attrs} />', '//input[@type="email"]'],
        'input-tel' => ['<atom:input type="tel" wire:model="a" {attrs} />', '//input[@type="tel"]'],
        'input-color' => ['<atom:input type="color" wire:model="a" {attrs} />', '//div[@data-atom-color-input]//input'],
        'input-email-multiple' => ['<atom:input type="email" multiple wire:model="a" {attrs} />', '//div[@data-atom-input-email]//div[@data-atom-dropdown-trigger]'],
        'input-otp' => ['<atom:input.otp wire:model="a" {attrs} />', '(//input[@data-atom-input-otp-box])[1]'],
        'textarea' => ['<atom:textarea wire:model="a" {attrs} />', '//textarea'],
        'date-picker' => ['<atom:date-picker wire:model="a" {attrs} />', '//div[@data-atom-date-picker]//input'],
        'date-range' => ['<atom:date-picker variant="range" wire:model="a" {attrs} />', '//div[@data-atom-date-range]//input'],
        'time-picker' => ['<atom:time-picker wire:model="a" {attrs} />', '//div[@x-modelable="timePickerValue"]'],
        'select-native' => ['<atom:select wire:model="a" {attrs} />', '//div[@data-atom-select-native]/div'],
        'select-listbox' => ['<atom:select variant="listbox" wire:model="a" {attrs} />', '//div[@data-atom-select-listbox]//button[@aria-haspopup="listbox"]'],
    ];
}

/**
 * The states a control is drawn in: what the call carries, and whether a disabled form wraps it.
 *
 * @return array<string, array{0: string, 1: bool}>
 */
function fieldRecipeStates(): array
{
    return [
        'default' => ['', false],
        'invalid' => ['invalid', false],
        'disabled' => ['disabled', false],
        'form-disabled' => ['', true],
        'invalid-disabled' => ['invalid disabled', false],
    ];
}

/**
 * The sorted class tokens of one control in one state.
 *
 * @return list<string>
 */
function fieldRecipeClasses(string $control, string $state): array
{
    [$template, $xpath] = fieldRecipeControls()[$control];
    [$attrs, $inForm] = fieldRecipeStates()[$state];

    $markup = str_replace('{attrs}', $attrs, $template);
    $html = renderBlade($inForm ? '<atom:form disabled>'.$markup.'</atom:form>' : $markup);
    $found = domQuery($html, $xpath);

    expect($found)->not->toBeEmpty("{$control} / {$state}: nothing at {$xpath}");

    $classes = domClasses($found[0]);
    sort($classes);

    return $classes;
}

/**
 * Every control in every state.
 *
 * @return array<string, list<string>>
 */
function fieldRecipeMatrix(): array
{
    $matrix = [];

    foreach (array_keys(fieldRecipeControls()) as $control) {
        foreach (array_keys(fieldRecipeStates()) as $state) {
            $matrix["{$control} / {$state}"] = fieldRecipeClasses($control, $state);
        }
    }

    return $matrix;
}

it('pins the class set of every control in every state', function () {
    $path = __DIR__.'/../Snapshots/field-recipe-classes.json';
    $matrix = fieldRecipeMatrix();
    $json = json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

    if (getenv('UPDATE_FIELD_RECIPE')) {
        file_put_contents($path, $json);
    }

    expect(file_exists($path))->toBeTrue("no snapshot at {$path}: run with UPDATE_FIELD_RECIPE=1 to write it");
    expect($matrix)->toBe(json_decode(file_get_contents($path), true));
});
