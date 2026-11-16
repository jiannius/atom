<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/*
 * The boxes and colour checks (hand-panel, hand-card-title, raw-control, palette-colour, shadow, arbitrary-value): what
 * their fixture hosts cannot say, the notes and the brand exemption. Each check's host is run by DesignCheckFixturesTest.
 */
describe('design-check: boxes and colour', function () {
    it('tells a hand panel with a heading row from a padded box and from a bare one', function () {
        $notes = DesignHost::notes(<<<'BLADE'
<div class="rounded-lg border bg-surface">
    <div class="border-b px-4 py-3">Lines</div>
    <div>Body</div>
</div>
<div class="rounded-lg border bg-surface p-4">
    <div class="border-b">No padding on the row</div>
</div>
<div class="rounded-lg border bg-surface">
    <p>Body</p>
</div>
BLADE, 'hand-panel');

        expect($notes[1])->toContain('with a heading row')->toContain('<atom:panel')
            ->and($notes[5])->toContain('padded')->toContain('<atom:card>')
            ->and($notes[8])->toContain('<atom:card>')->toContain('<atom:panel>')->not->toContain('heading row');
    });

    it('names the hand-built title of a card or panel', function () {
        $notes = DesignHost::notes(<<<'BLADE'
<atom:card><h3>A</h3></atom:card>
<atom:panel><atom:heading>B</atom:heading></atom:panel>
<atom:card><div><h6>C</h6></div></atom:card>
BLADE, 'hand-card-title');

        expect($notes[1])->toContain('<h3>')->toContain('heading')
            ->and($notes[2])->toContain('<atom:heading>')
            ->and($notes[3])->toContain('<h6>');
    });

    it('names the token it found, with its variants', function () {
        $palette = DesignHost::notes('<div class="md:hover:bg-red-500"></div>', 'palette-colour');
        $pair = DesignHost::notes('<div class="dark:bg-zinc-800"></div>', 'palette-colour');
        $role = DesignHost::notes('<div class="dark:bg-surface"></div>', 'palette-colour');

        expect($palette[1])->toContain('`md:hover:bg-red-500`')->toContain('role token')
            ->and($pair[1])->toContain('`dark:bg-zinc-800`')->toContain('`dark:` pair')
            ->and($role[1])->toContain('`dark:bg-surface`')->toContain('switches themes itself')
            ->and(DesignHost::notes('<div class="shadow-md"></div>', 'shadow')[1])->toBe('`shadow-md`')
            ->and(DesignHost::notes('<div class="gap-[22px]"></div>', 'arbitrary-value')[1])->toBe('`gap-[22px]`');
    });

    it('gives one finding for each token, however it is written on the element', function () {
        $source = '<div class="bg-red-500" :class="{ \'bg-red-500\': $on, \'text-white\': $on }" x-bind:class="\'bg-red-500\'" wrapper-class="text-white"></div>';

        expect(DesignHost::scanSource($source, 'palette-colour'))->toHaveCount(2);
    });

    it('leaves the brand and logo slots to the host, in either spelling, and nothing else', function (string $id, string $offender) {
        $slot = fn (string $open, string $close) => '<atom:layouts.sidebar>'.$open.$offender.$close.'</atom:layouts.sidebar>';

        expect(DesignHost::scanSource($slot('<x-slot:brand>', '</x-slot:brand>'), $id))->toBe([])
            ->and(DesignHost::scanSource($slot('<x-slot:logo>', '</x-slot:logo>'), $id))->toBe([])
            ->and(DesignHost::scanSource($slot('<x-slot name="brand">', '</x-slot>'), $id))->toBe([])
            ->and(DesignHost::scanSource($slot('<x-slot:caption>', '</x-slot:caption>'), $id))->toHaveCount(1)
            ->and(DesignHost::scanSource($offender, $id))->toHaveCount(1);
    })->with([
        'hand-panel' => ['hand-panel', '<div class="rounded-md border bg-surface"><p>x</p></div>'],
        'raw-control' => ['raw-control', '<button class="rounded-md border px-2 py-1">x</button>'],
        'palette-colour' => ['palette-colour', '<div class="bg-zinc-100"></div>'],
        'shadow' => ['shadow', '<div class="shadow-md"></div>'],
        'arbitrary-value' => ['arbitrary-value', '<div class="gap-[22px]"></div>'],
    ]);

    it('declines a card whose heading may come from elsewhere', function () {
        expect(DesignHost::scanSource('<atom:card :heading="$t"><h3>A</h3></atom:card>', 'hand-card-title'))->toBe([])
            ->and(DesignHost::scanSource('<atom:card heading="T"><h3>A</h3></atom:card>', 'hand-card-title'))->toBe([])
            ->and(DesignHost::scanSource('<atom:card><x-slot:heading>T</x-slot:heading><h3>A</h3></atom:card>', 'hand-card-title'))->toBe([])
            ->and(DesignHost::scanSource('<atom:card {{ $attributes }}><h3>A</h3></atom:card>', 'hand-card-title'))->toBe([])
            ->and(DesignHost::scanSource('<atom:card variant="chart"><h3>A</h3></atom:card>', 'hand-card-title'))->toBe([])
            ->and(DesignHost::scanSource('<atom:card><h3>A</h3></atom:card>', 'hand-card-title'))->toHaveCount(1);
    });

    it('reads the palette in the string literals of a PHP file at the line of the string, and nowhere else', function () {
        $dir = DesignHost::temp(['app/Enums/Tone.php' => <<<'PHP'
<?php

enum Tone
{
    public function classes(): string
    {
        return implode(' ', [
            'rounded-md px-2',
            'bg-red-100 text-red-800',
            "bg-{$this->name}-100",
            'shadow-md gap-[22px]',
        ]);
    }
}
PHP]);

        try {
            $result = (new DesignCheck)->scan($dir);
            $palette = array_column($result['checks']['palette-colour']['findings'], 'line');
        } finally {
            DesignHost::remove($dir);
        }

        // the palette check reads PHP; shadow and arbitrary-value read Blade only
        expect($palette)->toBe([9, 9])
            ->and($result['checks']['shadow']['findings'])->toBe([])
            ->and($result['checks']['arbitrary-value']['findings'])->toBe([]);
    });

    it('does not read a PHP string that is not a list of classes', function () {
        $dir = DesignHost::temp(['app/Words.php' => <<<'PHP'
<?php

return [
    'shadow',
    'zinc-100',
    'Use bg-red-500 for the paper',
    'text-red-500 and more',
    "bg-red-500\n",
];
PHP]);

        try {
            $findings = (new DesignCheck)->scan($dir, ['palette-colour'])['checks']['palette-colour']['findings'];
        } finally {
            DesignHost::remove($dir);
        }

        expect($findings)->toBe([]);
    });

    it('skips a view whose tags do not balance, since it cannot tell the brand slot from the rest', function (string $id, string $offender) {
        expect(DesignHost::scanSource('<atom:page>'.$offender, $id))->toBe([]);
    })->with([
        'hand-panel' => ['hand-panel', '<div class="rounded-md border bg-surface"><p>x</p></div>'],
        'palette-colour' => ['palette-colour', '<div class="bg-zinc-100"></div>'],
        'shadow' => ['shadow', '<div class="shadow-md"></div>'],
    ]);
});
