@props([
    'multiple' => false,
    'icon' => null,
    'prefix' => null,
    'suffix' => null,
    'invalid' => null,
    'searchable' => false,
    'clearable' => true,
    'placeholder' => 'Please select...',
    'options' => null,
    'filters' => null,
    'size' => null,
    'error' => null,
    'required' => null,
])

@php
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

$classes = Arr::toCssClasses([
    'appearance-none w-full rounded-lg shadow-sm outline-offset-1',
    'text-zinc-700 dark:text-zinc-200 text-left pr-10',
    'bg-white dark:bg-white/10',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid || $error ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    $multiple ? 'min-h-10 py-2' : 'h-10 py-1.5',
    $icon ? 'pl-10' : 'pl-3',
    'has-[option.placeholder:checked]:text-zinc-400',
    // 'group-has-[[data-atom-error]]/field:border-red-400',
    '[[data-atom-input-prefix]+[data-atom-select-native]>&]:rounded-l-none',
    '[[data-atom-input-suffix]+[data-atom-select-native]>&]:rounded-r-none',
]);

$merges = ['required' => $required];
@endphp

@if ($multiple)
    <div
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
                <atom:select.clear />
            @endif
        </div>
    </div>
@else
    <div class="group/select w-full relative" {{ $attributes->only('wire:key') }} data-atom-select-native>
        @if ($icon)
            <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
            </div>
        @endif

        <select {{ $attributes->class($classes)->merge($merges) }}>
            @if ($placeholder)
                <atom:select.option value="" selected class="placeholder">
                    {{ t($placeholder) }}
                </atom:select.option>
            @endif
    
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif (is_array($options) || $options instanceof \Illuminate\Support\Collection)
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
        </select>
    
        @if ($clearable)
            <atom:select.clear />
        @endif
    </div>
@endif