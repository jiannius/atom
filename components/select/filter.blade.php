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
$uid = app('atom')->uid('atom-select');
$filterKey = $attributes->wire('model')->value() ?: $attributes->get('data-filter-key');

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

$optionClasses = Arr::toCssClasses([
    'flex items-center gap-3 w-full py-2 px-3 my-1 first:mt-0 last:mb-0 rounded-md',
    'text-left text-zinc-800 dark:text-white cursor-default',
    'hover:bg-zinc-800/5 dark:hover:bg-zinc-600',
    '[&[data-active]]:bg-zinc-800/5 dark:[&[data-active]]:bg-zinc-600',
]);
@endphp

<div
x-data="select({
    options: @js($options),
    filters: @js($filters),
    multiple: @js($multiple),
    searchable: @js($searchable),
    uid: @js($uid),
})"
x-modelable="selectValue"
x-on:keydown.up.prevent.stop="keyUp()"
x-on:keydown.down.prevent.stop="keyDown()"
x-on:keydown.enter.prevent.stop="enterKey()"
x-on:keydown.home.prevent.stop="home()"
x-on:keydown.end.prevent.stop="end()"
x-on:keydown.escape.stop=""
class="group/select"
@if ($filterKey)
x-init="
    const emit = () => $dispatch('table-filter:set', {
        key: @js($filterKey),
        label: @js(t($label)),
        display: isEmpty ? null : (@js($multiple)
            ? (selectedOptions.length > 1 ? selectedOptions.length + ' {{ t('selected') }}' : (selectedOptions[0]?.label ?? null))
            : (selectedOptions?.label ?? null)),
    });
    $nextTick(emit);
    $watch('selectValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
"
x-on:table-filter:do-clear.window="$event.detail.key === @js($filterKey) && clear()"
@endif
{{ $attributes->except('class') }}>
    <atom:dropdown>
        <button
        type="button"
        aria-haspopup="listbox"
        @if (!$searchable)
            {{-- aria-expanded on this trigger is managed by dropdown.js --}}
            role="combobox"
            aria-controls="{{ $uid }}-list"
            x-on:keydown="typeAhead($event)"
            data-atom-select-combobox
        @endif
        {{ $attributes->class($classes)->only('class') }}>
            @if ($icon)
                <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                    <x-dynamic-component :component="'atom::icon.'.$icon" />
                </div>
            @endif

            <div class="font-medium text-muted">
                {{ t($label) }}
            </div>

            <div class="shrink-0">
                <atom:icon.dropdown />
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

        <atom:menu
        role="listbox"
        id="{{ $uid }}-list"
        aria-multiselectable="{{ $multiple ? 'true' : 'false' }}"
        class="max-w-xl min-w-sm" popover>
            <div
            x-show="searchable"
            x-on:input.stop="() => {
                clearTimeout(timer)
                timer = setTimeout(() => fetch(), 300)
            }"
            class="px-3 pt-2 pb-3 flex items-center gap-2 border-b dark:border-zinc-700">
                <atom:icon.search class="text-zinc-400 shrink-0"/>

                <input
                type="text"
                x-model="text"
                x-on:click.stop=""
                @if ($searchable)
                    role="combobox"
                    aria-controls="{{ $uid }}-list"
                    aria-autocomplete="list"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                @endif
                class="appearance-none grow w-full focus:outline-none"
                placeholder="{{ t('Search') }}"
                autofocus
                data-atom-select-search>

                <div
                x-show="!loading && text"
                x-on:click.stop="text = null; $dispatch('input')"
                class="shrink-0 flex items-center justify-center text-zinc-400 hover:text-zinc-800 cursor-pointer">
                    <atom:icon.close />
                </div>

                <div x-show="loading" class="shrink-0 flex items-center justify-center">
                    <atom:icon.loading />
                </div>
            </div>

            <div x-show="!searchable && loading" class="p-2 flex items-center justify-center gap-2 opacity-50">
                <atom:icon.loading /> {{ t('Loading') }}...
            </div>

            <div x-show="!loading && !options.length">
                <atom:empty size="sm"/>
            </div>

            {{-- Alpine owns these rows — see the note in select/listbox.blade.php. --}}
            <div x-show="options.length" class="max-h-[400px] overflow-auto" wire:ignore>
                <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
                    <div>
                        <template x-if="option.group" hidden>
                            <div role="group" x-bind:aria-label="option.group">
                                <div x-text="option.group" class="text-sm text-zinc-500 dark:text-zinc-400 py-1.5 px-3" aria-hidden="true"></div>
                                <template x-for="(groupOption, j) in option.options" x-bind:key="`group-option-${groupOption.value}-${j}`" hidden>
                                    <div
                                    role="option"
                                    x-bind:aria-selected="isSelected(groupOption) ? 'true' : 'false'"
                                    x-bind:data-label="groupOption.label"
                                    x-on:click="select(groupOption)"
                                    x-bind:class="isSelected(groupOption) && 'bg-zinc-100 dark:bg-zinc-600'"
                                    data-atom-option
                                    class="{{ $optionClasses }}">
                                        <div x-bind:class="!isSelected(groupOption) && 'opacity-0'" class="shrink-0 flex items-center justify-center">
                                            <atom:icon.check class="size-4 text-zinc-400 dark:text-zinc-200" />
                                        </div>

                                        <div x-html="getOptionHtml(groupOption)" class="grow" data-option-content></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!option.group" hidden>
                            <div
                            role="option"
                            x-bind:aria-selected="isSelected(option) ? 'true' : 'false'"
                            x-bind:data-label="option.label"
                            x-on:click="select(option)"
                            x-bind:class="isSelected(option) && 'bg-zinc-100 dark:bg-zinc-600'"
                            data-atom-option
                            class="{{ $optionClasses }}">
                                <div x-bind:class="!isSelected(option) && 'opacity-0'" class="shrink-0 flex items-center justify-center">
                                    <atom:icon.check class="size-4 text-zinc-400 dark:text-zinc-200" />
                                </div>

                                <div x-html="getOptionHtml(option)" class="grow" data-option-content></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            @if (isset($actions) && $actions->isNotEmpty())
                <div x-show="options.length || !loading" class="border-t mt-1 pt-1 dark:border-zinc-700">
                    {{ $actions }}
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
