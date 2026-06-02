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
                    class="rounded-lg border border-zinc-200 px-4 py-3 font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</atom:docs.layout>
