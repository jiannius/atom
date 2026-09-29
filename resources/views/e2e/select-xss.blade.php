@php
$hostile = '<img src=x onerror="window.__xss=1">Ann';
$breakout = 'red" data-injected="1';
$declaration = 'red; position: fixed; inset: 0; z-index: 9999';

$opts = [
    ['value' => 'a', 'label' => $hostile, 'color' => $breakout],
    ['value' => 'b', 'label' => 'Plain'],
    ['value' => 'c', 'label' => 'Declaration', 'color' => $declaration],
    ['value' => 'd', 'label' => 'Green', 'color' => '#22c55e'],
];
@endphp

<atom:html title="E2E: Select XSS" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Option labels that hold markup, through every way a select gets its options:
     remote (the served GetOptions fixture), static (the client-side fallback
     builder), the trusted `html` key, the server-rendered native select, and the
     multiple / list / filter variants that render the picked options themselves. --}}
<div class="p-4 space-y-4">
    <atom:select variant="listbox" label="Remote" options="hostile-people" data-select="remote" />

    <atom:select variant="listbox" label="Remote colours" options="hostile-people" multiple data-select="remote-multiple" />

    <atom:select variant="listbox" label="Trusted" options="trusted-html" data-select="trusted" />

    <atom:select variant="listbox" label="Static" data-select="static" :options="$opts" />

    <atom:select variant="listbox" label="Multiple" multiple data-select="multiple" :options="$opts" />

    <atom:select variant="listbox" label="List" multiple="list" data-select="list" :options="$opts" />

    <atom:select variant="filter" label="Filter" data-select="filter" :options="$opts" />

    <atom:select variant="filter" label="Filter multiple" multiple data-select="filter-multiple" :options="$opts" />

    {{-- the native select reads string options server-side, through its own branch --}}
    <atom:select variant="native" label="Native remote" options="hostile-people" data-select="native-remote" />

    <atom:select variant="native" label="Native remote groups" options="hostile-groups" data-select="native-remote-groups" />

    <atom:select
    variant="native"
    label="Native"
    data-select="native"
    :options="[
        ['value' => 'a', 'label' => '</select>'.$hostile],
    ]" />
</div>

@livewireScripts
</atom:html>
