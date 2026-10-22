<?php

namespace App\Livewire;

use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;

class Listens2 extends Component
{
    protected $listeners = array('a' => 'confirm');

    public function getListeners()
    {
        $unused = ['b' => 'command'];

        return array_merge(parent::getListeners(), ['c' => 'modal', 'd' => 'toast']);
    }

    public function notListeners($l)
    {
        return $l + ['e' => 'alert'];
    }

    #[On(['a', 'b'])]
    public function alert()
    {
    }

    #[On('x')] #[Lazy] public function modal()
    {
    }

    #[On('y')]
    #[Lazy]
    public function toast()
    {
    }

    #[Lazy]
    public function wirekey()
    {
    }

    #[On('z')]
    public function refresh()
    {
    }
}

class Listens3 extends Component
{
    public function getListeners()
    {
        $l = [];

        return $l + ['f' => 'action'];
    }
}

class Listens4 extends Component
{
    public function getListeners()
    {
        return array('g' => 'command');
    }
}
