@props([
    'title' => null,
    'level' => 1,
    'crumbs' => [],
    'facts' => [],
    'size' => null,
])

{{-- The header of a page or a record: crumbs, the title with its status beside it, a row of key facts, and the actions.
     Slots: `status` (an <atom:status>), `meta` (more facts as free markup), `actions` (secondary), `menu` (the contents of
     the overflow menu) and `primary` (THE action for the record's current state: the host passes one, last, on the right).
     `crumbs` is a list of `['label' => ..., 'href' => ...]` (a string is a label with no link); the last one is the
     current page, printed as the record's own text; every other label goes through t(), which splits on a pipe, so a
     parent crumb holding record data must not contain one. `facts` is a list of strings or
     `['label' => ..., 'value' => ..., 'mono' => true]`. `title`, labels and values print as text. It is pure Blade and CSS, so it also renders in a
     modal or a slide-over (a form, never a whole record): pass `size="sm"` and `level="2"` there (the overflow trigger follows it, and so should the buttons you pass: default size on a page, `sm` there). Use the shell's own
     `crumbs` slot OR `crumbs` here, not both.
     Where the actions go is decided by the header's own width, never by how long the title or the meta line is, so two pages of one
     app lay out alike. The header is a container (`@container`); from 48rem (`@3xl`: 768px, the width where a title column of about
     370px is left beside an action group of three buttons) the actions sit beside the title block, bottom-aligned, and the title
     wraps inside its column; narrower, the title block takes the whole row (`basis-full`) and the actions are on a row of their own
     below, at the start. `size="sm"` (a modal or a slide-over, always narrower than that) keeps the old rule: the actions share the
     row when they fit and wrap when they do not.
     A size container takes its width from its parent alone (its content cannot size it), so the default header needs a parent that gives
     it one: a block does (the page column, the shells, <atom:page>). In a flex row put `flex-1 min-w-0` on it; in an `inline-block`, a
     `w-fit` box or an `auto` grid column it collapses to nothing (the title wraps a letter a line), and in a content-sized modal it no
     longer widens the dialog: a modal or slide-over takes `size="sm"`, which is not a container.
     A slot counts as content only if it holds more than comments and whitespace (hasActualContent), so a slot made of `@if`
     blocks that all come out false in a Livewire view, which still prints its `<!--[if BLOCK]-->` markers, draws nothing. --}}
@php
$level = in_array((int) $level, [1, 2, 3], true) ? (int) $level : 1;
$el = 'h'.$level;
$small = $size === 'sm';

$trail = collect($crumbs)->values()->map(fn ($crumb) => [
    'label' => is_scalar($crumb) ? (string) $crumb : data_get($crumb, 'label'),
    'href' => is_scalar($crumb) ? null : safe_url(data_get($crumb, 'href')),
]);

$items = collect($facts)->map(fn ($fact) => is_scalar($fact)
    ? ['label' => null, 'value' => (string) $fact, 'mono' => false, 'plain' => true]
    : ['label' => data_get($fact, 'label'), 'value' => data_get($fact, 'value'), 'mono' => (bool) data_get($fact, 'mono'), 'plain' => false]
)->filter(fn ($fact) => filled($fact['value']))->values();

/* A slot counts only when it holds something other than comments and whitespace: in a Livewire component a slot made of `@if`
   blocks that all come out false is still `<!--[if BLOCK]><![endif]-->` markers, which isNotEmpty() takes for content and
   would draw an empty overflow button and a wrapper around nothing. */
$has = fn ($s) => isset($s) && $s->hasActualContent();
$hasActions = $has($actions ?? null) || $has($menu ?? null) || $has($primary ?? null);
@endphp

<header {{ $attributes->class([
    'flex flex-wrap items-end justify-between gap-x-6 gap-y-3.5',
    // a container: where the actions go is the header's own width, not how long the title or the meta line is
    '@container w-full' => ! $small,
]) }} data-atom-page-header>
    <div @class(['min-w-0 max-w-full grow', 'basis-full @3xl:basis-0' => ! $small])>
        @if ($trail->isNotEmpty())
            <nav aria-label="{{ t('Breadcrumb') }}" class="mb-2" data-atom-page-crumbs>
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-fg-subtle">
                    @foreach ($trail as $crumb)
                        <li class="flex min-w-0 items-center gap-1.5">
                            @if ($loop->last)
                                {{-- the current crumb is usually the record's own name: data, never a translation key (t() splits on a pipe) --}}
                                <span class="truncate font-medium text-fg" aria-current="page">{{ $crumb['label'] }}</span>
                            @elseif ($crumb['href'])
                                <a href="{{ $crumb['href'] }}" class="truncate text-fg-muted hover:text-fg" wire:navigate>{{ t($crumb['label']) }}</a>
                            @else
                                <span class="truncate">{{ t($crumb['label']) }}</span>
                            @endif

                            @unless ($loop->last)
                                <atom:icon.right class="size-3.5 shrink-0" />
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- with neither a title nor a status there is no row, so the gap under the crumbs is not doubled --}}
        @if (filled($title) || $has($status ?? null))
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
            {{-- no title, no heading: an empty h1 would also hide the layout's fallback one (atom.css) --}}
            @if (filled($title))
                <{{ $el }} @class([
                    'm-0 min-w-0 break-words font-semibold text-balance',
                    'text-lg' => $small,
                    'text-2xl' => ! $small,
                ]) data-atom-page-header-title>{{ $title }}</{{ $el }}>
            @endif

            @if ($has($status ?? null))
                {{ $status }}
            @endif
        </div>
        @endif

        @if ($items->isNotEmpty() || $has($meta ?? null))
            <div @class([
                'mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-fg-muted',
                'text-xs' => $small,
            ]) data-atom-page-meta>
                @foreach ($items as $fact)
                    <span>
                        @if (filled($fact['label']))
                            {{ t($fact['label']) }}
                        @endif
                        <b class="{{ $fact['plain'] ? 'font-normal' : 'font-medium text-fg' }} {{ $fact['mono'] ? 'font-id' : '' }}">{{ $fact['value'] }}</b>
                    </span>
                @endforeach

                @if ($has($meta ?? null))
                    {{ $meta }}
                @endif
            </div>
        @endif
    </div>

    @if ($hasActions)
        <div @class(['flex flex-wrap items-center gap-1.5', 'max-w-full' => ! $small]) data-atom-page-actions>
            @if ($has($actions ?? null))
                {{ $actions }}
            @endif

            @if ($has($menu ?? null))
                <atom:dropdown position="bottom" align="end">
                    {{-- the trigger is the size of the header's other buttons: the control's own on a page, `sm` in a modal or slide-over --}}
                    <atom:button :size="$small ? 'sm' : null" icon="more" :aria-label="t('More actions')" />

                    <atom:menu class="w-[220px]" popover>
                        {{ $menu }}
                    </atom:menu>
                </atom:dropdown>
            @endif

            @if ($has($primary ?? null))
                {{ $primary }}
            @endif
        </div>
    @endif
</header>
