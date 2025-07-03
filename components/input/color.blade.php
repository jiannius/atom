@aware(['size', 'invalid', 'placeholder'])

@php
$classes = $attributes->classes()
    ->add('w-full py-2 pl-10 pr-10 text-zinc-700 cursor-default')
    ->add('border border-zinc-200 border-b-zinc-300/80 rounded-lg shadow-sm bg-white')
    ->add('focus:outline-none focus:border-primary group-focus/input:border-primary hover:border-primary-300')
    ->add($invalid ? 'border-red-400' : 'group-has-[[data-atom-error]]/field:border-red-400')
    ->add($size === 'sm' ? 'h-8 text-sm' : 'h-10')
    ;

$attrs = $attributes
    ->class($classes)
    ->except(['size', 'placeholder', 'field', 'error'])
    ;
@endphp

<div
    x-data="color({
        @if ($attributes->wire('model')->value())
        value: @entangle($attributes->wire('model')),
        @endif
    })"
    x-modelable="value"
    class="group/input relative w-full block"
    {{ $attrs->whereStartsWith('wire:model') }}
    {{ $attrs->whereStartsWith('x-model') }}>
    <div
        x-ref="trigger"
        x-on:click="show()"
        x-on:click.away="close()"
        class="relative">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <div class="absolute top-0 left-0 bottom-0 px-3 flex items-center justify-center">
                <div
                    x-show="value"
                    class="w-4 h-4 rounded-full"
                    x-bind:style="{ backgroundColor: value }">
                </div>
            </div>

            <input
                type="text"
                x-ref="input"
                x-model="value"
                placeholder="{{ t($placeholder) }}"
                readonly
                {{ $attrs->whereDoesntStartWith('wire:model')->whereDoesntStartWith('x-model') }}>

            <div class="absolute top-0 right-0 bottom-0 flex items-center justify-center text-muted-more px-3">
                <atom:icon brush/>
            </div>
        @endif
    </div>

    <div x-ref="options" x-show="visible" x-transition.duration.200 class="absolute z-1">
        <atom:menu class="w-max">
            <div class="grow grid grid-cols-11 gap-1 p-2 max-h-[300px] overflow-auto">
                @foreach (atom()->color()->all() as $color)
                    <div
                        x-on:click="() => {
                            value = {{ js($color) }}
                            $dispatch('input', value)
                        }"
                        class="cursor-pointer w-6 h-6 border rounded hover:ring-1 hover:ring-offset-1 hover:ring-zinc-500"
                        style="background-color: {{ $color }};">
                    </div>
                @endforeach
            </div>
        </atom:menu>
    </div>
</div>
