@props([
    'variant' => null,
    'subtle' => false,
])

@if ($variant === 'card')
    <atom:card :subtle="$subtle" inset>
        <div class="flex flex-col divide-y dark:divide-zinc-700 [&>[data-atom-checkbox]]:py-3 [&>[data-atom-checkbox]]:px-5">
            {{ $slot }}
        </div>
    </atom:card>
@else
    <div {{ $attributes->class(['group/group flex flex-col gap-2 [&>[data-atom-heading]]:mb-1']) }}>
        {{ $slot }}
    </div>
@endif
