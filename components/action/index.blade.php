@props([
    'label' => null,
    'icon' => null,
    'priority' => null,
    'tone' => null,
    'confirm' => null,
    'href' => null,
    'newtab' => false,
    'passphrase' => '',
    'phrase' => null,   // alias of passphrase
])

{{-- One action of a surface: written where a button or a menu item was, drawn once by the surface it sits in (page.header today), as a visible button,
     the primary, or a row of its ⋯ menu. The surface sets the look, the cap and the order; the action says what it is.
     `label` is plain text (a lang key, or a `[key, replacements]` pair, translated once, so record data with a pipe is safe); markup goes in the slot.
     `priority` is `primary` (the screen's next step), `normal` (frequent) or `low` (a utility: duplicate, print, copy link); left out, the slot gives it
     (`primary` slot: primary, `menu` slot: low, any other: normal). `tone` is `warning`, `danger` or `delete`; a toned action asks before it acts
     (`:confirm="false"` opts out), is never a visible button, and goes after the others in the menu. `href`, `newtab`, `passphrase` and `confirm` are the button's;
     every other attribute (`wire:*`, `x-on:*`, `disabled`, `data-*`, `class`, `wire:key`) lands on the one element the action becomes.
     It draws nothing itself: a marker comment stands for it in the slot, and the surface strips it. An action that no surface takes draws nothing and is logged.
     `variant`, `color`, `size`, `type`, `inverted`, `block`, `social` and `tooltip` are refused: the surface sets the look. There is no `name`: it would read as an App\Actions class. --}}
@php
foreach (['variant', 'color', 'size', 'type', 'inverted', 'block', 'social', 'tooltip'] as $refused) {
    if ($attributes->has($refused)) {
        throw new \InvalidArgumentException("<atom:action> does not take `{$refused}`: the surface it sits in sets the look. Use `tone` (warning, danger, delete) for a consequence and `priority` (primary, normal, low) for its rank.");
    }
}

if ($priority !== null && ! in_array($priority, \Jiannius\Atom\Services\ActionPlacement::PRIORITIES, true)) {
    throw new \InvalidArgumentException('<atom:action> priority must be one of '.implode(', ', \Jiannius\Atom\Services\ActionPlacement::PRIORITIES).'; got ['.(is_scalar($priority) ? $priority : gettype($priority)).'].');
}

if ($tone !== null && ! in_array($tone, \Jiannius\Atom\Services\ActionPlacement::TONES, true)) {
    throw new \InvalidArgumentException('<atom:action> tone must be one of '.implode(', ', \Jiannius\Atom\Services\ActionPlacement::TONES).'; got ['.(is_scalar($tone) ? $tone : gettype($tone)).'].');
}

if ($label instanceof \Illuminate\Contracts\Support\Htmlable) {
    throw new \InvalidArgumentException('<atom:action> `label` is text, not markup: put the markup in the slot (`<atom:action>Save <b>draft</b></atom:action>`) or pass a string.');
}

$hasSlot = $slot->hasActualContent();
$hasLabel = $label !== null && $label !== '' && $label !== [];

if (preg_match(\Jiannius\Atom\Services\ActionItems::PATTERN, (string) $slot)) {
    throw new \InvalidArgumentException('An <atom:action> cannot hold another <atom:action>: write the actions side by side in the surface\'s slot.');
}

if ($hasSlot && $hasLabel) {
    throw new \InvalidArgumentException('<atom:action> takes a `label` or a slot, not both.');
}

/* the text of a slot: its markup stripped, and what an icon's `<style>` holds is not text */
$text = $hasLabel
    ? trim(app('atom')->translateOnce($label))
    : trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('#<(style|script)\b.*?</\1>#is', '', (string) $slot)))));

if (! $hasSlot && $text === '') {
    throw new \InvalidArgumentException('<atom:action> needs a label: pass `label` or put the text in the slot.');
}

if ($text === '' && blank($attributes->get('aria-label'))) {
    throw new \InvalidArgumentException('<atom:action> with markup and no text (an icon alone) would be a button with no name: pass `label`, or `aria-label` on the action.');
}

if ($text === '') {
    $text = trim((string) $attributes->get('aria-label'));
}

$marker = app(\Jiannius\Atom\Services\ActionItems::class)->add([
    'label' => $text,
    'html' => $hasSlot ? trim((string) $slot) : e($text),
    'icon' => $icon,
    'priority' => $priority,
    'tone' => $tone,
    'confirm' => $confirm,
    'href' => $href,
    'newtab' => $newtab,
    'passphrase' => $passphrase ?: $phrase,
    'attributes' => $attributes,
]);
@endphp
{!! $marker !!}
