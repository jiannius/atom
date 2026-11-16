@props([
    'item',
    'place' => 'menu',   // visible, primary, pinned or menu
    'surface' => 'header',   // header, toolbar, bulk, card, panel or row
    'small' => false,   // a size="sm" header
])

{{-- One placed action, drawn as the element the surface gives it: a button, a link or a row of the ⋯ menu. Private (underscore): the surfaces call it
     with the items `ActionPlacement::place()` returned. The host's attribute bag goes through `:attributes` and the engine's own props are written on the
     tag, so they win over it, and `wire:click`, `x-on:click`, `href` and `confirm` land on the one element exactly once. A toned action confirms in its tone
     (A4) and is never the primary; a delete is `type="delete"` / `variant="delete"` and keeps their copy, their bulk count and `$wire.delete()`. Looks: the header's buttons are
     bordered (`sm` in a `size="sm"` header), its primary is `variant="primary"`; the toolbar's are ghost, and one with an icon keeps its label as `sr-only` on a phone;
     the bulk bar's are the control height, its pinned delete the tint; a card's are a link for an `href` (else ghost `xs`), a panel's the same at `sm`. --}}
@php
$tone = $item['tone'] ?? null;
$html = (string) $item['html'];
$icon = $item['icon'] ?? null;
$href = $item['href'] ?? null;
$confirm = \Jiannius\Atom\Services\ActionItems::confirmFor($tone, $item['confirm'] ?? null);

$bag = new \Illuminate\View\ComponentAttributeBag([
    ...$item['attributes']->getAttributes(),
    'wire:key' => $item['key'],
    'data-atom-action' => $place,
]);

/* an icon-only look below `sm` keeps the label for a screen reader (`sr-only`); the label is markup then, so the dialog's copy comes from the text */
$wrapped = $surface === 'toolbar' && $place === 'visible' && filled($icon);
$plain = \Jiannius\Atom\Services\ConfirmProp::plainLabel(strip_tags($html) === $html ? $html : '');

if ($wrapped && $plain !== '' && $confirm !== false && $confirm !== null) {
    $given = is_array($confirm) ? $confirm : (is_string($confirm) && $confirm !== '' ? ['heading' => $confirm] : []);

    $confirm = [...['heading' => str_ends_with($plain, '?') ? $plain : $plain.'?', 'buttonConfirm' => $plain], ...$given];
}

/* the label is built here, not with an `@if` inside the tag: in a Livewire view a directive prints morph markers, and a label with a `<` in it is markup to the
   button, which then neither wraps it nor reads its text for the dialog */
$content = $wrapped ? '<span class="max-sm:sr-only">'.$html.'</span>' : $html;

$link = in_array($surface, ['card', 'panel'], true) && $place === 'visible' && filled($href) && $confirm === null;

$look = match (true) {
    $place === 'primary' => ['variant' => 'primary'],
    $place === 'pinned' && $tone === 'delete' => ['type' => 'delete'],
    $place === 'pinned' => ['variant' => 'danger', 'inverted' => true],
    $surface === 'toolbar' => ['variant' => 'ghost'],
    $surface === 'card' => ['variant' => 'ghost', 'size' => 'xs'],
    $surface === 'panel' => ['variant' => 'ghost', 'size' => 'sm'],
    $surface === 'header' && $small => ['size' => 'sm'],
    default => [],
};
@endphp
@if ($place === 'menu')
<atom:menu.item :attributes="$bag" :variant="$tone ?? 'default'" :icon="$icon" :href="$href" :newtab="$item['newtab']" :passphrase="$item['passphrase']" :confirm="$confirm">{!! $html !!}</atom:menu.item>
@elseif ($link)
<atom:link :attributes="$bag" :icon="$icon" :href="$href" :newtab="$item['newtab']" :class="$surface === 'panel' ? 'text-sm' : 'text-xs'">{!! $html !!}</atom:link>
@else
<atom:button :attributes="$bag" :variant="$look['variant'] ?? null" :size="$look['size'] ?? null" :type="$look['type'] ?? null" :inverted="$look['inverted'] ?? null" :icon="$icon" :href="$href" :newtab="$item['newtab']" :passphrase="$item['passphrase']" :confirm="$confirm">{!! $content !!}</atom:button>
@endif
