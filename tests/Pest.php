<?php

use Illuminate\Support\Facades\Blade;
use Jiannius\Atom\Tests\TestCase;
use Livewire\Mechanisms\HandleComponents\HandleComponents;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Run a callback with a Livewire component instance on the Livewire stack so
 * that code reading app('livewire')->current() (toTable(), modal name
 * defaults, ...) sees the component.
 *
 * app('livewire')->current() calls last(HandleComponents::$componentStack),
 * which returns false (not null) when the stack is empty — the ?-> operator
 * does not protect against false, so we must push/pop the component manually.
 */
function withLivewireContext(object $component, callable $callback): mixed
{
    array_push(HandleComponents::$componentStack, $component);
    try {
        return $callback($component);
    } finally {
        array_pop(HandleComponents::$componentStack);
    }
}

/**
 * Render a Blade string and unwind any output buffers left open by Blade's
 * slot-capture mechanism (Blade::render of a component with a named <x-slot>
 * leaves a dangling ob level, which PHPUnit flags as a "risky" test).
 */
function renderBlade(string $template, array $data = []): string
{
    $level = ob_get_level();

    try {
        return Blade::render($template, $data);
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
}

/**
 * Parse rendered markup and return the elements an XPath expression matches,
 * so a test can assert on the element rather than grep the string.
 *
 * @return list<DOMElement>
 */
function domQuery(string $html, string $xpath): array
{
    $document = new DOMDocument;

    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
    libxml_clear_errors();

    return array_values(iterator_to_array((new DOMXPath($document))->query($xpath)));
}

/**
 * The class tokens of an element, so `toContain('mt-2')` cannot match `mt-20`.
 *
 * @return list<string>
 */
function domClasses(DOMElement $element): array
{
    return preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
}
