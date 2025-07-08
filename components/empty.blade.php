@props([
    'icon' => 'inbox',
    'size' => null,
    'heading' => 'No Results',
    'subheading' => 'We could not find anything.',
])

@if ($size === 'sm')
    <div class="flex items-center justify-center w-full" data-atom-empty>
        <div class="flex justify-center gap-3 py-5">
            <div class="shrink-0 flex justify-center text-zinc-400">
                <x-dynamic-component :component="'atom::icon.'.$icon" class="size-8" />
            </div>

            <div class="grow self-center">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @else
                    <div class="font-medium">{{ t($heading) }}</div>
                    <div class="text-zinc-400 font-medium">{{ t($subheading) }}</div>
                @endif
            </div>
        </div>
    </div>
@else
    <div class="flex flex-col items-center justify-center gap-3 py-8" data-atom-empty>
        <div class="text-zinc-300">
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-10" />
        </div>

        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <div class="flex flex-col items-center justify-center gap-1">
                <div class="text-lg font-medium">{{ t($heading) }}</div>
                <div class="text-zinc-400 font-medium">{{ t($subheading) }}</div>
            </div>
        @endif
    </div>
@endif
