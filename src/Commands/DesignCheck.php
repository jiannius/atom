<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Jiannius\Atom\Services\DesignCheck as Scanner;
use Symfony\Component\Console\Output\OutputInterface;

class DesignCheck extends Command
{
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
        $root = (string) ($this->option('path') ?: base_path());

        if (! is_dir($root)) {
            $this->error('Not a directory: '.$root);

            return self::FAILURE;
        }

        $scanner = new Scanner;
        $only = null;

        if ($this->option('only') !== null && trim((string) $this->option('only')) !== '') {
            $only = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('only'))))));
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

        $content = $this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n"
            : $scanner->format($result, $this->option('all') ? null : 10);

        // raw: a matching line may hold <info> or <error>, which the console would read as a style
        $this->output->write($content, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
