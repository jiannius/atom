<?php

namespace App\Mail;

use Illuminate\Support\Str;

class Statement
{
    /**
     * A mail subject is not a screen
     */
    public function subject(): string
    {
        return Str::title($this->order->status->name).' statement';
    }
}
