<?php

use Illuminate\Support\Facades\Blade;

/*
 * An inline component sits inside running text, so its output must start and end with its own tag: a newline after
 * `</a>` is a space on screen ("Settings ." instead of "Settings."), issue #179. Blade copies the file's text, so a
 * newline after plain HTML at the end of the file is output; one after a closing `@endphp` / `@endif` is not.
 */

dataset('inline components', [
    'link' => ['link', 'link', '<atom:link href="/settings">Settings</atom:link>', '</a>'],
    'link with icons' => ['link', 'link', '<atom:link href="/settings" icon="add" icon-suffix="add">Settings</atom:link>', '</a>'],
    'link with no href' => ['link', 'link', '<atom:link>Settings</atom:link>', '</a>'],
    'badge' => ['badge/index', 'badge', '<atom:badge>New</atom:badge>', '</div>'],
    'badge with a hex colour' => ['badge/index', 'badge', '<atom:badge color="#ff0000">New</atom:badge>', '</div>'],
    'badge with an icon' => ['badge/index', 'badge', '<atom:badge icon="add">New</atom:badge>', '</div>'],
    'kbd' => ['kbd/index', 'kbd', '<atom:kbd>K</atom:kbd>', '</kbd>'],
    'kbd with several keys' => ['kbd/index', 'kbd', '<atom:kbd keys="cmd+k"/>', '</span>'],
    'status' => ['status', 'status', '<atom:status>Paid</atom:status>', '</span>'],
    'button' => ['button/index', 'button', '<atom:button>Save</atom:button>', '</button>'],
    'button as a link' => ['button/index', 'button', '<atom:button href="/save">Save</atom:button>', '</a>'],
    'button with a tooltip' => ['button/index', 'button', '<atom:button tooltip="Save it">Save</atom:button>', '</div>'],
    'tooltip' => ['tooltip/index', 'tooltip', '<atom:tooltip content="Save it"><span>Save</span></atom:tooltip>', '</div>'],
    'copy' => ['copy', 'copy', '<atom:copy value="x">Copy</atom:copy>', '</div>'],
    'icon' => ['icon/add', 'icon.add', '<atom:icon.add/>', '</span>'],
]);

it('keeps text after an inline component attached to it', function (string $file, string $name, string $template, string $closing) {
    $html = renderBlade('Use '.$template.'.');

    expect($html)->toContain($closing.'.')
        ->and($html)->toStartWith('Use <');
})->with('inline components');

it('keeps text before an inline component attached to it', function (string $file, string $name, string $template) {
    $html = renderBlade('('.$template.')');

    expect($html)->toStartWith('(<')
        ->and($html)->toEndWith(')');
})->with('inline components');

it('renders nothing but its own tag around it', function (string $file, string $name, string $template, string $closing) {
    $html = renderBlade($template);

    expect($html)->toMatch('/^<\S/')
        ->and($html)->toEndWith($closing);
})->with('inline components');

it('is the same through <x-atom::...>', function () {
    $html = renderBlade('(<x-atom::link href="/settings">Settings</x-atom::link>)');

    expect($html)->toEndWith('</a>)')
        ->and($html)->toStartWith('(<a');
});

// The files that end on a `@php ... @endphp` guard: a copy with the final newline an editor adds back (a bare newline
// after the guard is swallowed; the guard is what keeps it out). The other components glue their closing tag to
// `@else` / `@endif` / `@php` instead, which the render cases above pin: they fail if a newline goes back in.
// It does not hold a file that ends in two newlines: the second one is output.
dataset('guarded files', [
    'link' => ['link', 'link', '<atom:link href="/settings">Settings</atom:link>', '</a>'],
    'status' => ['status', 'status', '<atom:status>Paid</atom:status>', '</span>'],
    'copy' => ['copy', 'copy', '<atom:copy value="x">Copy</atom:copy>', '</div>'],
]);

it('survives an editor putting a newline back at the end of the file', function (string $file, string $name, string $template, string $closing) {
    $directory = sys_get_temp_dir().'/atom-inline-'.bin2hex(random_bytes(4));
    $target = $directory.'/'.$file.'.blade.php';
    $source = file_get_contents(dirname(__DIR__, 2).'/components/'.$file.'.blade.php');

    mkdir($directory, 0777, true);
    Blade::anonymousComponentPath($directory, 'inline-copy');

    try {
        foreach (["\n", "\r\n"] as $ending) {
            file_put_contents($target, rtrim($source).$ending);

            $copy = str_replace(['<atom:'.$name, '</atom:'.$name], ['<x-inline-copy::'.$name, '</x-inline-copy::'.$name], $template);

            expect(renderBlade('Use '.$copy.'.'))->toContain($closing.'.');
        }
    } finally {
        @unlink($target);
        @rmdir($directory);
    }
})->with('guarded files');
