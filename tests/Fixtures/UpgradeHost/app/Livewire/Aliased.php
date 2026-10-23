<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent as Atom;
use Livewire\Component;

class Aliased extends Component
{
    use Atom;

    public function render()
    {
        return view('partials.blank');
    }
}
