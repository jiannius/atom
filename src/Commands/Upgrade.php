<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Jiannius\Atom\Services\Upgrade as Codemod;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Upgrade extends Command
{
    protected $signature = 'atom:upgrade
        {--dry-run : Print the diff and the ATOM-UPGRADE-TODO.md it would write, and write nothing}
        {--path= : Upgrade this directory instead of base_path()}';

    protected $description = 'Move this app to atom 4: rewrite what text can fix (the CSS import, the traits, Date::use, the editor tags) and write ATOM-UPGRADE-TODO.md with the rest';

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

        $dry = (bool) $this->option('dry-run');
        $gate = $this->gate();

        if (! $dry && ! $gate['ready']) {
            $this->refuse($gate);

            return self::FAILURE;
        }

        $codemod = new Codemod;
        $plan = $codemod->plan($root);
        $todoPath = $plan['root'].'/'.Codemod::TODO;
        $existing = is_file($todoPath) ? (string) @file_get_contents($todoPath) : null;
        $todo = $codemod->todo($plan, $existing);

        if ($dry) {
            $this->raw($codemod->diff($plan) ?: "No file would change.\n");
            $this->raw("\n=== ".Codemod::TODO.($existing === $todo ? ' (unchanged)' : ' (would be written)')." ===\n".$todo."\n");
            $this->raw("=== Gate ===\n".$this->gateText($gate)."\n");

            return self::SUCCESS;
        }

        try {
            $codemod->apply($plan);

            if ($existing !== $todo && @file_put_contents($todoPath, $todo) === false) {
                throw new \RuntimeException('Could not write '.Codemod::TODO.'.');
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($plan['changes'] as $relative => $change) {
            $this->line('changed  '.$relative.'  ('.implode(', ', $change['kinds']).')');
        }

        $this->line($plan['changes'] ? count($plan['changes']).' file(s) changed.' : 'No file needed changing.');
        $this->line(($existing === $todo ? 'Unchanged  ' : 'Wrote      ').Codemod::TODO.': work through it, then run your tests.');

        return self::SUCCESS;
    }

    /**
     * Whether the editor columns are migrated, as `atom:tiptap-migrate --status` says. A status
     * that cannot be read (no database, a model that will not load) is not ready.
     *
     * @return array{ready: bool, summary: array<int, string>}
     */
    protected function gate(): array
    {
        try {
            $status = app(MigrateTiptapContent::class)->status();
        } catch (Throwable $e) {
            return ['ready' => false, 'summary' => ['Could not read the editor columns ('.class_basename($e).': '.$e->getMessage().').']];
        }

        $summary = [];

        foreach ($status['legacy_casts'] as $class => $columns) {
            $summary[] = $class.': '.implode(', ', $columns).' still cast as AsEditorContent';
        }

        foreach ($status['problems'] as $problem) {
            $summary[] = 'Could not read: '.$problem;
        }

        if ($status['unmigrated']) {
            $summary[] = $status['unmigrated'].' value(s) not yet migrated ('.$status['flagged'].' flagged, '.$status['refused'].' refused)';
        }

        return ['ready' => (bool) $status['ready'], 'summary' => $summary];
    }

    /**
     * @param  array{ready: bool, summary: array<int, string>}  $gate
     */
    protected function gateText(array $gate): string
    {
        return $gate['ready']
            ? 'Ready: atom:tiptap-migrate has nothing left, so atom:upgrade will write.'
            : 'Not ready: atom:upgrade will refuse to write.'."\n".implode("\n", array_map(fn ($line) => '  '.$line, $gate['summary']))."\n".$this->pointer();
    }

    /**
     * @param  array{ready: bool, summary: array<int, string>}  $gate
     */
    protected function refuse(array $gate): void
    {
        $this->error('Not writing anything: the editor columns are not migrated, or cannot be read.');

        foreach ($gate['summary'] as $line) {
            $this->line('  '.$line);
        }

        $this->line($this->pointer());
        $this->line('Add --dry-run to see what would change meanwhile.');
    }

    protected function pointer(): string
    {
        return 'Run `php artisan atom:tiptap-migrate --status` for the detail, and follow "Moving an app to v4" in the README (migrate on 3.x first: dry run, --force, switch the casts, --compare, --status).';
    }

    /**
     * Print text as it is: a diff line may hold <info> or <error>, which the console would read as a style
     */
    protected function raw(string $text): void
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_RAW);
    }
}
