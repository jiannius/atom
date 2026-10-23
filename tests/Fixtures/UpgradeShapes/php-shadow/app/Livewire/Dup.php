<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Jiannius\Atom\Traits\WithTable;
use Livewire\Component;

class Dup extends Component
{
    use AtomComponent, WithTable;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
