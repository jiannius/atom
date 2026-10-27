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
    'ariaLabelledby' => null,
    'tableFilter' => false,      // register with the table.filters bar as a chip
    'tableFilterLabel' => null,  // the chip's name; the select wrapper forwards its label
])

@php
$hasAddButton = $attributes->get('x-on:add') || $attributes->wire('add')->value();

// A form field filters a table like any bar control — it just has to say so,
// because a form is also where a select that filters nothing lives. The bar
// variants (select.filter, date-picker.range) register on the strength of a
// wire:model alone; here the flag is the opt-in and the model is the chip's key.
$filterKey = $tableFilter
    ? ($attributes->wire('model')->value() ?: $attributes->get('data-filter-key'))
    : null;

// Without a name the chip renders as "null: Red", so fall back to the field.
$filterLabel = $filterKey
    ? t($tableFilterLabel ?: (string) str($filterKey)->afterLast('.')->headline())
    : null;

/* The field recipe (see input/general); the trigger grows with its chips, never below the control token. */
$classes = Arr::toCssClasses([
    'min-h-control appearance-none w-full rounded-control border bg-surface text-sm text-fg text-left pr-8',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    $multiple === true ? 'py-1' : 'py-0.5',
    $icon ? 'pl-8' : 'pl-2.5',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);

// role=option rows: hover and the virtual-focus "active" state share the same
// highlight (the option never takes real DOM focus), selection is layered on top.
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
data-atom-select-listbox
@if ($filterKey)
x-init="
    const emit = () => $dispatch('table-filter:set', {
        key: @js($filterKey),
        label: @js($filterLabel),
        display: isEmpty ? null : (@js((bool) $multiple)
            ? (selectedOptions.length > 1 ? selectedOptions.length + ' {{ t('selected') }}' : (selectedOptions[0]?.label ?? null))
            : (selectedOptions?.label ?? null)),
    });
    $nextTick(emit);
    $watch('selectValue', () => { $nextTick(emit); $dispatch('table-filter:changed') });
"
x-on:table-filter:do-clear.window="$event.detail.key === @js($filterKey) && clear()"
@endif
@if ($disabled) aria-disabled="true" @endif
@class(['group/select w-full', 'pointer-events-none' => $disabled])
{{ $attributes->except(['class', 'aria-labelledby']) }}>
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
                @if ($ariaLabelledby) aria-labelledby="{{ $ariaLabelledby }}" @endif
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

                @if ($multiple === true)
                    {{-- x-show, not an x-if: the placeholder is server text, and a morph skips what an x-if rendered (Alpine 3.17) --}}
                    <div x-show="isEmpty" x-cloak class="flex items-center text-fg-subtle">{{ t($placeholder) }}</div>

                    <template x-if="!isEmpty" hidden>
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-for="item in selectedOptions" hidden>
                                <div class="shrink-0 max-w-56 truncate flex items-center text-sm border-r border-border-strong last:border-0">
                                    <div class="flex items-center gap-2 truncate">
                                        <template x-if="safeColor(item.color)" hidden>
                                            <div
                                            x-bind:style="'background-color: '+safeColor(item.color)"
                                            class="w-3 h-3 rounded-full flex items-center justify-center"></div>
                                        </template>

                                        <template x-if="item.avatar" hidden>
                                            <div class="relative flex items-center justify-center size-5 rounded-full bg-surface-sunken text-fg-muted text-xs overflow-hidden">
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
                                    <div x-on:click.stop="deselect(item)" class="shrink-0 flex items-center justify-center text-fg-subtle hover:text-fg pl-2 pr-2.5 cursor-pointer">
                                        <atom:icon.minus-circle class="size-4" />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                @elseif ($multiple === 'list')
                    <div class="flex items-center text-fg-subtle">{{ t($placeholder) }}</div>
                @else
                    {{-- x-show, not an x-if: the placeholder is server text, and a morph skips what an x-if rendered (Alpine 3.17) --}}
                    <div x-show="isEmpty" x-cloak class="flex items-center text-fg-subtle">{{ t($placeholder) }}</div>

                    {{-- The one x-if that holds a server slot: a host's `selected` slot is a template for the CLIENT's value (it reads `selectedOptions`,
                         which is empty here until something is picked), so it must not be drawn while empty, and x-show would draw it and run
                         its expressions on nothing. The cost is Alpine 3.17's: a morph never patches it, so a server-changing @if inside this
                         slot freezes; keep such markup outside it (CLAUDE.md). The default branch is client-only. --}}
                    <template x-if="!isEmpty" hidden>
                        @isset ($selected)
                            {{ $selected }}
                        @else
                            <div x-html="getOptionHtml(selectedOptions, true)" class="group/select-selected"></div>
                        @endisset
                    </template>
                @endif

                <div class="z-1 absolute top-0 right-0 h-control flex items-center justify-center text-fg-subtle" data-atom-select-affix>
                    @if ($multiple !== 'list' && $clearable)
                        <template x-if="isEmpty" hidden>
                            {{-- flex, like the clear button this alternates with: a
                                 block wrapper baselines the glyph, so the caret and
                                 the ✕ would sit at different heights --}}
                            <div class="pointer-events-none flex items-center justify-center pr-2 last:pr-2.5">
                                <atom:icon.dropdown class="size-4" />
                            </div>
                        </template>

                        <template x-if="!isEmpty" hidden>
                            <div x-on:click.stop="clear()" class="cursor-pointer flex items-center justify-center pl-2.5 pr-2 last:pr-2.5 hover:text-fg">
                                <atom:icon.close class="size-4" />
                            </div>
                        </template>
                    @else
                        <div class="pointer-events-none flex items-center justify-center pl-2.5 pr-2 last:pr-2.5">
                            <atom:icon.dropdown class="size-4" />
                        </div>
                    @endif

                    @if (isset($addButton) && $addButton->isNotEmpty())
                        <div x-on:click.stop class="px-1 cursor-pointer">
                            {{ $addButton }}
                        </div>
                    @elseif ($hasAddButton)
                        <atom:tooltip content="Add New">
                            <div x-on:click.stop="$dispatch('add')" class="px-1 cursor-pointer">
                                <div class="flex items-center justify-center size-5 bg-surface-sunken text-fg-muted hover:text-fg rounded-nav">
                                    <atom:icon.add class="size-3.5" />
                                </div>
                            </div>
                        </atom:tooltip>
                    @endif
                </div>
            </button>
        @endif

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
                x-intersect="$nextTick(() => $el.focus({ preventScroll: true }))"
                @if ($searchable)
                    role="combobox"
                    @if ($ariaLabelledby) aria-labelledby="{{ $ariaLabelledby }}" @endif
                    x-bind:aria-controls="`${$id('atom-select')}-list`"
                    aria-autocomplete="list"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                @endif
                class="appearance-none grow w-full text-sm placeholder:text-fg-muted focus-visible:outline-none"
                placeholder="{{ t('Search') }}"
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

            {{-- The option rows are Alpine's, not the server's: they have no
                 counterpart in the rendered HTML, so a Livewire morph over this
                 subtree drops rows x-for still has in its lookup (and re-inserts
                 copies outside the loop scope, which then error on `option`).
                 The list is populated from JS either way, so let the morph skip it. --}}
            <div x-show="options.length" class="max-h-[400px] overflow-auto" data-atom-option-list wire:ignore data-atom-scroll-region>
                <template x-for="(option, i) in options" x-bind:key="`option-${option.value}-${i}`" hidden>
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

            @if (isset($actions) && $actions->hasActualContent())
                <div x-show="options.length || !loading" class="border-t border-border mt-1 pt-1">
                    {{ $actions }}
                </div>
            @endif
        </atom:menu>
    </atom:dropdown>
</div>
