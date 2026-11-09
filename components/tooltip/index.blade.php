@props([
    'interactive' => null,
    'position' => 'top',
    'align' => 'center',
    'content' => null,
    'kbd' => null,
])

{{-- A wrapper a host makes inline (a button's tooltip is `inline-flex`): no newline after the closing tag (`</div>@endif`), or text after it gets a space (#179). --}}
@if ($content || $slot->isNotEmpty())
    <div x-data="tooltip({
        placement: @js($position.'-'.$align),
        interactive: @js($interactive),
    })" {{ $attributes }} data-atom-tooltip>
        {{ $slot }}

        @if ($content)
            <atom:tooltip.content :$kbd>{{ t($content) }}</atom:tooltip.content>
        @endif
    </div>@endif
