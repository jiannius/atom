@props([
    'heading' => null,
    'subheading' => null,
    'layout' => 'aside',
    'level' => 2,
    'variant' => null,
])

{{-- One section of a form or a settings page: a heading and a line of help, then the fields in a grid.
     `aside` (the default) puts the heading in a 220px column beside the fields once the section is 768px wide, and above them
     below that; `stacked` always puts it above, which is the layout for a narrow place (a slide-over, a modal). The fields are
     a grid of two columns once their own box is 448px wide, one below, whatever the screen: `class="@md:col-span-2"` on a
     field spans the row. A section is separated from the one before it by a hairline, and takes the gap its parent form
     (or form grid) gives it, so use it in <atom:form>, <atom:form.grid cols="1"> or <atom:form.modal cols="1">.
     `footer` is an optional row under the fields (right-aligned) for a per-section save: give the section its own form
     then. `level` is the heading's level (2 on a page, 3 in a modal).
     `variant="danger"` is the danger zone at the end of a settings or edit page (archive, delete): the heading in the danger
     ink and the fields in a box with a danger hairline. At most one, and last; on a record the same actions live in the
     menu, behind a confirm. --}}
@php
$layout = $layout === 'stacked' ? 'stacked' : 'aside';
$danger = $variant === 'danger';
$level = in_array((int) $level, [2, 3, 4], true) ? (int) $level : 2;
$el = 'h'.$level;

$hasText = filled($heading) || filled($subheading);
$hasFooter = isset($footer) && $footer->isNotEmpty();
@endphp

<section {{ $attributes->class('@container col-span-full min-w-0 border-t pt-6 first:border-t-0 first:pt-0') }} data-atom-form-section data-layout="{{ $layout }}" @if ($danger) data-variant="danger" @endif>
    <div @class([
        'grid gap-x-6 gap-y-3',
        '@3xl:grid-cols-[220px_minmax(0,1fr)]' => $layout === 'aside' && $hasText,
    ])>
        @if ($hasText)
            <div class="min-w-0" data-atom-form-section-label>
                @if (filled($heading))
                    <{{ $el }} @class(['m-0 text-base font-semibold', 'text-danger-fg' => $danger]) data-atom-form-section-heading>{{ t($heading) }}</{{ $el }}>
                @endif

                @if (filled($subheading))
                    <p class="m-0 mt-1 text-sm text-fg-subtle" data-atom-form-section-subheading>{{ t($subheading) }}</p>
                @endif
            </div>
        @endif

        <div @class(['@container min-w-0', 'rounded-panel border border-danger-fg/30 p-4' => $danger]) data-atom-form-section-body>
            <div class="grid grid-cols-1 gap-x-4 gap-y-3.5 @md:grid-cols-2" data-atom-form-section-fields>
                {{ $slot }}
            </div>

            @if ($hasFooter)
                <div {{ $footer->attributes->class('mt-5 flex flex-wrap items-center justify-end gap-2') }} data-atom-form-section-footer>
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</section>
