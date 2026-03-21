@props([
    'options' => [],
    'multiple' => true,
    'icon' => null,
    'invalid' => false,
    'clearable' => true,
    'placeholder' => 'Email address',
])

@php
$classes = Arr::toCssClasses([
    'min-h-10 w-full py-2 no-spinner rounded-lg shadow-sm outline-offset-1',
    'flex items-center gap-2 flex-wrap',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'dark:placeholder-zinc-400',
    'has-[:focus]:outline-1 has-[:focus]:outline-zinc-200 dark:has-[:focus]:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
    $icon ? 'pl-10' : 'pl-3',
    $clearable ? 'pr-10' : 'pr-3',
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
{{ $attributes->except(['type', 'name', 'class', 'placeholder', 'required', 'invalid', 'disabled', 'readonly']) }}
data-atom-input-email>
    <atom:dropdown>
        <div {{ $attributes->class($classes)->only('class') }} data-atom-dropdown-trigger>
            <template x-for="val in emailInputValue" hidden>
                <div
                x-bind:class="validate(val.email)
                ? 'bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-500 dark:text-muted-foreground'
                : 'bg-red-100 dark:bg-red-800/10 border-red-400 text-red-500 dark:text-red-400'"
                class="shrink-0 rounded-md border text-sm py-0.5 pl-2 inline-flex items-center -ml-1">
                    <div x-text="`${val.name} <${val.email}>`"></div>
                    <div x-on:click="remove(val.email)" class="shrink-0 flex items-center justify-center px-2">
                        <atom:icon.close class="size-4" />
                    </div>
                </div>
            </template>

            <input
            type="email"
            x-model="text"
            x-on:blur="text && keyEnter()"
            class="flex-1 focus:outline-none appearance-none bg-transparent"
            {{ $attributes->merge([
                'invalid' => $invalid,
                'placeholder' => t($placeholder),
            ])->only(['placeholder', 'required', 'disabled', 'readonly']) }}>
        </div>

        <atom:menu x-show="options.length" popover>
            <template x-for="(opt, i) in options" hidden>
                <atom:menu.item
                x-on:click="select(opt)"
                x-bind:class="pointer === i ? 'bg-zinc-100 dark:bg-zinc-800' : ''">
                    <div x-text="`${opt.name} <${opt.email}>`" class="text-sm"></div>
                </atom:menu.item>
            </template>
        </atom:menu>
    </atom:dropdown>

    @if ($clearable)
        <div
        x-show="emailInputValue?.length"
        x-on:click="emailInputValue = []"
        class="z-1 absolute top-0 right-0 h-10 flex items-center justify-center text-zinc-400 pr-3 cursor-pointer hover:text-muted text-muted-foreground">
            <atom:icon.close />
        </div>
    @endif
</div>
