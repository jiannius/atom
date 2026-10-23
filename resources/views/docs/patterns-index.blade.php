{{-- The patterns overview: every page under docs/patterns, with when to use it. The pages are built from atom's own components
     with invented data, and the Blade file of each is the example: open it, copy the structure, change the data. The list
     comes from Docs::PATTERNS and Docs::PATTERN_NOTES, so a new pattern page is listed by adding it there. --}}
@php
$docs = \Jiannius\Atom\Services\Docs::class;
$source = 'vendor/jiannius/atom/resources/views/docs/patterns/';
@endphp

<atom:docs.layout title="Patterns">
    <atom:heading size="xl">Patterns</atom:heading>

    <atom:caption class="mt-2">
        Whole screens built from atom's components, one page for each kind of screen. The Blade file is the example: open the page,
        read the file named under it, copy its structure and change the data. Every rule they follow (one primary action, status
        tones, empty states, money, density) is in the Boost skill's Patterns section.
    </atom:caption>

    <div class="mt-8 grid gap-3">
        @foreach ($docs::PATTERNS as $name => $label)
            <div class="rounded-panel border bg-surface p-4" data-testid="pattern-{{ $name }}">
                <div class="flex items-baseline justify-between gap-3">
                    <a href="{{ route('atom.docs.pattern', $name) }}" class="text-sm font-semibold text-accent-ink hover:underline">{{ $label }}</a>
                    <code class="text-xs text-fg-subtle">{{ $docs::PATTERN_NOTES[$name]['try'] }}</code>
                </div>

                <atom:caption class="mt-1">{{ $docs::PATTERN_NOTES[$name]['use'] }}</atom:caption>
                <atom:caption class="mt-1 font-mono text-xs">{{ $source.$name }}.blade.php</atom:caption>
            </div>
        @endforeach
    </div>

    <atom:heading class="mt-10">Shared by the two app shells</atom:heading>

    <atom:caption class="mt-1">These files are not pages: the shell patterns include them, so only the layout tag differs between the two.</atom:caption>

    <ul class="mt-3 grid gap-2">
        @foreach ($docs::PATTERN_PARTIALS as $name => $note)
            <li class="text-sm"><code class="font-mono text-xs">{{ $source.$name }}.blade.php</code> <span class="text-fg-muted">{{ $note }}</span></li>
        @endforeach
    </ul>
</atom:docs.layout>
