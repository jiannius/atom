@php
// Long unbroken text in every kind of timeline, in a narrow column: ?tw=floor|latest, ?dark=1
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$long = str_repeat('x', 400);
@endphp

<atom:html title="E2E: timeline wrap" :vite="[]" :dark="request()->query('dark') === '1'" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="grid gap-6 p-4" data-testid="wrap">
    <atom:timeline data-testid="wrap-event">
        <atom:timeline.item title="{{ $long }}" body="{{ $long }}" actor="{{ $long }}" time="{{ $long }}" wire:key="e1">
            <x-slot:detail>{{ $long }}</x-slot:detail>
        </atom:timeline.item>
    </atom:timeline>

    <atom:timeline kind="step" data-testid="wrap-step">
        <atom:timeline.item title="{{ $long }}" state="current" status="Running" time="{{ $long }}" wire:key="s1">{{ $long }}</atom:timeline.item>
    </atom:timeline>

    <atom:timeline kind="path" data-testid="wrap-path">
        <atom:timeline.item title="{{ $long }}" state="done" time="{{ $long }}" actor="{{ $long }}" wire:key="p1">{{ $long }}</atom:timeline.item>
        <atom:timeline.item title="Next" state="current" actor="{{ $long }}" wire:key="p2"/>
    </atom:timeline>

    <atom:timeline kind="message" data-testid="wrap-message">
        <atom:timeline.item speaker="{{ $long }}" time="{{ $long }}" body="{{ $long }}" wire:key="m1"/>
    </atom:timeline>
</div>

@livewireScripts
</atom:html>
