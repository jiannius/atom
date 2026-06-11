@props([
    'interactive' => null,
    'position' => 'top',
    'align' => 'center',
    'content' => null,
    'kbd' => null,
])

@if ($content || $slot->isNotEmpty())
    <div x-data="tooltip({
        placement: @js($position.'-'.$align),
        interactive: @js($interactive),
    })" {{ $attributes }} data-atom-tooltip>
        {{ $slot }}

        @if ($content)
            <atom:tooltip.content :$kbd>{{ t($content) }}</atom:tooltip.content>
        @endif
    </div>
@endif
