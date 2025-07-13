@aware(['variant'])

@php
$classes = Arr::toCssClasses([
    'flex',
    match ($variant) {
        'card' => 'py-3 px-4 border border-zinc-200 dark:border-zinc-700 shadow-sm rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700 has-checked:bg-zinc-100 dark:has-checked:bg-zinc-700',
        default => '',
    }
]);
@endphp

<label {{ $attributes->class($classes)->only('class') }}>
    <div @class(['shrink-0', match ($variant) {
        'card' => 'order-last py-[0.1rem] pl-2',
        default => 'pr-3',
    }])>
        <input
        type="radio"
        x-bind:name="groupName"
        class="peer hidden"
        {{ $attributes->except('class') }}>
        
        <div role="radio" class="size-5 rounded-full border border-zinc-300 shadow-sm bg-white peer-checked:border-accent peer-checked:border-4"></div>
    </div>

    <div class="grow">
        {{ $slot }}
    </div>
</label>
