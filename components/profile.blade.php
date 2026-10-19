@props([
    'name' => auth()->user()?->name,
    'avatar' => auth()->user()?->avatar,
    'email' => auth()->user()?->email,
    'size' => null,
])

<div {{ $attributes->class(['flex items-center gap-3']) }} data-atom-profile>
    <div class="shrink-0">
        <atom:avatar :name="$name" :src="$avatar" :size="$size" />
    </div>

    <div class="min-w-0 text-left truncate">
        <div class="font-medium text-fg truncate">
            {{ $name }}
        </div>

        @if ($email)
            <div class="text-xs text-fg-muted truncate">
                {{ $email }}
            </div>
        @endif
    </div>
</div>
