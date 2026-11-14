{{-- A hairline between groups of <atom:menu.item>s. It bleeds across the panel's 4px padding to the border, and takes 4px above and
     below (the items' own 1px margins sit inside it). Not an item: keyboard navigation reads [data-atom-menu-item] only. --}}
<div role="separator" {{ $attributes->class('-mx-1 my-1 h-px bg-border') }} data-atom-menu-separator></div>
