<?php

use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;

/**
 * A label prop is text, not markup. A host that passes a record's name to
 * `:label` must not have that name run as HTML, so each component prints it
 * escaped. A host that really wants markup passes an HtmlString, which
 * `{{ }}` leaves alone.
 *
 * The render is parsed, not grepped: a string check passes on a label that was
 * printed raw and then happened to be matched, a parsed `img` either exists or
 * it does not.
 */
const LABEL_ESCAPING_HOSTILE = '<img src=x onerror=alert(1)>';

/**
 * Each case is a template that takes `$label`.
 *
 * @return array<string, string>
 */
function labelEscapingTemplates(): array
{
    return [
        'badge' => '<atom:badge :label="$label" />',
        'badge (hex colour)' => '<atom:badge color="#ff0000" :label="$label" />',
        'radio' => '<atom:radio name="x" value="1" :label="$label" />',
        'checkbox' => '<atom:checkbox name="x" :label="$label" />',
        'toggle' => '<atom:toggle name="x" :label="$label" />',
        'input field' => '<atom:input.field name="x" :label="$label" />',
        'input' => '<atom:input name="x" :label="$label" />',
        'rating' => '<atom:rating name="x" :label="$label" />',
        'slider' => '<atom:slider name="x" :label="$label" />',
        'tab item' => '<atom:tabs.item :label="$label" />',
    ];
}

beforeEach(function () {
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
});

it('prints a hostile label as text', function (string $template) {
    $html = renderBlade($template, ['label' => LABEL_ESCAPING_HOSTILE]);

    expect(domQuery($html, '//img'))->toBeEmpty()
        ->and(domQuery($html, '//*[@onerror]'))->toBeEmpty()
        ->and($html)->toContain(e(LABEL_ESCAPING_HOSTILE));
})->with(labelEscapingTemplates());

it('prints an HtmlString label as markup', function (string $template) {
    $html = renderBlade($template, ['label' => new HtmlString('<b data-label-marker>x</b>')]);

    expect(domQuery($html, '//b[@data-label-marker]'))->toHaveCount(1);
})->with(labelEscapingTemplates());

it('translates a plain label and leaves an HtmlString alone in t()', function () {
    $html = new HtmlString('<b>x</b>');

    expect(t($html))->toBe($html)
        ->and(t('Hello'))->toBe('Hello');
});

it('prints a hostile mail call-to-action label as text', function () {
    $html = (string) app(Markdown::class)->render('atom::mail.generic', [
        'content' => 'Hello',
        'cta' => ['label' => LABEL_ESCAPING_HOSTILE, 'url' => 'https://example.com'],
    ]);

    expect(domQuery($html, '//*[@onerror]'))->toBeEmpty()
        ->and($html)->toContain(e(LABEL_ESCAPING_HOSTILE));
});

it('escapes an ampersand in a mail call-to-action label once', function () {
    $html = (string) app(Markdown::class)->render('atom::mail.generic', [
        'content' => 'Hello',
        'cta' => ['label' => 'Pay & go', 'url' => 'https://example.com'],
    ]);

    expect($html)->toContain('Pay &amp; go')->not->toContain('&amp;amp;');
});

it('escapes an ampersand in a label once', function (string $template) {
    $html = renderBlade($template, ['label' => 'Tom & Jerry']);

    expect($html)->toContain('Tom &amp; Jerry')->not->toContain('&amp;amp;');
})->with(labelEscapingTemplates());

/**
 * Every component that hands its label to input.field.
 *
 * @return array<string, string>
 */
function labelEscapingFieldWrappers(): array
{
    return [
        'input' => '<atom:input name="x" :label="$label" />',
        'input (file)' => '<atom:input type="file" name="x" :label="$label" />',
        'textarea' => '<atom:textarea name="x" :label="$label" />',
        'select (native)' => '<atom:select name="x" variant="native" :options="[]" :label="$label" />',
        'date-picker' => '<atom:date-picker name="x" :label="$label" />',
        'time-picker' => '<atom:time-picker name="x" :label="$label" />',
        'radio group' => '<atom:radio.group name="x" :label="$label" />',
        'tiptap' => '<atom:tiptap name="x" :label="$label" />',
    ];
}

it('prints a label once-escaped through every field wrapper', function (string $template) {
    $html = renderBlade($template, ['label' => 'Tom & Jerry']);

    expect($html)->toContain('Tom &amp; Jerry')->not->toContain('&amp;amp;');
})->with(labelEscapingFieldWrappers());

it('prints a hostile label as text through every field wrapper', function (string $template) {
    $html = renderBlade($template, ['label' => LABEL_ESCAPING_HOSTILE]);

    expect(domQuery($html, '//img'))->toBeEmpty()
        ->and(domQuery($html, '//*[@onerror]'))->toBeEmpty()
        ->and($html)->toContain(e(LABEL_ESCAPING_HOSTILE));
})->with(labelEscapingFieldWrappers());

it('prints an HtmlString label as markup through every field wrapper', function (string $template) {
    $html = renderBlade($template, ['label' => new HtmlString('<b data-label-marker>x</b>')]);

    expect(domQuery($html, '//b[@data-label-marker]'))->toHaveCount(1);
})->with(labelEscapingFieldWrappers());

it('does not print a raw string in a hand-built attribute bag as markup', function () {
    $html = renderBlade(
        '<atom:input.field :attributes="$bag" />',
        ['bag' => new ComponentAttributeBag(['label' => LABEL_ESCAPING_HOSTILE])],
    );

    expect(domQuery($html, '//img'))->toBeEmpty()
        ->and(domQuery($html, '//*[@onerror]'))->toBeEmpty()
        ->and($html)->toContain(e(LABEL_ESCAPING_HOSTILE));
});

it('prints an HtmlString in a hand-built attribute bag as markup', function () {
    $html = renderBlade(
        '<atom:input.field :attributes="$bag" />',
        ['bag' => new ComponentAttributeBag(['label' => new HtmlString('<b data-label-marker>x</b>')])],
    );

    expect(domQuery($html, '//b[@data-label-marker]'))->toHaveCount(1);
});

it('looks a field label up by its unescaped text', function () {
    app('translator')->addLines(['*.Tom & Jerry\'s' => 'Tom & Jerry (translated)'], 'en');

    $html = renderBlade('<atom:input name="x" :label="$label" />', ['label' => "Tom & Jerry's"]);

    expect($html)->toContain('Tom &amp; Jerry (translated)')->not->toContain('&amp;amp;');
});

it('keeps a label slot on input.field', function () {
    $html = renderBlade('<atom:input.field>BODY<x-slot:label><b data-label-marker>x</b></x-slot></atom:input.field>');

    expect(domQuery($html, '//b[@data-label-marker]'))->toHaveCount(1);
});

it('does not print a plain string icon on embed as markup', function () {
    $html = renderBlade('<atom:embed :icon="$icon" />', ['icon' => '<svg onload="alert(1)"></svg>']);

    expect(domQuery($html, '//*[@onload]'))->toBeEmpty();
});

it('prints an HtmlString svg icon on embed as markup', function () {
    $html = renderBlade('<atom:embed :icon="$icon" />', ['icon' => new HtmlString('<svg data-icon-marker></svg>')]);

    expect(domQuery($html, '//svg[@data-icon-marker]'))->toHaveCount(1);
});

it('still prints a named icon on embed', function () {
    expect(domQuery(renderBlade('<atom:embed icon="file" />'), '//svg'))->not->toBeEmpty();
});

describe('Htmlable text sent to the browser', function () {
    /**
     * A stand-in for the current Livewire component that records what it dispatches.
     */
    function labelEscapingRecorder(): object
    {
        return new class
        {
            public array $dispatched = [];

            public function dispatch(string $event, ...$params): void
            {
                $this->dispatched = [$event, $params];
            }

            public function getId(): string
            {
                return 'recorder';
            }
        };
    }

    it('turns an HtmlString into a string before a toast, alert or confirm is dispatched', function (string $call, string $event) {
        $recorder = labelEscapingRecorder();
        $html = new HtmlString('<b>x</b>');

        withLivewireContext($recorder, fn () => app('atom')->{$call}(message: [$html], heading: $html, subheading: $html));

        [$dispatched, $params] = $recorder->dispatched;

        expect($dispatched)->toBe($event)
            ->and($params['heading'])->toBe('<b>x</b>')
            ->and($params['subheading'])->toBe('<b>x</b>')
            ->and($params['message'])->toBe(['<b>x</b>']);
    })->with([
        'toast' => ['toast', 'atom-toast-show'],
        'alert' => ['alert', 'atom-alert-show'],
        'confirm' => ['confirm', 'atom-confirm-show'],
    ]);

    it('turns an HtmlString into a string in a breadcrumb title', function () {
        $breadcrumbs = app('atom')->breadcrumbs()->home(new HtmlString('<b>h</b>'), '/')->push(new HtmlString('<i>p</i>'), '/p');

        expect($breadcrumbs->home['title'])->toBe('<b>h</b>')
            ->and($breadcrumbs->items[0]['title'])->toBe('<i>p</i>');
    });
});
