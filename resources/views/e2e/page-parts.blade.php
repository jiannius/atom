@php
// ?tw=floor|latest opts in to the consumer build; ?dark=1 turns the dark class on; ?accent=%23rrggbb sets --color-accent the way a host does.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : null;
@endphp

<atom:html title="E2E: page parts" density="compact" :vite="[]" :dark="request()->query('dark') === '1'" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
@if ($accent)
<style>:root { --color-accent: {{ $accent }}; }</style>
@endif

<div class="mx-auto grid max-w-5xl gap-6 p-8">
    {{-- status: every tone, and a running one --}}
    <div class="flex flex-wrap gap-2">
        @foreach (['neutral', 'info', 'success', 'warning', 'danger', 'accent'] as $tone)
            <atom:status :tone="$tone" :label="ucfirst($tone)" :data-testid="'status-'.$tone"/>
        @endforeach
        <atom:status tone="info" running label="Running" data-testid="status-running"/>
    </div>

    {{-- stats --}}
    <atom:stat.group data-testid="stats">
        <atom:stat label="Open jobs" value="24" breakdown="6 due this week" data-testid="stat-link" href="/jobs?status=open"/>
        <atom:stat label="Parts cost" prefix="RM" value="12,480.00" delta="+6%" breakdown="across 9 jobs" data-testid="stat-money"/>
        <atom:stat label="Late" value="3" tone="danger" data-testid="stat-danger"/>
        <atom:stat label="Rework" value="4" delta="-2" inverse data-testid="stat-inverse"/>
    </atom:stat.group>

    {{-- the three kinds of empty state, and the small one --}}
    <div class="rounded-panel border bg-surface" data-testid="empty-first-use">
        <atom:empty kind="first-use" heading="No jobs yet" subheading="Create the first work order to start tracking it.">
            <x-slot:action><atom:button size="sm" icon="add">New job</atom:button></x-slot:action>
        </atom:empty>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="empty-no-results">
        <atom:empty kind="no-results" icon="search" heading="No jobs match" subheading="Try another search or clear the filters.">
            <x-slot:action><atom:button size="sm">Clear filters</atom:button></x-slot:action>
        </atom:empty>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="empty-error">
        <atom:empty kind="error">
            <x-slot:action><atom:button size="sm" icon="refresh">Try again</atom:button></x-slot:action>
        </atom:empty>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="empty-done">
        <atom:empty kind="done"/>
    </div>

    <div class="rounded-panel border bg-surface" data-testid="empty-sm">
        <atom:empty size="sm" kind="first-use"/>
    </div>
</div>

@livewireScripts
</atom:html>
