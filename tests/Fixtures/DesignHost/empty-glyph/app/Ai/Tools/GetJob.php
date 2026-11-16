<?php

namespace App\Ai\Tools;

class GetJob
{
    /**
     * Text for a language model to read is not a screen
     */
    public function handle($job): string
    {
        return 'Due: '.($job->due ?? '—');
    }
}
