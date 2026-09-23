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
    'tableFilter' => false,      // register with the table.filters bar as a chip
    'tableFilterLabel' => null,  // the chip's name; the select wrapper forwards its label
])

@php
$clearable = $clearable && !$disabled;
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

// See the note in select/listbox.blade.php. This variant holds raw values and
// keeps its labels in the <option> elements, so the chip's display is read back
// out of the DOM rather than from a selected-option object.
$filterKey = $tableFilter
    ? ($attributes->wire('model')->value() ?: $attributes->get('data-filter-key'))
    : null;

$filterLabel = $filterKey
    ? t($tableFilterLabel ?: (string) str($filterKey)->afterLast('.')->headline())
    : null;

$classes = Arr::toCssClasses([
    'w-full rounded-lg shadow-xs flex items-center gap-1 flex-wrap',
    'bg-white dark:bg-white/10 pr-10',
    'has-focus:outline-1 has-focus:outline-zinc-200 dark:has-focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    'has-disabled:bg-zinc-100 has-disabled:opacity-70 has-disabled:text-zinc-400 dark:has-disabled:bg-zinc-800/50 hover:has-disabled:outline-none',
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
@if ($filterKey)

    /** The chip's text: the chosen option's label, not the value behind it. */
    tableFilterDisplay () {
        const chosen = (this.multiple ? (this.value || []) : [this.value])
            .filter(val => val !== null && val !== undefined && val !== '')

        const options = Array.from(this.$root.querySelectorAll('option'))
        const labels = chosen
            .map(val => options.find(option => option.value == val)?.innerText.trim())
            .filter(Boolean)

        return labels.length > 1 ? labels.length + ' {{ t('selected') }}' : (labels[0] ?? null)
    },
@endif
}"
x-modelable="value"
@if ($filterKey)
x-init="
    const emit = () => $dispatch('table-filter:set', {
        key: @js($filterKey),
        label: @js($filterLabel),
        display: tableFilterDisplay(),
    });
    $nextTick(emit);
    $watch('value', () => { $nextTick(emit); $dispatch('table-filter:changed') });
"
x-on:table-filter:do-clear.window="$event.detail.key === @js($filterKey) && (value = multiple ? [] : null)"
@endif
x-on:input="() => {
    if (multiple) {
        value.push($event.target.value)
        $root.querySelector('select').value = ''
    }
    else value = $event.target.value
}"
x-on:click="$root.querySelector('select').showPicker()"
class="group/select w-full relative"
{{ $attributes->except(['id', 'class', 'disabled', 'required', 'readonly']) }}
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
                    <div class="shrink-0 max-w-56 flex items-center text-sm border-r border-zinc-300 dark:border-zinc-600 last:border-0">
                        <div x-text="item.label" class="grow truncate text-zinc-700 dark:text-zinc-200"></div>
                        <div x-on:click.stop="deselect(item)" class="shrink-0 flex items-center justify-center cursor-pointer text-muted dark:text-muted-foreground pl-2 pr-3">
                            <atom:icon.minus-circle class="size-4" />
                        </div>
                    </div>
                </template>
            </div>
        @endif

        <select
        @if (!$multiple) x-bind:value="value" @endif
        @if (!$multiple && $attributes->get('required')) required @endif
        {{ $attributes->merge($merges)->only(['id', 'disabled', 'readonly']) }}>
            @if ($placeholder)
                <atom:select.option value="" selected class="placeholder">
                    {{ t($placeholder) }}
                </atom:select.option>
            @endif

            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif (is_array($options) || $options instanceof \Illuminate\Support\Collection)
                @foreach ($options as $option)
                    @if (is_enum($option))
                        <atom:select.option :value="data_get($option->option(), 'value')" class="py-3">
                            {!! data_get($option->option(), 'label') !!}
                        </atom:select.option>
                    @elseif (data_get($option, 'group'))
                        <atom:select.group :label="data_get($option, 'group')">
                            @foreach (data_get($option, 'options') as $item)
                                <atom:select.option :value="data_get($item, 'value')">{!! data_get($item, 'label') !!}</atom:select.option>
                            @endforeach
                        </atom:select.group>
                    @else
                        <atom:select.option :value="data_get($option, 'value')">{!! data_get($option, 'label') !!}</atom:select.option>
                    @endif
                @endforeach
            @elseif (is_string($options))
                @foreach (app('atom')->action('get-options', ['name' => $options, 'filters' => $filters]) as $option)
                    @if (data_get($option, 'group'))
                        <atom:select.group :label="data_get($option, 'group')">
                            @foreach (data_get($option, 'options') as $item)
                                <atom:select.option :value="data_get($item, 'value')">{!! data_get($item, 'label') !!}</atom:select.option>
                            @endforeach
                        </atom:select.group>
                    @else
                        <atom:select.option :value="data_get($option, 'value')">{!! data_get($option, 'label') !!}</atom:select.option>
                    @endif
                @endforeach
            @endif
        </select>
    </div>

    <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center right-0">
        @if ($clearable)
            <div
            x-cloak
            x-data="{
                get show () {
                    return !empty(value)
                },
            }"
            x-on:click.stop="value = multiple ? [] : null"
            x-bind:class="!show && 'pointer-events-none'"
            class="flex items-center justify-center last:mr-2"
            data-atom-select-clear>
                <atom:icon.close x-show="show" class="text-muted dark:text-muted-foreground hover:text-zinc-800 dark:hover:text-white" />
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