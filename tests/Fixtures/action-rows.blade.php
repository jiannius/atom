{{-- <atom:action> in a table row's ⋯ menu: two rows that open an href (the landing route), the same actions in each. Row 2's delete has no action of its own. --}}
<div>
    <output data-testid="log" class="block">{{ json_encode($log) }}</output>
    <output data-testid="renders" class="block">{{ $renders }}</output>

    <table class="w-full">
        <tbody>
            <atom:table.row href="/atom/e2e/action-rows/landing?row=1" data-row="1" wire:key="row-1">
                <td class="p-2">Record 1</td>
                <atom:table.actions data-testid="cell-1">
                    <atom:action tone="delete" wire:click="record('destroy-1')" data-testid="delete-1">Delete</atom:action>
                    <atom:action tone="warning" wire:click="record('void-1')" data-testid="void-1">Void</atom:action>
                    <atom:action priority="low" wire:click="record('copy-1')" data-testid="copy-1">Copy link</atom:action>
                    <atom:action icon="edit" wire:click="record('edit-1')" data-testid="edit-1">Edit</atom:action>
                    <atom:action href="/atom/e2e/action-rows/landing?from=open-1" data-testid="open-1">Open in full</atom:action>
                </atom:table.actions>
            </atom:table.row>
            <atom:table.row href="/atom/e2e/action-rows/landing?row=2" data-row="2" wire:key="row-2">
                <td class="p-2">Record 2</td>
                <atom:table.actions data-testid="cell-2">
                    <atom:action tone="delete" data-testid="delete-2">Delete</atom:action>
                    <atom:action tone="warning" wire:click="record('void-2')" data-testid="void-2">Void</atom:action>
                    <atom:action priority="low" wire:click="record('copy-2')" data-testid="copy-2">Copy link</atom:action>
                    <atom:action icon="edit" wire:click="record('edit-2')" data-testid="edit-2">Edit</atom:action>
                    <atom:action href="/atom/e2e/action-rows/landing?from=open-2" data-testid="open-2">Open in full</atom:action>
                </atom:table.actions>
            </atom:table.row>
        </tbody>
    </table>

    <button type="button" wire:click="bump" data-testid="bump" class="mt-6">Re-render</button>
</div>
