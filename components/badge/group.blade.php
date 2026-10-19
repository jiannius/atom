@props([
    'max' => null,
])

@if ($max)
    {{-- The overflow is hidden by a rule that names this group's own limit, written here with the number: a class built from
         $max at run time is one Tailwind never sees. The rule sits before the group so it is not one of its children. --}}
    @php
    $limit = max(1, (int) $max);
    @endphp

    <style>[data-atom-badge-group][data-max="{{ $limit }}"] [data-atom-badge]:nth-child(n+{{ $limit + 1 }}) { display: none }</style>

    <div
    x-data="{
        max: @js($max),

        get badges () {
            return Array.from(this.$root.querySelectorAll(':scope > *'))
                .filter(child => (!child.hasAttribute('data-atom-badge-overflow')))
        },
    }"
    {{ $attributes->class('flex items-center gap-2 flex-wrap') }}
    data-atom-badge-group
    data-max="{{ $limit }}">
        {{ $slot }}

        <div style="display: inherit" data-atom-badge-overflow>
            <div x-show="badges.length > max" x-text="`+${badges.length - max}`" class="text-xs font-medium text-fg-muted tabular-nums"></div>
        </div>
    </div>
@else
    <div class="flex items-center gap-2 flex-wrap" data-atom-badge-group>
        {{ $slot }}
    </div>
@endif
