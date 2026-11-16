{{-- Logs when its own view runs, so a test can tell it from the moment its slot rendered (the action engine's first assumption). --}}
@php($GLOBALS['atomActionProbe'][] = 'view')
<div data-order-probe>{{ $slot }}</div>
