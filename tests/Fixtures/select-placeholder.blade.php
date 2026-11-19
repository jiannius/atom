<div class="space-y-4">
    {{-- a native select per way of binding it; the server values are printed as JSON so the spec can tell null from "" --}}
    @php($options = [['value' => '30', 'label' => 'Manual'], ['value' => '31', 'label' => 'Automatic']])

    <atom:select label="Plain" wire:model="plain" :options="$options" data-probe="plain" />
    <atom:select label="Live" wire:model.live="live" :options="$options" data-probe="live" />
    <atom:select label="Region" wire:model.live="region" :placeholder="false" data-probe="region">
        <atom:select.option value="">All</atom:select.option>
        <atom:select.option value="north">North</atom:select.option>
        <atom:select.option value="south">South</atom:select.option>
    </atom:select>
    <atom:select label="Multiple" multiple wire:model.live="multi" :options="$options" data-probe="multi" />

    <div x-data="{ name: 'unset' }">
        <atom:select label="Alpine" x-model="name" :options="$options" data-probe="alpine" />
        <span data-alpine-model x-text="JSON.stringify(name)"></span>
    </div>

    <atom:button type="button" wire:click="$refresh" data-refresh>Refresh</atom:button>

    <div>
        @foreach (['plain', 'live', 'region', 'multi'] as $field)
            <span data-server="{{ $field }}">{{ json_encode($this->{$field}) }}</span>
        @endforeach
    </div>
</div>
