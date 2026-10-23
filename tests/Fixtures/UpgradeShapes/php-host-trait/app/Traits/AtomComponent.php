<?php

namespace App\Traits;

trait AtomComponent
{
    use \Jiannius\Atom\Traits\AtomComponent;

    public function hostHelper(): string
    {
        return 'mine';
    }
}
