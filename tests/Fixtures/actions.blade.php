<div>
    <output data-testid="log" class="block">{{ json_encode($log) }}</output>
    <output data-testid="renders" class="block">{{ $renders }}</output>

    <atom:page.header title="INV-0042" :facts="[['label' => 'Customer', 'value' => 'Customer A']]">
        <x-slot:actions>
            @if ($reminder)
                <atom:action icon="send" wire:click="record('remind')" data-testid="remind">Send reminder</atom:action>
            @endif
            <atom:action icon="edit" wire:click="record('edit')" data-testid="edit">Edit</atom:action>
            <atom:action wire:click="record('print')" data-testid="print">Print</atom:action>
            <atom:action priority="low" wire:click="record('duplicate')" data-testid="duplicate">Duplicate</atom:action>
            <atom:action tone="warning" wire:click="record('void')" data-testid="void">Void invoice</atom:action>
            <atom:action tone="warning" href="/atom/e2e/actions/landing" data-testid="leave">Archive and leave</atom:action>
            <atom:action tone="warning" priority="primary" wire:click="record('retire')" data-testid="retire">Retire invoice</atom:action>
            <atom:action tone="delete" data-testid="delete">Delete invoice</atom:action>
        </x-slot:actions>

        <x-slot:primary>
            <atom:action priority="primary" wire:click="record('send')" data-testid="send">Send invoice</atom:action>
        </x-slot:primary>
    </atom:page.header>

    <button type="button" wire:click="bump" data-testid="bump" class="mt-6">Re-render</button>
    <button type="button" wire:click="addReminder" data-testid="add-reminder">Add the reminder action</button>
</div>
