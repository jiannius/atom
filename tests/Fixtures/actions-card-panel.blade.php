<div class="space-y-6">
    <output data-testid="log" class="block">{{ json_encode($log) }}</output>
    <output data-testid="renders" class="block">{{ $renders }}</output>

    <atom:card heading="Parts used" data-testid="card">
        <x-slot:actions>
            @if ($extra)
                <atom:action href="#new" data-testid="card-new">New part</atom:action>
            @endif
            <atom:action href="#view" data-testid="card-view">View all</atom:action>
            <atom:action wire:click="record('card-refresh')" data-testid="card-refresh">Refresh</atom:action>
            <atom:action priority="low" wire:click="record('card-print')" data-testid="card-print">Print</atom:action>
            <atom:action tone="warning" wire:click="record('card-archive')" data-testid="card-archive">Archive all</atom:action>
            <atom:action tone="delete" data-testid="card-clear">Clear parts</atom:action>
        </x-slot:actions>

        <p data-testid="card-body">3 parts, RM 420.00 <atom:action wire:click="record('stray')" data-testid="stray">Stray</atom:action></p>
    </atom:card>

    <atom:panel heading="Lines" :count="3" data-testid="panel">
        <x-slot:actions>
            <atom:action wire:click="record('panel-import')" data-testid="panel-import">Import</atom:action>
            <atom:action wire:click="record('panel-sync')" data-testid="panel-sync">Sync</atom:action>
            <atom:action tone="danger" wire:click="record('panel-purge')" data-testid="panel-purge">Purge lines</atom:action>
        </x-slot:actions>

        <x-slot:menu>
            <atom:action wire:click="record('panel-export')" data-testid="panel-export">Export</atom:action>
        </x-slot:menu>

        <p class="px-row py-3">Three lines.</p>
    </atom:panel>

    <button type="button" wire:click="bump" data-testid="bump">Re-render</button>
    <button type="button" wire:click="addExtra" data-testid="add-extra">Add an action</button>
</div>
