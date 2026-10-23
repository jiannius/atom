<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Listens extends Component
{
    public function getListeners()
    {
        return ['done' => 'confirm', 'alert', 'ok'];
    }

    #[On('saved')]
    public function command()
    {
    }

    public function options()
    {
        // not listeners: a payload and a view name that happen to be helper names
        return ['type' => 'alert', 'view' => 'modal', 'confirm', 'toast' => 'x'];
    }

    // an event named like a helper that a real method handles is not a finding, nor is a plain array
    protected $listeners = ['toast' => 'refresh', 'saved'];

    public function payload()
    {
        $options = ['type' => 'alert', 'view' => 'modal'];

        return ['confirm' => $options, 'toast' => 'action'];
    }
}
