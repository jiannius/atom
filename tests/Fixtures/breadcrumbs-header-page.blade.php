<div>
    @if (in_array($page, ['new', 'deep'], true))
        <atom:page.header title="{{ $page === 'new' ? 'New page' : 'Detail' }}" />
    @endif

    <nav>
        <a href="/atom/e2e/breadcrumbs-header/old" wire:navigate data-go="old">Old</a>
        <a href="/atom/e2e/breadcrumbs-header/new" wire:navigate data-go="new">New</a>
        <a href="/atom/e2e/breadcrumbs-header/plain" wire:navigate data-go="plain">Plain</a>
        <a href="/atom/e2e/breadcrumbs-header/deep" wire:navigate data-go="deep">Deep</a>
    </nav>
</div>
