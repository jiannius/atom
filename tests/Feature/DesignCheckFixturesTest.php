<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\DesignChecks\Rule;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The harness every check is held to. A check adds a class in src/Services/DesignChecks/ and a directory in
 * tests/Fixtures/DesignHost/ named for its id, with `expect:` markers on the lines it must find: this file reads both
 * by itself, so a check needs no test of its own to be held to its fixture (add one only for its notes and messages).
 */
$ids = array_keys((new DesignCheck)->rules());
$checks = array_combine($ids, array_map(fn ($id) => [$id], $ids));

describe('design-check: one mini host per check', function () use ($ids, $checks) {
    it('has a directory with at least one expect marker for every check', function (string $id) {
        expect(DesignHost::path($id))->toBeDirectory()
            ->and(array_sum(DesignHost::expected(DesignHost::path($id))))->toBeGreaterThan(0);
    })->with($checks);

    it('finds exactly what the host marks, with that check alone', function (string $id) {
        $result = DesignHost::scan($id, [$id]);

        expect($result['errors'])->toBe([])
            ->and(DesignHost::found($result))->toBe(DesignHost::expected(DesignHost::path($id)));
    })->with($checks);

    it('has a host for no check that does not exist', function () use ($ids) {
        foreach (DesignHost::names() as $name) {
            if (! str_starts_with($name, '_')) {
                expect($ids)->toContain($name);
            }
        }
    });

    it('gives no finding on the clean host, whichever check runs', function () {
        $result = DesignHost::scan('_clean');

        expect($result['errors'])->toBe([])
            ->and(DesignHost::found($result))->toBe([])
            ->and($result['scanned']['blade'])->toBeGreaterThan(3)
            ->and($result['text_only'])->toBe([])
            ->and($result['markers'])->toBe([]);
    });

    it('does not need a marker for a file kind that is no marker\'s', function () {
        // the expect markers read from a host are its own: a host marker with an unknown shape is an error, not a silent pass
        $dir = sys_get_temp_dir().'/atom-design-expect-'.bin2hex(random_bytes(4));
        mkdir($dir.'/resources/views', 0777, true);
        file_put_contents($dir.'/resources/views/a.blade.php', "<p>a</p> {{-- expect: Not an id --}}\n");

        try {
            expect(fn () => DesignHost::expected($dir))->toThrow(InvalidArgumentException::class);
        } finally {
            DesignHost::remove($dir);
        }
    });

    it('reads an expect marker alone on its line as the next line, x2 as two and a comma as several', function () {
        $dir = sys_get_temp_dir().'/atom-design-expect-'.bin2hex(random_bytes(4));
        mkdir($dir.'/resources/views', 0777, true);
        mkdir($dir.'/app', 0777, true);
        file_put_contents($dir.'/resources/views/a.blade.php', "{{-- expect: one-check x2, two-check --}}\n\n<p>a</p>\n<p>b</p> {{-- expect: one-check --}}\n");
        file_put_contents($dir.'/app/A.php', "<?php\n// expect: php-check\n\$a = 1;\n\$b = 2; # expect: php-check\n");

        try {
            expect(DesignHost::expected($dir))->toBe([
                'app/A.php:3:php-check' => 1,
                'app/A.php:4:php-check' => 1,
                'resources/views/a.blade.php:3:one-check' => 2,
                'resources/views/a.blade.php:3:two-check' => 1,
                'resources/views/a.blade.php:4:one-check' => 1,
            ]);
        } finally {
            DesignHost::remove($dir);
        }
    });
});

describe('design-check: what a check says about itself', function () use ($ids, $checks) {
    $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
    $guideline = file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php');

    it('has a kebab-case id, a severity, a group, and words for title, fix, rule and detect', function (string $id) {
        $rule = (new DesignCheck)->rules()[$id];

        expect($id)->toMatch('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/')
            ->and(DesignCheck::SEVERITIES)->toContain($rule::SEVERITY)
            ->and(Rule::GROUPS)->toContain($rule::GROUP)
            ->and(trim($rule->title()))->not->toBe('')
            ->and(trim($rule->rule()))->not->toBe('')
            ->and(trim($rule->detect()))->not->toBe('')
            ->and($rule->kinds())->not->toBeEmpty()
            ->and(array_diff($rule->kinds(), ['blade', 'php']))->toBe([]);
    })->with($checks);

    it('names an atom component that exists, or a prop that a component declares', function (string $id) {
        $fix = (new DesignCheck)->rules()[$id]->fix();
        $components = __DIR__.'/../../components/';
        preg_match_all('/<atom:([a-z][a-z0-9.-]*)/', $fix, $tags);

        foreach ($tags[1] as $tag) {
            $path = $components.str_replace('.', '/', $tag);

            expect(is_file($path.'.blade.php') || is_file($path.'/index.blade.php'))->toBeTrue('<atom:'.$tag.'> is not a component');
        }

        if (! $tags[1]) {
            // no tag: a backticked `prop="value"` that some component declares in its @props
            preg_match_all('/`([a-z][a-z-]*)="[^"]*"`/', $fix, $props);
            $declared = collect(glob($components.'{,*/,*/*/}*.blade.php', GLOB_BRACE))->map(fn ($file) => file_get_contents($file))->implode("\n");

            expect($props[1])->not->toBeEmpty('the fix names no <atom:...> tag and no `prop="value"`');

            foreach ($props[1] as $prop) {
                expect($declared)->toContain("'{$prop}' =>");
            }
        }
    })->with($checks);

    it('quotes a phrase that the skill or the guideline holds', function (string $id) use ($skill, $guideline) {
        $phrase = (new DesignCheck)->rules()[$id]->rule();

        expect(str_contains($skill, $phrase) || str_contains($guideline, $phrase))->toBeTrue('"'.$phrase.'" is in neither the skill nor the guideline');
    })->with($checks);

    it('is one class for each id, found without a registry: every non-abstract Rule in the folder, and no other file', function () use ($ids) {
        $classes = [];

        foreach (glob(__DIR__.'/../../src/Services/DesignChecks/*.php') as $path) {
            $class = 'Jiannius\\Atom\\Services\\DesignChecks\\'.basename($path, '.php');

            if (is_subclass_of($class, Rule::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        $found = array_map(fn ($rule) => get_class($rule), (new DesignCheck)->rules());

        expect(array_values($found))->toEqualCanonicalizing($classes)
            ->and(count($ids))->toBe(count(array_unique($ids)));
    });

    it('does not let two checks share an id, and does not take a check with none', function () {
        $first = new class extends Rule
        {
            public const ID = 'twin';

            public const SEVERITY = 'info';

            public const GROUP = 'Actions';

            public function title(): string { return 't'; }

            public function fix(): string { return 'f'; }

            public function rule(): string { return 'r'; }

            public function detect(): string { return 'd'; }

            public function find(Jiannius\Atom\Services\DesignChecks\File $file, Jiannius\Atom\Services\DesignChecks\Context $context): iterable { return []; }
        };

        expect(fn () => (new DesignCheck([$first, $first]))->rules())->toThrow(LogicException::class, 'repeats the id `twin`');
    });
});
