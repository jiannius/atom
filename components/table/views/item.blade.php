@aware(['counts', 'current'])

@props([
    'value' => null,
    'label' => null,
    'count' => null,
    'href' => null,
    'newtab' => false,
])

{{-- One saved view. `value` is what the bound property holds while this view is chosen; the label is the slot or the
     `label` prop (both go through t()). An `href` makes it a link (safe_url(), as everywhere), for a list that is a page per
     view; without one it is a button that sets the bound value. --}}
@php
$value = is_scalar($value) ? (string) $value : null;
$href = safe_url($href);
$element = $href ? 'a' : 'button';

$count ??= $counts instanceof Closure ? $counts($value) : data_get($counts, $value);
$count = is_int($count) || is_float($count) ? number_format($count) : (is_scalar($count) ? (string) $count : '');

$isCurrent = $value !== null && $value === $current;

$classes = Arr::toCssClasses([
    'relative -mb-px inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2 pb-2.5 pt-3 text-sm font-medium transition-colors',
    'focus-visible:outline-offset-[-2px]',
    'border-transparent text-fg-muted hover:text-fg',
    'aria-[pressed=true]:border-fg aria-[pressed=true]:text-fg',
]);
@endphp

<{{ $element }}
{{ $attributes->class($classes)->merge([
    'href' => $href,
    'type' => $element === 'button' ? 'button' : null,
    'target' => $element === 'a' && $newtab ? '_blank' : null,
    'rel' => $element === 'a' && $newtab ? 'noopener noreferrer' : null,
]) }}
@if ($value !== null)
    x-on:click="value = {{ js($value) }}"
    x-bind:aria-pressed="value === {{ js($value) }} ? 'true' : 'false'"
@endif
@if ($isCurrent) aria-pressed="true" @else aria-pressed="false" @endif
data-atom-table-view
@if ($value !== null) data-value="{{ $value }}" @endif>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {{ t($label) }}
    @endif

    @if ($count !== '')
        <span class="text-xs font-normal tabular-nums text-fg-subtle" data-atom-table-view-count>{{ $count }}</span>
    @endif
</{{ $element }}>
