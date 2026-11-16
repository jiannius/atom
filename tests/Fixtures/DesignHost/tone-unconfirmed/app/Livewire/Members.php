<?php

namespace App\Livewire;

class Members
{
    /**
     * Asks first: a toned element that calls it needs no `confirm` of its own
     */
    public function suspend(int $id = 0): void
    {
        $this->confirm(heading: 'Suspend member?', buttonConfirm: 'Suspend', onAccepted: 'doSuspend');
    }

    public function doSuspend(): void
    {
        $this->toast('Member suspended');
    }

    public function resume(): void
    {
        $this->toast('Member resumed');
    }
}
