<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Prune extends Command
{
    public function handle(): void
    {
        // a command asks in the terminal: there is no dialog to word
        if ($this->confirm('Proceed with the prune?')) {
            $this->info('Pruned');
        }
    }
}
