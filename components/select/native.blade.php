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
    'required' => null,
    'disabled' => null,
])

@php
$clearable = $clearable && !$disabled;
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

$classes = Arr::toCssClasses([
    'w-full rounded-lg shadow-sm flex items-center gap-1 flex-wrap',
    'bg-white dark:bg-white/10 pr-10',
    'has-focus:outline-1 has-focus:outline-zinc-200 dark:has-focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    '[&_select]:w-full [&_select]:flex-1 [&_select]:appearance-none [&_select]:outline-offset-1',
    '[&_select]:text-zinc-700 [&_select]:dark:text-zinc-200 [&_select]:text-left',
    '[&_select]:has-[option.placeholder:checked]:text-zinc-400',
    '[&_select]:focus:outline-none',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    $multiple ? 'min-h-10 py-2' : 'h-10 py-1.5',
    $icon ? 'pl-10' : 'pl-3',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
    '[[data-atom-input-prefix]+[data-atom-select-native]>&]:rounded-l-none',
    '[[data-atom-input-suffix]+[data-atom-select-native]>&]:rounded-r-none',
]);

$merges = [
    'required' => $required,
    'disabled' => $disabled,
];
@endphp

<div
x-data="{
    value: @js($multiple ? [] : null),
    multiple: @js($multiple),
}"
x-modelable="value"
x-on:input="() => {
    if (multiple) {
        value.push($event.target.value)
        $root.querySelector('select').value = ''
    }
    else value = $event.target.value
}"
x-on:click="$root.querySelector('select').showPicker()"
class="group/select w-full relative"
{{ $attributes->except(['class', 'disabled', 'required', 'readonly']) }}
data-atom-select-native>
    @if ($icon)
        <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
        </div>
    @endif

    <div {{ $attributes->class($classes)->only('class') }}>
        @if ($multiple === true)
            <div
            x-data="{
                get selected () {
                    let options = Array.from(this.$el.closest('[data-atom-select-native]').querySelectorAll('option'))
                    return (value || [])
                        .map(val => options.find(opt => opt.value == val))
                        .map(opt => ({
                            value: opt.value,
                            label: opt.innerText.trim(),
                        }))
                },

                deselect (item) {
                    let index = value.findIndex(val => (val == item.value))
                    if (index > -1) value.splice(index, 1)
                },
            }"
            class="flex items-center gap-2 flex-wrap">
                <template x-for="item in selected" hidden>
                    <div class="shrink-0 max-w-56 flex items-center text-sm border-r border-zinc-300 last:border-0">
                        <div x-text="item.label" class="grow truncate text-zinc-700 dark:text-zinc-200"></div>
                        <div x-on:click.stop="deselect(item)" class="shrink-0 flex items-center justify-center cursor-pointer text-muted-foreground pl-2 pr-3">
                            <atom:icon.minus-circle class="size-4" />
                        </div>
                    </div>
                </template>
            </div>
        @endif

        <select
        @if (!$multiple) x-bind:value="value" @endif
        {{ $attributes->only(['disabled', 'required', 'readonly']) }}>
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
                @foreach (app('atom')->action('get-options', ['name' => $options, 'filters' => $filters]) as $item)
                    <atom:select.option
                    :value="data_get($item, 'value')"
                    :disabled="data_get($item, 'is_group') ?? false"
                    class="{{ data_get($item, 'is_group') ? 'py-3' : '' }}">
                        {!! data_get($item, 'label') !!}
                    </atom:select.option>
                @endforeach
            @endif
        </select>
    </div>

    <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center right-0">
        @if ($clearable)
            <div
            x-data="{
                get show () {
                    return !empty(value)
                },
            }"
            x-on:click.stop="value = multiple ? [] : null"
            x-bind:class="!show && 'pointer-events-none'"
            class="flex items-center justify-center last:mr-2"
            data-atom-select-clear>
                <atom:icon.close x-show="show" class="text-muted-foreground hover:text-muted" />
                <atom:icon.dropdown x-show="!show" />
            </div>
        @elseif (!$disabled)
            <atom:icon.dropdown class="mr-2" />
        @endif

        @if (!$disabled)
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
        @endif
    </div>
</div>