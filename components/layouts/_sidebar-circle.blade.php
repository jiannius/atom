@props([
    'anchor' => 'center',
])

{{-- The sidebar's collapse button when the top bar is empty: a round button on the sidebar's right edge, half in and half out, on the
     line of the brand row's caret (`anchor`: caret = the default brand row's caret, which sits on the app name's line; center = the
     brand row's middle; logo = the default link's tile). It sits inside the brand row, so it follows the caret and a host-widened
     sidebar. atom.css draws it (lg up, bar empty, sidebar shown) and puts it on the edge; the bar's own button takes over otherwise. --}}
<button
type="button"
x-on:click="$dispatch('toggle-sidebar')"
aria-expanded="true"
aria-controls="atom-sidebar"
aria-keyshortcuts="Control+B Meta+B"
aria-label="{{ t('Sidebar') }}"
title="{{ t('Toggle sidebar') }} (Ctrl/⌘ B)"
class="items-center justify-center rounded-full border border-border bg-canvas text-fg-muted shadow-sm hover:text-fg"
data-atom-sidebar-circle="{{ $anchor }}">
    <atom:icon.panel-left-close class="size-4 rtl:-scale-x-100"/>
</button>
