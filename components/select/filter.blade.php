@props([
    'icon' => null,
    'label' => null,
    'options' => null,
    'filters' => null,
    'multiple' => false,
    'searchable' => false,
    'clearable' => true,
])

@php
$options = is_array($options) || $options instanceof \Illuminate\Support\Collection
    ? collect($options)->map(fn ($option) => is_enum($option) ? $option->option() : $option)->toArray()
    : $options;

$classes = Arr::toCssClasses([
    'inline-flex items-center gap-2',
    'min-h-10 py-1.5 appearance-none rounded-lg shadow-xs outline-offset-1',
    'text-zinc-700 dark:text-zinc-200 text-left pr-3',
    'bg-white dark:bg-white/10',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    'border border-zinc-200 dark:border-white/10',
    $icon ? 'pl-10' : 'pl-3',
]);
@endphp

<div
x-data="select({
    options: @js($options),
    filters: @js($filters),
    multiple: @js($multiple),
    searchable: @js($searchable),
})"
x-modelable="selectValue"
x-on:open="visible = true"
x-on:close="visible = false"
x-on:keydown.up.prevent.stop="keyUp()"
x-on:keydown.down.prevent.stop="keyDown()"
class="group/select"
{{ $attributes->except('class') }}>
    <atom:dropdown>
        <button type="button" {{ $attributes->class($classes)->only('class') }}>
            @if ($icon)
                <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                    <x-dynamic-component :component="'atom::icon.'.$icon" />
                </div>
            @endif

            <div class="font-medium">
                {{ t($label) }}
            </div>

            <div x-show="!loading" class="shrink-0">
                <atom:icon.dropdown />
            </div>

            <div x-show="loading" class="shrink-0 flex items-center justify-center">
                <atom:icon.loading class="size-4" />
            </div>

            @if ($multiple === true)
                <template x-if="!isEmpty" hidden>
                    <div class="shrink-0 bg-zinc-100 dark:bg-zinc-900 rounded-md px-2 py-1 text-sm max-w-56 truncate">
                        <template x-if="selectedOptions.length > 1" hidden>
                            <div class="flex items-center gap-2">
                                <span x-text="selectedOptions.length"></span> {{ t('selected') }}
                            </div>
                        </template>

                        <template x-if="selectedOptions.length === 1" hidden>
                            <div x-text="selectedOptions[0].label" class="truncate"></div>
                        </template>
                    </div>
                </template>
            @else
                <template x-if="!isEmpty" hidden>
                    <div x-text="selectedOptions.label" class="shrink-0 bg-zinc-100 dark:bg-zinc-900 rounded-md px-2 py-1 text-sm"></div>
                </template>
            @endif

            @if ($clearable)
                <template x-if="!isEmpty" hidden>
                    <div class="shrink-0 cursor-pointer flex items-center justify-center" x-on:click.stop="clear()">
                        <atom:icon.close class="size-4" />
                    </div>
                </template>
            @endif
        </button>

        <atom:menu x-show="!loading" class="max-w-xl min-w-sm" popover>
            <template x-if="searchable" hidden>
                <div class="px-3 pt-2 pb-3 flex items-center gap-2 border-b dark:border-zinc-700">
                    <atom:icon.search class="text-zinc-400 shrink-0"/>

                    <input
                    type="text"
                    x-model.debounce.300="text"
                    x-on:input.stop=""
                    x-on:click.stop=""
                    class="appearance-none grow w-full focus:outline-none"
                    placeholder="{{ t('Search') }}"
                    data-atom-select-search>

                    <div
                    x-show="!loading && text"
                    x-on:click.stop="text = null"
                    class="shrink-0 flex items-center justify-center text-zinc-400 hover:text-zinc-800 cursor-pointer">
                        <atom:icon.close />
                    </div>

                    <div x-show="loading" class="shrink-0 flex items-center justify-center">
                        <atom:icon.loading />
                    </div>
                </div>
            </template>

            <template x-if="!options.length" hidden>
                <atom:empty size="sm"/>
            </template>

            <template x-if="options.length" hidden>
                <div class="max-h-[400px] overflow-auto">
                    <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
                        <atom:menu.item
                        x-on:mouseover="moveTo($el)"
                        x-on:mouseout="moveTo($el, false)"
                        x-on:click="select(option)"
                        x-bind:class="isSelected(option) && 'bg-zinc-100 dark:bg-zinc-600'"
                        data-atom-option>
                            <div class="flex gap-3">
                                <div x-bind:class="!isSelected(option) && 'opacity-0'" class="shrink-0 flex items-center justify-center">
                                    <atom:icon.check class="size-4 text-zinc-400 dark:text-zinc-200" />
                                </div>

                                <div x-html="getOptionHtml(option)" class="grow" data-option-content></div>
                            </div>
                        </atom:menu.item>
                    </template>
                </div>
            </template>

            @if (isset($actions) && $actions->isNotEmpty())
                <div class="border-t mt-1 pt-1 dark:border-zinc-700">
                    {{ $actions }}
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
