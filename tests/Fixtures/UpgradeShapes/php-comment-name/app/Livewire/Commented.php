<?php

namespace App\Livewire;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

// see WithTable and WithPagination in the docs
class Commented extends Component
{
    use AtomComponent;

    public string $note = 'WithTable';

    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
