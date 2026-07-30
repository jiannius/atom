@props([
    'icon' => null,
    'locked' => false,
    'options' => null,
    'filters' => null,
    'invalid' => false,
    'multiple' => false,
    'disabled' => false,
    'clearable' => true,
    'searchable' => false,
    'placeholder' => 'Please select...',
])

@php
$uid = app('atom')->uid('atom-select');
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

$classes = Arr::toCssClasses([
    'min-h-10 appearance-none w-full rounded-lg shadow-xs outline-offset-1',
    'text-zinc-700 dark:text-zinc-200 text-left pr-10',
    'bg-white dark:bg-white/10',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    $multiple === true ? 'py-2' : 'py-1.5',
    $icon ? 'pl-10' : 'pl-3',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
]);

// role=option rows: hover and the virtual-focus "active" state share the same
// highlight (the option never takes real DOM focus), selection is layered on top.
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
data-atom-select-listbox
@if ($disabled) aria-disabled="true" @endif
@class(['group/select w-full', 'pointer-events-none' => $disabled])
{{ $attributes->except('class') }}>
    @if ($multiple === 'list')
        <template x-if="!isEmpty" hidden>
            <atom:list class="mb-2">
                <template x-for="item in selectedOptions" hidden>
                    <atom:list.item x-on:remove="deselect(item)" x-on:click.stop="$dispatch('click-selected', item)" class="text-sm">
                        <div x-html="getOptionHtml(item, true)" class="flex items-center gap-2 truncate cursor-default"></div>
                    </atom:list.item>
                </template>
            </atom:list>
        </template>
    @endif

    <atom:dropdown :locked="$locked">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
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

                @if ($multiple === true)
                    <template x-if="isEmpty" hidden>
                        <div class="flex items-center text-zinc-400">{{ t($placeholder) }}</div>
                    </template>

                    <template x-if="!isEmpty" hidden>
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-for="item in selectedOptions" hidden>
                                <div class="shrink-0 max-w-56 truncate flex items-center text-sm border-r border-zinc-300 last:border-0">
                                    <div class="flex items-center gap-2 truncate">
                                        <template x-if="item.color" hidden>
                                            <div
                                            x-bind:style="'background-color: '+item.color"
                                            class="w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center"></div>
                                        </template>

                                        <template x-if="item.avatar" hidden>
                                            <div class="relative flex items-center justify-center size-6 rounded-full bg-zinc-200 text-muted text-xs overflow-hidden">
                                                <div x-text="item.label.charAt(0).toUpperCase()"></div>
                                                <template x-if="typeof item.avatar === 'string'" hidden>
                                                    <div class="absolute inset-0 z-1">
                                                        <img x-bind:src="item.avatar" class="w-full h-full object-cover"/>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <div x-text="item.label" class="grow truncate"></div>
                                    </div>
                                    <div x-on:click.stop="deselect(item)" class="shrink-0 flex items-center justify-center text-muted-foreground pl-2 pr-3 cursor-pointer">
                                        <atom:icon.minus-circle class="size-4" />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                @elseif ($multiple === 'list')
                    <div class="flex items-center text-zinc-400">{{ t($placeholder) }}</div>
                @else
                    <template x-if="isEmpty" hidden>
                        <div class="flex items-center text-zinc-400">{{ t($placeholder) }}</div>
                    </template>

                    <template x-if="!isEmpty" hidden>
                        @isset ($selected)
                            {{ $selected }}
                        @else
                            <div x-html="getOptionHtml(selectedOptions, true)" class="group/select-selected"></div>
                        @endisset
                    </template>
                @endif

                <div class="z-1 absolute top-0 right-0 h-10 flex items-center justify-center">
                    @if ($multiple !== 'list' && $clearable)
                        <template x-if="isEmpty" hidden>
                            <div class="pointer-events-none py-3 pr-2 last:pr-3">
                                <atom:icon.dropdown class="text-muted-foreground" />
                            </div>
                        </template>

                        <template x-if="!isEmpty" hidden>
                            <div x-on:click.stop="clear()" class="cursor-pointer flex items-center justify-center pl-3 pr-2 last:pr-3 text-muted-foreground">
                                <atom:icon.close />
                            </div>
                        </template>
                    @else
                        <div class="pointer-events-none flex items-center justify-center pl-3 pr-2 last:pr-3">
                            <atom:icon.dropdown class="text-muted-foreground" />
                        </div>
                    @endif

                    @if (isset($addButton) && $addButton->isNotEmpty())
                        <div x-on:click.stop class="p-1 cursor-pointer">
                            {{ $addButton }}
                        </div>
                    @elseif ($hasAddButton)
                        <atom:tooltip content="Add New">
                            <div x-on:click.stop="$dispatch('add')" class="p-1 cursor-pointer">
                                <div class="p-2 h-[2.05rem] bg-zinc-100 dark:bg-zinc-800 rounded-md">
                                    <atom:icon.add />
                                </div>
                            </div>
                        </atom:tooltip>
                    @endif
                </div>
            </button>
        @endif

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
                x-intersect="$nextTick(() => $el.focus())"
                @if ($searchable)
                    role="combobox"
                    aria-controls="{{ $uid }}-list"
                    aria-autocomplete="list"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                @endif
                class="appearance-none grow w-full focus:outline-none"
                placeholder="{{ t('Search') }}"
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

            <div x-show="options.length" class="max-h-[400px] overflow-auto">
                <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
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

            @if (isset($actions) && $actions->isNotEmpty())
                <div x-show="options.length || !loading" class="border-t mt-1 pt-1 dark:border-zinc-700">
                    {{ $actions }}
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
