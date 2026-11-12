@php
// ?tz=<zone> sets the `timezone` prop on every picker; with none (or an unknown zone) it is left unset or invalid, as the spec asks.
$tz = request()->query('tz');
$tz = is_string($tz) && preg_match('~^[A-Za-z0-9_/+:-]{1,64}$~', $tz) ? $tz : null;
@endphp

<atom:html title="E2E: Date Picker Timezone" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Pickers on a plain Alpine model (no Livewire round trip), so the spec reads the value the picker wrote. The prop under test
     is `timezone`; the spec sets the BROWSER's zone with `test.use({ timezoneId })`. --}}
{{-- The rig ships no Tailwind: stand in for the utilities that size the trigger, or its unsized icons make it ~1300px tall
     and the calendar opens off-screen. --}}
<style>
    [data-atom-dropdown-trigger] { position: relative; }
    [data-atom-dropdown-trigger] > .absolute { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: .5rem; }
    [data-atom-dropdown-trigger] svg { width: 1.25rem; height: 1.25rem; }
</style>
<div class="space-y-4 p-4">
    <div data-probe="range" x-data="{ value: @js(request()->query('range')) }">
        <atom:date-picker variant="range" :timezone="$tz" x-model="value" />
        <span data-out x-text="value"></span>
    </div>

    <div data-probe="range-time" x-data="{ value: @js(request()->query('rangetime')) }">
        <atom:date-picker variant="range" time :timezone="$tz" x-model="value" />
        <span data-out x-text="value"></span>
    </div>

    <div data-probe="date" x-data="{ value: @js(request()->query('date')) }">
        <atom:date-picker :timezone="$tz" x-model="value" />
        <span data-out x-text="value"></span>
    </div>

    <div data-probe="date-time" x-data="{ value: @js(request()->query('datetime')) }">
        <atom:date-picker time :timezone="$tz" x-model="value" />
        <span data-out x-text="value"></span>
    </div>
</div>

@livewireScripts
</atom:html>
