<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component, Livewire\WithPagination;

class Multi extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
