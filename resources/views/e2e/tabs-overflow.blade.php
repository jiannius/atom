@php
// The line tab strips (<atom:page.tabs> and <atom:tabs>) in the places a host puts them: bare, in a card (which scrolls), and in a
// wrapper that scrolls sideways. None of them may scroll vertically. ?tw=floor|latest, ?dark=1, ?bare=1 (no Tailwind at all),
// ?current=<Label> (which tab is current; default Overview), ?hidden=1 (the strips start display:none, for a test to show)
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$current = request()->query('current', 'Overview');
$tabs = collect([
    ['label' => 'Overview', 'href' => '#overview'],
    ['label' => 'Changes', 'href' => '#changes', 'count' => 14],
    ['label' => 'Activity', 'href' => '#activity'],
    ['label' => 'Domains', 'href' => '#domains'],
    ['label' => 'Settings', 'href' => '#settings'],
])->map(fn ($tab) => $tab + ['current' => $tab['label'] === $current])->all();
@endphp

<atom:html title="E2E: tab strips" :vite="[]" :dark="request()->query('dark') === '1'" :styles="request()->query('bare') ? [] : ['/atom/e2e/consumer-css/'.$tw]">
<div class="grid grid-cols-[minmax(0,1fr)] gap-8 p-6" data-testid="tab-strips" @if (request()->query('hidden')) style="display: none" @endif>
    @foreach (['bare' => '', 'card' => 'card', 'scroller' => 'scroller'] as $where => $wrap)
        <section data-testid="where-{{ $where }}" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4">
            @if ($wrap === 'card')
                <atom:card class="p-0!">
                    <atom:page.tabs :tabs="$tabs" class="mt-0" data-testid="page-tabs-{{ $where }}"/>
                    <atom:tabs data-testid="tabs-{{ $where }}">
                        @foreach ($tabs as $tab)
                            <atom:tabs.item :label="$tab['label']" :count="$tab['count'] ?? null" :current="$tab['current'] ?? false"/>
                        @endforeach
                    </atom:tabs>
                </atom:card>
            @elseif ($wrap === 'scroller')
                <div class="overflow-x-auto">
                    <atom:page.tabs :tabs="$tabs" class="mt-0" data-testid="page-tabs-{{ $where }}"/>
                    <atom:tabs data-testid="tabs-{{ $where }}">
                        @foreach ($tabs as $tab)
                            <atom:tabs.item :label="$tab['label']" :count="$tab['count'] ?? null" :current="$tab['current'] ?? false"/>
                        @endforeach
                    </atom:tabs>
                </div>
            @else
                <atom:page.tabs :tabs="$tabs" class="mt-0" data-testid="page-tabs-{{ $where }}"/>
                <atom:tabs data-testid="tabs-{{ $where }}">
                    @foreach ($tabs as $tab)
                        <atom:tabs.item :label="$tab['label']" :count="$tab['count'] ?? null" :current="$tab['current'] ?? false"/>
                    @endforeach
                </atom:tabs>
            @endif
        </section>
    @endforeach
</div>

@livewireScripts
</atom:html>
