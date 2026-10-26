<div>
    @if (in_array($page, ['new', 'deep'], true))
        <atom:page.header title="{{ $page === 'new' ? 'New page' : 'Detail' }}" />
    @endif

    @if ($page === 'late')
        <button type="button" wire:click="load" data-load>Load</button>

        @if ($loaded)
            <atom:page.header title="Late page" />
        @endif
    @endif

    <nav>
        <a href="/atom/e2e/breadcrumbs-header/old{{ $carry }}" wire:navigate data-go="old">Old</a>
        <a href="/atom/e2e/breadcrumbs-header/new{{ $carry }}" wire:navigate data-go="new">New</a>
        <a href="/atom/e2e/breadcrumbs-header/plain{{ $carry }}" wire:navigate data-go="plain">Plain</a>
        <a href="/atom/e2e/breadcrumbs-header/late{{ $carry }}" wire:navigate data-go="late">Late</a>
        <a href="/atom/e2e/breadcrumbs-header/deep{{ $carry }}" wire:navigate data-go="deep">Deep</a>
    </nav>
</div>
