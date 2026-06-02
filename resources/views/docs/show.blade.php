<atom:docs.layout :title="str($entry['name'])->headline()" :editor="$entry['name'] === 'editor'">
    <atom:heading size="xl">{{ str($entry['name'])->headline() }}</atom:heading>

    <div class="mt-2 flex items-center gap-2">
        <code class="rounded bg-zinc-100 px-2 py-1 text-sm dark:bg-zinc-800">{{ $entry['tag'] }}</code>
        <atom:copy :value="$entry['tag']"/>
    </div>

    <div class="mt-10">
        @if ($entry['isGallery'])
            @include('atom::docs.gallery.'.$entry['name'])
        @elseif (view()->exists('atom::docs.demos.'.$entry['name']))
            @include('atom::docs.demos.'.$entry['name'])
        @else
            @include('atom::docs.fallback')
        @endif
    </div>

    @unless ($entry['isGallery'])
        <div class="mt-12">
            <atom:heading>Props</atom:heading>
            <atom:docs.props :props="$entry['props']"/>
        </div>

        <div class="mt-6">
            <atom:caption>Source: {{ $entry['path'] }}</atom:caption>
        </div>
    @endunless
</atom:docs.layout>
