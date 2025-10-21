@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
    'hiddenIfEmpty' => true,
])

@if ($hiddenIfEmpty && $slot->isEmpty())

@elseif ($expandable && $heading)

<ui-disclosure
{{ $attributes->class('group/disclosure') }}
@if ($expanded === true) open @endif
data-atom-navlist-group>
    <button
    type="button"
    class="group/disclosure-button mb-[2px] flex h-10 w-full items-center rounded-lg text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 lg:h-8 dark:text-white/80 dark:hover:bg-white/[7%] dark:hover:text-white">
        <div class="ps-3 pe-4">
            <atom:icon.down class="hidden size-3! group-data-open/disclosure-button:block" />
            <atom:icon.right class="block size-3! group-data-open/disclosure-button:hidden" />
        </div>

        <span class="text-sm font-medium leading-none">{{ t($heading) }}</span>
    </button>

    <div class="relative hidden space-y-[2px] ps-7 data-open:block" @if ($expanded === true) data-open @endif>
        <div class="absolute inset-y-[3px] start-0 ms-4 w-px bg-zinc-200 dark:bg-white/30"></div>
        {{ $slot }}
    </div>
</ui-disclosure>

@elseif ($heading)
    <div>
        <div class="py-2 px-3">
            <div class="text-sm leading-none text-zinc-400">{{ t($heading) }}</div>
        </div>

        <div {{ $attributes->class(['flex flex-col']) }}>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
