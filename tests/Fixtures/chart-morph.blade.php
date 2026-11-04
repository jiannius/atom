<div class="space-y-4">
    <div>
        Renders: <span data-renders>{{ $renders }}</span>
        <button type="button" wire:click="bump" data-bump>Bump</button>
    </div>

    {{-- a chart under a server-rendered root that changes with the render --}}
    <atom:chart type="bar" :data="[['label' => 'Mon', 'value' => 32, 'tooltip' => '32'], ['label' => 'Tue', 'value' => 48, 'tooltip' => '48']]" data-state="render-{{ $renders }}" data-testid="morph-bar"/>
    <atom:chart type="trend" :data="[8, 12, 9, 14, 11, 18]" data-testid="morph-trend"/>
    <atom:chart type="area" :data="[['label' => 'Mon', 'value' => 32, 'tooltip' => '32'], ['label' => 'Tue', 'value' => 48, 'tooltip' => '48']]" data-testid="morph-area"/>
</div>
