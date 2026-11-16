<?php

namespace App\Services;

class Prompter
{
    public function ask(): void
    {
        // not a Livewire component: its confirm() is its own
        $this->confirm(heading: 'Void invoice?');
    }
}
