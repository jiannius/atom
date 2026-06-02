@props([
    'title' => null,
    'description' => null,
    'view' => null,
])

@php
$source = app(\Jiannius\Atom\Services\Docs::class)->source($view);
@endphp

<section class="mb-12">
    <atom:heading>{{ $title }}</atom:heading>

    @if ($description)
        <atom:caption class="mt-1">{{ $description }}</atom:caption>
    @endif

    <div class="mt-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        @include($view)
    </div>

    <div class="relative mt-2 rounded-xl bg-zinc-900 dark:border dark:border-zinc-700">
        <div class="absolute end-3 top-3 text-zinc-400 hover:text-white">
            <atom:copy :value="$source"/>
        </div>

        <pre class="overflow-x-auto p-4 text-sm text-zinc-100"><code>{{ $source }}</code></pre>
    </div>
</section>
