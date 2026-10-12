<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Services\Docs;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    Auth::setUser(new GenericUser(['id' => 1, 'timezone' => 'UTC', 'name' => 'Tester', 'avatar' => null]));
    view()->share('errors', new ViewErrorBag);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * The page /atom/docs/calendar serves, rendered (the docs routes only exist in a local app).
 */
function calendarDocsPage(): string
{
    // the layout links to the docs index and to each component
    if (! Route::has('atom.docs.show')) {
        Route::get('/atom/docs', fn () => '')->name('atom.docs');
        Route::get('/atom/docs/{component}', fn () => '')->name('atom.docs.show');
    }

    return view('atom::docs.show', ['entry' => app(Docs::class)->component('calendar')])->render();
}

/**
 * The calendars of a page, each as the payload its browser would read.
 *
 * @return list<array<string, mixed>>
 */
function calendarDocsPayloads(string $html): array
{
    return array_map(
        fn (DOMElement $root) => json_decode($root->getAttribute('data-atom-calendar-payload'), true, flags: JSON_THROW_ON_ERROR),
        domQuery($html, '//*[@data-atom-calendar]'),
    );
}

describe('the calendar docs page', function () {
    it('is listed and renders every demo, one calendar each', function () {
        Carbon::setTestNow('2026-10-14 10:30:00');

        $demos = array_map(fn ($file) => $file->getBasename('.blade.php'), iterator_to_array(Finder::create()->files()->in(__DIR__.'/../../resources/views/docs/demos/calendar')->name('*.blade.php')->sortByName(), false));

        $html = calendarDocsPage();
        $payloads = calendarDocsPayloads($html);

        expect(app(Docs::class)->component('calendar'))->not->toBeNull()
            ->and($demos)->toBe(['basic', 'busy', 'gestures', 'sunday', 'week'])
            ->and($payloads)->toHaveCount(count($demos));

        foreach ($payloads as $payload) {
            expect($payload['window']['days'])->toHaveCount(42)
                ->and($payload['items'])->not->toBe([]);
        }
    });

    it('puts every demo item in the window it shows, so none is a blank month', function () {
        foreach (['2026-10-14 10:30:00', '2026-10-01 00:30:00', '2026-10-31 23:30:00', '2026-11-01 08:00:00', '2026-12-31 22:00:00'] as $now) {
            Carbon::setTestNow($now);

            foreach (calendarDocsPayloads(calendarDocsPage()) as $payload) {
                $first = $payload['window']['start'];
                $last = $payload['window']['end'];

                foreach ($payload['items'] as $item) {
                    $date = substr($item['start'], 0, 10);

                    expect($date >= $first && $date <= $last)->toBeTrue($now.': '.$item['title'].' on '.$date);
                }
            }
        }
    });

    it('shows each demo as its own source, with no old prop and no TagCompiler hazard in it', function () {
        $files = [
            __DIR__.'/../../resources/views/docs/demos/calendar.blade.php',
            ...array_map(fn ($file) => $file->getPathname(), iterator_to_array(Finder::create()->files()->in(__DIR__.'/../../resources/views/docs/demos/calendar')->name('*.blade.php'), false)),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            expect($contents)->not->toMatch('/\s:?(?:modes|periods)=/')
                ->and($contents)->not->toContain('x-html')
                ->and($contents)->not->toContain('{!!');

            // a `//` comment inside @php splices the component's PHP into the comment (see CLAUDE.md, TagCompiler hazards)
            preg_match_all('/@php(.*?)@endphp/s', $contents, $blocks);

            foreach ($blocks[1] as $block) {
                expect(preg_replace('/https?:\/\/\S+/', '', $block))->not->toContain('//');
            }
        }
    });

    it('sets what the week demo says it sets: its slot length, the time it opens at, and the now line', function () {
        Carbon::setTestNow('2026-10-14 10:30:00');

        $week = array_values(array_filter(calendarDocsPayloads(calendarDocsPage()), fn ($payload) => in_array('Quick check-in', array_column($payload['items'], 'title'), true)));

        expect($week)->toHaveCount(1)
            ->and($week[0]['config'])->toMatchArray(['slotDuration' => 15, 'scrollTime' => '07:30', 'nowIndicator' => true]);
    });

    it('prints the source of the demos on the page', function () {
        $html = calendarDocsPage();

        expect($html)->toContain('x-on:calendar-item-move=&quot;add($event)&quot;')
            ->and($html)->toContain('week-start=&quot;sunday&quot;');
    });
});

describe('the calendar section of the README', function () {
    /**
     * The README's Calendar section, up to the next top-level heading.
     */
    function calendarReadmeSection(): string
    {
        $readme = file_get_contents(__DIR__.'/../../README.md');
        $start = strpos($readme, "\n## Calendar\n");

        expect($start)->not->toBeFalse();

        return substr($readme, $start, strpos($readme, "\n## ", $start + 5) - $start);
    }

    it('lists every prop of the component, by its tag name', function () {
        preg_match('/@props\(\[(.*?)\]\)/s', file_get_contents(__DIR__.'/../../components/calendar/index.blade.php'), $props);
        preg_match_all("/^\s*'([A-Za-z]+)' =>/m", $props[1], $names);

        $section = calendarReadmeSection();

        expect($names[1])->toHaveCount(16);

        foreach ($names[1] as $name) {
            expect($section)->toContain('| `'.str($name)->kebab().'` |');
        }
    });

    it('has a Quick start whose tag is the wired fixture and whose class has every method the tag calls', function () {
        $section = calendarReadmeSection();

        preg_match('/```blade\n(<atom:calendar\n.*?)\/>\n```/s', $section, $blade);
        preg_match('/```php\n(use App.*?\n)```/s', $section, $php);

        // the tag, line by line (but for its closing), is what the served fixture's `?wired=1` branch has
        $tag = array_map('trim', explode("\n", rtrim($blade[1])));
        $fixture = file_get_contents(__DIR__.'/../Fixtures/calendar.blade.php');
        $wired = substr($fixture, strpos($fixture, '@if ($wired)'), strpos($fixture, '@else') - strpos($fixture, '@if ($wired)'));

        expect($tag)->toContain('<atom:calendar');

        foreach (array_filter($tag, fn ($line) => $line !== '<atom:calendar') as $line) {
            expect($wired)->toContain("\n        ".$line."\n");
        }

        // a save that does not return true is a refusal: each handler that saves says so
        foreach (['calendar-select', 'calendar-item-move', 'calendar-item-resize'] as $event) {
            $line = collect($tag)->first(fn ($line) => str_starts_with($line, 'x-on:'.$event.'='));

            expect(str_contains((string) $line, '.then((saved) => saved || Promise.reject())'))->toBeTrue('the README tag line for '.$event.' turns a save that did not return true into a rejection');
        }

        // the skill shows the same handlers
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');

        foreach (array_filter($tag, fn ($line) => str_starts_with($line, 'x-on:')) as $line) {
            expect($skill)->toContain($line);
        }

        // every method the tag calls is declared by the README's class, with the parameters the fixture's has
        preg_match_all('/\$wire\.(\w+)\(([^)]*)\)/', $blade[1], $calls);

        expect(array_unique($calls[1]))->toEqualCanonicalizing(['create', 'reschedule', 'edit']);

        foreach (array_unique($calls[1]) as $method) {
            expect(preg_match('/public function '.$method.'\(([^)]*)\): (void|bool)/', $php[1], $declared))->toBe(1, 'the README class has no public '.$method.'()');

            // what the tag turns into a refusal when it is not true is what the class returns once it has saved
            expect($declared[2])->toBe($method === 'edit' ? 'void' : 'bool', $method.'() of the README class');

            preg_match_all('/\$(\w+)/', $declared[1], $names);

            expect((string) (new ReflectionMethod(Jiannius\Atom\Tests\Fixtures\CalendarFixture::class, $method))->getReturnType())->toBe($declared[2], $method.'() of the fixture returns what the README class does');

            expect(array_map(fn (ReflectionParameter $parameter) => $parameter->getName(), (new ReflectionMethod(Jiannius\Atom\Tests\Fixtures\CalendarFixture::class, $method))->getParameters()))->toBe($names[1], $method.'() of the fixture');
        }
    });

    // Livewire catches a ValidationException on the server and answers 200, so `$wire.method()` resolves with null: only a throw, an abort or a failed
    // request rejects (and shows Livewire's error modal). The README once said validation rejects, so a failed save left the item where it was dropped.
    it('refuses a save by what the method returns, and never says validation rejects the promise', function () {
        $section = calendarReadmeSection();

        expect($section)->toContain('saved || Promise.reject()')
            ->and($section)->toContain('does not reject it')
            ->and($section)->toContain('error modal')
            ->and($section)->toContain('return `false` to refuse, `true` once saved')
            ->and($section)->toContain('it must still `return true`')
            ->and($section)->not->toMatch('/(?:Livewire rejects|rejects the promise)[^.]*validat/i')
            ->and($section)->not->toContain('refuse by validating')
            ->and($section)->not->toContain('fails validation, aborts');
    });

    it('states, for each prop, the default the component has', function () {
        $defaults = calendarPropDefaults();

        // the Default column of the Props table, by the prop's tag name, with the backticks taken off
        $section = calendarReadmeSection();
        $table = substr($section, strpos($section, "\n### Props\n"));
        $table = substr($table, 0, strpos($table, "\n### ", 5) ?: null);

        preg_match_all('/^\| `([a-z-]+)` \| (.+?) \| /m', $table, $rows, PREG_SET_ORDER);

        $documented = [];

        foreach ($rows as $row) {
            $documented[$row[1]] = str_replace('`', '', $row[2]);
        }

        expect($defaults)->toHaveCount(16)->and($documented)->toHaveCount(16);

        foreach ($defaults as $name => $default) {
            $cell = $documented[(string) str($name)->kebab()] ?? null;

            // a default is written the way a reader reads it: on and off for a flag, none for null (or what a null falls back to, in words), and an array as its short syntax
            $expected = match (true) {
                $default === true => ['on'],
                $default === false => ['off'],
                $default === null => ['none', 'first of views'],
                is_array($default) => ['['.implode(', ', array_map(fn ($value) => var_export($value, true), $default)).']'],
                default => [(string) $default],
            };

            expect($cell)->toBeIn($expected, 'the README default of `'.$name.'`');
        }
    });

    it('states, for each event, the detail keys and the cancelability the code has', function () {
        $section = calendarReadmeSection();
        $table = substr($section, strpos($section, "\n### Events\n"));
        $table = substr($table, 0, strpos($table, "\n### ", 5) ?: null);

        preg_match_all('/^\| `(calendar-[a-z-]+)` \| `\{ (.*?) \}` \| (.+) \|$/m', $table, $rows, PREG_SET_ORDER);

        $facts = calendarEventFacts();
        $documented = [];

        foreach ($rows as [, $event, $keys, $description]) {
            $keys = array_map('trim', explode(',', $keys));
            sort($keys);

            $documented[$event] = ['keys' => $keys, 'description' => $description];
        }

        expect(array_keys($documented))->toEqualCanonicalizing(array_keys($facts));

        foreach ($facts as $event => $fact) {
            expect($documented[$event]['keys'])->toBe($fact['keys'], 'the detail keys of '.$event);

            // an event is documented as Not cancelable, or as Cancelable, when the code says; the click leaves it to Alpine and makes no claim
            $says = str_contains($documented[$event]['description'], 'Not cancelable') ? false : (str_contains($documented[$event]['description'], 'Cancelable') ? true : null);

            expect($says)->toBe($fact['cancelable'], 'the cancelability of '.$event);
        }
    });

    it('lists the legacy events the tag guard throws for, and the guard lists every one the upgrade table does', function () {
        $section = calendarReadmeSection();
        $guard = file_get_contents(__DIR__.'/../../components/calendar/index.blade.php');

        preg_match('/\$legacyEvents = \[(.*?)\n\];/s', $guard, $block);
        preg_match_all("/^\s*'(calendar-[a-z-]+)' =>/m", $block[1], $guarded);

        $table = substr($section, strpos($section, '**Events.**'));
        $table = substr($table, 0, strpos($table, "\n\n", strpos($table, '| Old | Now |') ?: 0) ?: null);

        preg_match_all('/`(calendar-(?:date|event)-[a-z]+|calendar-changed|calendar-mounted)`/', $table, $listed);

        expect($guarded[1])->toHaveCount(7)->and(array_values(array_unique($listed[1])))->toEqualCanonicalizing($guarded[1]);
    });

    it('documents each event the calendar dispatches, and the guarded legacy props', function () {
        $section = calendarReadmeSection();

        foreach (['calendar-select', 'calendar-item-move', 'calendar-item-resize', 'calendar-item-click'] as $event) {
            expect($section)->toContain('| `'.$event.'` |');
        }

        expect($section)->toContain('### Upgrading from the event-calendar wrapper')
            ->and($section)->toContain('`Calendar::fromLocal()`')
            ->and($section)->toContain('waitUntil')
            ->and($section)->toContain('`#[Renderless]`')
            ->and($section)->toContain('Passing `periods` throws')
            ->and($section)->toContain('Passing `modes` throws');
    });

    it('dispatches only events the README names', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/alpinejs/calendar.js').file_get_contents(__DIR__.'/../../resources/js/alpinejs/calendar/pointer.js');

        preg_match_all("/(?:CustomEvent\(|\\\$dispatch\()\s*(?:kind === 'move' \? )?'(calendar-[a-z-]+)'(?: : '(calendar-[a-z-]+)')?/", $js, $found);

        $dispatched = array_values(array_unique(array_filter([...$found[1], ...$found[2]])));
        sort($dispatched);

        expect($dispatched)->toBe(['calendar-item-click', 'calendar-item-move', 'calendar-item-resize', 'calendar-select']);
    });
});
