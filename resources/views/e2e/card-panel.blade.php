@php
// The card's heading row and <atom:panel> over a real Livewire round trip: ?tw=floor|latest on the consumer's Tailwind, or ?tw=none for
// no stylesheet at all (the rig, to prove what atom.css alone guarantees), ?dark=1, and the fixture's own ?wide=1 ?menus=0 and ?host=grid|flex|space3|space6 (a host's own layout on the card).
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$params = [
    'wide' => request()->query('wide') === '1',
    'menus' => request()->query('menus') !== '0',
    'host' => in_array(request()->query('host'), ['grid', 'flex', 'space3', 'space6'], true) ? request()->query('host') : null,
];
@endphp

<atom:html title="E2E: Card and Panel" :vite="[]" :dark="request()->query('dark') === '1'" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="p-6">
    @livewire('atom-e2e-card-panel', $params)
</div>

@livewireScripts
</atom:html>
