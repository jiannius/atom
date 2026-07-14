@props([
    'heading' => null,
    'expanded' => false,
])

@php
$id = 'atom-accordion-'.Str::random(8);
@endphp

<div data-atom-accordion-item x-init="@if ($expanded) open('{{ $id }}') @endif">
    <button
        type="button"
        x-on:click="toggle('{{ $id }}')"
        x-bind:aria-expanded="isOpen('{{ $id }}')"
        {{ $attributes->class(['w-full flex items-center justify-between gap-3 py-3 text-left font-medium select-none']) }}>
        <span>
            @if ($heading instanceof \Illuminate\View\ComponentSlot)
                {{ $heading }}
            @else
                {{ t($heading) }}
            @endif
        </span>

        <atom:icon.down class="size-4 shrink-0 transition-transform duration-200" x-bind:class="isOpen('{{ $id }}') && 'rotate-180'"/>
    </button>

    <div
        style="display: grid; transition: grid-template-rows .2s ease"
        x-bind:style="{ 'grid-template-rows': isOpen('{{ $id }}') ? '1fr' : '0fr' }">
        <div style="overflow: hidden">
            <div class="pb-3">{{ $slot }}</div>
        </div>
    </div>
</div>
