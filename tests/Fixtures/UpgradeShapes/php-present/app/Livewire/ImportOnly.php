<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ImportOnly extends Component
{
    use AtomComponent;

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
