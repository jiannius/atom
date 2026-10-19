@props([
    'invalid' => false,
    'placeholder' => 'Select a color',
])

@php
$wiremodel = $attributes->wire('model')->value();
$classes = Arr::toCssClasses([
    'h-control w-full px-8 cursor-default rounded-control border bg-surface text-sm text-fg',
    'placeholder:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
]);
@endphp

<div
x-data="{ color: @if ($wiremodel) $wire.entangle('{{ $wiremodel }}') @else null @endif }"
x-modelable="color"
class="group/input relative w-full block"
{{ $attributes->except(['id', 'class']) }}
data-atom-color-input>
    <atom:dropdown>
        <div class="relative" data-atom-dropdown-trigger>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                <div class="absolute top-0 left-0 bottom-0 px-2.5 flex items-center justify-center">
                    <div x-show="color" x-bind:style="{ backgroundColor: color }" class="size-4 rounded-full border border-border-strong"></div>
                </div>

                <input
                type="text"
                x-model="color"
                readonly
                {{ $attributes->class($classes)->merge(['placeholder' => t($placeholder)])->only(['id', 'class', 'placeholder']) }}>

                <div class="absolute top-0 right-0 bottom-0 flex items-center justify-center text-fg-subtle px-2.5">
                    <atom:icon.brush class="size-4" />
                </div>
            @endif
        </div>

        <atom:menu popover>
            <div class="grow grid grid-cols-11 gap-1 p-2 max-h-[400px] overflow-auto" data-atom-scroll-region>
                @foreach (Arr::collapse(\Jiannius\Atom\Services\Color::all()) as $color)
                    <div
                    x-on:click="color = @js($color)"
                    class="cursor-pointer size-6 border border-border-strong rounded-nav hover:outline-2 hover:outline-offset-1 hover:outline-fg-muted"
                    style="background-color: {{ $color }};">
                    </div>
                @endforeach
            </div>
        </atom:menu>
    </atom:dropdown>
</div>
