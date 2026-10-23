<?php

namespace App\Livewire {

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Braced extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => X::paginate(5)]);
    }
}

}
