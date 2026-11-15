{{-- A fixed trail (`<atom:breadcrumbs :items>`) in both shells, on the consumer Tailwind (`node tests/consumer/build.mjs`, ?tw=floor|latest, latest by default).
     NOT persisted: each page prints its own items, which is what a host with no Livewire component does. ?shell=header draws the top-nav shell.
     /one and /new have one crumb (/new has a page.header), /two two, /deep six with long titles, /other three of another hierarchy, /hostile a javascript: URL
     in the middle and /hostile-parent one as the parent, /nolink a parent with no address (its own parent has one) and /nolink-all a trail with no address at all, /unlinked-mid one whose middle crumbs (the ones the "..." menu hides) have none. ?extra=1 puts an element beside
     the trail in the slot, so the server cannot know the bar is empty (the first frame is the browser's to get right); ?persist=1 wraps the trail in @persist, which is a mistake. /morph is a Livewire component whose own view prints the trail, so its items change on a morph
     (tests/Fixtures/BreadcrumbsStaticFixture.php). The links between the pages are wire:navigate. --}}
@php
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
$page = (string) request()->segment(4);
$carry = request()->getQueryString() ? '?'.request()->getQueryString() : '';
$url = fn (string $slug) => url('/atom/e2e/breadcrumbs-static/'.$slug);
$hostile = 'javascript:window.__pwned = 1';

$trails = [
    'one' => [['label' => 'Reports']],
    'new' => [['label' => 'Reports']],
    'two' => [['label' => 'Reports', 'href' => $url('one')], ['label' => 'Monthly']],
    'other' => [['label' => 'Settings', 'href' => $url('one')], ['label' => 'Billing', 'href' => $url('two')], ['label' => 'Cards']],
    'deep' => [
        ['label' => 'Dashboard', 'href' => $url('one')],
        ['label' => 'Sales and Marketing Operations', 'href' => $url('two')],
        ['label' => 'Regional Distribution Centres', 'href' => $url('other')],
        ['label' => 'Asia Pacific and Oceania', 'href' => $url('deep')],
        ['label' => 'Customer Accounts Receivable', 'href' => $url('new')],
        ['label' => 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur'],
    ],
    'hostile' => [
        ['label' => 'Dashboard', 'href' => $url('one')],
        ['label' => 'Hostile Crumb', 'href' => $hostile],
        ['label' => 'Regional Distribution Centres', 'href' => $url('other')],
        ['label' => 'Asia Pacific and Oceania', 'href' => $url('deep')],
        ['label' => 'Customer Accounts Receivable', 'href' => $url('new')],
        ['label' => 'Quarterly Consolidated Statement of Financial Position and Comprehensive Income for the Group'],
    ],
    'nolink' => [['label' => 'Dashboard', 'href' => $url('one')], ['label' => 'Section'], ['label' => 'Current']],
    'unlinked-mid' => [
        ['label' => 'Dashboard', 'href' => $url('one')],
        ['label' => 'Sales and Marketing Operations'],
        ['label' => 'Regional Distribution Centres'],
        ['label' => 'Asia Pacific and Oceania'],
        ['label' => 'Customer Accounts Receivable', 'href' => $url('new')],
        ['label' => 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur'],
    ],
    'nolink-all' => [['label' => 'Section'], ['label' => 'Current']],
    'hostile-parent' => [['label' => 'Dashboard', 'href' => $url('one')], ['label' => 'Hostile Parent', 'href' => $hostile], ['label' => 'Current Page']],
];
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Static trail" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
    @if (isset($trails[$page]))
        <x-slot:crumbs>
            @if (request()->query('persist'))
                @persist('breadcrumbs')
                    <atom:breadcrumbs :items="$trails[$page]" />
                @endpersist
            @else
                <atom:breadcrumbs :items="$trails[$page]" />
            @endif

            @if (request()->query('extra'))
                <span data-testid="crumbs-extra">Extra</span>
            @endif
        </x-slot:crumbs>
    @endif

    <div class="px-7">
        @if ($page === 'new')
            <atom:page.header title="Reports" />
        @endif

        @if ($page === 'morph')
            <livewire:atom-e2e-breadcrumbs-static />
        @endif

        <nav>
            @foreach (['one', 'new', 'two', 'other', 'deep', 'hostile', 'hostile-parent', 'nolink', 'nolink-all', 'unlinked-mid'] as $slug)
                <a href="{{ $url($slug).$carry }}" wire:navigate data-go="{{ $slug }}">{{ $slug }}</a>
            @endforeach
        </nav>
    </div>

    @livewireScripts
</x-dynamic-component>
