@props([
    'items' => [],   // what ActionPlacement::place() returned for one place: a list of items
    'place' => 'visible',   // visible, primary, pinned or menu
    'surface' => 'header',
    'small' => false,
    'separator' => null,   // the index the menu's separator goes before
])

{{-- A surface's placed actions of one place, drawn in order through <atom:action._item>. Private (underscore). It loops in PHP blocks, not with a foreach directive:
     in a Livewire view the loop and condition directives print morph markers even when they draw nothing, and a surface with no action must print the bytes it
     always did. A list with nothing in it prints nothing at all. --}}
@php foreach ($items as $index => $item): @endphp
@php if ($separator !== null && $index === $separator): @endphp
<atom:menu.separator />
@php endif; @endphp
<atom:action._item :item="$item" :place="$place" :surface="$surface" :small="$small" />
@php endforeach; @endphp
