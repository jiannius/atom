@props([
    'sticky' => false,
    'bar' => false,
    'dirty' => false,
])

{{-- The footer of a form, and its save bar. Three shapes:
     - plain: the buttons in a row, the first at the start and the last at the end (the default `Save`).
     - `sticky`: pinned to the bottom of a modal or slide-over (the footer <atom:form.modal> draws), bleeding to the panel's edges.
     - `bar`: the save bar of a PAGE form: a card that floats at the bottom of the screen while the page is longer than it. It is a plain
       block in the form's flow, so it is exactly as wide as the form's sections above it, inside the page's own padding; it draws a
       solid surface (`bg-surface`, no blur, nothing behind it shows through), a 1px border, the panel radius (10px) and the pop shadow, and
       paints nothing outside its own box, so no shell has to clip it. Sticky, 0.75rem (plus the device's bottom inset) above the bottom of
       the screen, so the page shows beneath it.
       Bottom room: <atom:page> reserves --atom-page-pb (120px) as its bottom padding for the bar. The card takes all of it back but the
       0.75rem it floats by, as a negative bottom margin of (--atom-page-pb - 0.75rem - the inset). At the end of the page the card
       therefore sits in flow right below the last section, 0.75rem above the page's bottom edge: the same place it holds while it is
       stuck, so there is no hole under the last section and no jump as it unsticks. (A sticky box that is stuck takes its place from its
       border box, not its margin box, so the margin moves where the page ENDS, not where the card sticks.) Outside a page the variable is
       missing, the margin comes out as 0.75rem, and the card simply has that much room under it. A page that sets its own room
       (`[--atom-page-pb:80px]`) is followed.
       `dirty` adds a muted "Unsaved changes" after the start slot's content, shown only while the form holds changes that were not saved
       (the bar itself is always there; without `dirty` there is no label at all). See resources/js/alpinejs/form-dirty.js for what counts as a
       change and what clears it.
       The `start` slot is what sits at its start (live totals): muted text, wrapping, with a <b> or <strong> in it
       set as the figure (`text-fg`, semibold, tabular). The default slot is the buttons, at the end, in the order the host writes them (a
       ghost Cancel, a secondary, then the submit, which is the screen's one primary action); full width on a phone, the submit growing.
       Put it last inside the page's form, and give the page its own bottom room (<atom:page> does). A bottom <atom:toast> sits above
       the card while one is on screen (helpers/toast-lift.js).
     The bleed of `sticky` in a modal rests on the p-6 the modal draws: a sticky element is held inside its scroll
     container's CONTENT box, so `bottom-0` would park it a padding's height above the panel edge and a strip of
     scrolling content would stay visible underneath. `-bottom-6` moves the stop to the panel edge, `pb-6` refills
     what that exposes, `-mb-6` absorbs the padding once the scroll reaches the end, and -mx-6/px-6 lets the
     background and the divider span the full width. --}}
@php
$hasStart = isset($start) && $start->hasActualContent();
$dirty = $bar && (bool) $dirty;
$stickyBar = $sticky && ! $bar;
@endphp

<div {{ $attributes->class([
    'flex items-center',
    'justify-between gap-3' => ! $bar,
    'sticky isolate z-10 flex-wrap gap-2 rounded-panel border border-border bg-surface px-4 py-3 shadow-pop' => $bar,
    'bottom-[calc(0.75rem_+_env(safe-area-inset-bottom,0px))] mb-[calc((var(--atom-page-pb,0px)_-_0.75rem_-_env(safe-area-inset-bottom,0px))*-1)]' => $bar,
    'sticky -bottom-6 z-10 bg-surface -mx-6 px-6 pt-4 pb-6 -mb-6 border-t mt-auto' => $stickyBar,
]) }} data-atom-form-actions @if ($bar) data-atom-save-bar @endif>
    @if ($hasStart || $dirty)
        {{-- With `dirty` the group is the formDirty scope (resources/js/alpinejs/form-dirty.js): the label shows while it says so, and a group that is only the label shows with it; x-cloak keeps both from showing before Alpine runs --}}
        <div {{ ($hasStart ? $start->attributes : new \Illuminate\View\ComponentAttributeBag)->class('flex min-w-0 flex-wrap items-baseline gap-x-3.5 gap-y-1 text-fg-muted tabular-nums [&_:is(b,strong)]:font-semibold [&_:is(b,strong)]:text-fg [&_:is(b,strong)]:tabular-nums') }} data-atom-form-actions-start @if ($dirty) x-data="formDirty" @unless ($hasStart) x-show="dirty" x-cloak @endunless @endif>
            @if ($hasStart) {{ $start }} @endif

            @if ($dirty)
                <span x-show="dirty" x-cloak data-atom-form-dirty>{{ t('Unsaved changes') }}</span>
            @endif
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
