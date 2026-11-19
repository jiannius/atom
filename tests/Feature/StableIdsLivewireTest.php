<?php

use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\StableIdsFixture;
use Livewire\Livewire;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
    Livewire::component('atom-test-stable-ids', StableIdsFixture::class);
});

/**
 * The ids a rendered fixture prints, in order: accordion toggles, the slider input and the rating track.
 */
function stableIdsIn(string $html): array
{
    preg_match_all("/toggle\\('([^']+)'\\)|<input[^>]*\\sid=\"([^\"]+)\"[^>]*type=\"range\"|<div\\s[^>]*id=\"([^\"]+)\"[^>]*data-atom-rating-track/s", $html, $m);

    return array_values(array_filter(array_merge($m[1], $m[2], $m[3])));
}

/**
 * The snapshot and rendered html of each Livewire component on a page, in order.
 *
 * @return array<int, array{snapshot: array, html: string}>
 */
function mountedComponents(string $page): array
{
    preg_match_all('/<div\b[^>]*\swire:snapshot="([^"]+)"[^>]*>(.*?)<\/div>\s*(?=<div\b[^>]*\swire:snapshot=|$)/s', $page, $m, PREG_SET_ORDER);

    return array_map(fn ($match) => [
        'snapshot' => json_decode(html_entity_decode($match[1], ENT_QUOTES), true),
        'html' => $match[0],
    ], $m);
}

describe('stableId() inside Livewire components', function () {
    it('prints the same ids when a sibling component re-renders alone as it did on the page load', function () {
        $page = renderBlade('<livewire:atom-test-stable-ids/><livewire:atom-test-stable-ids/>');
        $components = mountedComponents($page);

        expect($components)->toHaveCount(2);

        $first = stableIdsIn($components[0]['html']);
        $second = stableIdsIn($components[1]['html']);

        // one accordion toggle pair per item (2 items, 3 uses each is more than the id count: compare the set)
        expect(array_unique($first))->not->toBeEmpty()
            // the siblings share every part, so only the Livewire component tells their ids apart
            ->and(array_intersect($first, $second))->toBe([]);

        // a Livewire update re-renders one component alone, with an empty "rendered once" ledger
        [, $effects] = array_values(app('livewire')->update($components[1]['snapshot'], [], [
            ['path' => '', 'method' => 'bump', 'params' => []],
        ]));

        expect(stableIdsIn($effects['html']))->toBe($second);
    });

    it('still numbers a repeat inside one component, the same on the page load and on an update', function () {
        Livewire::component('atom-test-stable-ids-twin', TwinStableIdsFixture::class);

        $page = renderBlade('<livewire:atom-test-stable-ids-twin/><livewire:atom-test-stable-ids-twin/>');
        $components = mountedComponents($page);
        $second = stableIdsIn($components[1]['html']);

        expect(count($second))->toBe(count(array_unique($second)));

        [, $effects] = array_values(app('livewire')->update($components[1]['snapshot'], [], [
            ['path' => '', 'method' => 'bump', 'params' => []],
        ]));

        expect(stableIdsIn($effects['html']))->toBe($second);
    });
});

class TwinStableIdsFixture extends \Livewire\Component
{
    public int $renders = 0;

    public function bump(): void
    {
        $this->renders++;
    }

    public function render()
    {
        return <<<'BLADE'
            <div>
                <atom:accordion>
                    <atom:accordion.item heading="Same">a</atom:accordion.item>
                    <atom:accordion.item heading="Same">b</atom:accordion.item>
                </atom:accordion>
            </div>
        BLADE;
    }
}
