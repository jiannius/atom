<?php

use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;

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
