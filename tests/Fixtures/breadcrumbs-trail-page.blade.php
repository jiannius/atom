<div>
    <nav class="flex flex-wrap gap-3 p-4">
        @foreach ($pages as $slug)
            <a href="/atom/e2e/breadcrumbs-trail/{{ $slug }}" wire:navigate data-go="{{ $slug }}">{{ $slug }}</a>
        @endforeach
    </nav>
</div>
