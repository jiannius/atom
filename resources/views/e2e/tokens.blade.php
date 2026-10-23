@php
// ?tw=floor|latest opts in to the consumer build (tests/consumer/build.mjs); ?density=compact|comfortable; ?fonts=0 passes fonts=false; ?dark=1 turns the
// dark class on; ?accent=%23rrggbb sets --color-accent the way a host does in its @theme.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
// written out in full: Tailwind finds a utility by reading the class as text, never by running this template
$tones = [
    'neutral' => 'bg-neutral-bg text-neutral-fg',
    'info' => 'bg-info-bg text-info-fg',
    'success' => 'bg-success-bg text-success-fg',
    'warning' => 'bg-warning-bg text-warning-fg',
    'danger' => 'bg-danger-bg text-danger-fg',
];
$roles = [
    'canvas' => 'bg-canvas',
    'surface' => 'bg-surface',
    'surface-sunken' => 'bg-surface-sunken',
    'surface-raised' => 'bg-surface-raised',
];
@endphp

<atom:html title="E2E: Tokens" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" :fonts="request()->query('fonts') === '0' ? false : 'geist'" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div class="mx-auto grid max-w-5xl gap-8 p-8" data-testid="sheet">
    {{-- type --}}
    <section class="grid gap-2">
        <h2 class="text-2xl font-semibold" data-testid="size-2xl">Page title 22</h2>
        <p class="text-xl" data-testid="size-xl">Section 18</p>
        <p class="text-lg" data-testid="size-lg">Section 16</p>
        <p class="text-base" data-testid="size-base">Reading text, long form and editors: base</p>
        <p class="text-sm" data-testid="size-sm">Body and UI text: sm</p>
        <p class="text-xs" data-testid="size-xs">Floor, counts and captions: xs</p>
        <p class="text-sm"><span class="font-medium" data-testid="weight-medium">Medium weight</span>, <span class="font-semibold" data-testid="weight-semibold">semibold weight</span>, <span class="font-id" data-testid="id">INV-2026-0042</span>, <span class="tabular-nums" data-testid="digits-a">1111.11</span> <span class="tabular-nums" data-testid="digits-b">8888.88</span> <span data-testid="digits-plain-a">1111.11</span> <span data-testid="digits-plain-b">8888.88</span></p>
        <p class="font-sans text-sm" data-testid="sans">The quick brown fox</p>
        <p class="font-mono text-sm" data-testid="mono">The quick brown fox</p>
    </section>

    {{-- roles --}}
    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ($roles as $role => $bg)
            <div class="{{ $bg }} rounded-panel border p-3 text-xs" data-testid="role-{{ $role }}">
                <div class="font-medium text-fg">{{ $role }}</div>
                <div class="text-fg-muted">muted</div>
                <div class="text-fg-subtle">subtle</div>
            </div>
        @endforeach
        <div class="rounded-panel border border-border-strong p-3 text-xs" data-testid="role-border-strong">border-strong</div>
    </section>

    {{-- tones --}}
    <section class="flex flex-wrap gap-2">
        @foreach ($tones as $tone => $classes)
            <span class="{{ $classes }} inline-flex h-[22px] items-center gap-1.5 rounded-pill px-2 text-xs font-medium" data-testid="tone-{{ $tone }}">
                <span class="size-1.5 rounded-full bg-current"></span>{{ ucfirst($tone) }}
            </span>
        @endforeach
        <span class="bg-accent-soft text-accent-ink inline-flex h-[22px] items-center gap-1.5 rounded-pill px-2 text-xs font-medium" data-testid="tone-accent">
            <span class="size-1.5 rounded-full bg-current"></span>Accent
        </span>
    </section>

    {{-- accent --}}
    <section class="flex flex-wrap items-center gap-2">
        <button type="button" class="h-control rounded-control bg-accent px-3 text-sm font-medium text-accent-foreground hover:bg-accent-hover" data-testid="accent-button">Primary action</button>
        <span class="size-7 rounded-nav bg-accent" data-testid="accent"></span>
        <span class="size-7 rounded-nav bg-accent-hover" data-testid="accent-hover"></span>
        <span class="size-7 rounded-nav bg-accent-soft" data-testid="accent-soft"></span>
        <span class="text-sm font-medium text-accent-ink" data-testid="accent-ink">Accent text</span>
    </section>

    {{-- controls --}}
    <section class="flex flex-wrap items-center gap-2">
        <button type="button" class="h-control rounded-control border border-border-strong bg-surface px-3 text-sm font-medium" data-testid="control-button">Button</button>
        <input type="text" placeholder="Search" class="h-control w-48 rounded-control border border-border-strong bg-surface px-2.5 text-sm" data-testid="control-input">
        <a href="#" class="inline-flex h-control items-center rounded-nav px-2.5 text-sm" data-testid="control-link">Nav row</a>
        <div class="grid size-16 place-items-center rounded-modal bg-surface-raised text-xs shadow-pop" data-testid="raised">raised</div>
        <div class="size-6 bg-white dark:bg-black" data-testid="dark-variant"></div>
    </section>

    {{-- a subtree can ask for the other density --}}
    <section data-atom-density="comfortable" class="flex items-center gap-2" data-testid="nested">
        <button type="button" class="h-control rounded-control border border-border-strong bg-surface px-3 text-sm" data-testid="nested-button">Comfortable inside</button>
        <span class="text-xs" data-testid="nested-xs">xs</span>
    </section>

    {{-- table: rows grow with a two-line cell and keep their padding --}}
    <section class="overflow-hidden rounded-panel border bg-surface">
        <table class="w-full border-collapse text-sm tabular-nums" data-testid="table">
            <tbody>
                <tr class="border-b" data-testid="row-one">
                    <td class="h-(--atom-row-min-h) px-row py-row leading-row" data-testid="cell-one">One line</td>
                    <td class="px-row py-row leading-row text-end">1,234.50</td>
                </tr>
                <tr data-testid="row-two">
                    <td class="h-(--atom-row-min-h) px-row py-row leading-row" data-testid="cell-two" style="width: 160px">A cell whose text wraps onto a second line when the column is narrow</td>
                    <td class="px-row py-row leading-row text-end">88.00</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="flex gap-2">
        <button type="button" class="h-control rounded-control border border-border-strong bg-surface px-3 text-sm" data-testid="focus-target">Focus me</button>
    </section>

    {{-- a dark island inside the page, whatever the page's own theme --}}
    <section class="dark rounded-panel bg-canvas p-3 text-fg" data-testid="island">
        <span class="bg-accent-soft text-accent-ink inline-flex h-[22px] items-center rounded-pill px-2 text-xs font-medium" data-testid="island-tone">Accent</span>
        <span class="text-sm font-medium text-accent-ink" data-testid="island-ink">Accent text</span>
        <button type="button" class="h-control rounded-control bg-accent px-3 text-sm font-medium text-accent-foreground" data-testid="island-accent-button">Primary action</button>
        <button type="button" class="h-control rounded-control border border-border-strong bg-surface px-3 text-sm" data-testid="island-focus">Focus me</button>
    </section>
</div>

@livewireScripts
</atom:html>
