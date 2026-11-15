@props(['variant' => null])
{{-- A host component with a `variant` of its own: it wraps its slot, and must not decide whether the options in it are tiles. --}}
<div data-wrapper data-variant="{{ $variant }}">{{ $slot }}</div>
