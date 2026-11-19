<?php

use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;
use Livewire\Livewire;

// Livewire's morph-aware precompiler (SupportMorphAwareBladeCompilation) puts `<!--[if BLOCK]-->` after every `@if` and
// `<!--[if ENDBLOCK]-->` after every `@endif`, but its regex starts with `\B@`, so a directive glued straight after a word character
// (`...@endif@endif`) is never seen. The cell's second `@endif` then had no end marker, and Alpine morph threw
// `Block.appendChild` on a null end when a filled cell became the empty mark (issue #263).

/** The markers a Livewire component prints for the given Blade. */
function cellMarkers(string $blade): array
{
    $component = new class extends Component {
        use WithTable;

        public static string $blade = '';

        public function render()
        {
            return '<table><tbody><tr>'.static::$blade.'</tr></tbody></table>';
        }
    };

    $component::$blade = $blade;

    $html = Livewire::test($component::class)->html();

    return [
        'block' => substr_count($html, '<!--[if BLOCK]><![endif]-->'),
        'end' => substr_count($html, '<!--[if ENDBLOCK]><![endif]-->'),
        'html' => $html,
    ];
}

it('prints as many end markers as start markers for a cell', function (string $blade) {
    $markers = cellMarkers($blade);

    expect($markers['block'])->toBeGreaterThan(0)
        ->and($markers['end'])->toBe($markers['block']);
})->with([
    'a filled cell' => '<atom:table.cell>Ada</atom:table.cell>',
    'an empty cell (the missing-value mark)' => '<atom:table.cell></atom:table.cell>',
    'an empty cell with a filler of its own' => '<atom:table.cell filler="n/a"></atom:table.cell>',
    'an empty cell with no filler' => '<atom:table.cell :filler="false"></atom:table.cell>',
    'an empty spacer cell' => '<atom:table.cell colspan="2"></atom:table.cell>',
    'a checkbox cell' => '<atom:table.cell checkbox="1"></atom:table.cell>',
]);

it('still draws the mark, the filler and the content as before', function () {
    $empty = cellMarkers('<atom:table.cell></atom:table.cell>')['html'];
    $filler = cellMarkers('<atom:table.cell filler="n/a"></atom:table.cell>')['html'];
    $filled = cellMarkers('<atom:table.cell>Ada</atom:table.cell>')['html'];

    expect(domQuery($empty, '//td//*[@data-atom-missing]'))->toHaveCount(1)
        ->and(domQuery($filler, '//td')[0]->textContent)->toContain('n/a')
        ->and(domQuery($filled, '//td')[0]->textContent)->toContain('Ada')
        ->and(domQuery($filled, '//td//*[@data-atom-missing]'))->toBeEmpty();
});

it('has no block directive glued after a word character in any component or view', function () {
    $pattern = '/\w@(if|endif|else|elseif|foreach|endforeach|forelse|endforelse|for|endfor|while|endwhile|unless|endunless|isset|endisset|empty|endempty|switch|endswitch|auth|endauth|guest|endguest|can|endcan|cannot|endcannot)\b/';
    $found = [];

    foreach (['components', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$dir, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match($pattern, $line)) {
                    $found[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname()).':'.($number + 1);
                }
            }
        }
    }

    expect($found)->toBe([]);
});
