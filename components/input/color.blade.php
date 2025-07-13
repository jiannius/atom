@props([
    'invalid' => false,
    'placeholder' => 'Select a color',
])

@php
$wiremodel = $attributes->wire('model')->value();
$classes = Arr::toCssClasses([
    'h-10 w-full py-2 pl-10 pr-10 text-zinc-700 cursor-default',
    'border border-zinc-200 border-b-zinc-300/80 rounded-lg shadow-sm bg-white',
    'focus:outline-none focus:border-primary group-focus/input:border-primary hover:border-primary-300',
    $invalid ? 'border-red-400' : 'group-has-[[data-atom-error]]/field:border-red-400',
]);
@endphp

<div
x-data="{ color: @if ($wiremodel) $wire.entangle('{{ $wiremodel }}') @else null @endif }"
x-modelable="color"
class="group/input relative w-full block"
{{ $attributes->except(['class']) }}
data-atom-color-input>
    <atom:dropdown>
        <div class="relative" data-atom-dropdown-trigger>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                <div class="absolute top-0 left-0 bottom-0 px-3 flex items-center justify-center">
                    <div x-show="color" x-bind:style="{ backgroundColor: color }" class="size-4 rounded-full"></div>
                </div>

                <input
                type="text"
                x-model="color"
                readonly
                {{ $attributes->class($classes)->merge(['placeholder' => t($placeholder)])->only(['class', 'placeholder']) }}>

                <div class="absolute top-0 right-0 bottom-0 flex items-center justify-center text-muted-foreground px-3">
                    <atom:icon.brush />
                </div>
            @endif
        </div>

        <atom:menu popover>
            <div class="grow grid grid-cols-11 gap-1 p-2 max-h-[400px] overflow-auto">
                @foreach (Arr::collapse(\Jiannius\Atom\Services\Color::all()) as $color)
                    <div
                    x-on:click="color = @js($color)"
                    class="cursor-pointer size-6 border rounded hover:ring-1 hover:ring-offset-1 hover:ring-zinc-500"
                    style="background-color: {{ $color }};">
                    </div>
                @endforeach
            </div>
        </atom:menu>
    </atom:dropdown>
</div>
