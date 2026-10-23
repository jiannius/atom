<?php

namespace App\Livewire;

use App\Traits\AtomComponent;
use Livewire\Component;

class Imported extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
