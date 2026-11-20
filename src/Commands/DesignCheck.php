<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Jiannius\Atom\Commands\Concerns\PrintsScanReport;
use Jiannius\Atom\Services\DesignCheck as Scanner;

class DesignCheck extends Command
{
    use PrintsScanReport;

    protected $signature = 'atom:design-check
        {--json : Print the result as JSON (every check, even at zero) instead of the report}
        {--all : List every finding of every check (the report shows the first 10)}
        {--path= : Scan this directory instead of base_path()}
        {--only= : Run only these checks (comma-separated ids), for fixing one at a time}';

    protected $description = 'Read-only: list where the app\'s views drift from atom\'s design rules, with file:line and the component to use';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (($root = $this->scanRoot()) === null) {
            return self::FAILURE;
        }

        if (! array_filter(Scanner::READ_DIRECTORIES, fn ($directory) => is_dir($root.DIRECTORY_SEPARATOR.$directory))) {
            $this->error('Nothing to read in '.$root.': --path is the app root, the directory that holds '.implode('/, ', array_slice(Scanner::READ_DIRECTORIES, 0, 3)).'/ (not resources/views).');

            return self::FAILURE;
        }

        $scanner = new Scanner;
        $only = null;

        if ($this->option('only') !== null) {
            $only = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('only'))))));

            if (! $only) {
                $this->error('--only needs check ids, comma-separated. The checks are: '.implode(', ', $scanner->ruleIds()));

                return self::FAILURE;
            }

            $unknown = array_diff($only, $scanner->ruleIds());

            if ($unknown) {
                $this->error('Unknown check: '.implode(', ', $unknown).'. The checks are: '.implode(', ', $scanner->ruleIds()));

                return self::FAILURE;
            }
        }

        try {
            $result = $scanner->scan($root, $only);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->raw($this->option('json')
            ? $this->scanJson($result)
            : $scanner->format($result, $this->option('all') ? null : 10));

        return self::SUCCESS;
    }
}
