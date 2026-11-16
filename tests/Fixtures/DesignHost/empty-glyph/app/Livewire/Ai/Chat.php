<?php

namespace App\Livewire\Ai;

class Chat
{
    /**
     * A chat screen is read: `Ai` under Livewire is no tool directory
     */
    public function owner($job): string
    {
        return $job->owner ?? '—'; // expect: empty-glyph
    }
}
