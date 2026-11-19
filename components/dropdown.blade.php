@props([
    'position' => 'bottom',
    'align' => 'start',
    'locked' => false,
    'remote' => false,
])

{{-- `remote`: a menu printed once for triggers elsewhere in the page (layouts.sidebar's account menu). The slot is the menu alone; each trigger is a
     <button data-atom-dropdown-remote aria-controls="<the menu's id>">, and the one pressed last is the menu's trigger. The root has no box of its
     own (atom.css), so it takes no cell of a grid it sits in. --}}
@php
$classes = Arr::toCssClasses([
    // The menu's `position` is not set here: it is a top-layer [popover], so it
    // must be viewport-positioned to match floating-ui. atom.css owns that.
    'group/dropdown [:where(&)]:relative',
    '[:where(&_[data-atom-menu])]:transition',
    '[:where(&_[data-atom-menu])]:duration-300',
    '[:where(&_[data-atom-menu])]:ease-in-out',
    '[:where(&_[data-atom-menu])]:opacity-0',
    '[:where(&[data-open]_[data-atom-menu])]:opacity-100',
]);
@endphp

<div
x-data="dropdown({
    locked: @js($locked),{!! $remote ? "\n    remote: true," : '' !!}
    placement: @js($position.'-'.$align),
})"
{{ $attributes->class($classes)->merge($remote ? ['data-atom-dropdown-detached' => true] : []) }}
data-atom-dropdown>
    {{ $slot }}
</div>
