<?php

namespace App\Livewire;

use Livewire\Component;

class Local extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
