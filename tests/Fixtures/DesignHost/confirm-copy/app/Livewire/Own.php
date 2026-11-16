<?php

namespace App\Livewire;

use Livewire\Component;

class Own extends Component
{
    public function confirm(string $heading): void
    {
        // a confirm of its own
    }

    public function void(): void
    {
        $this->confirm(heading: 'Void invoice?');
    }
}
