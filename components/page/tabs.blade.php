@props([
    'tabs' => [],
    'label' => null,
])

{{-- A record's sections as link tabs, with counts, straight under the page header. Each tab is
     `['label' => ..., 'href' => ..., 'count' => ..., 'current' => true]`. The current one carries aria-current="page"
     (and the underline); a tab with no href, or one safe_url() refuses, is drawn as plain text, never a link, and still
     carries it when current. A row that does not fit scrolls sideways: the current tab is scrolled into view when it appears, and an edge with more tabs past it fades (the `pageTabs` Alpine factory writes data-fade-start and data-fade-end; atom.css masks them). The -mt-1.5 pulls the tabs up under the header,
     so it assumes they sit inside <atom:page> (the 22px gap becomes the sample's 16px); elsewhere pass `class="mt-0"`. --}}
@php
$items = collect($tabs)->map(fn ($tab) => [
    'label' => data_get($tab, 'label'),
    'href' => safe_url(data_get($tab, 'href')),
    'count' => data_get($tab, 'count'),
    'current' => (bool) data_get($tab, 'current'),
]);
@endphp

<nav {{ $attributes->class('-mt-1.5') }} x-data="pageTabs" aria-label="{{ $label ? t($label) : t('Sections') }}" data-atom-page-tabs>
    {{-- pb-px: the current tab's -mb-px underline lands in the ul's padding box, which the overflow clip keeps, not on its border, which it cuts to 1px --}}
    <ul x-ref="list" class="m-0 flex list-none gap-0.5 overflow-x-auto overflow-y-hidden border-b p-0 pb-px">
        @foreach ($items as $tab)
            @php
            $classes = Arr::toCssClasses([
                'relative -mb-px inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 py-2.5 text-sm font-medium',
                'focus-visible:outline-offset-[-2px]',
                'border-fg text-fg' => $tab['current'],
                'border-transparent text-fg-muted' => ! $tab['current'],
                'hover:text-fg' => $tab['href'] && ! $tab['current'],
            ]);
            @endphp

            <li class="shrink-0">
                @if ($tab['href'])
                    <a href="{{ $tab['href'] }}" class="{{ $classes }}" wire:navigate @if ($tab['current']) aria-current="page" @endif data-atom-page-tab>
                        {{ t($tab['label']) }}
                        @if (filled($tab['count']))
                            <span class="text-xs font-normal tabular-nums text-fg-subtle" data-atom-page-tab-count>{{ $tab['count'] }}</span>
                        @endif
                    </a>
                @else
                    <span class="{{ $classes }}" @if ($tab['current']) aria-current="page" @endif data-atom-page-tab>
                        {{ t($tab['label']) }}
                        @if (filled($tab['count']))
                            <span class="text-xs font-normal tabular-nums text-fg-subtle" data-atom-page-tab-count>{{ $tab['count'] }}</span>
                        @endif
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
