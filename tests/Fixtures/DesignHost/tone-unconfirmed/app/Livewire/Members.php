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

    /**
     * Hands over to a method that asks: one level counts
     */
    public function hand(): void
    {
        $this->suspend();
    }

    public function chain(): void
    {
        $this->resume();
    }

    public function outer(): void
    {
        $this->hand();
    }

    /**
     * A dialog of its own
     */
    public function dialog(): void
    {
        $this->modal('purge')->show();
    }

    public function viaEvent(): void
    {
        $this->dispatch('atom-confirm-show', heading: 'Purge?');
    }
}
