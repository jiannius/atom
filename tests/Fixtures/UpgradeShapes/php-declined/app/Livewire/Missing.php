<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Missing extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.nowhere');
    }
}
