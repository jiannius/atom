@php
$icons = app(\Jiannius\Atom\Services\Docs::class)->icons();
@endphp

<div x-data="{ q: '' }">
    <atom:input placeholder="Search {{ $icons->count() }} icons..." x-model="q"/>

    <atom:caption class="mt-2">Click an icon to copy its tag.</atom:caption>

    <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-5">
        @foreach ($icons as $icon)
            <div x-show="!q || @js($icon).includes(q.toLowerCase())">
                <atom:copy :value="'<atom:icon.'.$icon.'/>'">
                    <div class="flex cursor-pointer flex-col items-center gap-2 rounded-panel border bg-surface p-3 hover:bg-surface-sunken">
                        <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5"/>
                        <span class="w-full truncate text-center text-xs">{{ $icon }}</span>
                    </div>
                </atom:copy>
            </div>
        @endforeach
    </div>
</div>
