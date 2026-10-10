<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
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

        // the loading spinner, the view switcher and the month grid; the rest are cloned from templates by Alpine
        expect(array_map(fn ($el) => $el->getAttribute('x-show'), $shown))->toBe(['loading', 'views.length > 1', "view === 'month'"]);

        foreach ($shown as $element) {
            expect($element->hasAttribute('x-cloak'))->toBeTrue($element->getAttribute('x-show'));
        }
    });

    it('prints one icon template for each icon an item uses, and one for the warning', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'icon' => 'calendar'],
            ['id' => 2, 'title' => 'b', 'start' => '2026-10-09', 'icon' => 'calendar'],
            ['id' => 3, 'title' => 'c', 'start' => '2026-10-09', 'icon' => '../x'],
        ]]);

        $names = array_map(fn ($template) => $template->getAttribute('data-atom-calendar-icon'), domQuery($html, '//template[@data-atom-calendar-icon]'));

        expect($names)->toBe(['calendar', 'warning']);
    });

    it('keys each icon template by its icon, so a morph cannot hand one the glyph of another', function () {
        $html = calendarRender('month="2026-10" :items="$items"', ['items' => [
            ['id' => 1, 'title' => 'a', 'start' => '2026-10-09', 'icon' => 'calendar'],
        ]]);

        $keys = array_map(
            fn ($template) => $template->getAttribute('data-atom-calendar-icon').'='.$template->getAttribute('wire:key'),
            domQuery($html, '//template[@data-atom-calendar-icon]'),
        );

        expect($keys)->toBe(['calendar=atom-calendar-icon-calendar', 'warning=atom-calendar-icon-warning']);
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

            preg_match_all('/class="([^"]*)"/', $contents, $matches);

            foreach ($matches[1] as $classes) {
                $tokens = preg_split('/\s+/', trim($classes));
                $hasWidth = (bool) array_filter($tokens, fn ($t) => preg_match('/^border(-[trblxy])?$/', $t) || str_starts_with($t, 'not-first:border-l'));

                if ($hasWidth) {
                    expect(array_filter($tokens, fn ($t) => preg_match('/^border-zinc-\d+$/', $t)))->not->toBe([], $path.': '.$classes);
                    expect(array_filter($tokens, fn ($t) => preg_match('/^dark:border-zinc-\d+$/', $t)))->not->toBe([], $path.': '.$classes);
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
