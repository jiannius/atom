@props([
    'locked' => false,
])

<div
x-data="contextMenu({ locked: {{ $locked ? 'true' : 'false' }} })"
{{ $attributes }}
data-atom-context-menu>
    <div data-atom-context-menu-trigger>
        {{ $slot }}
    </div>

    <atom:menu popover>
        {{ $menu }}
    </atom:menu>
</div>
