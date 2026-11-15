@props([
    'variant' => 'card',
    'level' => 2,
    'title' => null,
    'count' => null,
    'actions' => null,
    'menu' => null,
])

{{-- The row a card or a panel opens with: a title, then (panel only) a count, and at the end the actions and a ⋯ menu. Private
     (underscore): <atom:card> and <atom:panel> draw it, so the two cannot drift apart; they differ only in the title (`card`: the
     12px muted one of a rail box; `panel`: a 14px semibold section title) and in the row's own edge (a panel's is a hairline and
     the cells' padding, set here; a card's is the card's, set by the card on `class`). `title` is a string (through t()) or a slot;
     `actions` and `menu` are the slots the caller got, or null. Nothing is drawn without a title, actions or a menu. A slot counts
     as content only if it holds more than comments and whitespace (hasActualContent), so a slot of `@if` blocks that all come out
     false in a Livewire view, which still prints its `<!--[if BLOCK]-->` markers, draws nothing. The title sits in the heading
     element and the count beside it, so the count is not read as part of the heading. The ⋯ is named "More actions for" and the title's text when there is one. The trailing group has a negative block
     margin so the ghost `sm` ⋯ trigger, which is taller than the text, does not make a row with a menu taller than one without. --}}
@php
$panel = $variant === 'panel';
$level = in_array((int) $level, [2, 3, 4], true) ? (int) $level : 2;
$el = 'h'.$level;

$has = fn ($s) => $s instanceof \Illuminate\View\ComponentSlot ? $s->hasActualContent() : filled($s);
$hasTitle = $has($title);
$hasActions = $has($actions);
$hasMenu = $has($menu);

/* the ⋯ is named for the box it belongs to ("More actions for Parts used"), so two of them on a page are not both "More actions";
   the heading is the plain text of the title (a slot's markup stripped), translated once, and no heading leaves the plain name */
$titleText = match (true) {
    ! $hasTitle => '',
    $title instanceof \Illuminate\View\ComponentSlot => trim(preg_replace('/\s+/', ' ', strip_tags((string) $title))),
    default => trim((string) t($title)),
};
$moreLabel = $titleText !== '' ? t('More actions for :heading', ['heading' => $titleText]) : null;
@endphp

@if ($hasTitle || $hasActions || $hasMenu)
    <div {{ $attributes->class([
        'flex items-center justify-between gap-3',
        'border-b px-row py-2.5' => $panel,
    ]) }} data-atom-heading-row>
        <div class="flex min-w-0 items-baseline gap-2">
            @if ($hasTitle)
                <{{ $el }} @class([
                    'm-0 min-w-0',
                    'text-sm font-semibold text-fg' => $panel,
                    'text-xs font-medium text-fg-subtle' => ! $panel,
                ]) data-atom-heading-row-title>{{ $title instanceof \Illuminate\View\ComponentSlot ? $title : t($title) }}</{{ $el }}>
            @endif

            @if ($panel && filled($count))
                <span class="shrink-0 text-xs font-normal tabular-nums text-fg-subtle" data-atom-heading-row-count>{{ $count }}</span>
            @endif
        </div>

        @if ($hasActions || $hasMenu)
            <div @class(['flex shrink-0 items-center gap-2', 'text-sm -my-1' => $panel, 'text-xs -my-1.5' => ! $panel]) data-atom-heading-row-actions>
                @if ($hasActions)
                    {{ $actions }}
                @endif

                @if ($hasMenu)
                    <atom:menu._more variant="ghost" size="sm" :label="$moreLabel">
                        {{ $menu }}
                    </atom:menu._more>
                @endif
            </div>
        @endif
    </div>
@endif
