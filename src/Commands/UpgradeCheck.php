<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Jiannius\Atom\Services\UpgradeCheck as Scanner;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeCheck extends Command
{
    protected $signature = 'atom:upgrade-check
        {--json : Print the result as JSON (the shape the v4 codemod reads) instead of the report}
        {--output= : Also write what is printed to this file (the only thing the command ever writes)}
        {--all : List every finding of every check (the report shows the first 10)}
        {--path= : Scan this directory instead of base_path()}
        {--fail-on= : Exit 1 when the scan leaves a finding of this kind in app code (`breaks`: a break at runtime), for a CI step; the exit code is 0 without it}';

    protected $description = 'Read-only: list what moving to atom 4 will break or change in this app, with file:line and counts';

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

        $failOn = (string) $this->option('fail-on');

        if ($failOn !== '' && $failOn !== 'breaks') {
            $this->error('--fail-on takes `breaks`, not `'.$failOn.'`');

            return self::FAILURE;
        }

        $scanner = new Scanner;
        $result = $scanner->scan($root);

        $content = $this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n"
            : $scanner->format($result, $this->option('all') ? null : 10);

        if ($output = $this->option('output')) {
            if (@file_put_contents((string) $output, $content) === false) {
                $this->error('Could not write '.$output);

                return self::FAILURE;
            }
        }

        // raw: a matching line may hold <info> or <error>, which the console would read as a style
        $this->output->write($content, false, OutputInterface::OUTPUT_RAW);

        if ($failOn === 'breaks') {
            $remaining = collect($result['checks'])->filter(fn ($check) => $check['severity'] === Scanner::BREAKS && $check['count'] > 0);

            if ($remaining->isNotEmpty()) {
                // the error stream of a real console, so --json and --output stay exactly what they were
                $console = $this->output->getOutput();

                if ($console instanceof ConsoleOutputInterface) {
                    $console->getErrorOutput()->writeln('atom:upgrade-check: '.$remaining->sum('count').' break(s) at runtime remain in app code ('.$remaining->keys()->implode(', ').').');
                }

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
