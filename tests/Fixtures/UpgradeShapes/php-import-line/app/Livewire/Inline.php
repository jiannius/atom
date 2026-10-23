<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent; use Livewire\Component;

class Inline extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.plain');
    }
}
