@php
// Selectable tiles over a real Livewire round trip (tests/Fixtures/TilesFixture.php): ?tw=floor|latest on the consumer's Tailwind, ?dark=1,
// ?accent=%23rrggbb, ?density=compact|comfortable.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : '#4c55c9';
@endphp

<atom:html title="E2E: Tiles" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<style>:root { --color-accent: {{ $accent }}; }</style>

<div class="mx-auto max-w-3xl p-4">
    <livewire:atom-e2e-tiles />
</div>

@livewireScripts
</atom:html>
