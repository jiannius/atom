@props([
    'options' => null,
])

@php
if (is_string($options)) {
    $callback = $options;
    $options = [];
} else {
    $callback = null;
    $options = is_array($options) ? $options : [];
}
@endphp

<div x-data="mention({ options: @js($options), callback: @js($callback) })" class="tiptap-mention">
    <div
    x-ref="dropdown"
    x-on:keydown.up.prevent="arrowUp()"
    x-on:keydown.down.prevent="arrowDown()"
    x-bind:class="(!show || !filteredOptions.length) && 'invisible'"
    class="fixed max-w-lg min-w-72 rounded-lg border shadow-lg z-10 bg-white dark:bg-zinc-800/50">
        <ul class="flex flex-col max-h-[300px] overflow-auto p-2">
            <template x-for="(opt, i) in filteredOptions" hidden>
                <li
                x-on:mouseover="pointer = i"
                x-on:click="select(opt)"
                x-bind:class="pointer === i && '*:bg-zinc-100 *:border-zinc-200 dark:*:bg-zinc-700 dark:*:border-transparent'"
                class="cursor-pointer">
                    <div class="rounded-md p-3 border border-transparent">
                        @if ($slot->isNotEmpty())
                            {{ $slot }}
                        @else
                            <template x-if="typeof opt === 'string'" hidden>
                                <div x-text="opt"></div>
                            </template>

                            <template x-if="typeof opt === 'object'" hidden>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <div x-show="opt.type" x-text="opt.type" class="uppercase bg-zinc-100 border rounded font-medium text-zinc-500" style="font-size: 0.65rem; padding: 1px 3px;"></div>
                                        <div x-text="opt.label" class="font-medium text-sm"></div>
                                    </div>
                                    <div x-show="opt.caption" x-text="opt.caption" class="text-sm text-zinc-500"></div>
                                </div>
                            </template>
                        @endif
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
