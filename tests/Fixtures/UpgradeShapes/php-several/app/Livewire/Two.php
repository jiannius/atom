<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class One extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}

class Two extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.plain');
    }
}
