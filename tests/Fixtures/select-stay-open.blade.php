<div class="space-y-4" style="display: flex; flex-direction: column; gap: 16px; max-width: 480px">
    <div>Renders: <span data-renders>{{ $renders }}</span></div>

    {{-- a field the server shows only while exactly one client is ticked: it sits above the client filter, so its coming and going moves that filter's trigger --}}
    @if (count($clients) === 1)
        <div data-branch>
            <atom:select variant="listbox" label="Branch" wire:model="branch" :options="[['value' => 'hq', 'label' => 'HQ']]" />
        </div>
    @endif

    <div data-probe="clients">
        <atom:select variant="filter" label="Client" wire:model.live="clients" :options="$clientOptions" multiple searchable />
    </div>

    <div data-probe="regions">
        <atom:select variant="filter" label="Region" wire:model.live="regions" :options="$regionOptions" multiple />
    </div>

    <div data-probe="tags">
        <atom:select variant="listbox" label="Tags" wire:model.live="tags" :options="$tagOptions" multiple searchable />
    </div>

    <div data-probe="sizes">
        <atom:select variant="listbox" label="Sizes" wire:model.live="sizes" :options="$sizeOptions" multiple />
    </div>

    <div data-probe="status">
        <atom:select variant="filter" label="Status" wire:model.live="status" :options="$statusOptions" />
    </div>

    <div data-probe="priority">
        <atom:select variant="listbox" label="Priority" wire:model.live="priority" :options="$statusOptions" />
    </div>

    <pre data-server>{{ json_encode(['clients' => $clients, 'regions' => $regions, 'tags' => $tags, 'sizes' => $sizes, 'status' => $status, 'priority' => $priority]) }}</pre>
</div>
