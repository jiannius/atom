@props([
    'sticky' => false,
    'bar' => false,
])

{{-- The footer of a form, and its save bar. Three shapes:
     - plain: the buttons in a row, the first at the start and the last at the end (the default `Save`).
     - `sticky`: pinned to the bottom of a modal or slide-over (the footer <atom:form.modal> draws), bleeding to the panel's edges.
     - `bar`: the save bar of a PAGE form. Sticky at the bottom of the screen. Its CONTENT is aligned to the page column: the bar's
       own box is the page's padding box (it bleeds out to the edges <atom:page> pads) and pads itself by the page's gutter, whatever
       the page's width. Its blurred surface (::before) and the hairline above it (::after) fill that box, and atom.css goes on from there to the edge
       of the screen with a box-shadow spread, clipped by a clip-path (in the sidebar shell from the shell's content edge, in the
       top-nav shell from the screen's left edge), the way a full-width bar does: atom.css sets --atom-bar-left and --atom-bar-right
       on the shells' <main>. A shadow takes no room, so nothing scrolls sideways or prints smaller. The sidebar shell's bleed assumes
       the page starts at <main>'s content edge (no mx-auto on <atom:page>). Outside a shell and a page the variables are missing: the
       surface is the bar's own box, it bleeds nowhere and keeps a 1rem padding, so it never overflows its parent.
       It also takes the page's 120px of bottom room as its own (a negative margin), so at the end of the page it sits flush with the
       bottom, as it does everywhere above it, instead of 120px up.
       The `start` slot is what sits at its start (live totals, "Unsaved changes"); the default slot is the buttons, at the end,
       full width on a phone. Put it last inside the page's form, and give the page its own bottom room (<atom:page> does).
       The submit button is the screen's one primary action.
     The bleed of `sticky` in a modal rests on the p-6 the modal draws: a sticky element is held inside its scroll
     container's CONTENT box, so `bottom-0` would park it a padding's height above the panel edge and a strip of
     scrolling content would stay visible underneath. `-bottom-6` moves the stop to the panel edge, `pb-6` refills
     what that exposes, `-mb-6` absorbs the padding once the scroll reaches the end, and -mx-6/px-6 lets the
     background and the divider span the full width. --}}
@php
$hasStart = isset($start) && $start->isNotEmpty();
$stickyBar = $sticky && ! $bar;
@endphp

<div {{ $attributes->class([
    'flex items-center gap-3',
    'justify-between' => ! $bar,
    'bottom-0 z-10 flex-wrap gap-x-7 gap-y-3 sticky isolate' => $bar,
    'before:absolute before:inset-0 before:-z-10 before:bg-surface/95 before:backdrop-blur-sm after:absolute after:inset-x-0 after:top-0 after:-z-10 after:h-px after:bg-border' => $bar,
    'ms-[calc(var(--atom-gutter-s,0px)*-1)] me-[calc(var(--atom-gutter-e,0px)*-1)] ps-[var(--atom-gutter-s,1rem)] pe-[var(--atom-gutter-e,1rem)] mb-[calc(var(--atom-page-pb,0px)*-1)] pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom,0px))]' => $bar,
    'sticky -bottom-6 z-10 bg-surface -mx-6 px-6 pt-4 pb-6 -mb-6 border-t' => $stickyBar,
]) }} data-atom-form-actions @if ($bar) data-atom-save-bar @endif>
    @if ($hasStart)
        <div {{ $start->attributes->class('flex min-w-0 flex-wrap items-baseline gap-x-5 gap-y-1 tabular-nums') }} data-atom-form-actions-start>
            {{ $start }}
        </div>
    @endif

    @if ($bar)
        <div class="flex w-full flex-wrap items-center justify-end gap-2 max-sm:[&>[type=submit]]:flex-1 sm:ms-auto sm:w-auto" data-atom-form-actions-buttons>
            @if ($slot->isEmpty())
                <atom:button type="submit">{{ t('Save') }}</atom:button>
            @else
                {{ $slot }}
            @endif
        </div>
    @elseif ($slot->isEmpty())
        <atom:button type="submit">{{ t('Save') }}</atom:button>
    @else
        {{ $slot }}
    @endif
</div>
