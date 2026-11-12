<?php

use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;

/**
 * #216: toast, alert and confirm translate a host's heading, subheading and message exactly once.
 * A host that has already interpolated user data must not see it cut at a `|`, swapped for a lang key or searched for `:word`.
 */

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);

    app('translator')->addLines([
        '*.Block :name?' => 'Blokir :name?',
        '*.Ann' => 'TRANSLATED-ANN',
        '*.Are you sure?' => 'Yakin?',
    ], 'en');
});

/**
 * What atom()->{method}() dispatches on the current component, as [event, params].
 */
function dispatchedBy(string $method, array $args): array
{
    $fake = new class {
        public array $dispatched = [];

        public function dispatch(string $event, ...$params): void
        {
            $this->dispatched[] = [$event, $params];
        }

        public function getId(): string
        {
            return 'fake-id';
        }
    };

    withLivewireContext($fake, fn () => app('atom')->{$method}(...$args));

    return $fake->dispatched[0];
}

dataset('flows', ['toast', 'alert', 'confirm']);

it('translates a plain key once', function (string $flow) {
    [, $params] = dispatchedBy($flow, ['heading' => 'Are you sure?', 'subheading' => 'Block :name?', 'message' => ['Are you sure?']]);

    expect($params['heading'])->toBe('Yakin?')
        ->and($params['subheading'])->toBe('Blokir :name?')
        ->and($params['message'])->toBe(['Yakin?']);
})->with('flows');

it('keeps a pipe in an already-interpolated plain string', function (string $flow) {
    [, $params] = dispatchedBy($flow, ['heading' => 'Block Ann|Bob?', 'subheading' => 'Block Ann|Bob?', 'message' => 'Block Ann|Bob?']);

    expect($params['heading'])->toBe('Block Ann|Bob?')
        ->and($params['subheading'])->toBe('Block Ann|Bob?')
        ->and($params['message'])->toBe(['Block Ann|Bob?']);
})->with('flows');

it('translates a [key, replacements] pair once, whatever the values hold', function (string $flow, string $name) {
    $expected = 'Blokir '.$name.'?';

    [, $params] = dispatchedBy($flow, [
        'heading' => ['Block :name?', ['name' => $name]],
        'subheading' => ['Block :name?', ['name' => $name]],
        'message' => ['Block :name?', ['name' => $name]],
    ]);

    expect($params['heading'])->toBe($expected)
        ->and($params['subheading'])->toBe($expected)
        ->and($params['message'])->toBe([$expected]);
})->with('flows')->with([
    'a pipe' => 'Ann|Bob',
    'a :word' => ':name :other',
    'a lang key' => 'Ann',
    'a pipe and a count' => '{0} none|[1,*] some',
]);

it('takes a list of messages, pairs and plain strings mixed', function (string $flow) {
    [, $params] = dispatchedBy($flow, ['message' => [['Block :name?', ['name' => 'Ann']], 'Are you sure?']]);

    expect($params['message'])->toBe(['Blokir Ann?', 'Yakin?']);
})->with('flows');

// An Htmlable is not translated and not escaped by atom; the components print heading, subheading and message with x-text, so the browser shows it as text.
it('passes an Htmlable through untranslated', function (string $flow) {
    [, $params] = dispatchedBy($flow, ['heading' => new HtmlString('<b>Ann|Bob</b>')]);

    expect($params['heading'])->toBe('<b>Ann|Bob</b>');
})->with('flows');

it('translates once for the trigger components', function (string $tag) {
    $html = renderBlade('<atom:'.$tag.'.trigger :heading="$heading" :subheading="$sub">Go</atom:'.$tag.'.trigger>', [
        'heading' => ['Block :name?', ['name' => 'Ann|Bob']],
        'sub' => ['Block :name?', ['name' => 'Ann']],
    ]);

    expect($html)->toContain('Blokir Ann|Bob?')->toContain('Blokir Ann?');
})->with(['toast', 'alert', 'confirm']);

it('keeps a pipe in a plain heading on the trigger components', function (string $tag) {
    $html = renderBlade('<atom:'.$tag.'.trigger :heading="$heading">Go</atom:'.$tag.'.trigger>', ['heading' => 'Block Ann|Bob?']);

    expect($html)->toContain('Block Ann|Bob?')->not->toContain('"Block Ann?"');
})->with(['toast', 'alert', 'confirm']);

it('still translates a plain key on the trigger components, and the message alias for the subheading', function () {
    $html = renderBlade('<atom:confirm.trigger heading="Are you sure?" message="Block :name?">Go</atom:confirm.trigger>');

    expect($html)->toContain('Yakin?')->toContain('Blokir :name?');
});

it('accepts the pair as a literal in the tag', function () {
    $html = renderBlade('<atom:confirm.trigger :heading="[\'Block :name?\', [\'name\' => $name]]">Go</atom:confirm.trigger>', ['name' => 'Ann|Bob']);

    expect($html)->toContain('Blokir Ann|Bob?');
});
