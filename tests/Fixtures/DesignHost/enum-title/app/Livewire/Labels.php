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

    public function logged(): void
    {
        Log::info('status', ['word' => Str::title($this->order->status->name)]);
        logger(Str::headline($this->order->status->value));
        cache()->put('k', Str::title($this->order->status->name));
        Cache::remember('x'.Str::title($this->order->status->name), 60, fn () => 1);
    }

    public function fine(): string
    {
        return $this->order->status->label().Str::title($this->user->name);
    }
}
