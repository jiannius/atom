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
$filterKey = $attributes->wire('model')->value() ?: $attributes->get('data-filter-key');

$options = is_array($options) || $options instanceof \Illuminate\Support\Collection
    ? collect($options)->map(fn ($option) => is_enum($option) ? $option->option() : $option)->toArray()
    : $options;

/* A filter trigger is a control of the toolbar: the control token's height, a strong hairline, the label in the muted tone
   and the chosen value in a chip beside it. */
$classes = Arr::toCssClasses([
    'inline-flex items-center gap-1.5',
    'min-h-control py-0.5 appearance-none rounded-control border border-control-border bg-surface text-sm text-fg text-left pr-2',
    'hover:bg-surface-sunken',
    $icon ? 'pl-8' : 'pl-2.5',
]);

$optionClasses = Arr::toCssClasses([
    'flex items-center gap-2 w-full min-h-control py-1 px-2 my-0.5 first:mt-0 last:mb-0 rounded-nav',
    'text-left text-sm text-fg cursor-default',
    'hover:bg-fg/5',
    '[&[data-active]]:bg-fg/5',
]);
@endphp

{{-- The listbox id is minted client-side with $id rather than server-side: a
     server counter is positional, so the same select gets a different id
     whenever the render mints a different number of ids before it. That
     changes the x-data attribute, Livewire's morph re-evaluates it, and the
     subtree's effects are left on the previous scope — the picker then opens
     on a permanent "No Results". --}}
<div
x-id="['atom-select']"
x-data="select({
    options: @js($options),
    filters: @js($filters),
    multiple: @js($multiple),
    searchable: @js($searchable),
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
            {{-- the trigger's visible text is the filter's name, but role="combobox"
                 takes no accessible name from its own content the way a button does, so
                 without this it is announced as an unnamed combobox. A filter takes no
                 label prop from <atom:table.filters>, so there is no anchor to point at
                 and the name is given directly, as <atom:table.search> does. --}}
            @if ($label) aria-label="{{ t($label) }}" @endif
            x-bind:aria-controls="`${$id('atom-select')}-list`"
            x-on:keydown="typeAhead($event)"
x-on:keyup.space.prevent=""
            data-atom-select-combobox
        @endif
        {{ $attributes->class($classes)->only('class') }}>
            @if ($icon)
                <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pl-2.5 left-0">
                    <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4" />
                </div>
            @endif

            <div class="font-medium text-fg-muted">
                {{ t($label) }}
            </div>

            {{-- no wrapper: a block div around a bare icon is a line box, so the icon
                 sits on ITS baseline with the descender gap underneath, ~2.5px above
                 the centre the button's items-center is holding everything else at.
                 The icon wrapper is already inline-flex shrink-0. --}}
            <atom:icon.dropdown class="size-4 text-fg-subtle" />

            @if ($multiple === true)
                <template x-if="!isEmpty" hidden>
                    <div class="shrink-0 bg-surface-sunken rounded-nav px-1.5 py-px text-sm max-w-56 truncate">
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
                    <div x-text="selectedOptions.label" class="shrink-0 bg-surface-sunken rounded-nav px-1.5 py-px text-sm"></div>
                </template>
            @endif

            @if ($clearable)
                <template x-if="!isEmpty" hidden>
                    <div class="shrink-0 cursor-pointer flex items-center justify-center text-fg-subtle hover:text-fg" x-on:click.stop="clear()">
                        <atom:icon.close class="size-4" />
                    </div>
                </template>
            @endif
        </button>

        <atom:menu
        role="listbox"
        x-bind:id="`${$id('atom-select')}-list`"
        aria-multiselectable="{{ $multiple ? 'true' : 'false' }}"
        class="max-w-xl min-w-sm" popover>
            <div
            x-show="searchable"
            x-on:input.stop="() => {
                clearTimeout(timer)
                timer = setTimeout(() => fetch(), 300)
            }"
            class="px-2.5 py-1.5 flex items-center gap-2 border-b border-border">
                <atom:icon.search class="size-4 text-fg-subtle shrink-0"/>

                <input
                type="text"
                x-model="text"
                x-on:click.stop=""
                @if ($searchable)
                    role="combobox"
                    {{-- searchable moves the combobox role off the trigger and onto this
                         input, so the name has to follow it --}}
                    @if ($label) aria-label="{{ t($label) }}" @endif
                    x-bind:aria-controls="`${$id('atom-select')}-list`"
                    aria-autocomplete="list"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                @endif
                class="appearance-none grow w-full text-sm placeholder:text-fg-muted focus-visible:outline-none"
                placeholder="{{ t('Search') }}"
                autofocus
                data-atom-select-search>

                <div
                x-show="!loading && text"
                x-on:click.stop="text = null; $dispatch('input')"
                class="shrink-0 flex items-center justify-center text-fg-subtle hover:text-fg cursor-pointer">
                    <atom:icon.close class="size-4" />
                </div>

                <div x-show="loading" class="shrink-0 flex items-center justify-center">
                    <atom:icon.loading class="size-4" />
                </div>
            </div>

            <div x-show="!searchable && loading" class="p-2 flex items-center justify-center gap-2 text-sm opacity-50">
                <atom:icon.loading class="size-4" /> {{ t('Loading') }}...
            </div>

            <div x-show="!loading && !options.length">
                <atom:empty size="sm"/>
            </div>

            {{-- Alpine owns these rows — see the note in select/listbox.blade.php. --}}
            <div x-show="options.length" class="max-h-[400px] overflow-auto" data-atom-option-list wire:ignore data-atom-scroll-region>
                <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
                    <div>
                        <template x-if="option.group" hidden>
                            <div role="group" x-bind:aria-label="option.group">
                                <div x-text="option.group" class="text-xs font-medium text-fg-muted py-1.5 px-2" aria-hidden="true"></div>
                                <template x-for="(groupOption, j) in option.options" x-bind:key="`group-option-${groupOption.value}-${j}`" hidden>
                                    <div
                                    role="option"
                                    x-bind:aria-selected="isSelected(groupOption) ? 'true' : 'false'"
                                    x-bind:data-label="groupOption.label"
                                    x-on:click="select(groupOption)"
                                    x-bind:class="isSelected(groupOption) && 'bg-fg/10'"
                                    data-atom-option
                                    class="{{ $optionClasses }}">
                                        <div x-bind:class="!isSelected(groupOption) && 'opacity-0'" class="shrink-0 flex items-center justify-center">
                                            <atom:icon.check class="size-4 text-fg-muted" />
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
                            x-bind:class="isSelected(option) && 'bg-fg/10'"
                            data-atom-option
                            class="{{ $optionClasses }}">
                                <div x-bind:class="!isSelected(option) && 'opacity-0'" class="shrink-0 flex items-center justify-center">
                                    <atom:icon.check class="size-4 text-fg-muted" />
                                </div>

                                <div x-html="getOptionHtml(option)" class="grow" data-option-content></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            @if (isset($actions) && $actions->isNotEmpty())
                <div x-show="options.length || !loading" class="border-t border-border mt-1 pt-1">
                    {{ $actions }}
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
