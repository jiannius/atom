<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Services\CalendarItem;
use Jiannius\Atom\Services\Carbon;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    // wall-clock expectations below are the viewer's own; tests for another zone sign one in themselves
    Auth::setUser(new GenericUser(['id' => 1, 'timezone' => 'UTC']));
});

afterEach(function () {
    Carbon::setTestNow();
    config()->set('atom.calendar.colors', []);
});

/**
 * Render <atom:calendar> and return its markup.
 *
 * @param  array<string, mixed>  $data
 */
function calendarRender(string $attributes = '', array $data = [], string $slot = ''): string
{
    // the title's date picker is an atom:input, which reads $errors
    view()->share('errors', new ViewErrorBag);

    return renderBlade('<atom:calendar '.$attributes.'>'.$slot.'</atom:calendar>', $data);
}

/**
 * The calendar root element of a render.
 */
function calendarRoot(string $html): DOMElement
{
    $roots = domQuery($html, '//*[@data-atom-calendar]');

    expect($roots)->toHaveCount(1);

    return $roots[0];
}

/**
 * The payload the browser would read off the root.
 *
 * @return array<string, mixed>
 */
function calendarPayload(string $html): array
{
    return json_decode(calendarRoot($html)->getAttribute('data-atom-calendar-payload'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Render inside a Livewire component whose public properties are given, so a bound anchor is read from it.
 *
 * @param  array<string, mixed>  $properties
 */
function calendarRenderBound(array $properties, string $attributes = 'wire:model="date"'): string
{
    $component = new class($properties)
    {
        public function __construct(array $properties)
        {
            foreach ($properties as $key => $value) {
                $this->{$key} = $value;
            }
        }

        public function getName(): string
        {
            return 'calendar-test';
        }
    };

    return withLivewireContext($component, fn () => calendarRender($attributes));
}

/**
 * The calendar's own sources: its Blade files and its browser modules.
 *
 * @return array<string, string>
 */
function calendarSources(): array
{
    $sources = [];

    foreach (Finder::create()->files()->in(__DIR__.'/../../components/calendar')->name('*.blade.php') as $file) {
        $sources['components/calendar/'.$file->getRelativePathname()] = $file->getContents();
    }

    $sources['resources/js/alpinejs/calendar.js'] = file_get_contents(__DIR__.'/../../resources/js/alpinejs/calendar.js');

    foreach (Finder::create()->files()->in(__DIR__.'/../../resources/js/alpinejs/calendar')->name('*.js') as $file) {
        $sources['resources/js/alpinejs/calendar/'.$file->getRelativePathname()] = $file->getContents();
    }

    return $sources;
}


describe('calendar component payload', function () {
    it('prints the payload as one attribute the browser reads back as it was built', function () {
        Carbon::setTestNow('2026-10-09 10:30:00');

        $html = calendarRender('month="2026-10" :items="[[\'id\' => 1, \'title\' => \'Dentist\', \'start\' => \'2026-10-09T09:30\', \'color\' => \'red\']]"');
        $payload = calendarPayload($html);

        expect($payload)->toHaveKeys(['anchor', 'today', 'timezone', 'window', 'labels', 'config', 'items'])
            ->and($payload)->not->toHaveKey('now')
            ->and($payload['anchor'])->toBe('2026-10-01')
            ->and($payload['today'])->toBe('2026-10-09')
            ->and($payload['window']['days'])->toHaveCount(42)
            ->and($payload['items'])->toHaveCount(1)
            ->and($payload['items'][0])->toMatchArray(['id' => 1, 'title' => 'Dentist', 'start' => '2026-10-09T09:30', 'color' => 'red']);
    });

    it('cannot be broken out of by an item title', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 'x', 'title' => '"><script>window.hacked = 1</script><b x="', 'start' => '2026-10-09'],
        ]]);

        expect(domQuery($html, '//script'))->toBe([])
            ->and(domQuery($html, '//b'))->toBe([])
            ->and(calendarPayload($html)['items'][0]['title'])->toBe('"><script>window.hacked = 1</script><b x="');
    });

    it('survives a title that is not valid UTF-8', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 'x', 'title' => "bad \xff title", 'start' => '2026-10-09'],
        ]]);

        expect(calendarPayload($html)['items'][0]['title'])->toContain('bad')->toContain('title');
    });

    it('calls the items closure with the window as app-timezone instants', function () {
        Auth::setUser(new GenericUser(['id' => 1, 'timezone' => 'Asia/Kuala_Lumpur']));

        $seen = [];

        $items = function ($from, $to) use (&$seen) {
            $seen = [$from->toIso8601String(), $to->toIso8601String()];

            return [['id' => 1, 'title' => 'a', 'start' => '2026-10-09']];
        };

        $html = calendarRender('month="2026-10" :items="$items"', ['items' => $items]);

        // 2026-09-28 00:00 and 2026-11-08 23:59:59 in Kuala Lumpur, as UTC
        expect($seen)->toBe(['2026-09-27T16:00:00+00:00', '2026-11-08T15:59:59+00:00'])
            ->and(calendarPayload($html)['items'])->toHaveCount(1);
    });

    it('takes the anchor from the bound property', function () {
        $payload = calendarPayload(calendarRenderBound(['date' => '2026-02-14']));

        expect($payload['anchor'])->toBe('2026-02-14')
            ->and($payload['window']['start'])->toBe('2026-01-26');
    });

    it('takes the anchor from a bound Carbon property, as the local date of that instant', function () {
        $date = Carbon::parse('2026-02-14 20:00:00', 'UTC');

        // the viewer in these tests is in UTC (see beforeEach)
        expect(calendarPayload(calendarRenderBound(['date' => $date]))['anchor'])->toBe('2026-02-14');

        Auth::setUser(new GenericUser(['id' => 1, 'timezone' => 'Asia/Kuala_Lumpur']));

        expect(calendarPayload(calendarRenderBound(['date' => $date]))['anchor'])->toBe('2026-02-15');
    });

    it('falls back to the current month for an anchor a client could send', function (mixed $sent) {
        Carbon::setTestNow('2026-10-09 10:30:00');

        expect(calendarPayload(calendarRenderBound(['date' => $sent]))['anchor'])->toBe('2026-10-09');
    })->with([
        'month 13' => '2026-13',
        'text' => 'x',
        'five digit year' => '99999-01',
        'empty' => '',
        'february 31' => '2026-02-31',
        'year zero' => '0000-01-01',
        'an array' => [['2026-10-01']],
        'null' => [null],
    ]);

    it('names the bound property for the browser and never prints wire:model', function () {
        $root = calendarRoot(calendarRenderBound(['date' => '2026-10-09'], 'wire:model.live="date"'));

        expect($root->getAttribute('data-atom-calendar-model'))->toBe('date');

        foreach ($root->attributes as $attribute) {
            expect($attribute->name)->not->toStartWith('wire:model');
        }
    });

    it('is static without a bound property', function () {
        $root = calendarRoot(calendarRender('month="2026-03"'));

        expect($root->hasAttribute('data-atom-calendar-model'))->toBeFalse()
            ->and(calendarPayload(calendarRender('month="2026-03"'))['anchor'])->toBe('2026-03-01');
    });

    it('carries the config the props set', function () {
        $config = calendarPayload(calendarRender(':views="[\'month\', \'week\']" view="week" week-start="sunday" :max="5" time-format="H:i" date-format="d/m/Y" selectable :draggable="false"'))['config'];

        expect($config)->toMatchArray([
            'views' => ['month', 'week'],
            'view' => 'week',
            'weekStart' => 'sunday',
            'max' => 5,
            'timeFormat' => 'H:i',
            'dateFormat' => 'd/m/Y',
            'selectable' => true,
            'draggable' => false,
            'resizable' => true,
        ]);
    });

    it('shows three rows a day by default, and every row for max false, 0 or none', function () {
        expect(calendarPayload(calendarRender())['config']['max'])->toBe(3)
            ->and(calendarPayload(calendarRender(':max="false"'))['config']['max'])->toBeNull()
            ->and(calendarPayload(calendarRender(':max="0"'))['config']['max'])->toBeNull()
            ->and(calendarPayload(calendarRender('max="0"'))['config']['max'])->toBeNull()
            ->and(calendarPayload(calendarRender('max="false"'))['config']['max'])->toBeNull()
            ->and(calendarPayload(calendarRender('max="none"'))['config']['max'])->toBeNull()
            ->and(calendarPayload(calendarRender(':max="5"'))['config']['max'])->toBe(5);
    });

    it('drops an unsafe link and keeps the item', function () {
        $item = calendarPayload(calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'href' => 'javascript:alert(1)'],
        ]]))['items'][0];

        expect($item['href'])->toBeNull()->and($item['title'])->toBe('a');
    });

    it('lets a host item through unchanged in every other key', function () {
        $item = calendarPayload(calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 'abc', 'title' => 'Hex', 'start' => '2026-10-09', 'color' => '#FF8800', 'danger' => true, 'href' => '/tasks/1'],
        ]]))['items'][0];

        expect($item)->toMatchArray(['id' => 'abc', 'color' => '#ff8800', 'danger' => true, 'href' => '/tasks/1']);
    });
});

describe('calendar component markup', function () {
    it('prints x-data as a constant, whatever the month', function () {
        $first = calendarRender('month="2026-10"');
        $second = calendarRender('month="2027-02" :items="$items"', ['items' => [['id' => 1, 'title' => 'a', 'start' => '2027-02-10']]]);

        expect(calendarRoot($first)->getAttribute('x-data'))->toBe('calendar')
            ->and(calendarRoot($second)->getAttribute('x-data'))->toBe('calendar')
            ->and(calendarRoot($first)->getAttribute('x-data'))->toBe(calendarRoot($second)->getAttribute('x-data'));
    });

    it('prints no id, random or otherwise, that a morph could key on', function () {
        $html = calendarRender('month="2026-10"');

        expect(calendarRoot($html)->hasAttribute('id'))->toBeFalse()
            ->and(domQuery($html, '//*[@id]'))->toBe([]);
    });

    it('is the same markup render after render', function () {
        $once = calendarRender('month="2026-10"');
        $twice = calendarRender('month="2026-10"');

        expect($once)->toBe($twice);
    });

    it('keeps the engine in wire:ignore, and the payload on the morphable root', function () {
        $html = calendarRender('month="2026-10"');

        $body = domQuery($html, '//*[@data-atom-calendar-body]');

        expect($body)->toHaveCount(1)
            ->and($body[0]->hasAttribute('wire:ignore'))->toBeTrue();

        // the payload is read off the root, which is not inside any wire:ignore
        expect(domQuery($html, '//*[@data-atom-calendar-payload]/ancestor-or-self::*[@*[name()="wire:ignore"]]'))->toBe([])
            ->and(domQuery($html, '//*[@data-atom-calendar-weeks]/ancestor::*[@*[name()="wire:ignore"]]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-calendar-popover]/ancestor::*[@*[name()="wire:ignore"]]'))->toHaveCount(1);
    });

    it('starts a selection from the three places empty space is: the month\'s weeks, the time grid\'s body and the all-day row', function () {
        $html = calendarRender(':views="[\'month\', \'week\']" selectable month="2026-10"');

        foreach (['month' => 'data-atom-calendar-weeks', 'grid' => 'data-atom-calendar-grid-body', 'allday' => 'data-atom-calendar-allday-area'] as $area => $marker) {
            $starts = domQuery($html, '//*[@'.$marker.']');

            expect($starts)->toHaveCount(1)
                ->and($starts[0]->getAttribute('x-on:pointerdown'))->toBe('selectStart($event, \''.$area.'\')')
                // inside the engine's body, which a morph does not reach, so a gesture in progress keeps its nodes
                ->and(domQuery($html, '//*[@'.$marker.']/ancestor::*[@*[name()="wire:ignore"]]'))->toHaveCount(1);
        }
    });

    it('gives each day of the all-day row its date, which is what a selection in the row reads off it', function () {
        $html = calendarRender(':views="[\'week\']" selectable month="2026-10"');

        // a cell is drawn by x-for, one for each column, so the template's one cell is what is read
        $dates = array_map(fn ($cell) => $cell->getAttribute('x-bind:data-date'), domQuery($html, '//*[@data-atom-calendar-allday-bg]//*[@*[name()="x-bind:data-date"]]'));

        expect($dates)->toBe(['column.date']);
    });

    it('draws a selection, and the placeholder that stands for it, in the all-day row without taking a pointer', function () {
        $html = calendarRender(':views="[\'week\']" selectable month="2026-10"');
        $over = domQuery($html, '//*[@data-atom-calendar-allday-over]');

        expect($over)->toHaveCount(1)
            ->and($over[0]->getAttribute('aria-hidden'))->toBe('true')
            ->and(domQuery($html, '//*[@data-atom-calendar-allday-over]//*[@data-atom-calendar-selection]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-calendar-allday-over]//*[@data-atom-calendar-pending]'))->toHaveCount(1);
    });

    it('names the placeholder a selection leaves while the host creates what it asked for', function () {
        $ui = calendarPayload(calendarRender('month="2026-10"'))['labels']['ui'];

        expect($ui['creating'])->toBe('Creating...');
    });

    it('names what is said while an item that was moved or resized waits for the host', function () {
        $ui = calendarPayload(calendarRender('month="2026-10"'))['labels']['ui'];

        expect($ui['saving'])->toBe('Saving...');
    });

    it('keeps the host slot outside wire:ignore, so it morphs like the rest of the page', function () {
        $html = calendarRender('month="2026-10"', slot: '<button data-host-slot>Add</button>');

        expect(domQuery($html, '//*[@data-host-slot]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-host-slot]/ancestor::*[@*[name()="wire:ignore"]]'))->toBe([]);
    });

    it('renders no toolbar controls that would navigate when there is nowhere to go', function () {
        $static = calendarRender('month="2026-10"');
        $bound = calendarRenderBound(['date' => '2026-10-09']);

        expect($static)->not->toContain('x-on:click="prev()"')->not->toContain('x-on:click="next()"')->not->toContain('x-on:click="today()"')
            ->and($bound)->toContain('x-on:click="prev()"')->toContain('x-on:click="next()"')->toContain('x-on:click="today()"');
    });

    it('labels the icon buttons, so a screen reader hears more than "button"', function () {
        $html = calendarRenderBound(['date' => '2026-10-09']);

        $previous = domQuery($html, '//button[@*[name()="x-on:click"][.="prev()"]]');
        $next = domQuery($html, '//button[@*[name()="x-on:click"][.="next()"]]');

        expect($previous[0]->getAttribute('aria-label'))->toBe('Previous')
            ->and($next[0]->getAttribute('aria-label'))->toBe('Next');
    });

    it('prints the clock on an attribute of its own, so a render seconds later leaves the payload alone', function () {
        $attributes = 'month="2026-10" :items="[[\'id\' => 1, \'title\' => \'Dentist\', \'start\' => \'2026-10-09T09:30\']]"';

        Carbon::setTestNow('2026-10-09 10:30:00');
        $first = calendarRoot(calendarRender($attributes));

        Carbon::setTestNow('2026-10-09 10:30:09');
        $second = calendarRoot(calendarRender($attributes));

        expect($first->getAttribute('data-atom-calendar-now'))->toBe('2026-10-09T10:30:00')
            ->and($second->getAttribute('data-atom-calendar-now'))->toBe('2026-10-09T10:30:09')
            ->and($second->getAttribute('data-atom-calendar-payload'))->toBe($first->getAttribute('data-atom-calendar-payload'));
    });

    it('cloaks every x-show element that is on the page before Alpine starts, so none flashes', function () {
        $html = calendarRender('month="2026-10"');
        $shown = domQuery($html, '//*[@x-show][not(ancestor::template)]');

        // the title's year (a week in Chinese has none), the loading spinner, the view switcher, the month grid and the week and day grid; the rest are cloned from templates by Alpine
        expect(array_map(fn ($el) => $el->getAttribute('x-show'), $shown))->toBe(['title.year', 'loading', 'views.length > 1', "view === 'month'", "view !== 'month'"]);

        foreach ($shown as $element) {
            expect($element->hasAttribute('x-cloak'))->toBeTrue($element->getAttribute('x-show'));
        }
    });

    it('prints one icon template for each icon an item uses, and one each for the warning and for the spinner of an item waiting for the host', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'icon' => 'calendar'],
            ['id' => 2, 'title' => 'b', 'start' => '2026-10-09', 'icon' => 'calendar'],
            ['id' => 3, 'title' => 'c', 'start' => '2026-10-09', 'icon' => '../x'],
        ]]);

        $names = array_map(fn ($template) => $template->getAttribute('data-atom-calendar-icon'), domQuery($html, '//template[@data-atom-calendar-icon]'));

        expect($names)->toBe(['calendar', 'warning', 'loading']);
    });

    it('keys each icon template by its icon, so a morph cannot hand one the glyph of another', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'icon' => 'calendar'],
        ]]);

        $keys = array_map(
            fn ($template) => $template->getAttribute('data-atom-calendar-icon').'='.$template->getAttribute('wire:key'),
            domQuery($html, '//template[@data-atom-calendar-icon]'),
        );

        expect($keys)->toBe(['calendar=atom-calendar-icon-calendar', 'warning=atom-calendar-icon-warning', 'loading=atom-calendar-icon-loading']);
    });

    it('prints the colour palette with every preset, and lets config add or override one', function () {
        $presets = json_decode(calendarRoot(calendarRender())->getAttribute('data-atom-calendar-palette'), true);

        expect(array_keys($presets))->toBe(['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'black', 'zinc', 'gray']);

        foreach ($presets as $classes) {
            expect(array_keys($classes))->toBe(['tint', 'accent', 'dot']);
        }

        config()->set('atom.calendar.colors', [
            'brand' => ['tint' => 'bg-brand-100', 'accent' => 'border-brand-500', 'dot' => 'bg-brand-500', 'ignored' => 'x', 'bad' => ['y']],
            'red' => ['dot' => 'bg-rose-500'],
            'broken' => 'not an array',
        ]);

        $merged = json_decode(calendarRoot(calendarRender())->getAttribute('data-atom-calendar-palette'), true);

        expect($merged['brand'])->toBe(['tint' => 'bg-brand-100', 'accent' => 'border-brand-500', 'dot' => 'bg-brand-500'])
            ->and($merged['red']['dot'])->toBe('bg-rose-500')
            ->and($merged['red']['tint'])->toBe($presets['red']['tint'])
            ->and($merged)->not->toHaveKey('broken');
    });

    it('only puts a colour name in the palette that an item is allowed to carry', function () {
        config()->set('atom.calendar.colors', [
            'brand' => ['dot' => 'bg-brand-500'],
            'Brand' => ['dot' => 'bg-shouting'],
            'has space' => ['dot' => 'bg-space'],
            '9lives' => ['dot' => 'bg-digit'],
            '#ff0000' => ['dot' => 'bg-hex'],
            'with_underscore' => ['dot' => 'bg-underscore'],
            5 => ['dot' => 'bg-int'],
        ]);

        $palette = json_decode(calendarRoot(calendarRender())->getAttribute('data-atom-calendar-palette'), true);

        expect(array_keys($palette))->toBe(CalendarItem::colors())
            ->and($palette)->toHaveKey('brand')
            ->and($palette)->not->toHaveKeys(['Brand', 'has space', '9lives', '#ff0000', 'with_underscore', '5']);
    });

    it('accepts a configured colour name on an item', function () {
        config()->set('atom.calendar.colors', ['brand' => ['dot' => 'bg-brand-500']]);

        $item = calendarPayload(calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'color' => 'brand'],
        ]]))['items'][0];

        expect($item['color'])->toBe('brand');
    });

    it('lists the toolbar views from the config, for the browser to switch between', function () {
        expect(calendarPayload(calendarRender(':views="[\'month\', \'week\', \'day\']"'))['config']['views'])->toBe(['month', 'week', 'day'])
            ->and(calendarPayload(calendarRender())['config']['views'])->toBe(['month']);
    });

    it('carries the translated labels the browser prints, and translates them', function () {
        $english = calendarPayload(calendarRender('month="2026-10"'))['labels'];

        expect($english['ui'])->toMatchArray(['today' => 'Today', 'more' => '+:count more', 'item' => ':count item', 'items' => ':count items'])
            ->and($english['weekdays'][0]['short'])->toBe('Mon');

        app()->setLocale('zh');

        expect(calendarPayload(calendarRender('month="2026-10"'))['labels']['weekdays'][0]['long'])->toBe('星期一');
    });
});

describe('calendar source guards', function () {
    it('has no path from a value to markup: no x-html, no unescaped Blade, no innerHTML', function () {
        $sources = calendarSources();

        expect($sources)->not->toBe([]);

        foreach ($sources as $path => $contents) {
            // one needle at a time: toContain() with several needles passes when only some of them are missing
            foreach (['x-html', '{!!', 'innerHTML', 'insertAdjacentHTML', 'outerHTML'] as $forbidden) {
                expect(str_contains($contents, $forbidden))->toBeFalse($path.' uses '.$forbidden);
            }
        }
    });

    it('keeps chip text to zinc, with red for danger only', function () {
        // a hue on a tint of itself fails contrast (yellow on yellow-100 is 1.8:1), so colour is decoration
        // and the text stays neutral
        $offenders = [];

        foreach (['components/calendar/chip.blade.php', 'components/calendar/index.blade.php'] as $path) {
            $contents = file_get_contents(__DIR__.'/../../'.$path);

            preg_match_all('/(?:^|[\s\'"])((?:[a-z-]+:)*text-([a-z]+)-(\d+))(?=[\s\'"])/m', $contents, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                [, $token, $hue, $step] = $match;

                $allowed = $hue === 'zinc' || ($hue === 'red' && in_array($step, ['700', '300'], true));

                if (! $allowed) {
                    $offenders[] = $path.' → '.$token;
                }
            }
        }

        expect(array_values(array_unique($offenders)))->toBe([]);
    });

    it('draws every border and divider with a light and a dark colour on the same element', function () {
        // Tailwind v4 draws an uncoloured border in currentColor; PaletteTest holds the whole package to this,
        // and this pins that it is looking at the calendar's own files
        foreach (calendarSources() as $path => $contents) {
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            // the class attributes, which include the strings an x-bind:class chooses between (a colour may carry an opacity)
            preg_match_all('/class="([^"]*)"/', $contents, $matches);

            foreach ($matches[1] as $classes) {
                $tokens = array_filter(preg_split('/[\s\'?]+/', trim($classes)));
                $hasWidth = (bool) array_filter($tokens, fn ($t) => preg_match('/^border(-[trblxy])?$/', $t) || str_starts_with($t, 'not-first:border-l'));

                if ($hasWidth) {
                    expect(array_filter($tokens, fn ($t) => preg_match('/^border-zinc-\d+(\/\d+)?$/', $t)))->not->toBe([], $path.': '.$classes);
                    expect(array_filter($tokens, fn ($t) => preg_match('/^dark:border-zinc-\d+(\/\d+)?$/', $t)))->not->toBe([], $path.': '.$classes);
                }
            }
        }
    });
});

describe('calendar replaces the third-party wrapper', function () {
    it('leaves no trace of it in the dependencies, the build or the render', function () {
        $root = __DIR__.'/../../';

        expect(file_get_contents($root.'package.json'))->not->toContain('event-calendar')
            ->and(file_get_contents($root.'package.json'))->not->toContain('svelte')
            ->and(file_get_contents($root.'package-lock.json'))->not->toContain('event-calendar')
            ->and(file_get_contents($root.'package-lock.json'))->not->toContain('"node_modules/svelte"')
            ->and(file_get_contents($root.'vite.config.js'))->not->toContain('calendar')
            ->and(file_get_contents($root.'dist/manifest.json'))->not->toContain('calendar')
            ->and(file_exists($root.'resources/css/calendar.css'))->toBeFalse()
            ->and(file_exists($root.'components/calendar.blade.php'))->toBeFalse()
            ->and(glob($root.'dist/assets/calendar-*'))->toBe([]);

        $html = calendarRender();

        foreach (['calendar.css', '<link', 'event-calendar', 'ec-'] as $forbidden) {
            expect(str_contains($html, $forbidden))->toBeFalse($forbidden);
        }
    });

    it('leaves no import of it in the browser modules', function () {
        foreach (glob(__DIR__.'/../../resources/js/{,alpinejs/,alpinejs/calendar/}*.js', GLOB_BRACE) as $file) {
            expect(str_contains(file_get_contents($file), '@event-calendar'))->toBeFalse($file);
            expect(str_contains(file_get_contents($file), 'dayjs/plugin/utc'))->toBeFalse($file);
        }
    });
});

describe('calendar accessibility markup', function () {
    /**
     * Render a bound calendar inside a component that has the given Livewire id.
     */
    function calendarRenderAs(string $id, string $attributes = 'wire:model="date"'): string
    {
        $component = new class($id)
        {
            public string $date = '2026-10-09';

            public function __construct(private string $id) {}

            public function getName(): string
            {
                return 'calendar-test';
            }

            public function getId(): string
            {
                return $this->id;
            }
        };

        return withLivewireContext($component, fn () => calendarRender($attributes));
    }

    /**
     * The uid a calendar of this name prints.
     */
    function calendarUidOf(string $name): string
    {
        return calendarRoot(calendarRender('name="'.$name.'"'))->getAttribute('data-atom-calendar-uid');
    }

    it('builds its ids from a name: slugged with a hash of the name, and the same on every render', function () {
        $first = calendarRoot(calendarRender('name="Team plan"'));
        $second = calendarRoot(calendarRender('name="Team plan"'));

        expect($first->getAttribute('data-atom-calendar-uid'))->toBe('atom-calendar-team-plan-'.substr(md5('Team plan'), 0, 8))
            ->and($second->getAttribute('data-atom-calendar-uid'))->toBe($first->getAttribute('data-atom-calendar-uid'));
    });

    it('tells apart names that make the same slug', function () {
        expect(calendarUidOf('Team plan'))->not->toBe(calendarUidOf('team-plan'))
            ->and(calendarUidOf('Team plan'))->toStartWith('atom-calendar-team-plan-')
            ->and(calendarUidOf('team-plan'))->toStartWith('atom-calendar-team-plan-');
    });

    it('tells apart names that have nothing for a slug to keep, and does not leave an id with nothing after `atom-calendar-`', function () {
        $uids = array_map(calendarUidOf(...), ['日本語', '团队', '!!!', '???', '1', 'n1']);

        expect($uids)->toHaveCount(count(array_unique($uids)));

        foreach ($uids as $uid) {
            expect($uid)->toMatch('/^atom-calendar-([a-z0-9-]+-)?[0-9a-f]{8}$/');
        }

        // a name that is all punctuation is the hash alone
        expect(calendarUidOf('!!!'))->toBe('atom-calendar-'.substr(md5('!!!'), 0, 8));
    });

    it('never gives a name the id the engine numbers a calendar with no name by', function () {
        // the engine's are `atom-calendar-n<k>`, with nothing after the number; a name always ends in its hash
        foreach (['1', 'n1', 'n', '2'] as $name) {
            expect(calendarUidOf($name))->not->toMatch('/^atom-calendar-n?\d+$/');
        }
    });

    it('keeps the engine fallback out of the names\' way in the browser module', function () {
        expect(file_get_contents(__DIR__.'/../../resources/js/alpinejs/calendar.js'))
            ->toContain("'atom-calendar-n' + (++instances)")
            ->not->toContain("'atom-calendar-' + (++instances)");
    });

    it('does not print the name as an attribute of its own', function () {
        expect(calendarRoot(calendarRender('name="Team plan"'))->hasAttribute('name'))->toBeFalse();
    });

    it('derives the ids of a bound calendar from its component and its property, so a render gives the same ones', function () {
        $one = calendarRoot(calendarRenderAs('abc123'))->getAttribute('data-atom-calendar-uid');

        expect($one)->toStartWith('atom-calendar-')
            ->and(calendarRoot(calendarRenderAs('abc123'))->getAttribute('data-atom-calendar-uid'))->toBe($one)
            // another component with the same property is another calendar
            ->and(calendarRoot(calendarRenderAs('def456'))->getAttribute('data-atom-calendar-uid'))->not->toBe($one);
    });

    it('lets two calendars of one component tell apart by their property', function () {
        $first = calendarRoot(calendarRenderAs('abc123', 'wire:model="date"'))->getAttribute('data-atom-calendar-uid');
        $second = calendarRoot(calendarRenderAs('abc123', 'wire:model="other"'))->getAttribute('data-atom-calendar-uid');

        expect($first)->not->toBe($second);
    });

    it('leaves a calendar with neither a name nor a property to take its id from the engine, which numbers them in the order the page starts them', function () {
        expect(calendarRoot(calendarRender())->hasAttribute('data-atom-calendar-uid'))->toBeFalse();
    });

    it('puts no id of its own on the root, which would be a morph key', function () {
        expect(calendarRoot(calendarRender('name="x"'))->hasAttribute('id'))->toBeFalse()
            ->and(calendarRoot(calendarRenderAs('abc123'))->hasAttribute('id'))->toBeFalse();
    });

    it('carries the words the keyboard and the names need, translated', function () {
        $ui = calendarPayload(calendarRender())['labels']['ui'];

        expect($ui)->toHaveKeys(['view', 'open_day', 'colors', 'overdue', 'all_day', 'choose_date', 'range'])
            ->and($ui['open_day'])->toContain(':date')
            // an item over more than a day is named from its first end to its last
            ->and($ui['range'])->toContain(':start')->toContain(':end')
            ->and($ui['colors'])->toHaveKeys(['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'black', 'zinc', 'gray'])
            ->and($ui['colors']['blue'])->toBe('Blue');
    });

    it('makes the header of a day in the week view a button, named for the day', function () {
        $heads = domQuery(calendarRender(), '//*[@data-atom-calendar-column-head]');

        $buttons = array_values(array_filter($heads, fn ($head) => $head->nodeName === 'button'));

        expect($buttons)->toHaveCount(1)
            ->and($buttons[0]->getAttribute('type'))->toBe('button')
            ->and($buttons[0]->getAttribute('x-on:click'))->toBe('showDay(column.date)')
            ->and($buttons[0]->getAttribute('x-bind:aria-label'))->toContain('open_day')
            // it is a button only outside the day view
            ->and($buttons[0]->parentNode->getAttribute('x-for'))->toContain("view === 'day' ? [] : grid.columns");
    });

    it('makes the header of the one day of the day view a group named for the day, which is no button and not disabled', function () {
        $heads = array_values(array_filter(domQuery(calendarRender(), '//*[@data-atom-calendar-column-head]'), fn ($head) => $head->nodeName !== 'button'));

        expect($heads)->toHaveCount(1)
            ->and($heads[0]->nodeName)->toBe('div')
            ->and($heads[0]->getAttribute('role'))->toBe('group')
            ->and($heads[0]->getAttribute('tabindex'))->toBe('-1')
            ->and($heads[0]->getAttribute('x-bind:aria-label'))->toBe('longDate(column.date)')
            ->and($heads[0]->hasAttribute('x-bind:aria-disabled'))->toBeFalse()
            ->and($heads[0]->parentNode->getAttribute('x-for'))->toContain("view === 'day' ? grid.columns : []");
    });

    it('says a column holds the blocks that are in it, and the live title is written from a text that moves only with the title', function () {
        $html = calendarRender();

        expect(domQuery($html, '//*[@data-atom-calendar-column]')[0]->getAttribute('x-bind:aria-label'))->toBe('dayLabel(column, column.blocks.length)')
            ->and(domQuery($html, '//*[@role="status"]')[0]->getAttribute('x-text'))->toBe('liveTitle');
    });

    it('has a second polite status for a selection that waits for the host, apart from the title\'s, with the text only the engine writes', function () {
        $html = calendarRender();
        $statuses = domQuery($html, '//*[@role="status"]');
        $announce = domQuery($html, '//*[@data-atom-calendar-announce]');

        expect($statuses)->toHaveCount(2)
            ->and($statuses[0]->getAttribute('x-text'))->toBe('liveTitle')
            ->and($statuses[0]->hasAttribute('data-atom-calendar-announce'))->toBeFalse()
            ->and($announce)->toHaveCount(1)
            ->and($announce[0]->getAttribute('role'))->toBe('status')
            ->and($announce[0]->getAttribute('aria-live'))->toBe('polite')
            ->and($announce[0]->getAttribute('x-text'))->toBe('announcement')
            ->and($announce[0]->hasAttribute('data-atom-calendar-sr'))->toBeTrue()
            ->and($announce[0]->textContent)->toBe('');
    });

    it('carries the probe the engine asks whether the month is narrow, hidden from assistive tech and not by the hidden attribute', function () {
        $probe = domQuery(calendarRender(), '//*[@data-atom-calendar-narrow]');

        expect($probe)->toHaveCount(1)
            ->and($probe[0]->getAttribute('aria-hidden'))->toBe('true')
            ->and($probe[0]->hasAttribute('hidden'))->toBeFalse()
            ->and($probe[0]->parentNode->hasAttribute('data-atom-calendar-body'))->toBeTrue();

        // the stylesheet shows it inside the same container rule that hides the titles, and nowhere else
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toMatch('/\[data-atom-calendar-narrow\]\s*\{\s*display:\s*none;/')
            ->and($css)->toMatch('/@container \(max-width: 600px\) \{\s*\[data-atom-calendar-narrow\]\s*\{\s*display:\s*block;/');
    });

    it('keys a day\'s and a "+N more"\'s aria-expanded off what opened the list', function () {
        $html = calendarRender();

        expect(domQuery($html, '//*[@data-atom-calendar-day]')[0]->getAttribute('x-bind:aria-expanded'))->toContain("popoverSource === 'day'")
            ->and(domQuery($html, '//*[@data-atom-calendar-more]')[0]->getAttribute('x-bind:aria-expanded'))->toContain("popoverSource === 'more'")
            ->and(domQuery($html, '//*[@data-atom-calendar-more]')[1]->getAttribute('x-bind:aria-expanded'))->toContain("popoverSource === 'more'");
    });

    it('names every kind of item in full, and gives none a tooltip beside its name', function () {
        $chips = file_get_contents(__DIR__.'/../../components/calendar/chip.blade.php');

        preg_match_all('/<template x-if="chip.kind === \'(bar|dot|block)\'">(.*?)<\/template>/s', $chips, $kinds, PREG_SET_ORDER);

        expect($kinds)->toHaveCount(3);

        foreach ($kinds as [, $kind, $markup]) {
            expect($markup)->toContain('x-bind:aria-label="itemLabel(chip)"', $kind)
                // a `title` beside a name is read out as well: a tooltip is put on by tooltip(), and only when the title shown is cut
                ->not->toContain('x-bind:title=');
        }

        expect(substr_count($chips, 'tooltip($el)'))->toBe(2);
    });

    it('lets the pointer pick every kind of item up, and pull the end of the two that have one, with a handle that is for the pointer alone', function () {
        $chips = file_get_contents(__DIR__.'/../../components/calendar/chip.blade.php');

        preg_match_all('/<template x-if="chip.kind === \'(bar|dot|block)\'">(.*?)<\/template>/s', $chips, $kinds, PREG_SET_ORDER);

        foreach ($kinds as [, $kind, $markup]) {
            expect($markup)->toContain('x-on:pointerdown="itemPress(chip, $event)"', $kind)
                ->toContain('x-bind:data-draggable="canDrag(chip)', $kind)
                // a link is dragged by the engine, never by the browser: its own drag would take the pointer from the gesture
                ->toContain('x-bind:draggable="canDrag(chip) ? \'false\' : null"', $kind);

            // an end to pull: a bar's right edge and a block's bottom edge; a dot is a point in time and has none
            if ($kind === 'dot') {
                expect($markup)->not->toContain('data-atom-calendar-handle');
            } else {
                expect($markup)->toContain('<span x-show="canResize(chip)" aria-hidden="true" data-atom-calendar-handle="end"></span>', $kind);
            }
        }

        expect($kinds)->toHaveCount(3);
    });

    it('gives each day of the month one roving tab stop, a key handler, and what a day with a list says about it', function () {
        $cell = domQuery(calendarRender(), '//*[@data-atom-calendar-day]')[0];

        expect($cell->getAttribute('x-bind:tabindex'))->toBe('day.date === tabDate ? 0 : -1')
            ->and($cell->getAttribute('x-on:keydown'))->toBe('dayKey(day, $event)')
            ->and($cell->getAttribute('x-bind:aria-haspopup'))->toContain('dialog')
            ->and($cell->getAttribute('x-bind:aria-controls'))->toContain("'-popover'");
    });

    it('names the grids, the body and the list by ids the same uid builds', function () {
        $html = calendarRender();

        expect(domQuery($html, '//*[@role="grid"]')[0]->getAttribute('x-bind:aria-labelledby'))->toBe("uid + '-title'")
            ->and(domQuery($html, '//*[@data-atom-calendar-grid]')[0]->getAttribute('x-bind:aria-labelledby'))->toBe("uid + '-title'")
            ->and(domQuery($html, '//*[@data-atom-calendar-body]')[0]->getAttribute('x-bind:id'))->toBe("uid + '-body'")
            ->and(domQuery($html, '//*[@data-atom-calendar-popover]')[0]->getAttribute('x-bind:id'))->toBe("uid + '-popover'");
    });

    it('renders the same markup twice, so no id in it is random', function () {
        Carbon::setTestNow('2026-10-09 10:30:00');

        expect(calendarRender('name="a"'))->toBe(calendarRender('name="a"'))
            ->and(calendarRenderAs('abc123'))->toBe(calendarRenderAs('abc123'));
    });
});

describe('calendar refuses the props of the wrapper it replaced', function () {
    /**
     * Render with attributes and return the exception that stopped it (the view engine may wrap it), or null.
     */
    function calendarRenderFailure(string $attributes): ?Throwable
    {
        try {
            calendarRender($attributes);
        } catch (Throwable $e) {
            while ($e->getPrevious() && ! $e instanceof LogicException) {
                $e = $e->getPrevious();
            }

            return $e;
        }

        return null;
    }

    it('throws for each prop the old wrapper took and the new one has no meaning for', function (string $attributes, string $prop) {
        $failure = calendarRenderFailure($attributes);

        expect($failure)->toBeInstanceOf(LogicException::class)
            ->and($failure->getMessage())->toContain('`'.$prop.'`')
            ->and($failure->getMessage())->toContain('`views`')
            ->and($failure->getMessage())->toContain('Upgrading from the event-calendar wrapper');
    })->with([
        'modes, bound' => [':modes="[\'calendar\']"', 'modes'],
        'modes, plain' => ['modes="calendar"', 'modes'],
        'periods, bound' => [':periods="[\'month\', \'week\']"', 'periods'],
        'periods, plain' => ['periods="month"', 'periods'],
    ]);

    it('throws for an x-model on the tag, which the root no longer honours, and points at wire:model', function (string $attribute) {
        $failure = calendarRenderFailure($attribute);

        expect($failure)->toBeInstanceOf(LogicException::class)
            ->and($failure->getMessage())->toContain('`'.explode('=', $attribute)[0].'`')
            ->and($failure->getMessage())->toContain('`wire:model="date"`')
            ->and($failure->getMessage())->toContain('Upgrading from the event-calendar wrapper');
    })->with([
        'plain' => ['x-model="date"'],
        'with a modifier' => ['x-model.live="date"'],
        'with an argument' => ['x-model:value="date"'],
    ]);

    it('throws for a listener of each event the old wrapper dispatched, in both spellings, and names the replacement', function (string $old, string $replacement) {
        foreach (['x-on:', '@'] as $prefix) {
            foreach (['', '.window', '.prevent', '.document.prevent'] as $modifier) {
                $attribute = $prefix.$old.$modifier;
                $failure = calendarRenderFailure($attribute.'="open($event)"');

                expect($failure)->toBeInstanceOf(LogicException::class, $attribute)
                    ->and($failure->getMessage())->toContain('`'.$attribute.'`')
                    ->and($failure->getMessage())->toContain($replacement)
                    ->and($failure->getMessage())->toContain('Upgrading from the event-calendar wrapper');
            }
        }
    })->with([
        'date clicked' => ['calendar-date-clicked', '`calendar-select`'],
        'date selected' => ['calendar-date-selected', '`calendar-select`'],
        'event clicked' => ['calendar-event-clicked', '`calendar-item-click`'],
        'event dropped' => ['calendar-event-dropped', '`calendar-item-move`'],
        'event resized' => ['calendar-event-resized', '`calendar-item-resize`'],
        'changed' => ['calendar-changed', '`items` closure'],
        'mounted' => ['calendar-mounted', 'no calendar object'],
    ]);

    it('lists every legacy attribute at once', function () {
        $failure = calendarRenderFailure('modes="calendar" x-model="date" x-on:calendar-changed="fetch($event)" @calendar-event-clicked="open($event)"');

        expect($failure->getMessage())->toContain('`modes`')
            ->toContain('`x-model`')
            ->toContain('`x-on:calendar-changed`')
            ->toContain('`@calendar-event-clicked`');
    });

    it('does not throw for wire:model, data attributes, the new events, or a name that only starts like an old one', function (string $attributes) {
        expect(calendarRender($attributes))->toContain('data-atom-calendar');
    })->with([
        'wire:model' => ['wire:model="date"'],
        'wire:model.live' => ['wire:model.live="date"'],
        'data attributes named after old events' => ['data-calendar-changed="1" data-x-model="a" data-calendar-event-clicked="1"'],
        'x-on, the new events' => ['x-on:calendar-select="a" x-on:calendar-item-move="b" x-on:calendar-item-resize.window="c" x-on:calendar-item-click.prevent="d"'],
        'at, the new events' => ['@calendar-select="a" @calendar-item-move="b" @calendar-item-click.window="d"'],
        'an old name as a prefix of a longer one' => ['x-on:calendar-changed-by-me="a" x-on:calendar-mounted-later="b"'],
        'other x- attributes' => ['x-data="{ a: 1 }" x-on:click="a" x-bind:class="b" x-modelable="c"'],
    ]);

    it('names both when both are passed', function () {
        $failure = calendarRenderFailure(':modes="[\'calendar\']" :periods="[\'month\']"');

        expect($failure->getMessage())->toContain('`modes`')->toContain('`periods`');
    });

    it('does not throw for any prop it does take, or for the old name that is still a prop', function () {
        $html = calendarRender('name="team" month="2026-10" :views="[\'month\', \'week\', \'day\']" view="week" week-start="sunday" :weekend="[\'friday\', \'saturday\']" :max="2" time-format="H:i" date-format="d/m/Y" selectable :draggable="false" :resizable="false" :slot-duration="15" scroll-time="08:00" :now-indicator="false"');

        expect(calendarPayload($html)['config'])->toMatchArray(['views' => ['month', 'week', 'day'], 'view' => 'week', 'weekStart' => 'sunday', 'max' => 2]);
    });

    it('does not mistake an attribute that only starts like one for a legacy prop', function () {
        expect(calendarRender('data-modes="x" data-periods="y"'))->toContain('data-atom-calendar');
    });

    it('is not passed by any docs page, demo or e2e page of the package', function () {
        $root = __DIR__.'/../../';

        foreach (Finder::create()->files()->in([$root.'resources/views', $root.'tests/Fixtures'])->name('*.blade.php') as $file) {
            preg_match_all('/<atom:calendar\b(?:[^>]|=>)*?(?:\s:?(?:modes|periods)\b)/s', $file->getContents(), $matches);

            expect($matches[0])->toBe([], $file->getRelativePathname());
        }
    });
});
