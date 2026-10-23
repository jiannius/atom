<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Commented extends Component
{
    use Other, /* keep */ AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
