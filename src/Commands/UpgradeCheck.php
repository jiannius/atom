<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Jiannius\Atom\Services\UpgradeCheck as Scanner;

class UpgradeCheck extends Command
{
    protected $signature = 'atom:upgrade-check
        {--json : Print the result as JSON (the shape the v4 codemod reads) instead of the report}
        {--output= : Also write what is printed to this file (the only thing the command ever writes)}
        {--all : List every finding of every check (the report shows the first 10)}
        {--path= : Scan this directory instead of base_path()}';

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

        $this->output->write($content);

        return self::SUCCESS;
    }
}
