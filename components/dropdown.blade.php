@props([
    'position' => 'bottom',
    'align' => 'start',
    'locked' => false,
])

@php
$classes = Arr::toCssClasses([
    'group/dropdown relative',
    '[:where(&_[data-atom-menu])]:hidden',
    '[:where(&_[data-atom-menu])]:absolute',
    '[:where(&_[data-atom-menu])]:transition',
    '[:where(&_[data-atom-menu])]:duration-300',
    '[:where(&_[data-atom-menu])]:ease-in-out',
    '[:where(&_[data-atom-menu])]:opacity-0',
    '[:where(&[data-open]_[data-atom-menu])]:opacity-100',
]);
@endphp

<div x-data="dropdown({
    locked: @js($locked),
    placement: @js($position.'-'.$align),
})" {{ $attributes->class($classes) }} data-atom-dropdown>
    {{ $slot }}
</div>
