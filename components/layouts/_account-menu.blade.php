@props([
    'avatar' => null,
    'name' => null,
    'email' => null,
    'initials' => null,
    'profile' => null,
])

{{-- The shells' account menu: the profile card (or the host's `profile` slot), a divider and the host's `dropdown` items. --}}
<atom:menu class="w-[220px]" popover>
    @if (! is_null($profile))
        {{ $profile }}
    @else
        <div class="flex items-center gap-2 truncate p-1.5" data-atom-account-card>
            <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-bg text-xs font-semibold text-neutral-fg" data-atom-account-avatar>
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover">
                @else
                    {{ $initials }}
                @endif
            </div>

            <div class="min-w-0 text-start leading-tight">
                <div class="truncate text-sm font-medium">
                    {{ $name }}
                </div>
                <div class="truncate text-xs text-fg-subtle">
                    {{ $email }}
                </div>
            </div>
        </div>
    @endif

    <atom:separator class="my-2"/>

    {{ $slot }}
</atom:menu>
