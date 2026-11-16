<?php

namespace App\Exports;

class JobsExport
{
    /**
     * A CSV is not a screen either
     */
    public function row($job): array
    {
        return [$job->number, $job->site ?: '—', $job->due ?? '--'];
    }
}
