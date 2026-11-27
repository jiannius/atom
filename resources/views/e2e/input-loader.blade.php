@php
// In-field loader and icon positions on the consumer's Tailwind: ?tw=floor|latest, ?density=compact|comfortable
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'comfortable';
@endphp

<atom:html title="E2E: Input Loader" :vite="[]" :dark="false" :density="$density" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []" class="min-h-screen bg-white">
{{-- Hosts tests/Fixtures/InputLoaderFixture.php: table.search and the in-field icons at the standard height and taller. --}}
<div class="p-4">
    <livewire:atom-e2e-input-loader />
</div>

@livewireScripts
</atom:html>
