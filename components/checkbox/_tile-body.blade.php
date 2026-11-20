@props([
    'content' => null,
    'caption' => null,
])

{{-- What a selectable tile (radio or checkbox in a `variant="tile"` group) holds besides its indicator: the body, kept clear of the indicator that sits over the
     top end, and the caption under it. `content` is the control's slot, or its label already through t(). --}}
<div class="min-w-0 pe-6 text-fg peer-disabled:text-fg-subtle" data-atom-tile-body>
    @if (! is_null($content))
        {{ $content }}
    @endif
</div>

@if ($caption)
    <atom:caption class="pe-6">{{ t($caption) }}</atom:caption>
@endif
