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
   the input beside it) is the control height less those 8px, so one row of chips is as tall as the empty field at every
   density and sits centred. Chips that do not fit wrap onto another row, and that row grows the field. */
$classes = Arr::toCssClasses([
    'min-h-control w-full py-[3px] no-spinner rounded-control border bg-surface text-sm text-fg',
    'flex items-center gap-1 flex-wrap',
    $invalid ? 'has-[:focus-visible]:border-danger-fg' : 'has-[:focus-visible]:border-focus',
    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:has-[:focus-visible]:border-danger-fg',
    $icon ? 'pl-8' : 'pl-2.5',
    $clearable ? 'pr-8' : 'pr-2.5',
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
class="group/input relative w-full block"
{{ $attributes->except(['id', 'type', 'name', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}
data-atom-input-email>
    <atom:dropdown>
        <div {{ $attributes->class($classes)->only('class') }} data-atom-dropdown-trigger>
            <template x-for="val in emailInputValue" hidden>
                <div
                x-bind:class="validate(val.email)
                ? 'bg-surface-sunken border-border-strong text-fg-muted'
                : 'bg-danger-bg border-danger-fg text-danger-fg'"
                class="shrink-0 h-[calc(var(--atom-control-h)-8px)] rounded-nav border text-xs pl-2 inline-flex items-center">
                    <div x-text="`${val.name} <${val.email}>`"></div>
                    <button type="button" x-on:click="remove(val.email)" x-bind:aria-label="`{{ t('Remove') }} ${val.email}`" class="shrink-0 h-full flex items-center justify-center px-1.5">
                        <atom:icon.close class="size-3.5" />
                    </button>
                </div>
            </template>

            <input
            type="email"
            x-model="text"
            x-on:blur="text && keyEnter()"
            class="flex-1 h-[calc(var(--atom-control-h)-8px)] focus-visible:outline-none appearance-none bg-transparent placeholder:text-fg-subtle"
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
