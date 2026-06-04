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

    <div class="text-zinc-500 text-left dark:text-white truncate">
        <div class="font-medium truncate">
            {{ $name }}
        </div>

        @if ($email)
            <div class="text-sm text-zinc-500 truncate dark:text-white/50">
                {{ $email }}
            </div>
        @endif
    </div>
</div>
