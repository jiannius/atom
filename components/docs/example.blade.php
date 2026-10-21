@props([
    'title' => null,
    'description' => null,
    'view' => null,
])

@php
$source = app(\Jiannius\Atom\Services\Docs::class)->source($view);
@endphp

<section class="mb-16">
    <atom:heading size="lg">{{ $title }}</atom:heading>

    @if ($description)
        <atom:caption class="mt-1.5 max-w-2xl leading-relaxed">{{ $description }}</atom:caption>
    @endif

    <div class="mt-5 rounded-panel border bg-surface p-6">
        @include($view)
    </div>

    <div class="relative mt-3 rounded-panel border bg-surface-sunken">
        <div class="absolute end-3 top-3 text-fg-subtle hover:text-fg">
            <atom:copy :value="$source"/>
        </div>

        <pre class="font-id overflow-x-auto p-4 text-fg"><code>{{ $source }}</code></pre>
    </div>
</section>
