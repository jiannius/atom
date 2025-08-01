@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$merges = ['name' => $name];
@endphp

<label class="group/checkbox inline-block space-y-2" data-atom-checkbox>
    <div>
        <div class="flex gap-2 items-center">
            <div class="shrink-0">
                <input type="checkbox" class="hidden peer" {{ $attrs }}>

                <div
                tabindex="0"
                role="checkbox"
                @class([
                    'size-4.5 rounded-md flex items-center justify-center focus:outline-none',
                    'bg-white dark:bg-white/10',
                    'border border-zinc-300 dark:border-white/10',
                    'text-zinc-300 dark:text-transparent',
                    'peer-checked:bg-accent peer-checked:border-accent peer-checked:text-zinc-300',
                    'dark:peer-checked:bg-white dark:peer-checked:border-white dark:peer-checked:text-black',
                    'group-has-[.error]/checkbox:outline-1 group-has-[.error]/checkbox:outline-red-500 group-has-[.error]/checkbox:outline-offset-1',
                ])>
                    <atom:icon.check class="size-3"/>
                </div>
            </div>

            <div class="dark:text-white">
                @if ($slot->isNotEmpty())
                    {{ $slot }}
                @elseif ($label)
                    {!! t($label) !!}
                @endif
            </div>
        </div>

        @if ($caption)
            <div class="inline-flex ml-6.5">
                <atom:caption>{{ t($caption) }}</atom:caption>
            </div>
        @endif
    </div>

    <atom:error>{{ t($error) }}</atom:error>
</label>
