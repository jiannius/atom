<?php

namespace Jiannius\Atom\Commands\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * What the read-only host scan commands (atom:design-check, atom:upgrade-check,
 * atom:upgrade) share: the root they scan, the JSON they print and printing a
 * report as it is. The command declares a --path option.
 */
trait PrintsScanReport
{
    /**
     * The directory to scan: --path, or base_path(). Null, after saying so, when it is not a directory.
     */
    protected function scanRoot(): ?string
    {
        $root = (string) ($this->option('path') ?: base_path());

        if (! is_dir($root)) {
            $this->error('Not a directory: '.$root);

            return null;
        }

        return $root;
    }

    /**
     * A scan result as the JSON the commands print
     *
     * @param  array<string, mixed>  $result
     */
    protected function scanJson(array $result): string
    {
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
    }

    /**
     * Print text as it is: a matching line or a diff may hold <info> or <error>, which the console would read as a style
     */
    protected function raw(string $text): void
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_RAW);
    }
}
