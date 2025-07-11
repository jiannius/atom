@php
$sortable = $attributes->has('x-sort:item');
$removeable = $attributes->hasAny('wire:remove', 'x-on:remove');
@endphp

<div class="'py-2 pr-1 flex rounded-md hover:bg-zinc-100'" {{ $attributes->except('class') }}>
    @if ($sortable)
        <div x-sort:handle class="shrink-0 w-8 h-6 flex items-center justify-center text-muted-more cursor-move">
            <atom:icon.sort-handle />
        </div>
    @endif

    <div {{ $attributes->class(['grow first:pl-3'])->only('class') }}>
        {{ $slot }}
    </div>

    @if ($removeable)
        <div x-on:click.stop="$dispatch('remove')" class="shrink-0 w-8 h-6 flex items-center justify-center text-muted-more cursor-pointer">
            <atom:icon.delete />
        </div>
    @endif
</div>
