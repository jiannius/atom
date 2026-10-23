<?php

namespace App\Livewire;

use App\Support\{function helper, Foo};
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Mixed extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
