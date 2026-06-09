<?php

use Illuminate\Support\Facades\Blade;
use Jiannius\Atom\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

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
