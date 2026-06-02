@php
$logos = app(\Jiannius\Atom\Services\Docs::class)->logos();
@endphp

<div>
    <atom:caption>Payment and brand marks. Click a logo to copy its tag.</atom:caption>

    <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-5">
        @foreach ($logos as $logo)
            <atom:copy :value="'<atom:logo.'.$logo.'/>'">
                <div class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <x-dynamic-component :component="'atom::logo.'.$logo" class="h-6"/>
                    <span class="w-full truncate text-center text-xs">{{ $logo }}</span>
                </div>
            </atom:copy>
        @endforeach
    </div>
</div>
