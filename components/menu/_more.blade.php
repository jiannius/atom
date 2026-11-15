@props([
    'variant' => null,
    'size' => null,
    'tooltip' => false,
    'wide' => true,
    'label' => null,
])

{{-- The one ⋯ trigger: the dropdown (aligned to the end), the `more` icon button named "More actions" and the popover menu around the
     items it is given. Private (underscore): the page header, the table toolbar, a table row and the bulk bar's `checked-menu` slot draw it, each
     with its own variant and size, so a trigger is the same control wherever it sits. `variant` and `size` are the button's (null is the
     button's own default); `tooltip` names it by a tooltip instead of an aria-label (the toolbar's, where there is room to hover); `:wide="false"`
     leaves the menu at its minimum width (a row's, which holds a few short items), the default is the 220px panel. `label` is the button's
     accessible name when it is not the plain "More actions" (a card or panel names the box it belongs to). --}}
<atom:dropdown position="bottom" align="end">
    @if ($tooltip)
        <atom:button :variant="$variant" :size="$size" icon="more" tooltip="More actions" />
    @else
        <atom:button :variant="$variant" :size="$size" icon="more" :aria-label="$label ?: t('More actions')" />
    @endif

    <atom:menu :class="$wide ? 'w-[220px]' : null" popover>
        {{ $slot }}
    </atom:menu>
</atom:dropdown>
