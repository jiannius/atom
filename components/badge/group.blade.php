@props([
    'max' => null,
])

@if ($max)
    <div
    x-data="{
        max: @js($max),

        get badges () {
            return Array.from(this.$root.querySelectorAll(':scope > *'))
                .filter(child => (!child.hasAttribute('data-atom-badge-overflow')))
        },
    }"
    {{ $attributes->class([
        'flex items-center gap-2 flex-wrap [&_[data-atom-badge]:nth-child(n+'.($max + 1).')]:hidden',
    ]) }}
    data-atom-badge-group>
        {{ $slot }}

        <div style="display: inherit" data-atom-badge-overflow>
            <div x-show="badges.length > max" x-text="`+${badges.length - max}`" class="text-sm text-muted font-medium"></div>
        </div>
    </div>
@else
    <div class="flex items-center gap-2 flex-wrap" data-atom-badge-group>
        {{ $slot }}
    </div>
@endif
