@aware(['size', 'invalid', 'placeholder'])

@php
$dialcodes = \Jiannius\Atom\Atom::action('get-options', ['name' => 'dialcodes']);
$lazy = $attributes->has('wire:model.lazy') || $attributes->has('x-model.lazy');

$classes = $attributes->classes()
    ->add('w-full py-2 pl-[9.5rem] pr-10 text-zinc-700')
    ->add('border border-zinc-200 border-b-zinc-300/80 rounded-lg shadow-sm bg-white')
    ->add('focus:outline-none focus:border-primary group-focus/input:border-primary hover:border-primary-300')
    ->add($invalid ? 'border-red-400' : 'group-has-[[data-atom-error]]/field:border-red-400')
    ->add($size === 'sm' ? 'h-8 text-sm' : 'h-10')
    ;

$attrs = $attributes
    ->class($classes)
    ->whereDoesntStartWith('wire:model')
    ->whereDoesntStartWith('x-model')
    ->except(['size', 'placeholder', 'field', 'error'])
    ;
@endphp

<div
x-data="tel({
    code: {{ js($attributes->get('code', '+60')) }},
    lazy: {{ js($lazy) }},
    @if ($attributes->wire('model')->value())
    value: @entangle($attributes->wire('model')),
    @endif
})"
class="group/input relative w-full block">
    @if ($attributes->has('wire:model.lazy'))
        <input type="hidden" x-ref="hidden" wire:model="{{ $attributes->wire('model')->value() }}">
    @elseif ($attributes->has('x-model.lazy'))
        <input type="hidden" x-ref="hidden" x-model="{{ $attributes->get('x-model.lazy') }}">
    @else
        <input type="hidden" x-ref="hidden"
            {{ $attributes->whereStartsWith('wire:model') }}
            {{ $attributes->whereStartsWith('x-model') }}>
    @endif

    <div class="absolute top-0 bottom-0 left-0 w-[9rem] flex items-center gap-2">
        <div class="relative w-full">
            <select
            x-ref="options"
            x-model="code"
            x-on:change="format()"
            x-on:input.stop
            data-atom-input-tel-country
            class="appearance-none bg-transparent pl-3 pr-6 w-full focus:outline-none">
                @foreach ($dialcodes as $dialcode)
                    <option value="{{ get($dialcode, 'value') }}">
                        {{ get($dialcode, 'label') }}
                    </option>
                @endforeach
            </select>

            <div class="pointer-events-none absolute top-0 bottom-0 right-0 pr-2 flex items-center justify-center">
                <atom:icon dropdown/>
            </div>
        </div>
    </div>

    <input
    type="tel"
    x-model="tel"
    @if ($lazy)
    x-on:blur="format()"
    @else
    x-on:input.stop="format()"
    @endif
    placeholder="{{ t($placeholder) }}"
    {{ $attrs }}>
</div>
