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

/* `data-atom-disabled` (`self` or `inherited`) comes from the input tag. The box takes the disabled look from it as plain utilities, because a
   form-disabled box has a readonly input and no :disabled to key on (a focus border still beats them). Chips are Alpine nodes, so their remove and the clear
   button hide through `[[data-atom-disabled]_&]:hidden` on a plain wrapper (an element with a display class of its own would lose to it), and the JS refuses the same. */
$disabledBy = $attributes->get('data-atom-disabled');

$classes = Arr::toCssClasses([
    'min-h-control w-full py-[3px] no-spinner rounded-control border text-sm',
    $disabledBy ? 'cursor-not-allowed bg-surface-sunken text-fg-subtle' : 'bg-surface text-fg',
    'flex items-center gap-1 flex-wrap',
    'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:border-border has-[input:disabled]:bg-surface-sunken has-[input:disabled]:text-fg-subtle',
    $invalid ? 'has-[:focus-visible]:border-danger-fg' : 'has-[:focus-visible]:border-focus',
    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus',
    $invalid ? 'border-danger-fg' : ($disabledBy ? 'border-border' : 'border-control-border'),
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
                ? 'bg-surface-sunken border-border-strong text-fg-muted [[data-atom-disabled]_&]:text-fg-subtle'
                : 'bg-danger-bg border-danger-fg text-danger-fg'"
                class="shrink-0 h-control-xs max-w-full min-w-0 rounded-nav border text-xs pl-2 inline-flex items-center">
                    <div x-text="`${val.name} <${val.email}>`" x-bind:title="`${val.name} <${val.email}>`" class="min-w-0 truncate"></div>
                    <div class="shrink-0 h-full [[data-atom-disabled]_&]:hidden">
                        <button type="button" x-on:click="remove(val.email)" x-bind:aria-label="`{{ t('Remove') }} ${val.email}`" class="relative h-full flex items-center justify-center px-1.5 after:absolute after:inset-x-0 after:-inset-y-[3px]" data-atom-chip-remove>
                            <atom:icon.close class="size-3.5" />
                        </button>
                    </div>
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
        <div class="[[data-atom-disabled]_&]:hidden">
            <button
            type="button"
            x-show="emailInputValue?.length"
            x-on:click="clear()"
            aria-label="{{ t('Clear all') }}"
            class="z-1 absolute top-0 right-0 h-control flex items-center justify-center text-fg-subtle px-2.5 cursor-pointer hover:text-fg">
                <atom:icon.close class="size-4" />
            </button>
        </div>
    @endif
</div>
