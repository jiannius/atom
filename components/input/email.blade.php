@props([
    'options' => [],
    'multiple' => true,
    'icon' => null,
    'invalid' => false,
    'clearable' => true,
    'placeholder' => 'Email address',
])

@php
/* A chip never grows the field: the field is min-h-control with 3px of padding above and below its 1px border, and the chip (and
   the input beside it, h-control-xs) is the control height less those 8px, so one row of chips is as tall as the empty field at every
   density and sits centred. Chips that do not fit wrap onto another row, and that row grows the field; a
   chip wider than the field is capped at it and its text truncates (the full address is its title). The remove button's hit area (an ::after) reaches 3px past
   the content box above and below, so it is 24px high at the compact density without growing the chip; the field's root is min-w-0
   so a long chip cannot widen a grid or flex parent. */
$classes = Arr::toCssClasses([
    'min-h-control w-full py-[3px] no-spinner rounded-control border bg-surface text-sm text-fg',
    'flex items-center gap-1 flex-wrap',
    $invalid ? 'has-[:focus-visible]:border-danger-fg' : 'has-[:focus-visible]:border-focus',
    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:has-[:focus-visible]:border-danger-fg',
    $icon ? 'pl-8' : 'pl-2.5',
    /* pr-9: the clear button is 36px wide (16px icon, 10px each side), so a chip that fills the row ends where it starts */
    $clearable ? 'pr-9' : 'pr-2.5',
]);
@endphp

<div
x-data="emailInput({ options: @js($options) })"
x-modelable="emailInputValue"
x-on:keydown.up.prevent="keyUp()"
x-on:keydown.down.prevent="keyDown()"
x-on:keydown.enter.stop.prevent="keyEnter()"
x-on:keydown.space.stop.prevent="keyEnter()"
x-on:keydown.;.prevent="keyEnter()"
class="group/input relative w-full min-w-0 block"
{{ $attributes->except(['id', 'type', 'name', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}
data-atom-input-email>
    <atom:dropdown>
        <div {{ $attributes->class($classes)->only('class') }} data-atom-dropdown-trigger>
            <template x-for="val in emailInputValue" hidden>
                <div
                x-bind:class="validate(val.email)
                ? 'bg-surface-sunken border-border-strong text-fg-muted'
                : 'bg-danger-bg border-danger-fg text-danger-fg'"
                class="shrink-0 h-control-xs max-w-full min-w-0 rounded-nav border text-xs pl-2 inline-flex items-center">
                    <div x-text="`${val.name} <${val.email}>`" x-bind:title="`${val.name} <${val.email}>`" class="min-w-0 truncate"></div>
                    <button type="button" x-on:click="remove(val.email)" x-bind:aria-label="`{{ t('Remove') }} ${val.email}`" class="relative shrink-0 h-full flex items-center justify-center px-1.5 after:absolute after:inset-x-0 after:-inset-y-[3px]" data-atom-chip-remove>
                        <atom:icon.close class="size-3.5" />
                    </button>
                </div>
            </template>

            <input
            type="email"
            x-model="text"
            x-on:blur="text && keyEnter()"
            class="flex-1 h-control-xs focus-visible:outline-none appearance-none bg-transparent placeholder:text-fg-subtle"
            {{ $attributes->merge([
                'invalid' => $invalid,
                'placeholder' => t($placeholder),
            ])->only(['id', 'placeholder', 'required', 'disabled', 'readonly']) }}>
        </div>

        <atom:menu x-show="options.length" popover>
            <template x-for="(opt, i) in options" hidden>
                <atom:menu.item
                x-on:click="select(opt)"
                x-bind:class="pointer === i ? 'bg-fg/5' : ''">
                    <div x-text="`${opt.name} <${opt.email}>`" class="text-sm"></div>
                </atom:menu.item>
            </template>
        </atom:menu>
    </atom:dropdown>

    @if ($clearable)
        <button
        type="button"
        x-show="emailInputValue?.length"
        x-on:click="emailInputValue = []"
        aria-label="{{ t('Clear all') }}"
        class="z-1 absolute top-0 right-0 h-control flex items-center justify-center text-fg-subtle px-2.5 cursor-pointer hover:text-fg">
            <atom:icon.close class="size-4" />
        </button>
    @endif
</div>
