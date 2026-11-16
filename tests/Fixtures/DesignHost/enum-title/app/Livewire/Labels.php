<?php

namespace App\Livewire;

use Illuminate\Support\Str;

class Labels
{
    public function word(): string
    {
        return Str::title($this->order->status->name); // expect: enum-title
    }

    public function second(): string
    {
        return str($this->order->status->value)->replace('_', ' ')->title(); // expect: enum-title
    }

    public function fine(): string
    {
        return $this->order->status->label().Str::title($this->user->name);
    }
}
