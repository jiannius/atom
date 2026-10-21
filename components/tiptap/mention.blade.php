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
    class="fixed max-w-lg min-w-72 rounded-panel border border-border-strong shadow-pop z-10 bg-surface-raised">
        <ul class="flex flex-col max-h-[300px] overflow-auto p-2" data-atom-scroll-region>
            <template x-for="(opt, i) in filteredOptions" hidden>
                <li
                x-on:mouseover="pointer = i"
                x-on:click="select(opt)"
                x-bind:class="pointer === i && '*:bg-fg/5'"
                class="cursor-pointer">
                    <div class="rounded-nav p-3">
                        @if ($slot->isNotEmpty())
                            {{ $slot }}
                        @else
                            <template x-if="typeof opt === 'string'" hidden>
                                <div x-text="opt"></div>
                            </template>

                            <template x-if="typeof opt === 'object'" hidden>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <div x-show="opt.type" x-text="opt.type" class="uppercase bg-surface-sunken border border-border rounded font-medium text-fg-muted text-xs px-1"></div>
                                        <div x-text="opt.label" class="font-medium text-sm"></div>
                                    </div>
                                    <div x-show="opt.caption" x-text="opt.caption" class="text-sm text-fg-muted"></div>
                                </div>
                            </template>
                        @endif
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
