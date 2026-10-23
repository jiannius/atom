<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;
use Livewire\WithPagination as Paginates;

class Mixed extends Component
{
    use AtomComponent, Paginates;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
