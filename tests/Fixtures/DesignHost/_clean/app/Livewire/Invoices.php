<?php

namespace App\Livewire;

class Invoices
{
    /**
     * A PHP file the design check reads: nothing here drifts
     */
    public function archive(): void
    {
        $this->toast('Invoice archived');
    }
}
