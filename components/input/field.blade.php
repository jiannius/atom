@props([
    'inline' => false,
    'caption' => null,
    'required' => false,
    'error' => null,
])

<div class="group/field {{ $inline ? 'grid md:grid-cols-5' : '' }}">
    @if (isset($label) && $label instanceof \Illuminate\View\ComponentSlot)
        <div class="{{ $inline ? 'py-2 md:col-span-2' : 'pb-2' }}">
            {{ $label }}
        </div>
    @elseif ($label = $attributes->get('label'))
        <div class="{{ $inline ? 'py-2 md:col-span-2' : 'pb-2' }}">
            <atom:label>
                <div class="inline-flex items-center justify-center gap-2">
                    {!! t($label) !!}

                    @if ($required)
                        <atom:icon.asterisk class="text-red-500 dark:text-red-300 shrink-0 size-3"/>
                    @endif
                </div>

                <x-slot:actions>
                    {{ $actions ?? '' }}
                </x-slot:actions>
            </atom:label>
        </div>
    @endif

    <div class="space-y-2 {{ $inline ? 'md:col-span-3' : '' }}">
        {{ $slot }}

        @if ($error)
            <atom:error>{{ t($error) }}</atom:error>
        @endif

        @if ($caption)
            <atom:caption>{{ t($caption) }}</atom:caption>
        @endif
    </div>
</div>
