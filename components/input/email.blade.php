@aware(['size', 'invalid', 'placeholder'])

@php
$options = $attributes->get('options') ?? [];
$placeholder = $placeholder ?? t('Email address');

$classes = $attributes->classes()
    ->add('w-full py-2 pl-2 pr-10 text-zinc-700 flex items-center gap-3 flex-wrap')
    ->add('border border-zinc-200 border-b-zinc-300/80 rounded-lg shadow-sm bg-white')
    ->add('has-[:focus]:border-primary hover:border-primary-300')
    ->add($invalid ? 'border-red-400' : 'group-has-[[data-atom-error]]/field:border-red-400')
    ->add($size === 'sm' ? 'min-h-8 text-sm' : 'min-h-10')
    ;

$attrs = $attributes
    ->class($classes)
    ->whereDoesntStartWith('wire:model')
    ->whereDoesntStartWith('x-model')
    ->except(['size', 'placeholder', 'field', 'error', 'options'])
    ;
@endphp

<div
wire:ignore.self
x-data="email({
    options: {{ js($options) }},
    @if ($attributes->wire('model')->value())
    value: @entangle($attributes->wire('model')),
    @endif
})"
x-modelable="value"
x-on:keydown.up.prevent="keyUp()"
x-on:keydown.down.prevent="keyDown()"
x-on:keydown.enter.prevent="keyEnter()"
x-on:keydown.space.prevent="keyEnter()"
x-on:keydown.;.prevent="keyEnter()"
x-on:keydown.esc.prevent="close()"
data-atom-input-email
class="group/input w-full"
{{ $attrs->whereDoesntStartWith('wire:model')->except('class') }}>
    <div x-ref="trigger" class="relative w-full block">
        <div {{ $attrs->only('class') }}>
            <template x-for="val in value" hidden>
                <div
                x-bind:class="validate(val.email) ? 'bg-zinc-100 border-zinc-200' : 'bg-red-100 border-red-300 text-red-500'"
                class="shrink-0 rounded-md border text-sm py-0.5 pl-2 inline-flex items-center">
                    <div x-text="`${val.name} <${val.email}>`"></div>
                    <div x-on:click="remove(val.email)" class="shrink-0 flex items-center justify-center px-2">
                        <atom:icon.close size="12"/>
                    </div>
                </div>
            </template>

            <input
            type="email"
            x-model="text"
            x-on:focus="open()"
            x-on:blur="close()"
            placeholder="{{ $placeholder }}"
            class="appearance-none grow focus:outline-none pl-1">
        </div>
    </div>
    
    <div
    x-ref="options"
    x-show="visible && filtered.length"
    x-transition.duration.200
    class="absolute z-10 w-full">
        <atom:menu class="max-h-[300px] overflow-auto">
            <template x-for="(opt, i) in filtered" hidden>
                <atom:menu.item
                x-on:click="select(opt)"
                x-on:mouseover="pointer = null"
                x-bind:class="pointer === i ? 'bg-zinc-100' : ''">
                    <div x-text="`${opt.name} <${opt.email}>`"></div>
                </atom:menu.item>
            </template>
        </atom:menu>
    </div>
</div>
