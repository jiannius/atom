<atom:docs.layout>
    <atom:heading size="xl">Atom Components</atom:heading>

    <atom:caption class="mt-2">
        {{ $docs->components()->count() }} components. Pick one from the sidebar, or browse by category below.
        Pages with authored examples show live previews; the rest show an auto-generated prop reference.
    </atom:caption>

    @foreach ($docs->grouped() as $category => $items)
        <div class="mt-10">
            <atom:heading>{{ $category }}</atom:heading>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($items as $item)
                    <a
                    href="{{ route('atom.docs.show', $item['name']) }}"
                    class="rounded-panel border bg-surface px-4 py-3 text-sm font-medium hover:bg-surface-sunken">
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="mt-10">
        <atom:heading>Patterns</atom:heading>
        <atom:caption class="mt-1">Whole screens built from atom's components. The Blade file is the example: copy it.</atom:caption>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach (\Jiannius\Atom\Services\Docs::PATTERNS as $name => $label)
                <a
                href="{{ route('atom.docs.pattern', $name) }}"
                class="rounded-panel border bg-surface px-4 py-3 text-sm font-medium hover:bg-surface-sunken">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
</atom:docs.layout>
