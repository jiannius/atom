<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Shadowed extends Component
{
    use AtomComponent, WithTable;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
