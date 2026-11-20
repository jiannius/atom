<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Pins the rendered markup of both pagers (`<atom:pagination>` and `<atom:table.pagination>`) against files in
 * tests/Fixtures/pager-snapshots, so a change to either (or to the part they share) shows up as a diff in a snapshot.
 * Set UPDATE_PAGER_SNAPSHOTS=1 to rewrite them.
 */

/**
 * Collapse whitespace so indentation changes in a blade do not count, one tag per line so a diff reads.
 */
function normalisePager(string $html): string
{
    $html = preg_replace('/\s+/', ' ', trim($html));
    $html = preg_replace('/>\s*</', ">\n<", $html);

    return $html."\n";
}

/**
 * Compare the render to the stored snapshot, or write it when asked to.
 */
function expectPagerSnapshot(string $name, string $template, array $data): void
{
    $path = __DIR__.'/../Fixtures/pager-snapshots/'.$name.'.html';
    $html = normalisePager(renderBlade($template, $data));

    if (getenv('UPDATE_PAGER_SNAPSHOTS') || ! file_exists($path)) {
        file_put_contents($path, $html);
    }

    expect($html)->toBe(file_get_contents($path));
}

/**
 * A length-aware paginator over $total items, 20 per page.
 */
function pagerOf(int $page, int $total = 200, array $options = []): LengthAwarePaginator
{
    return new LengthAwarePaginator(collect(range(1, min(20, $total))), $total, 20, $page, $options + ['path' => '/']);
}

describe('pager snapshots', function () {
    it('pins the standalone pager', function (string $name, string $template, callable $paginator) {
        expectPagerSnapshot('pagination-'.$name, $template, ['p' => $paginator()]);
    })->with([
        'first' => ['first', '<atom:pagination :paginator="$p"/>', fn () => pagerOf(1)],
        'middle' => ['middle', '<atom:pagination :paginator="$p"/>', fn () => pagerOf(5)],
        'last' => ['last', '<atom:pagination :paginator="$p"/>', fn () => pagerOf(10)],
        'dots' => ['dots', '<atom:pagination :paginator="$p"/>', fn () => pagerOf(4, 2000)],
        'simple-middle' => ['simple-middle', '<atom:pagination :paginator="$p" simple/>', fn () => pagerOf(5)],
        'simple-first' => ['simple-first', '<atom:pagination :paginator="$p" simple/>', fn () => pagerOf(1)],
        'simple-last' => ['simple-last', '<atom:pagination :paginator="$p" simple/>', fn () => pagerOf(10)],
        'no-summary' => ['no-summary', '<atom:pagination :paginator="$p" :summary="false"/>', fn () => pagerOf(5)],
        'single-page' => ['single-page', '<atom:pagination :paginator="$p"/>', fn () => new LengthAwarePaginator(collect([1, 2, 3]), 3, 20, 1)],
        'empty' => ['empty', '<atom:pagination :paginator="$p"/>', fn () => new LengthAwarePaginator([], 0, 20, 1)],
        'page-name' => ['page-name', '<atom:pagination :paginator="$p"/>', fn () => pagerOf(2, 200, ['pageName' => 'leads'])],
        'bag' => ['bag', '<atom:pagination :paginator="$p" id="pg" class="mt-6" data-x="1"/>', fn () => pagerOf(2)],
        'simple-paginator' => ['simple-paginator', '<atom:pagination :paginator="$p" simple/>', fn () => new Paginator(collect(range(1, 20)), 20, 2, ['path' => '/'])],
    ]);

    it('pins the table pager', function (string $name, string $template, callable $paginator) {
        expectPagerSnapshot('table-'.$name, $template, ['p' => $paginator()]);
    })->with([
        'first' => ['first', '<atom:table.pagination :paginate="$p"/>', fn () => pagerOf(1)],
        'middle' => ['middle', '<atom:table.pagination :paginate="$p"/>', fn () => pagerOf(5)],
        'last' => ['last', '<atom:table.pagination :paginate="$p"/>', fn () => pagerOf(10)],
        'dots' => ['dots', '<atom:table.pagination :paginate="$p"/>', fn () => pagerOf(4, 2000)],
        'no-max-rows' => ['no-max-rows', '<atom:table.pagination :paginate="$p" :max-rows="false"/>', fn () => pagerOf(5)],
        'custom-max-rows' => ['custom-max-rows', '<atom:table.pagination :paginate="$p" :max-rows="[10, 25]"/>', fn () => pagerOf(5)],
        'not-sticky' => ['not-sticky', '<atom:table.pagination :paginate="$p" :sticky="false"/>', fn () => pagerOf(5)],
        'single-page' => ['single-page', '<atom:table.pagination :paginate="$p"/>', fn () => new LengthAwarePaginator(collect([1, 2, 3]), 3, 20, 1)],
        'empty' => ['empty', '<atom:table.pagination :paginate="$p"/>', fn () => new LengthAwarePaginator([], 0, 20, 1)],
        'page-name' => ['page-name', '<atom:table.pagination :paginate="$p"/>', fn () => pagerOf(2, 200, ['pageName' => 'leads'])],
        'bag' => ['bag', '<atom:table.pagination :paginate="$p" id="pg" class="mt-6" data-x="1"/>', fn () => pagerOf(2)],
    ]);
});
