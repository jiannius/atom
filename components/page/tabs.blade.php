@props([
    'tabs' => [],
    'label' => null,
])

{{-- A record's sections as link tabs, with counts, straight under the page header. Each tab is
     [label, href, count, current]. The current one carries aria-current="page" (and the underline); a tab whose href
     safe_url() refuses is drawn as plain text, never a link. A row that does not fit scrolls sideways. --}}
@php
$items = collect($tabs)->map(fn ($tab) => [
    'label' => data_get($tab, 'label'),
    'href' => safe_url(data_get($tab, 'href')),
    'count' => data_get($tab, 'count'),
    'current' => (bool) data_get($tab, 'current'),
]);
@endphp

<nav {{ $attributes->class('-mt-1.5') }} aria-label="{{ $label ? t($label) : t('Sections') }}" data-atom-page-tabs>
    <ul class="m-0 flex list-none gap-0.5 overflow-x-auto overflow-y-hidden border-b p-0">
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
                    <span class="{{ $classes }}" data-atom-page-tab>
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
