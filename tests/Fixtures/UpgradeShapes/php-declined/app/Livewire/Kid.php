<?php

namespace App\Livewire;

class Kid extends Parented
{
    public function render()
    {
        return view('livewire.table', ['rows' => Row::query()->paginate(10)]);
    }
}
