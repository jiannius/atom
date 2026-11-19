@props([
    'heading' => null,
    'expanded' => false,
])

@php
// Derived, never random: the open state is keyed by this id, so a new one on every render closes an open item.
$id = \Jiannius\Atom\Services\Attributes::stableId($attributes, 'atom-accordion', $heading);
@endphp

<div data-atom-accordion-item x-init="@if ($expanded) open('{{ $id }}') @endif">
    <button
        type="button"
        x-on:click="toggle('{{ $id }}')"
        x-bind:aria-expanded="isOpen('{{ $id }}')"
        {{ $attributes->class(['w-full flex items-center justify-between gap-3 min-h-row py-2 text-left text-sm font-medium text-fg select-none focus-visible:-outline-offset-2']) }}>
        <span>
            @if ($heading instanceof \Illuminate\View\ComponentSlot)
                {{ $heading }}
            @else
                {{ t($heading) }}
            @endif
        </span>

        <atom:icon.down class="size-4 shrink-0 text-fg-subtle transition-transform duration-200" x-bind:class="isOpen('{{ $id }}') && 'rotate-180'"/>
    </button>

    <div
        style="display: grid; transition: grid-template-rows .2s ease"
        x-bind:style="{ 'grid-template-rows': isOpen('{{ $id }}') ? '1fr' : '0fr' }">
        <div style="overflow: hidden">
            <div class="pb-3">{{ $slot }}</div>
        </div>
    </div>
</div>
