<?php

namespace App\Livewire;

use Livewire\Component;

class Upper extends Component
{
    use \JIANNIUS\ATOM\TRAITS\ATOMCOMPONENT;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
