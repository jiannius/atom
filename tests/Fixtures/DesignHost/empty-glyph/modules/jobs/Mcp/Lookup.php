<?php

namespace Modules\Jobs\Mcp;

class Lookup
{
    /**
     * A module's MCP tool writes text for a model
     */
    public function handle($job): string
    {
        return 'Due: '.($job->due ?? '—');
    }
}
