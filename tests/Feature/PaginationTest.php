<?php

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Build a LengthAwarePaginator over 200 items, 20 per page (10 pages).
 */
function paginator(int $currentPage = 1, array $options = []): LengthAwarePaginator
{
    return new LengthAwarePaginator(collect(range(1, 20)), 200, 20, $currentPage, $options);
}

describe('pagination', function () {
    it('renders the nav with numbered links and the result summary', function () {
        $html = renderBlade('<atom:pagination :paginator="$p"/>', ['p' => paginator(1)]);

        expect($html)
            ->toContain('role="navigation"')
            ->toContain('Showing')
            ->toContain('wire:click="gotoPage(')
            ->toContain('wire:click="nextPage(');
    });

    it('disables the previous control on the first page', function () {
        $html = renderBlade('<atom:pagination :paginator="$p"/>', ['p' => paginator(1)]);

        expect($html)
            ->toContain('aria-disabled="true"')
            ->not->toContain('wire:click="previousPage(');
    });

    it('disables the next control on the last page', function () {
        $html = renderBlade('<atom:pagination :paginator="$p"/>', ['p' => paginator(10)]);

        expect($html)
            ->toContain('aria-disabled="true"')
            ->not->toContain('wire:click="nextPage(')
            ->toContain('wire:click="previousPage(');
    });

    it('marks the current page with aria-current', function () {
        expect(renderBlade('<atom:pagination :paginator="$p"/>', ['p' => paginator(3)]))
            ->toContain('aria-current="page"');
    });

    it('hides numbered links in simple mode but keeps prev/next', function () {
        $html = renderBlade('<atom:pagination :paginator="$p" simple/>', ['p' => paginator(5)]);

        expect($html)
            ->toContain('wire:click="previousPage(')
            ->toContain('wire:click="nextPage(')
            ->not->toContain('wire:click="gotoPage(');
    });

    it('omits the summary when summary is false', function () {
        expect(renderBlade('<atom:pagination :paginator="$p" :summary="false"/>', ['p' => paginator(2)]))
            ->not->toContain('Showing');
    });

    it('renders no page controls for a single page but keeps the summary', function () {
        // 3 items, 20 per page => a single page (hasPages() is false)
        $p = new LengthAwarePaginator(collect(range(1, 3)), 3, 20, 1);
        $html = renderBlade('<atom:pagination :paginator="$p"/>', ['p' => $p]);

        expect($html)
            ->toContain('Showing')
            ->not->toContain('wire:click="gotoPage(')
            ->not->toContain('wire:click="nextPage(')
            ->not->toContain('wire:click="previousPage(');
    });

    it('honours a custom page name in the wire:click calls', function () {
        $html = renderBlade('<atom:pagination :paginator="$p"/>', ['p' => paginator(1, ['pageName' => 'leads'])]);

        expect($html)
            ->toContain("wire:click=\"gotoPage(2, 'leads')\"")
            ->toContain("wire:click=\"nextPage('leads')\"");
    });
});
