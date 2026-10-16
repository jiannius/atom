@php
// ?tw=floor or ?tw=latest opts in to the stylesheet tests/consumer/build.mjs compiled with a consumer's Tailwind;
// without it the page has atom's own CSS only, which is what every other e2e page gets.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
@endphp

<atom:html title="E2E: Consumer Tailwind" :vite="[]" :dark="false" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
{{-- A bare utility class on a plain element: its computed display is flex only when a Tailwind build is on the page. --}}
<div class="flex" data-testid="utility">Utility</div>
<div class="hidden" data-testid="hidden">Hidden</div>

{{-- A real atom component, so the build that scanned atom's blades is what styles it --}}
<atom:button data-testid="button">Button</atom:button>

@livewireScripts
</atom:html>
