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

/**
 * The controls that pick their border from the state on the server (a disabled one is drawn with `border-border`, and a disabled
 * one is never also drawn invalid), and so are held to the state rules below. A textarea, a native select and the range picker
 * take the disabled look from a variant or the root's marker instead, so a plain border class says nothing about them.
 *
 * @return list<string>
 */
function fieldRecipeStateful(): array
{
    return ['input', 'input-email-single', 'input-tel', 'input-color', 'input-email-multiple', 'input-otp', 'date-picker', 'time-picker', 'select-listbox'];
}

/**
 * Assert a control's tokens, naming the control and state in the failure.
 *
 * @param  list<string>  $classes
 * @param  list<string>  $has
 * @param  list<string>  $lacks
 */
function expectFieldTokens(array $classes, string $label, array $has = [], array $lacks = []): void
{
    foreach ($has as $token) {
        expect(in_array($token, $classes, true))->toBeTrue("{$label} should carry {$token}");
    }

    foreach ($lacks as $token) {
        expect(in_array($token, $classes, true))->toBeFalse("{$label} should not carry {$token}");
    }
}

it('draws every control with one border per state: control, danger when invalid, faint when disabled (and then never danger)', function () {
    $matrix = fieldRecipeMatrix();

    foreach (array_keys(fieldRecipeControls()) as $control) {
        expectFieldTokens($matrix["{$control} / default"], "{$control} / default", ['border-control-border'], ['border-danger-fg']);
        expectFieldTokens($matrix["{$control} / invalid"], "{$control} / invalid", ['border-danger-fg']);
    }

    foreach (fieldRecipeStateful() as $control) {
        foreach (['disabled', 'form-disabled', 'invalid-disabled'] as $state) {
            expectFieldTokens($matrix["{$control} / {$state}"], "{$control} / {$state}", ['border-border'], ['border-control-border', 'border-danger-fg']);
        }
    }
});

it('keeps a field error from painting a disabled control, while a focused one still takes the focus border', function () {
    $matrix = fieldRecipeMatrix();
    $error = 'group-has-[[data-atom-error]]/field:border-danger-fg';
    $focus = fn (string $control) => in_array($control, ['input-email-multiple', 'time-picker', 'select-native'], true)
        ? 'group-has-[[data-atom-error]]/field:has-[:focus-visible]:border-danger-fg'
        : 'group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg';

    foreach (array_keys(fieldRecipeControls()) as $control) {
        expectFieldTokens($matrix["{$control} / default"], "{$control} / default", [$error, $focus($control)]);

        foreach (['disabled', 'form-disabled', 'invalid-disabled'] as $state) {
            expectFieldTokens($matrix["{$control} / {$state}"], "{$control} / {$state}", [$focus($control)], [$error]);
        }
    }
});

it('gives every control the disabled variants of a client-driven disabled, and the shared geometry', function () {
    $matrix = fieldRecipeMatrix();
    $direct = ['disabled:cursor-not-allowed', 'disabled:border-border', 'disabled:bg-surface-sunken', 'disabled:text-fg-subtle'];

    foreach (['input', 'input-email-single', 'input-tel', 'input-color', 'input-otp', 'textarea', 'date-picker', 'date-range', 'select-listbox'] as $control) {
        expectFieldTokens($matrix["{$control} / default"], "{$control} / default", $direct);
    }

    foreach (['input-email-multiple' => 'input', 'time-picker' => 'input', 'select-native' => 'select'] as $control => $inner) {
        expectFieldTokens($matrix["{$control} / default"], "{$control} / default", array_map(fn (string $utility) => "has-[{$inner}:disabled]:{$utility}", ['cursor-not-allowed', 'border-border', 'bg-surface-sunken', 'text-fg-subtle']));
    }

    foreach (array_keys(fieldRecipeControls()) as $control) {
        // an OTP box is a 40px square digit, not a text line, so its size and type are its own
        expectFieldTokens($matrix["{$control} / default"], "{$control} / default", $control === 'input-otp' ? ['rounded-control', 'border'] : ['rounded-control', 'border', 'text-sm', 'w-full']);
    }
});

it('draws a disabled textarea, native select and range picker disabled even when invalid, through their own variants', function () {
    $matrix = fieldRecipeMatrix();

    // what makes each one disabled, which sorts above its plain border: a textarea and the range picker read the marker (a form's disabled is `readonly`, which `:disabled` does not
    // match), the native select its <select>'s own `:disabled` (a disabled form disables it)
    $variants = [
        'textarea' => ['[&[data-atom-disabled]]:border-border', '[&[data-atom-disabled]]:bg-surface-sunken', '[&[data-atom-disabled]]:text-fg-subtle', '[&[data-atom-disabled]]:cursor-not-allowed'],
        'date-range' => ['[[data-atom-disabled]_&]:border-border', '[[data-atom-disabled]_&]:bg-surface-sunken', '[[data-atom-disabled]_&]:text-fg-subtle', '[[data-atom-disabled]_&]:cursor-not-allowed'],
        'select-native' => ['has-[select:disabled]:border-border', 'has-[select:disabled]:bg-surface-sunken', 'has-[select:disabled]:text-fg-subtle', 'has-[select:disabled]:cursor-not-allowed'],
    ];

    foreach ($variants as $control => $tokens) {
        foreach (['disabled', 'form-disabled', 'invalid-disabled'] as $state) {
            expectFieldTokens($matrix["{$control} / {$state}"], "{$control} / {$state}", $tokens);
        }

        // and the invalid one is still drawn invalid where it is not disabled
        expectFieldTokens($matrix["{$control} / invalid"], "{$control} / invalid", ['border-danger-fg']);
    }

    // a focused one keeps its focus border under the variant: the focus variant is stacked after it
    expectFieldTokens($matrix['textarea / invalid-disabled'], 'textarea / invalid-disabled', ['[&[data-atom-disabled]]:focus-visible:border-danger-fg']);
    expectFieldTokens($matrix['date-range / invalid-disabled'], 'date-range / invalid-disabled', ['[[data-atom-disabled]_&]:focus-visible:border-danger-fg']);
});
