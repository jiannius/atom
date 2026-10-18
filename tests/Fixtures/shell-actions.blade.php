<div class="flex items-center gap-1" data-testid="shell-actions-counter">
    <button type="button" wire:click="bump" data-testid="shell-bump">Count</button>
    <span data-testid="shell-count">{{ $count }}</span>
</div>
