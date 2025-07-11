@props([
    'variant' => 'native',
    'multiple' => false,
    'label' => null,
    'inline' => null,
    'caption' => null,
    'required' => null,
    'error' => null,
])

@php
$error ??= $errors?->first($name);


$name = $attributes->get('name') ?? $attributes->wire('model')->value();
$icon = $attributes->get('icon');
$variant = $attributes->get('variant') ?? 'native'; // native, listbox
$prefix = $attributes->get('prefix');
$suffix = $attributes->get('suffix');
$invalid = $attributes->get('invalid');
$multiple = $attributes->get('multiple') ?? false;
$searchable = $attributes->get('searchable') ?? false;
$clearable = $attributes->get('clearable') ?? true;
$placeholder = $attributes->get('placeholder', 'Please select...');
$options = $attributes->get('options');
$filters = $attributes->get('filters');
$size = $attributes->get('size');
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

$classes = Arr::toCssClasses([
    'appearance-none w-full text-zinc-700 text-left',
    $hasAddButton || isset($addButton) ? 'pr-20' : 'pr-10',
    $variant === 'native' && $multiple ? '' : 'py-1.5',
    'border rounded-lg shadow-sm bg-white',
    'focus:outline-none focus:border-primary has-[:focus]:border-primary group-focus/input:border-primary hover:border-primary-300',
    'has-[option.placeholder:checked]:text-zinc-400',
    $invalid || $error ? 'border-red-400' : 'border-zinc-200 border-b-zinc-300/80',
    'group-has-[[data-atom-error]]/field:border-red-400',
    $icon ? 'pl-10' : 'pl-3',

    $multiple || $variant === 'listbox'
        ? ($size === 'sm' ? 'min-h-8 text-sm' : 'min-h-10')
        : ($size === 'sm' ? 'h-8 text-sm' : 'h-10'),

    '[[data-atom-input-prefix]+[data-atom-select-native]>&]:rounded-l-none',
    '[[data-atom-input-suffix]+[data-atom-select-native]>&]:rounded-r-none',
]);

$merges = ['required' => $required];
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :required="$required"
    :inline="$inline"
    :error="$error">
        <atom:select :attributes="$attributes">
            {{ $slot }}

            @isset ($addButton)
                <x-slot:add-button>
                    {{ $addButton }}
                </x-slot:add-button>
            @endisset

            @isset ($actions)
                <x-slot:actions>
                    {{ $actions }}
                </x-slot:actions>
            @endisset
        </atom:select>
    </atom:input.field>
@elseif ($prefix || $suffix)
    <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
        <atom:select :attributes="$attributes->except(['prefix', 'suffix'])">
            {{ $slot }}

            @isset ($actions)
                <x-slot:actions>
                    {{ $actions }}
                </x-slot:actions>
            @endisset
        </atom:select>
    </atom:input.prefix>
@elseif ($variant === 'listbox')
    {{-- <div
    wire:ignore.self
    x-data="select({
        id: @js($id),
        options: @js($options),
        filters: @js($filters),
        multiple: @js($multiple),
        searchable: @js($searchable),
        @if ($attributes->wire('model')->value())
        value: @entangle($attributes->wire('model')),
        @endif
    })"
    x-modelable="value"
    x-on:keydown.up.prevent.stop="keyUp()"
    x-on:keydown.down.prevent.stop="keyDown()"
    x-on:keydown.enter.prevent.stop="keyEnter()"
    x-on:keydown.esc.prevent.stop="close()"
    x-on:click.away="close()"
    data-atom-select-listbox
    class="group/select w-full"
    {{ $attrs->whereDoesntStartWith('wire:model')->except('class') }}>
        @if ($multiple === 'list')
            <template x-if="!isEmpty" hidden>
                <atom:list class="mb-2">
                    <template x-for="item in selected" hidden>
                        <atom:list.item x-on:remove="deselect(item.value)" x-on:click.stop="$dispatch('click-selected', item)" class="text-sm">
                            <div x-html="item.html" class="flex items-center gap-2 truncate cursor-default"></div>
                        </atom:list.item>
                    </template>
                </atom:list>
            </template>
        @endif

        <div wire:ignore x-ref="trigger" data-atom-select-listbox-trigger class="relative block">
            <button type="button" x-on:click="open()" {{ $attrs->only('class') }}>
                @if ($icon)
                    <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
                    </div>
                @endif

                @if ($multiple === true)
                    <template x-if="isEmpty" hidden>
                        <div class="flex items-center text-zinc-400">@t($placeholder)</div>
                    </template>

                    <template x-if="!isEmpty" hidden>
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-for="item in selected" hidden>
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
                                    <div x-on:click.stop="deselect(item.value)" class="shrink-0 flex items-center justify-center text-muted-more pl-2 pr-3">
                                        <atom:icon.minus-circle size="12"/>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                @elseif ($multiple === 'list')
                    <div class="flex items-center text-zinc-400">@t($placeholder)</div>
                @else
                    <template x-if="isEmpty" hidden>
                        <div class="flex items-center text-zinc-400">@t($placeholder)</div>
                    </template>

                    <template x-if="!isEmpty" hidden>
                        @isset ($selected)
                            {{ $selected }}
                        @else
                            <div x-html="selected.html" class="group/select-selected"></div>
                        @endisset
                    </template>
                @endif

                <template x-if="!visible && loading">
                    <div class="z-1 absolute top-0 bottom-0 pr-3 right-0 text-primary py-3">
                        <atom:icon.loading/>
                    </div>
                </template>

                <template x-if="!(!visible && loading)">
                    <div class="z-1 absolute top-0 bottom-0 right-0 flex">
                        @if ($multiple === 'list')
                            <div class="pointer-events-none py-3 pl-3 pr-2 last:pr-3">
                                <atom:icon.dropdown/>
                            </div>
                        @else
                            <template x-if="isEmpty" hidden>
                                <div class="pointer-events-none py-3 pr-2 last:pr-3">
                                    <atom:icon.dropdown/>
                                </div>
                            </template>

                            <template x-if="!isEmpty" hidden>
                                <div x-on:click.stop="clear()" class="cursor-pointer py-3 pl-3 pr-2 last:pr-3">
                                    <atom:icon.close size="14"/>
                                </div>
                            </template>
                        @endif

                        @if (isset($addButton))
                            <div x-on:click.stop class="p-1 cursor-pointer">
                                {{ $addButton }}
                            </div>
                        @elseif ($hasAddButton)
                            <div
                            x-tooltip="t('add-new')"
                            x-on:click.stop="$dispatch('add')"
                            class="p-1 cursor-pointer">
                                <div class="p-2 h-[2.05rem] bg-zinc-100 rounded">
                                    <atom:icon.add/>
                                </div>
                            </div>
                        @endif
                    </div>
                </template>
            </button>
        </div>

        <atom:popover
        wire:ignore.self
        x-ref="options"
        x-on:popover-open="setWidth()"
        class="max-w-screen-lg">
            <atom:menu>
                <template x-if="searchable" hidden>
                    <div class="py-3 px-4 flex items-center gap-2 border-b">
                        <atom:icon.search class="text-zinc-400 shrink-0"/>

                        <input
                        type="text"
                        x-ref="search"
                        x-model.debounce.300="text"
                        x-on:input.stop=""
                        class="appearance-none grow w-full focus:outline-none"
                        placeholder="{{ t('search') }}">

                        <div
                        x-show="!loading && text"
                        x-on:click.stop="text = null"
                        class="shrink-0 flex items-center justify-center text-zinc-400 hover:text-zinc-800 cursor-pointer">
                            <atom:icon.close/>
                        </div>

                        <div x-show="loading" class="shrink-0 flex items-center justify-center text-primary">
                            <atom:icon.loading/>
                        </div>
                    </div>
                </template>

                <template x-if="!options.length" hidden>
                    <div class="px-10">
                        <atom:empty size="sm"/>
                    </div>
                </template>

                <template x-if="options.length" hidden>
                    <ul class="max-h-[300px] overflow-auto space-y-1 mt-1">
                        @if ($slot->isNotEmpty())
                            {{ $slot }}
                        @else
                            <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
                                <atom:select.option x-model="option"/>
                            </template>
                        @endif
                    </ul>
                </template>

                @isset ($actions)
                    <div class="border-t pt-1">
                        {{ $actions }}
                    </div>
                @endif
            </atom:menu>
        </atom:popover>
    </div> --}}
@elseif ($variant === 'native' && $multiple)
    {{-- <div
    x-data="{
        @if ($attributes->wire('model')->value())
        value: @entangle($attributes->wire('model')),
        @else
        value: [],
        @endif
        get selected () {
            return Array.from(this.$root.querySelectorAll('option'))
                .filter(opt => opt.value)
                .filter(opt => ((this.value || []).map(val => `${val}`).includes(`${opt.value}`)))
                .map(opt => ({
                    value: opt.value,
                    label: opt.innerText.trim(),
                }))
        },
        select (e) {
            this.value.push(e.target.value)
            e.target.value = ''
        },
        deselect (value) {
            let index = this.value.findIndex(val => (val == value))
            if (index > -1) this.value.splice(index, 1)
        },
    }"
    x-modelable="value"
    class="group/select w-full"
    data-atom-select-native
    {{ $attrs->whereStartsWith('x-model') }}
    {{ $attrs->only('wire:key') }}>
        @if ($multiple === 'list')
            <atom:list class="mb-2">
                <template x-for="item in selected" hidden>
                    <atom:list.item x-on:remove="deselect(item.value)" x-on:click.stop="$dispatch('click-selected', item)" class="cursor-default text-sm">
                        <div x-html="item.label"></div>
                    </atom:list.item>
                </template>
            </atom:list>
        @endif
        <div class="relative">
            @if ($icon)
                <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                    <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
                </div>
            @endif
            <div {{ $attrs->class(['flex items-center gap-1 flex-wrap'])->only('class') }}>
                @if ($multiple === true)
                    <div class="flex items-center gap-2 flex-wrap py-2">
                        <template x-for="item in selected" hidden>
                            <div class="shrink-0 max-w-56 flex items-center text-sm border-r border-zinc-300 last:border-0">
                                <div x-text="item.label" class="grow truncate text-zinc-700"></div>
                                <div x-on:click.stop="deselect(item.value)" class="shrink-0 flex items-center justify-center cursor-pointer text-muted-more pl-2 pr-3">
                                    <atom:icon.minus-circle size="12"/>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif
                <select
                x-on:input.stop="select($event)"
                class="py-2 grow appearance-none bg-transparent focus:outline-none no-spinner"
                {{ $attrs->whereDoesntStartWith('wire:model')->whereDoesntStartWith('x-model')->except(['wire:key', 'class']) }}>
                    @if ($placeholder)
                    <atom:select.option value="" selected class="placeholder">@t($placeholder)</atom:select.option>
                    @endif
                    @if ($slot->isNotEmpty())
                        {{ $slot }}
                    @elseif ($options)
                        @foreach (\Jiannius\Atom\Atom::action('get-options', [
                            'name' => $options,
                            'filters' => $filters,
                        ]) as $item)
                            <atom:select.option
                                :value="get($item, 'value')"
                                :disabled="get($item, 'is_group') ?? false"
                                class="{{ get($item, 'is_group') ? 'py-3' : '' }}">
                                @e(get($item, 'label'))
                            </atom:select.option>
                        @endforeach
                    @endif
                </select>
            </div>
            @if ($clearable)
                <div
                x-data="{
                    clearable: false,
                    init () {
                        this.setClearable()
                        this.getSelect().addEventListener('change', () => this.setClearable())
                    },
                    getSelect () {
                        return $el.parentNode.querySelector('select')
                    },
                    setClearable () {
                        this.clearable = !empty(this.getSelect().value)
                    },
                }"
                x-on:click.stop="() => {
                    getSelect().value = ''
                    getSelect().dispatch('change')
                }"
                x-bind:class="!clearable && 'pointer-events-none'"
                class="z-1 absolute top-0 bottom-0 flex items-center justify-center pr-3 right-0">
                    <atom:icon.close x-show="clearable"/>
                    <atom:icon.dropdown x-show="!clearable"/>
                </div>
            @endif
        </div>
    </div> --}}
@else
    <div wire:replace class="group/select w-full relative" {{ $attributes->only('wire:key') }} data-atom-select-native>
        @if ($icon)
            <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
            </div>
        @endif

        {{-- <select {{ $attributes->class($classes)->merge($merges) }}>
            @if ($placeholder)
                <atom:select.option value="" selected class="placeholder">
                    {{ t($placeholder) }}
                </atom:select.option>
            @endif --}}
    
            @if ($slot->isNotEmpty())
            {{-- {{dd($slot->toHTML())}} --}}
                {{ $slot }}
            @elseif (is_array($options) || $options instanceof \Illuminate\Support\Collection)
                {{dd($options)}}
                @foreach ($options as $item)
                    @if (is_enum($item))
                        <atom:select.option :value="data_get($item->option(), 'value')" class="py-3">
                            {!! data_get($item->option(), 'label') !!}
                        </atom:select.option>
                    @else
                        <atom:select.option
                        :value="data_get($item, 'value')"
                        :disabled="data_get($item, 'is_group') ?? false"
                        class="{{ data_get($item, 'is_group') ? 'py-3' : '' }}">
                            {!! data_get($item, 'label') !!}
                        </atom:select.option>
                    @endif
                @endforeach
            @elseif (is_string($options))
                @foreach (\Jiannius\Atom\Atom::action('get-options', [
                    'name' => $options,
                    'filters' => $filters,
                ]) as $item)
                    <atom:select.option
                    :value="data_get($item, 'value')"
                    :disabled="data_get($item, 'is_group') ?? false"
                    class="{{ data_get($item, 'is_group') ? 'py-3' : '' }}">
                        {!! data_get($item, 'label') !!}
                    </atom:select.option>
                @endforeach
            @endif
        {{-- </select> --}}
    
        @if ($clearable)
            <div
            x-data="{
                clearable: false,
    
                init () {
                    this.setClearable()
                    this.getSelect().addEventListener('change', () => this.setClearable())
                },
    
                getSelect () {
                    return $el.parentNode.querySelector('select')
                },
    
                setClearable () {
                    this.clearable = !empty(this.getSelect().value)
                },
            }"
            x-on:click.stop="() => {
                getSelect().value = ''
                getSelect().dispatch('change')
            }"
            x-bind:class="!clearable && 'pointer-events-none'"
            class="z-1 absolute top-0 bottom-0 flex items-center justify-center pr-3 right-0">
                <atom:icon.close x-show="clearable"/>
                <atom:icon.dropdown x-show="!clearable"/>
            </div>
        @endif
    </div>
@endif