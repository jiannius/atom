<?php

namespace App\Livewire;

trait AtomComponent
{
    use \Jiannius\Atom\Traits\AtomComponent;

    public function localHelper(): string
    {
        return 'mine';
    }
}
