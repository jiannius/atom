<atom:modal name="import">
    <div class="space-y-6">
        @if ($preview)
            @php
                $columnOptions = collect($preview[0])->map(fn ($label, $i) => ['value' => $i, 'label' => $label])->values()->all();
            @endphp
            <div class="space-y-4">
                <div>We guessed the columns below.</div>

                <atom:form.grid cols="auto">
                    <atom:select wire:model.live="mapping.date" label="Date Column" :options="$columnOptions" required data-probe="date" />

                    @if ($deferred)
                        <atom:select wire:model="mapping.date_format" label="Date Format" :options="$formats" required data-probe="format" />
                    @else
                        <atom:select wire:model.live="mapping.date_format" label="Date Format" :options="$formats" required data-probe="format" />
                    @endif

                    <atom:select variant="listbox" wire:model.live="listboxFormat" label="Listbox Date Format" :options="$formats" data-probe="format-listbox" />
                    <atom:select wire:model="mapping.description" label="Description Column" :options="$columnOptions" required />
                    <atom:select wire:model="mapping.amount" label="Amount Column" :options="$columnOptions" />
                    <atom:input type="number" wire:model="mapping.data_start_row" label="First Data Row" required />
                </atom:form.grid>

                <div>
                    <atom:button wire:click="cancel" data-cancel>Choose a different file</atom:button>
                </div>
            </div>
        @else
            <div class="space-y-4">
                <atom:select wire:model.live="accountId" label="Bank Account" :options="$accounts" required data-probe="account" />

                @if ($accountId)
                    <atom:button wire:click="load" data-load>Upload</atom:button>
                @endif
            </div>
        @endif

        <pre data-server>{{ json_encode(['mapping' => $mapping, 'listboxFormat' => $listboxFormat]) }}</pre>
    </div>
</atom:modal>
