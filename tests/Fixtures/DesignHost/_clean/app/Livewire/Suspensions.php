<?php

namespace App\Livewire;

class Suspensions
{
    /**
     * Asks before it acts, so the toned item that calls it needs no `confirm` of its own
     */
    public function suspend(): void
    {
        $this->confirm(heading: 'Suspend billing?', buttonConfirm: 'Suspend', onAccepted: 'doSuspend');
    }

    public function doSuspend(): void
    {
        $this->toast('Billing suspended');
    }
}
