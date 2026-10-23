<?php

namespace App\Livewire;

use jiannius\atom\traits\atomcomponent;
use livewire\component;

class Lower extends Component
{
    use atomcomponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
