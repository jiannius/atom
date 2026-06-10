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
