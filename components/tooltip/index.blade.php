@props([
    'interactive' => null,
    'position' => 'top',
    'align' => 'center',
    'content' => null,
    'kbd' => null,
    'toggleable' => null,
])

@if ($toggleable)
    <ui-dropdown position="{{ $position }} {{ $align }}" {{ $attributes }} data-atom-tooltip>
        {{ $slot }}

        @if ($content !== null)
            <atom:tooltip.content :$kbd>{{ t($content) }}</atom:tooltip.content>
        @endif
    </ui-dropdown>
@elseif ($content || $slot->isNotEmpty())
    <div x-data="tooltip({
        placement: @js($position.'-'.$align),
        interactive: @js($interactive),
    })" {{ $attributes }} data-atom-tooltip>
        {{ $slot }}

        @if ($content !== null)
            <atom:tooltip.content :$kbd>{{ t($content) }}</atom:tooltip.content>
        @endif
    </div>
@endif