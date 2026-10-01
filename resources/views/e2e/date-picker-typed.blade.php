<atom:html title="E2E: Date Picker Typed" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/DatePickerTypedFixture.php) so the E2E
     can type into the date pickers and read back what reached the server. --}}
{{-- The rig ships no Tailwind: stand in for the utilities that size the trigger, or its
     unsized icons make it ~1300px tall and the calendar opens off-screen; and for
     pointer-events-none, or a tap test can't tell an icon that lets taps through. --}}
<style>
    [data-atom-dropdown-trigger] { position: relative; }
    [data-atom-dropdown-trigger] > .absolute { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: .5rem; }
    [data-atom-dropdown-trigger] svg { width: 1.25rem; height: 1.25rem; }
    [data-atom-dropdown-trigger] .pointer-events-none { pointer-events: none; }
</style>
<div class="p-4">
    <button type="button" data-before>Before</button>
    <livewire:atom-e2e-date-picker-typed />
</div>

@livewireScripts
</atom:html>
