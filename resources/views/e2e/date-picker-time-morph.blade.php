<atom:html title="E2E: Date Picker Time Morph" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts the Livewire fixture (tests/Fixtures/DatePickerTimeMorphFixture.php) so the E2E
     can drive a real Livewire re-render over the single date-picker's `time` panel while
     it is open. --}}
<div class="p-4">
    <livewire:atom-e2e-date-picker-time-morph />
</div>

@livewireScripts
</atom:html>
