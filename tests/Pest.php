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
 * Run a callback with every PHP warning and notice thrown as an ErrorException, as a
 * Laravel host does. Without it a vendor warning (tiptap-php's serialiser raising one on a
 * document it cannot encode) is swallowed by the test runner and a test can pass over it.
 */
function withWarningsThrown(callable $callback): mixed
{
    set_error_handler(function (int $level, string $message, string $file, int $line) {
        if (! (error_reporting() & $level)) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    });

    try {
        return $callback();
    } finally {
        restore_error_handler();
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

/**
 * The props of <atom:calendar> and their defaults, as `@props` declares them (camelCase names).
 *
 * @return array<string, mixed>
 */
function calendarPropDefaults(): array
{
    preg_match('/@props\(\[(.*?)\]\)/s', file_get_contents(__DIR__.'/../components/calendar/index.blade.php'), $props);
    preg_match_all("/^\s*'([A-Za-z]+)' => (.+),$/m", $props[1], $declared, PREG_SET_ORDER);

    $defaults = [];

    foreach ($declared as [, $name, $php]) {
        $defaults[$name] = eval('return '.$php.';');
    }

    return $defaults;
}

/**
 * What the calendar's browser code dispatches, read off its dispatch sites rather than written out again: for each event, the keys of
 * its detail (sorted) and whether it is cancelable (null where the dispatch leaves it to Alpine's `$dispatch`). A regex that stops
 * matching fails here by name, so a change to a dispatch site has to be followed by this helper and then by the docs it feeds.
 *
 * @return array<string, array{keys: list<string>, cancelable: ?bool}>
 */
function calendarEventFacts(): array
{
    $js = __DIR__.'/../resources/js/alpinejs/';
    $pointer = file_get_contents($js.'calendar/pointer.js');

    $keysOf = function (string $body): array {
        // `key: value` and a shorthand `key` alike, but not a word inside a call's arguments
        preg_match_all('/(?:^|,)\s*(\w+)\s*(?::|,|$)/', $body, $keys);

        return $keys[1];
    };

    expect(preg_match('/export const selectionRange = .*?\n}\n/s', file_get_contents($js.'calendar/gestures.js'), $range))->toBe(1, 'selectionRange not found');
    expect(preg_match_all('/return \{ ([^}]*) \}/', $range[0], $returns))->toBeGreaterThan(0, 'selectionRange returns no object');
    expect(preg_match("/'calendar-select', \{.*?cancelable: (true|false),.*?detail: \{ \.\.\.range, (waitUntil):/s", $pointer, $select))->toBe(1, 'the calendar-select dispatch moved');
    expect(preg_match("/new CustomEvent\(kind === 'move' \? 'calendar-item-move' : 'calendar-item-resize', \{.*?cancelable: (true|false),.*?detail: \{ \.\.\.detail, (waitUntil):/s", $pointer, $change))->toBe(1, 'the move and resize dispatch moved');
    expect(preg_match('/let detail = \{ ([^}]*) \}/', $pointer, $detail))->toBe(1, 'the move and resize detail moved');
    expect(preg_match("/if \(kind === 'move'\) detail\.(\w+) =/", $pointer, $moveOnly))->toBe(1, 'the move-only key moved');
    expect(preg_match("/\\\$dispatch\('calendar-item-click', \{ ([^}]*) \}\)/", file_get_contents($js.'calendar.js'), $click))->toBe(1, 'the calendar-item-click dispatch moved');

    $facts = [
        'calendar-select' => ['keys' => [...collect($returns[1])->flatMap($keysOf)->all(), $select[2]], 'cancelable' => $select[1] === 'true'],
        'calendar-item-move' => ['keys' => [...$keysOf($detail[1]), $moveOnly[1], $change[2]], 'cancelable' => $change[1] === 'true'],
        'calendar-item-resize' => ['keys' => [...$keysOf($detail[1]), $change[2]], 'cancelable' => $change[1] === 'true'],
        'calendar-item-click' => ['keys' => $keysOf($click[1]), 'cancelable' => null],
    ];

    foreach ($facts as &$fact) {
        $fact['keys'] = array_values(array_unique($fact['keys']));
        sort($fact['keys']);
    }

    return $facts;
}
