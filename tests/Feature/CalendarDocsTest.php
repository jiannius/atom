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

    it('prints the source of the demos on the page', function () {
        $html = calendarDocsPage();

        expect($html)->toContain('x-on:calendar-item-move=&quot;add($event)&quot;')
            ->and($html)->toContain('week-start=&quot;sunday&quot;');
    });
});
