@props([
    'name' => auth()->user()->name,
    'initials' => auth()->user()->initials(),
    'avatar' => auth()->user()->avatar ?? null,
    'email' => auth()->user()->email,
])

<div {{ $attributes->class(['flex items-center gap-2']) }} data-atom-profile>
    <div class="shrink-0 size-10 text-lg bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
        @if ($avatar)
            <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-full object-cover">
        @else
            {{ $initials }}
        @endif
    </div>

    <div class="mx-2 text-zinc-500 text-left dark:text-white truncate">
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
