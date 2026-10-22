<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jiannius\Atom\Casts\AsEditorContent;
use Jiannius\Atom\Casts\AsTiptapContent;
use Jiannius\Atom\Commands\Concerns\DiscoversModels;
use Jiannius\Atom\Services\EditorBackupException;
use Jiannius\Atom\Services\EditorBackups;
use Jiannius\Atom\Tiptap\Content;
use Throwable;

class MigrateTiptapContent extends Command
{
    use DiscoversModels;

    /**
     * Rows are read this many at a time (by primary key)
     */
    protected const CHUNK = 50;

    /**
     * Say how far a long scan has got every this many rows
     */
    protected const PROGRESS_EVERY = 1000;

    /**
     * Tags that only wrap content: their text is compared, and their loss is not
     * a loss of content (a div becomes a paragraph, a table gets a tbody). A tag
     * that means something by its name alone (center, figure, figcaption, label,
     * section, nav...) is not one: the editor drops it, so it shows up as lost.
     */
    protected const WRAPPER_TAGS = [
        'html', 'head', 'body', 'div', 'span', 'p', 'br', 'tbody', 'thead', 'tfoot', 'colgroup', 'col',
    ];

    /**
     * Tags the editor writes under another name
     */
    protected const TAG_ALIASES = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's'];

    /**
     * Inline marks: the editor merges neighbours that carry the same one
     * (<b>a</b><b>b</b> is one strong), so a mark counts as lost when it is gone
     * altogether, not when there are fewer of them
     */
    protected const MARK_TAGS = ['strong', 'em', 'u', 's', 'code', 'mark', 'sub', 'sup'];

    /**
     * Tags that end a line of text, so two neighbours do not fuse into one word
     */
    protected const BLOCK_TAGS = [
        'p', 'div', 'li', 'ul', 'ol', 'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre',
        'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'section', 'article', 'header', 'footer', 'main',
        'nav', 'aside', 'figure', 'figcaption', 'center', 'hr', 'br', 'form', 'fieldset', 'address',
    ];

    /**
     * Tags whose text is never shown
     */
    protected const HIDDEN_TEXT_TAGS = ['script', 'style', 'head', 'title', 'template', 'noscript'];

    protected $signature = 'atom:tiptap-migrate
        {--force : Write the conversions (without it nothing is written: the run is a dry run)}
        {--dry : The default; kept so scripts that pass it keep working. Not with --restore or --prune-backups}
        {--accept= : Also convert these flagged (lossy) rows: row ids, comma separated, or "all"}
        {--restore= : Put the original values back (only these row ids, when given); needs --force to write}
        {--discard-edits : With --restore, also restore a row that was edited after it was migrated}
        {--compare= : Write a JSON Lines review file of the migrated rows (only these row ids, when given)}
        {--output= : Where --compare writes (default storage/app/atom-editor-review-<time>.jsonl)}
        {--prune-backups : Delete the backups. Only after the migration is signed off}
        {--confirm-prune : Without a terminal, confirm --prune-backups (with one, it always asks)}
        {--status : Say what is left to migrate (exit code 1 when anything is)}
        {--model=* : Only this model (class name or basename, repeatable)}
        {--column=* : Only this column (repeatable); with --model, narrows a row id that is in more than one}
        {--path=* : Also scan the models in this directory (repeatable, absolute or relative to base_path())}';

    protected $description = 'Convert legacy editor columns (cast as AsEditorContent or AsTiptapContent) to Tiptap JSON: dry run, backups, restore, review file';

    /**
     * Columns still cast as AsEditorContent (v4 removes it), by model class
     *
     * @var array<string, array<int, string>>
     */
    protected array $legacyCasts = [];

    /**
     * Connections whose backup table is known to exist this run
     *
     * @var array<string, bool>
     */
    protected array $ensured = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modes = array_filter([
            '--status' => (bool) $this->option('status'),
            '--prune-backups' => (bool) $this->option('prune-backups'),
            '--restore' => $this->input->hasParameterOption('--restore'),
            '--compare' => $this->input->hasParameterOption('--compare'),
        ]);

        if (count($modes) > 1) {
            $this->error(implode(', ', array_keys($modes)).' cannot be combined.');

            return self::FAILURE;
        }

        if ($this->option('dry') && (isset($modes['--restore']) || isset($modes['--prune-backups']))) {
            $this->error('--dry cannot be combined with '.(isset($modes['--restore']) ? '--restore' : '--prune-backups').': neither is a dry run by that flag (--restore lists without --force).');

            return self::FAILURE;
        }

        if ($this->input->hasParameterOption('--accept') && $this->acceptList() === []) {
            $this->error('--accept needs row ids (comma separated) or "all".');

            return self::FAILURE;
        }

        foreach (['restore', 'compare'] as $name) {
            $value = $this->option($name);

            if ($this->input->hasParameterOption('--'.$name) && is_string($value) && $this->splitIds($value) === []) {
                $this->error('--'.$name.'= needs row ids (comma separated). For every row, leave the = off: --'.$name.'.');

                return self::FAILURE;
            }
        }

        return match (array_key_first($modes)) {
            '--status' => $this->handleStatus(),
            '--prune-backups' => $this->handlePrune(),
            '--restore' => $this->handleRestore(),
            '--compare' => $this->handleCompare(),
            default => $this->handleMigrate(),
        };
    }

    /**
     * What is left to migrate, as the upgrade gate needs it: the casts still on
     * AsEditorContent, the rows not yet converted (clean, flagged and refused),
     * the flagged rows and anything that could not be read. `ready` means no row
     * is left to convert and nothing was unreadable; a cast still on
     * AsEditorContent does not count against it on 3.x, where the class is valid
     * and the command converts its rows. `ready_for_v4` is `ready` and no cast
     * left on AsEditorContent: the state in which a host may require v4. Reads
     * every row, writes nothing, and can be called without console input
     * (`app(MigrateTiptapContent::class)->status()`); $paths are extra model
     * directories to scan, like --path.
     *
     * @param  array<int, string>  $paths
     * @return array{legacy_casts: array<string, array<int, string>>, rows: array<string, int>, unmigrated: int, flagged: int, refused: int, problems: array<int, string>, ready: bool, ready_for_v4: bool}
     */
    public function status(array $paths = []): array
    {
        $this->modelPaths = $paths;
        $rows = ['migrated' => 0, 'clean' => 0, 'lossy' => 0, 'refused' => 0, 'empty' => 0];

        foreach ($this->targets() as $target) {
            try {
                $this->scan($target, function ($key, $column, $raw) use (&$rows, $target) {
                    $rows[$this->analyse($raw, $target['capacity'][$column] ?? null)['status']]++;
                });
            }
            catch (Throwable $e) {
                $this->problems[] = $target['class'].': '.EditorBackups::describe($e);
            }
        }

        $unmigrated = $rows['clean'] + $rows['lossy'] + $rows['refused'];

        return [
            'legacy_casts' => $this->legacyCasts,
            'rows' => $rows,
            'unmigrated' => $unmigrated,
            'flagged' => $rows['lossy'],
            'refused' => $rows['refused'],
            'problems' => $this->problems,
            'ready' => $unmigrated === 0 && empty($this->problems),
            'ready_for_v4' => empty($this->legacyCasts) && $unmigrated === 0 && empty($this->problems),
        ];
    }

    /**
     * --status: print status() and fail when there is a row left to convert or
     * something could not be read. A cast still on AsEditorContent is reported
     * as not yet switched, and is not a failure on 3.x.
     */
    protected function handleStatus(): int
    {
        $status = $this->status();

        foreach ($status['legacy_casts'] as $class => $columns) {
            $this->line($class.': '.implode(', ', $columns).' not yet switched to AsTiptapContent');
        }

        foreach ($status['problems'] as $problem) {
            $this->error('Could not read: '.$problem);
        }

        $this->line('Casts not yet switched from AsEditorContent: '.collect($status['legacy_casts'])->flatten()->count());
        $this->line('Already Tiptap JSON: '.$status['rows']['migrated'].' value(s)');
        $this->line('Not yet migrated: '.$status['unmigrated'].' value(s)');
        $this->line('Flagged (lossy, need --accept): '.$status['flagged'].' value(s)');
        $this->line('Refused (need fixing by hand): '.$status['refused'].' value(s)');

        if ($status['ready']) {
            $this->info('Nothing left to migrate.');
        }

        if ($status['ready'] && ! $status['ready_for_v4']) {
            $this->line('Not ready for v4 yet: switch the casts above to AsTiptapContent (the class AsEditorContent is removed in v4).');
        }
        elseif ($status['ready_for_v4']) {
            $this->info('Ready for v4.');
        }

        return $status['ready'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The default mode: classify every value, and with --force convert the ones
     * that are safe (and the flagged ones named in --accept). The exit code is 1
     * when something failed, and, with --force, when a refused value remains
     * (it needs fixing by hand); flagged values left for review are not a
     * failure, the dry run and --status say how many there are.
     */
    protected function handleMigrate(): int
    {
        $write = (bool) $this->option('force');

        if ($write && $this->option('dry')) {
            $this->error('--force and --dry cannot be combined.');

            return self::FAILURE;
        }

        $targets = $this->targets();

        foreach ($this->legacyCasts as $class => $columns) {
            $this->line($class.': '.implode(', ', $columns).' is still cast as AsEditorContent. It is migrated like any other column; switch the cast to AsTiptapContent (v4 removes the class).');
        }

        if ($this->problems) {
            $this->error('Aborted, nothing was written. These could not be read:');

            foreach ($this->problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        $accept = $this->acceptList();

        if ($accept !== [] && $accept !== ['all']) {
            $ambiguous = $this->ambiguousRows($targets, $accept);

            if ($this->problems) {
                $this->error('Aborted, nothing was written. These could not be checked:');

                foreach ($this->problems as $problem) {
                    $this->line('  - '.$problem);
                }

                return self::FAILURE;
            }

            if ($this->refuseAmbiguous($ambiguous, '--accept')) {
                return self::FAILURE;
            }
        }

        $acceptSeen = [];
        $batch = (string) Str::uuid();
        $totals = ['migrated' => 0, 'clean' => 0, 'lossy' => 0, 'refused' => 0, 'empty' => 0, 'written' => 0, 'failed' => 0];

        foreach ($targets as $target) {
            $lines = ['lossy' => [], 'refused' => [], 'failed' => []];
            $counts = ['migrated' => 0, 'clean' => 0, 'lossy' => 0, 'refused' => 0, 'empty' => 0, 'written' => 0, 'failed' => 0];
            $read = 0;

            try {
                $this->scan($target, function ($key, $column, $raw) use ($write, $target, $accept, &$acceptSeen, $batch, &$counts, &$lines, &$read) {
                    if (++$read % self::PROGRESS_EVERY === 0) {
                        $this->info('  '.$target['class'].': '.$read.' value(s) read');
                    }

                    $found = $this->analyse($raw, $target['capacity'][$column] ?? null);
                    $counts[$found['status']]++;
                    $where = $target['class'].'#'.$key.' '.$column;

                    if ($found['status'] === 'refused') {
                        $lines['refused'][] = $where.': '.$found['reason'];

                        return;
                    }

                    if (! in_array($found['status'], ['clean', 'lossy'], true)) {
                        return;
                    }

                    if ($found['status'] === 'lossy') {
                        $acceptSeen[(string) $key] = true;

                        if ($accept !== ['all'] && ! in_array((string) $key, $accept, true)) {
                            $lines['lossy'][] = $where.': '.$this->describe($found['diff']);

                            return;
                        }
                    }

                    if (! $write) {
                        return;
                    }

                    try {
                        $result = $this->write($target['model'], (string) $key, $column, $raw, $found['json'], $found['status'] === 'lossy', $batch);
                    }
                    catch (Throwable $e) {
                        $counts['failed']++;
                        $lines['failed'][] = $where.': '.EditorBackups::describe($e);

                        return;
                    }

                    if ($result === 'written') {
                        $counts['written']++;
                    }
                    else {
                        $counts['failed']++;
                        $lines['failed'][] = $where.': the value changed while the command ran, left untouched';
                    }
                });
            }
            catch (Throwable $e) {
                $counts['failed']++;
                $lines['failed'][] = $target['class'].': '.EditorBackups::describe($e);
            }

            $this->info($target['class'].' ('.implode(', ', $target['columns']).'): '
                .$counts['clean'].' clean, '.$counts['lossy'].' flagged, '.$counts['refused'].' refused, '
                .$counts['migrated'].' already JSON');

            foreach ($lines['lossy'] as $line) {
                $this->line('  flagged  '.$line);
            }

            foreach ($lines['refused'] as $line) {
                $this->warn('  refused  '.$line);
            }

            foreach ($lines['failed'] as $line) {
                $this->error('  failed   '.$line);
            }

            foreach ($totals as $name => $n) {
                $totals[$name] += $counts[$name];
            }
        }

        $this->reportAccept($accept, $acceptSeen);
        $this->newLine();

        if (! $write) {
            $this->info('Dry run, nothing was written: '.$totals['clean'].' value(s) are clean'
                .($accept === [] ? '' : ', and the flagged ones named in --accept would be converted too').'.');
            $this->line('Flagged rows are left untouched until --accept=<ids> (or "all"). Run with --force to write.');
        }
        elseif ($totals['written']) {
            $this->info('Migrated '.$totals['written'].' value(s) to Tiptap JSON. The originals are in '.EditorBackups::TABLE.'.');
            $this->line('Next: atom:tiptap-migrate --compare, review the file, then --prune-backups.');
        }
        else {
            $this->info('Nothing was migrated.');
        }

        if ($write && $totals['refused']) {
            $this->error($totals['refused'].' value(s) were refused and need fixing by hand: the exit code is 1 until none is left.');
        }

        return $totals['failed'] || ($write && $totals['refused']) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Say which --accept ids matched no flagged row
     *
     * @param  array<int, string>  $accept
     * @param  array<string, bool>  $seen
     */
    protected function reportAccept(array $accept, array $seen): void
    {
        if ($accept === [] || $accept === ['all']) {
            return;
        }

        $unmatched = array_values(array_filter($accept, fn ($id) => ! isset($seen[$id])));

        if ($unmatched) {
            $this->warn('--accept: no flagged row with id '.implode(', ', $unmatched).' (a typo, already migrated, clean, or refused: a refused row can never be accepted).');
        }
    }

    /**
     * The row ids, of those given, that are in more than one model or column of
     * the targets in scope, with where. An id is only a safe handle for one
     * (model, column): --model and --column narrow the targets to one.
     *
     * @param  array<int, array{class: string, model: Model, columns: array<int, string>}>  $targets
     * @param  array<int, string>  $ids
     * @return array<string, array<int, string>> id => where
     */
    protected function ambiguousRows(array $targets, array $ids): array
    {
        $found = [];

        foreach ($targets as $target) {
            $model = $target['model'];
            $key = $model->getKeyName();

            // an id the key column cannot hold is not in it, and asking would be an error on PostgreSQL
            $holdable = array_values(array_filter($ids, fn ($id) => $this->keyHolds($model, $id)));

            if (! $holdable) {
                continue;
            }

            foreach ($target['columns'] as $column) {
                try {
                    foreach (array_chunk($holdable, 500) as $chunk) {
                        foreach ($this->presentIds($model, $key, $column, $chunk) as $id) {
                            $found[(string) $id][] = $target['class'].'.'.$column;
                        }
                    }
                }
                catch (Throwable $e) {
                    $this->problems[] = $target['class'].'.'.$column.': the ids could not be looked up ('.EditorBackups::describe($e).')';
                }
            }
        }

        return array_filter($found, fn ($where) => count($where) > 1);
    }

    /**
     * The ids, of a chunk, that have a value in a column
     *
     * @param  array<int, string>  $ids
     * @return iterable<int, mixed>
     */
    protected function presentIds(Model $model, string $key, string $column, array $ids): iterable
    {
        return $model->getConnection()->table($model->getTable())->whereIn($key, $ids)->whereNotNull($column)->pluck($key);
    }

    /**
     * What a model's key column holds: 'int', 'uuid' or 'string' (anything else).
     * Looked up from the table's columns, the model's key type when they cannot be.
     */
    protected function keyKind(Model $model): string
    {
        try {
            foreach ($model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()) as $info) {
                if (strtolower($info['name']) === strtolower($model->getKeyName())) {
                    $type = strtolower((string) $info['type_name']);

                    return match (true) {
                        (bool) preg_match('/int|serial/', $type) => 'int',
                        $type === 'uuid' => 'uuid',
                        default => 'string',
                    };
                }
            }
        }
        catch (Throwable) {
        }

        return $model->getKeyType() === 'int' ? 'int' : 'string';
    }

    /**
     * Whether a model's key column could hold this id (so a lookup of it is not a type error)
     */
    protected function keyHolds(Model $model, string $id): bool
    {
        return match ($this->keyKind($model)) {
            'int' => (bool) preg_match('/^-?\d{1,19}$/', $id),
            'uuid' => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id),
            default => true,
        };
    }

    /**
     * The row ids, of those given, that are in more than one model or column of
     * the backups in scope, with where
     *
     * @param  array<int, string>  $ids
     * @return array<string, array<int, string>> id => where
     */
    protected function ambiguousBackups(array $ids): array
    {
        $found = [];

        foreach ($this->backupRows($ids, true) as [$backup]) {
            $found[(string) $backup->row_key][$backup->model.'.'.$backup->column_name] = true;
        }

        return array_filter(array_map('array_keys', $found), fn ($where) => count($where) > 1);
    }

    /**
     * Print the ambiguous ids and say whether there were any
     *
     * @param  array<string, array<int, string>>  $ambiguous
     */
    protected function refuseAmbiguous(array $ambiguous, string $option): bool
    {
        if (! $ambiguous) {
            return false;
        }

        $this->error($option.': these ids are in more than one model or column, so no row was touched. Narrow them with --model and --column:');

        foreach ($ambiguous as $id => $where) {
            $this->line('  id '.$id.': '.implode(', ', $where));
        }

        return true;
    }

    /**
     * Back up one value and write its conversion, in one transaction, with the
     * backup first: if the update fails the backup goes with it, and a
     * committed update always has its backup. One transaction per value, not per
     * chunk: the rows are read and parsed before it opens, so a lock is held for
     * two quick statements, and one bad row cannot roll back its neighbours.
     * The row is locked and read again first, and left alone when it no longer
     * holds the value that was converted. The write is a query-builder update:
     * no model events and no updated_at.
     *
     * @return string 'written' or 'changed'
     */
    protected function write(Model $model, string $key, string $column, string $raw, string $json, bool $lossy, string $batch): string
    {
        $backups = EditorBackups::on($model->getConnectionName());
        $name = $model->getConnection()->getName();

        if (! isset($this->ensured[$name])) {
            $backups->create();
            $this->ensured[$name] = true;
        }

        $connection = $model->getConnection();
        $keyName = $model->getKeyName();

        return $connection->transaction(function () use ($backups, $connection, $model, $keyName, $key, $column, $raw, $json, $lossy, $batch) {
            $row = fn () => $connection->table($model->getTable())->where($keyName, $key);

            if ($row()->lockForUpdate()->value($column) !== $raw) {
                return 'changed';
            }

            $backups->store($batch, $model::class, $key, $column, $raw, $json, $lossy);

            $updated = $row()->update([$column => $json]);

            if ($updated !== 1) {
                throw new EditorBackupException('the update changed '.$updated.' rows, expected 1');
            }

            if ($row()->value($column) !== $json) {
                throw new EditorBackupException('the database did not store the converted value as sent');
            }

            return 'written';
        });
    }

    /**
     * --restore[=ids]: put original values back, byte for byte. A row edited
     * since it was migrated is skipped unless --discard-edits, and so is a backup
     * that no longer matches its own hash. With --force the exit code is 1 when a
     * value was skipped (an edited row, a row that is gone, a model that is not
     * there) or failed: it is still migrated.
     */
    protected function handleRestore(): int
    {
        $write = (bool) $this->option('force');
        $discard = (bool) $this->option('discard-edits');
        $ids = $this->idList('restore');
        $restored = 0;
        $skipped = 0;
        $failed = 0;

        if ($ids !== null && $this->refuseAmbiguous($this->ambiguousBackups($ids), '--restore')) {
            return self::FAILURE;
        }

        foreach ($this->backupRows($ids) as [$backup, $backups, $model]) {
            $label = $backup->model.'#'.$backup->row_key.' '.$backup->column_name;

            if (! $model) {
                $this->warn('Skipped '.$label.': the model class is not available.');
                $skipped++;

                continue;
            }

            if (! EditorBackups::intact($backup)) {
                $this->error('Refused '.$label.': the backup does not match its own hash, so it was changed after it was written.');
                $failed++;

                continue;
            }

            try {
                $original = EditorBackups::original($backup);
                $connection = $model->getConnection();
                $row = fn () => $connection->table($model->getTable())->where($model->getKeyName(), $backup->row_key);

                $outcome = $connection->transaction(function () use ($row, $backup, $backups, $original, $write, $discard) {
                    $current = $row()->lockForUpdate()->value($backup->column_name);

                    if ($current === null && $row()->doesntExist()) {
                        return 'the row no longer exists';
                    }

                    $hash = EditorBackups::hash((string) $current);

                    if ($hash === $backup->original_hash) {
                        if ($write) {
                            $backups->markRestored($backup->id);
                        }

                        return 'already original';
                    }

                    if ($hash !== $backup->migrated_hash && ! $discard) {
                        return 'edited since the migration (use --discard-edits to overwrite it)';
                    }

                    if (! $write) {
                        return 'would restore';
                    }

                    $row()->update([$backup->column_name => $original]);

                    if ($row()->value($backup->column_name) !== $original) {
                        throw new EditorBackupException('the database did not give the original back byte for byte');
                    }

                    $backups->markRestored($backup->id);

                    return 'restored';
                });
            }
            catch (Throwable $e) {
                $this->error('Failed '.$label.': '.EditorBackups::describe($e));
                $failed++;

                continue;
            }

            if ($outcome === 'restored' || $outcome === 'would restore') {
                $this->line(($write ? 'Restored ' : 'Would restore ').$label);
                $restored++;
            }
            elseif ($outcome === 'already original') {
                $this->line('Already original: '.$label);
            }
            else {
                $this->warn('Skipped '.$label.': '.$outcome.'.');
                $skipped++;
            }
        }

        foreach ($this->problems as $problem) {
            $this->error('Could not read: '.$problem);
            $failed++;
        }

        $this->newLine();
        $this->info(($write ? 'Restored ' : 'Dry run, nothing was written: would restore ').$restored.' value(s); '.$skipped.' skipped.');

        if (! $write && $restored) {
            $this->line('Run with --force to restore.');
        }

        if ($write && $skipped) {
            $this->error($skipped.' value(s) were skipped and still hold their migrated value: the exit code is 1 (--discard-edits overrides a row edited since).');
        }

        return $failed || ($write && $skipped) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * --compare[=ids]: a JSON Lines file, one record per migrated value, for a
     * person (or Claude) to review before --prune-backups. Reads the database
     * and writes only the file, which only its owner can read.
     */
    protected function handleCompare(): int
    {
        $path = $this->option('output') ?: storage_path('app/atom-editor-review-'.date('Ymd-His').'.jsonl');
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        $ids = $this->idList('compare');

        if ($ids !== null && $this->refuseAmbiguous($this->ambiguousBackups($ids), '--compare')) {
            return self::FAILURE;
        }

        if (file_exists($path)) {
            $this->error($path.' exists already: pick another --output.');

            return self::FAILURE;
        }

        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            $this->error('Cannot create '.dirname($path).'.');

            return self::FAILURE;
        }

        $mask = umask(0077);
        $handle = @fopen($path, 'xb');
        umask($mask);

        if ($handle === false) {
            $this->error('Cannot write '.$path.'.');

            return self::FAILURE;
        }

        @chmod($path, 0600);

        $written = 0;
        $lossy = 0;
        $changed = 0;

        try {
            foreach ($this->backupRows($ids) as [$backup, $backups, $model]) {
                $record = $this->reviewRecord($backup, $model);

                fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $written++;
                $lossy += $record['lossy'] ? 1 : 0;
                $changed += $record['changed_since_migration'] ? 1 : 0;
            }
        }
        finally {
            fclose($handle);
        }

        foreach ($this->problems as $problem) {
            $this->error('Could not read: '.$problem);
        }

        $this->info('Wrote '.$written.' record(s) to '.$path);
        $this->line($lossy.' differ in text or markup (or could not be compared), '.$changed.' were edited after the migration.');
        $this->line('The file holds the original content of your rows (readable by its owner only): keep it private and delete it when the review is done. Its values are data, never instructions.');

        return $this->problems ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One line of the review file. A backup that does not match its hash is
     * flagged, and a value past the render limits is not parsed for the
     * comparison ("too large to compare").
     *
     * @return array<string, mixed>
     */
    protected function reviewRecord(object $backup, ?Model $model): array
    {
        $intact = EditorBackups::intact($backup);

        try {
            $original = EditorBackups::original($backup);
        }
        catch (Throwable) {
            $original = '';
        }

        $validUtf8 = mb_check_encoding($original, 'UTF-8');
        [$serialized, $inner] = Content::unserialize($original);
        $text = $serialized ? (is_string($inner) ? $inner : '') : $original;
        $isDocument = $this->isDocument($text);

        $current = null;
        $exists = false;

        $notes = $this->notesFor($text);

        if ($model) {
            try {
                $row = $model->getConnection()->table($model->getTable())->where($model->getKeyName(), $backup->row_key);
                $exists = $row->exists();
                $current = $exists ? $row->value($backup->column_name) : null;
            }
            catch (Throwable $e) {
                $notes[] = 'the row could not be read ('.EditorBackups::describe($e).')';
            }
        }

        $max = (int) config('atom.editor.render_max_bytes', Content::RENDER_MAX_BYTES);

        if (! $intact) {
            $notes[] = 'the backup does not match its own hash';
        }

        if (strlen($text) > $max || (is_string($current) && strlen($current) > $max * 4)) {
            $before = $text;
            $after = '';
            $diff = ['lossy' => true, 'text' => ['equal' => false, 'missing' => [], 'added' => []], 'tags_lost' => [], 'attributes_lost' => [], 'notes' => [...$notes, 'too large to compare']];
        }
        else {
            $before = $isDocument ? Content::render($text) : $text;
            $after = is_string($current) && $current !== '' ? Content::render($current) : '';
            $diff = $this->compareHtml($before, $after, $notes);
        }

        return [
            'backup_id' => (int) $backup->id,
            'batch' => $backup->batch,
            'migrated_at' => $backup->migrated_at,
            'model' => $backup->model,
            'key' => $backup->row_key,
            'column' => $backup->column_name,
            'accepted_lossy' => (bool) $backup->accepted_lossy,
            'changed_since_migration' => is_string($current) && EditorBackups::hash($current) !== $backup->migrated_hash,
            'backup_intact' => $intact,
            'row_exists' => $exists,
            'original_kind' => ($serialized ? 'serialized-' : '').($isDocument ? 'json' : 'html'),
            'original_html' => mb_scrub($before, 'UTF-8'),
            'original_json' => $isDocument ? mb_scrub($text, 'UTF-8') : null,
            'original_valid_utf8' => $validUtf8,
            'original_base64' => $validUtf8 ? null : base64_encode($original),
            'new_html' => $after,
            'text' => $diff['text'],
            'tags_lost' => (object) $diff['tags_lost'],
            'attributes_lost' => (object) $diff['attributes_lost'],
            'notes' => $diff['notes'],
            'lossy' => $diff['lossy'],
        ];
    }

    /**
     * --prune-backups: delete the originals. With a terminal it always asks;
     * without one it needs --confirm-prune (--force is not a confirmation). The
     * only thing that removes a backup. The table is left in place, empty: a
     * migration that is running could be writing to it, and dropping it would
     * make that write fail. Drop it yourself when no migration can be running.
     */
    protected function handlePrune(): int
    {
        $filter = $this->modelFilter();
        $columns = $this->columnFilter();
        $found = [];
        $total = 0;

        foreach ($this->connectionNames() as $name) {
            $backups = EditorBackups::on($name);

            if (! $backups->exists()) {
                continue;
            }

            $models = null;
            $query = $backups->connection()->table(EditorBackups::TABLE);

            if ($filter) {
                $models = $backups->connection()->table(EditorBackups::TABLE)->distinct()->pluck('model')
                    ->filter(fn ($class) => $this->matchesFilter($class, $filter))->values()->all();
                $query->whereIn('model', $models);
            }

            if ($columns) {
                $query->whereIn('column_name', $columns);
            }

            $total += $query->count();
            $found[] = [$backups, $models];
        }

        foreach ($this->problems as $problem) {
            $this->error('Could not read: '.$problem);
        }

        if ($this->problems) {
            return self::FAILURE;
        }

        if (! $total) {
            $this->info(($filter || $columns) && $found ? '--model and --column matched no backup. Nothing was deleted.' : 'There are no backups.');

            return self::SUCCESS;
        }

        $this->warn('This deletes '.$total.' backup(s) of original values. After it --restore cannot bring them back.');
        $this->line('Only prune once the migration is signed off (see --compare).');

        if ($this->input->isInteractive()) {
            if (! $this->confirm('Delete the backups?')) {
                $this->info('Nothing was deleted.');

                return self::SUCCESS;
            }
        }
        elseif (! $this->option('confirm-prune')) {
            $this->error('Refusing to prune without a terminal: pass --confirm-prune (--force does not confirm a prune).');

            return self::FAILURE;
        }

        $deleted = 0;

        foreach ($found as [$backups, $models]) {
            $deleted += $backups->prune($models, $columns);
        }

        $this->info('Deleted '.$deleted.' backup(s). The empty '.EditorBackups::TABLE.' table stays; drop it yourself once no migration can be running.');

        return self::SUCCESS;
    }

    /**
     * The backup rows to restore or review, each with the table they came from
     * and the model they belong to (null when its class is gone), oldest first.
     * A restored backup is not listed again. $quiet skips the --model unmatched
     * warning (a caller that only looks).
     *
     * @param  array<int, string>|null  $ids  row ids; null is every row
     * @return \Generator<int, array{0: object, 1: EditorBackups, 2: Model|null}>
     */
    protected function backupRows(?array $ids, bool $quiet = false): \Generator
    {
        $this->problems = [];
        $filter = $this->modelFilter();
        $columns = $this->columnFilter();
        $matched = [];

        foreach ($this->connectionNames() as $name) {
            $backups = EditorBackups::on($name);

            try {
                if (! $backups->exists()) {
                    continue;
                }

                $query = $backups->pending();

                if ($ids !== null) {
                    $query->whereIn('row_key', $ids);
                }

                if ($columns) {
                    $query->whereIn('column_name', $columns);
                }

                $rows = $query->lazyById(self::CHUNK);
            }
            catch (Throwable $e) {
                $this->problems[] = 'connection '.($name ?? 'default').': '.EditorBackups::describe($e);

                continue;
            }

            foreach ($rows as $backup) {
                if ($filter) {
                    $hits = array_filter($filter, fn ($one) => $this->matchesFilter($backup->model, [$one]));

                    if (! $hits) {
                        continue;
                    }

                    $matched += array_fill_keys($hits, true);
                }

                $model = null;

                try {
                    $model = class_exists($backup->model) && is_subclass_of($backup->model, Model::class) ? app($backup->model) : null;
                }
                catch (Throwable) {
                }

                yield [$backup, $backups, $model];
            }
        }

        if (! $quiet) {
            foreach ($filter as $name) {
                if (! isset($matched[$name])) {
                    $this->problems[] = '--model '.$name.' matched no backup';
                }
            }
        }
    }

    /**
     * The connections that may hold backups, by their resolved name (so the
     * default connection is one, whether a model names it or not): the default
     * one and every connection a discovered model uses. A connection that cannot
     * be resolved is a problem, never skipped.
     *
     * @return array<int, string|null> the name to hand EditorBackups::on()
     */
    protected function connectionNames(): array
    {
        $names = [DB::connection()->getName() => null];

        foreach ($this->getModels() as $class) {
            $model = null;

            try {
                $model = $this->resolveModel($class);

                if ($model) {
                    $names[$model->getConnection()->getName()] = $model->getConnectionName();
                }
            }
            catch (\InvalidArgumentException $e) {
                // a connection this config does not define cannot hold backups this config wrote: a model
                // without an editor column on one is skipped with a warning, one with a column is a problem
                if ($model && ! $this->hasEditorColumn($model)) {
                    if (isset($this->output)) {
                        $this->warn($class.': '.EditorBackups::describe($e).' (no editor column, so it is skipped)');
                    }

                    continue;
                }

                $this->problems[] = $class.': '.EditorBackups::describe($e);
            }
            catch (Throwable $e) {
                $this->problems[] = $class.': '.EditorBackups::describe($e);
            }
        }

        return array_values($names);
    }

    /**
     * Whether a model casts any column as AsTiptapContent or AsEditorContent
     */
    protected function hasEditorColumn(Model $model): bool
    {
        foreach ($model->getCasts() as $cast) {
            $name = is_string($cast) ? Str::before($cast, ':') : '';

            if ($name !== '' && (is_a($name, AsEditorContent::class, true) || is_a($name, AsTiptapContent::class, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The models with an AsTiptapContent or AsEditorContent column, found the way
     * purge finds them: every class under app/Models plus --path, narrowed by
     * --model and --column. A column still cast as AsEditorContent is scanned
     * like any other, and also recorded in $legacyCasts so the status can say the
     * cast is not yet switched. A child model that shares a table and column with its parent is
     * scanned once, as the parent. Anything unreadable, and a --model or
     * --column that matched nothing, is recorded in $problems.
     *
     * @return array<int, array{class: string, model: Model, columns: array<int, string>, capacity: array<string, int|null>}>
     */
    protected function targets(): array
    {
        $this->problems = [];
        $this->classFiles = [];
        $this->legacyCasts = [];
        $filter = $this->modelFilter();
        $columnFilter = $this->columnFilter();
        $candidates = [];

        foreach ($this->getModels() as $class) {
            try {
                $model = $this->resolveModel($class);

                if (! $model || ($filter && ! $this->matchesFilter($class, $filter))) {
                    continue;
                }

                $columns = [];

                foreach ($model->getCasts() as $column => $cast) {
                    $name = is_string($cast) ? Str::before($cast, ':') : '';

                    if ($name === '') {
                        continue;
                    }

                    $legacy = is_a($name, AsEditorContent::class, true);

                    if ($legacy) {
                        $this->legacyCasts[$class][] = $column;
                    }

                    if (($legacy || is_a($name, AsTiptapContent::class, true)) && (! $columnFilter || in_array($column, $columnFilter, true))) {
                        $columns[] = $column;
                    }
                }

                if (! $columns) {
                    continue;
                }

                $key = $model->getKeyName();

                if (! $key || ! $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $key)) {
                    $this->problems[] = $class.': no primary key column to address its rows by, so it cannot be migrated safely';

                    continue;
                }

                $candidates[] = ['class' => $class, 'model' => $model, 'columns' => $columns];
            }
            catch (Throwable $e) {
                $this->problems[] = $class.': '.EditorBackups::describe($e);
            }
        }

        // a parent before its children, so a child on the same table is the one left out
        usort($candidates, fn ($a, $b) => [count(class_parents($a['class'])), $a['class']] <=> [count(class_parents($b['class'])), $b['class']]);

        $claimed = [];
        $targets = [];

        foreach ($candidates as $candidate) {
            $model = $candidate['model'];
            $own = [];

            foreach ($candidate['columns'] as $column) {
                $id = $model->getConnection()->getName().'|'.$model->getTable().'|'.$column;

                if (! isset($claimed[$id])) {
                    $claimed[$id] = true;
                    $own[] = $column;
                }
            }

            if ($own) {
                $targets[] = [...$candidate, 'columns' => $own, 'capacity' => collect($own)->mapWithKeys(fn ($column) => [$column => $this->columnCapacity($model, $column)])->all()];
            }
        }

        foreach ($filter as $name) {
            if (! array_filter($targets, fn ($target) => $this->matchesFilter($target['class'], [$name]))) {
                $this->problems[] = '--model '.$name.' matched no model with an AsTiptapContent or AsEditorContent column';
            }
        }

        foreach ($columnFilter as $name) {
            if (! array_filter($targets, fn ($target) => in_array($name, $target['columns'], true))) {
                $this->problems[] = '--column '.$name.' matched no AsTiptapContent or AsEditorContent column';
            }
        }

        return $targets;
    }

    /**
     * How many bytes a column holds, or null when it is not bounded (or cannot
     * be found out). Looked up once per column.
     */
    protected function columnCapacity(Model $model, string $column): ?int
    {
        try {
            $connection = $model->getConnection();

            foreach ($connection->getSchemaBuilder()->getColumns($model->getTable()) as $info) {
                if (strtolower($info['name']) === strtolower($column)) {
                    return static::capacityOf($connection->getDriverName(), strtolower((string) $info['type_name']), strtolower((string) $info['type']));
                }
            }
        }
        catch (Throwable) {
        }

        return null;
    }

    /**
     * The bytes a column type holds: MySQL and MariaDB text types by name, a
     * char or varchar by its length, everything else (longtext, PostgreSQL
     * text, SQLite, which does not enforce one) unbounded.
     */
    public static function capacityOf(string $driver, string $typeName, string $type): ?int
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $bounded = ['tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215];

            if (isset($bounded[$typeName])) {
                return $bounded[$typeName];
            }
        }

        if (str_contains($typeName, 'char') && preg_match('/\((\d+)\)\s*$/', $type, $length)) {
            return (int) $length[1];
        }

        return null;
    }

    /**
     * Visit every non-null value of a target's editor columns, in primary key
     * order, a chunk at a time. Reads through the query builder: no casts, no
     * scopes (trashed rows included), no model events.
     *
     * @param  array{class: string, model: Model, columns: array<int, string>}  $target
     */
    protected function scan(array $target, callable $visit): void
    {
        $model = $target['model'];
        $key = $model->getKeyName();
        $columns = $target['columns'];

        $rows = $model->getConnection()->table($model->getTable())
            ->select(array_values(array_unique([$key, ...$columns])))
            ->where(function ($query) use ($columns) {
                foreach ($columns as $column) {
                    $query->orWhereNotNull($column);
                }
            })
            ->lazyById(self::CHUNK, $key);

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if ($row->{$column} !== null) {
                    $visit($row->{$key}, $column, $row->{$column});
                }
            }
        }
    }

    /**
     * Decide what to do with one stored value.
     *
     * - empty: nothing there
     * - migrated: already a Tiptap JSON document, left alone (a re-run is a no-op)
     * - refused: cannot be converted safely (a serialized object or other
     *   non-string, JSON that is not a document, the parser's reserved
     *   placeholder, over the limits, a parser failure): never written
     * - clean: converts, and HTML -> JSON -> HTML keeps the visible text and the
     *   tag and attribute inventory
     * - lossy: converts but loses something, listed and untouched until --accept
     *
     * $capacity is how many bytes the column holds (null: unbounded); JSON that
     * would not fit is refused, since the update would fail, or truncate on a
     * non-strict MySQL.
     *
     * @return array{status: string, reason?: string, json?: string, diff?: array<string, mixed>}
     */
    protected function analyse(mixed $raw, ?int $capacity = null): array
    {
        if ($raw === null || $raw === '') {
            return ['status' => 'empty'];
        }

        if (! is_string($raw)) {
            return ['status' => 'refused', 'reason' => 'the value is not text ('.get_debug_type($raw).')'];
        }

        $html = $raw;
        $serialized = false;
        [$isSerialized, $inner] = Content::unserialize($raw);

        if ($isSerialized) {
            if (! is_string($inner)) {
                return ['status' => 'refused', 'reason' => 'a serialized value that holds '.($inner === null ? 'an object or null' : get_debug_type($inner)).', not a string'];
            }

            $serialized = true;
            $html = $inner;
        }

        if (trim($html) === '') {
            return ['status' => 'empty'];
        }

        $isDocument = $this->isDocument($html);

        if ($isDocument && ! $serialized) {
            return ['status' => 'migrated'];
        }

        if (! $isDocument && $this->isJsonButNotDocument($html)) {
            return ['status' => 'refused', 'reason' => 'it is JSON, but not a Tiptap document'];
        }

        if (! $isDocument && Content::carriesPlaceholder($html)) {
            return ['status' => 'refused', 'reason' => 'it carries the HTML parser\'s reserved placeholder'];
        }

        // the same checks and normalisation as Content::render(); a value the parser cannot
        // take is refused and named, never allowed to stop the run, and nothing is written for it
        try {
            $json = $this->convert($html);
        }
        catch (Throwable $e) {
            return ['status' => 'refused', 'reason' => 'the HTML parser failed on it ('.class_basename($e).')'];
        }

        if ($json === null) {
            return ['status' => 'refused', 'reason' => 'it has nothing to convert, or is over the size or nesting limits'];
        }

        if ($capacity !== null && strlen($json) > $capacity) {
            return ['status' => 'refused', 'reason' => 'the JSON ('.strlen($json).' bytes) does not fit the column (it holds at most '.$capacity.' bytes): change it to longText first'];
        }

        $diff = $this->compareHtml($isDocument ? Content::render($html) : $html, Content::render($json), $this->notesFor($html));

        return ['status' => $diff['lossy'] ? 'lossy' : 'clean', 'json' => $json, 'diff' => $diff];
    }

    /**
     * Tiptap JSON for one legacy value, or null when there is nothing to convert or it is refused.
     */
    protected function convert(string $html): ?string
    {
        return Content::toJson($html);
    }

    /**
     * Is this string a Tiptap JSON document (JSON with a type key)?
     */
    protected function isDocument(string $value): bool
    {
        $trimmed = ltrim($value);

        if ($trimmed === '' || $trimmed[0] !== '{') {
            return false;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) && isset($decoded['type']);
    }

    /**
     * Is this valid JSON that is an object or a list but no document? Text that
     * only starts with a bracket ("[gallery] <img ...>") is not.
     */
    protected function isJsonButNotDocument(string $value): bool
    {
        $trimmed = ltrim($value);

        if ($trimmed === '' || ! in_array($trimmed[0], ['{', '['], true)) {
            return false;
        }

        return is_array(json_decode($trimmed, true)) && ! $this->isDocument($trimmed);
    }

    /**
     * Losses that are about the bytes, not the markup: the editor reads invalid
     * UTF-8 as replacement characters and drops NUL bytes
     *
     * @return array<int, string>
     */
    protected function notesFor(string $value): array
    {
        $notes = [];

        if (! mb_check_encoding($value, 'UTF-8')) {
            $notes[] = 'invalid UTF-8 bytes would be replaced';
        }

        if (str_contains($value, "\0")) {
            $notes[] = 'NUL bytes would be dropped';
        }

        // libxml 2.9.13 drops the text after a "<" that opens no tag, on both sides of the comparison,
        // so the two would look equal; a later libxml keeps it
        if (preg_match('/<(?![A-Za-z\/!?])/', $value)) {
            $notes[] = "a bare '<' can drop text on older libxml";
        }

        return $notes;
    }

    /**
     * Compare an HTML value with what it became (HTML -> JSON -> HTML): the
     * visible text, and the tag and attribute inventory. Only a loss counts as
     * one: the editor adds attributes (rel, target) and rewrites tags (b to
     * strong), so extra or renamed things in the result are not a difference.
     * The same comparison feeds the dry-run report and the review file.
     *
     * @param  array<int, string>  $notes  losses found before the comparison
     * @return array{lossy: bool, text: array{equal: bool, missing: array<int, string>, added: array<int, string>}, tags_lost: array<string, int>, attributes_lost: array<string, int>, notes: array<int, string>}
     */
    protected function compareHtml(string $before, string $after, array $notes = []): array
    {
        $a = $this->profile($before);
        $b = $this->profile($after);

        // the words, not the letters: <b>Hello</b> <i>world</i> printed with its space dropped is "Helloworld"
        $equal = $a['words'] === $b['words'];
        $tags = $this->lost($a['tags'], $b['tags']);
        $attributes = $this->lost($a['attributes'], $b['attributes']);

        return [
            'lossy' => ! $equal || $tags || $attributes || $notes,
            'text' => [
                'equal' => $equal,
                'missing' => $equal ? [] : $this->surplus($a['words'], $b['words']),
                'added' => $equal ? [] : $this->surplus($b['words'], $a['words']),
            ],
            'tags_lost' => $tags,
            'attributes_lost' => $attributes,
            'notes' => $notes,
        ];
    }

    /**
     * The words that are in $words more often than in $other (as "word xN" when
     * more than once), at most 50
     *
     * @param  array<int, string>  $words
     * @param  array<int, string>  $other
     * @return array<int, string>
     */
    protected function surplus(array $words, array $other): array
    {
        $have = array_count_values($other);
        $out = [];

        foreach (array_count_values($words) as $word => $n) {
            $extra = $n - ($have[$word] ?? 0);

            if ($extra > 0) {
                $out[] = $extra > 1 ? $word.' x'.$extra : (string) $word;
            }

            if (count($out) >= 50) {
                break;
            }
        }

        return $out;
    }

    /**
     * The entries that are in $before more often than in $after, with how many
     * are gone (for an inline mark: the ones that are gone altogether)
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, int>
     */
    protected function lost(array $before, array $after): array
    {
        $out = [];

        foreach ($before as $key => $n) {
            $remaining = $after[$key] ?? 0;

            if (in_array(Str::before((string) $key, '@'), self::MARK_TAGS, true)) {
                if ($remaining === 0) {
                    $out[(string) $key] = $n;
                }

                continue;
            }

            if ($n > $remaining) {
                $out[(string) $key] = $n - $remaining;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * The visible words of an HTML string and its tag and attribute inventory:
     * tags (renamed to the editor's name, wrappers left out) and
     * "tag@attribute" entries, with a style as its properties, a class as its
     * tokens and a src or href with its value.
     *
     * @return array{words: array<int, string>, tags: array<string, int>, attributes: array<string, int>}
     */
    protected function profile(string $html): array
    {
        $empty = ['words' => [], 'tags' => [], 'attributes' => []];
        $html = mb_scrub(str_replace("\0", '', $html), 'UTF-8');

        if (trim($html) === '') {
            return $empty;
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        }
        finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return $empty;
        }

        $parts = [];
        $tags = [];
        $attributes = [];
        $this->walk($dom, $parts, $tags, $attributes);

        $text = str_replace("\u{00A0}", ' ', implode('', $parts));
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return ['words' => $words, 'tags' => $tags, 'attributes' => $attributes];
    }

    /**
     * Collect the text, the tags and the attributes under a node. The depth is
     * bounded by libxml's own (256).
     *
     * @param  array<int, string>  $parts
     * @param  array<string, int>  $tags
     * @param  array<string, int>  $attributes
     */
    protected function walk(\DOMNode $node, array &$parts, array &$tags, array &$attributes): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $parts[] = $child->nodeValue;

                continue;
            }

            if (! $child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);
            $tag = self::TAG_ALIASES[$name] ?? $name;
            $block = in_array($name, self::BLOCK_TAGS, true);

            if (! in_array($tag, self::WRAPPER_TAGS, true)) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }

            foreach ($child->attributes as $attribute) {
                foreach ($this->attributeKeys($tag, strtolower($attribute->nodeName), (string) $attribute->nodeValue) as $key) {
                    $attributes[$key] = ($attributes[$key] ?? 0) + 1;
                }
            }

            if (in_array($name, self::HIDDEN_TEXT_TAGS, true)) {
                continue;
            }

            if ($block) {
                $parts[] = ' ';
            }

            $this->walk($child, $parts, $tags, $attributes);

            if ($block) {
                $parts[] = ' ';
            }
        }
    }

    /**
     * The inventory entries one attribute stands for
     *
     * @return array<int, string>
     */
    protected function attributeKeys(string $tag, string $name, string $value): array
    {
        if ($name === 'style') {
            $properties = [];

            foreach (explode(';', $value) as $declaration) {
                $property = strtolower(trim(Str::before($declaration, ':')));

                if ($property !== '' && str_contains($declaration, ':')) {
                    $properties[] = $tag.'@style:'.$property;
                }
            }

            return $properties ?: [$tag.'@style'];
        }

        if ($name === 'class') {
            $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return $tokens ? array_map(fn ($token) => $tag.'@class:'.$token, $tokens) : [$tag.'@class'];
        }

        if (in_array($name, ['src', 'href'], true)) {
            return [$tag.'@'.$name.'='.trim($value)];
        }

        return [$tag.'@'.$name];
    }

    /**
     * One line of what a flagged row would lose, for the dry-run report
     *
     * @param  array<string, mixed>  $diff
     */
    protected function describe(array $diff): string
    {
        $parts = [];

        if (! $diff['text']['equal']) {
            $parts[] = 'text: missing "'.implode('", "', array_slice($diff['text']['missing'], 0, 6)).'"'
                .($diff['text']['added'] ? '; added "'.implode('", "', array_slice($diff['text']['added'], 0, 6)).'"' : '');
        }

        foreach (['tags_lost' => 'tags', 'attributes_lost' => 'attributes'] as $field => $label) {
            if ($diff[$field]) {
                $items = [];

                foreach (array_slice($diff[$field], 0, 6, true) as $key => $n) {
                    $items[] = $key.($n > 1 ? ' x'.$n : '');
                }

                $parts[] = $label.': '.implode(', ', $items).(count($diff[$field]) > 6 ? ', ...' : '');
            }
        }

        foreach ($diff['notes'] as $note) {
            $parts[] = $note;
        }

        return implode(' | ', $parts);
    }

    /**
     * The ids given to --accept: ['all'], a list of ids, or [] for none
     *
     * @return array<int, string>
     */
    protected function acceptList(): array
    {
        $value = $this->option('accept');

        if (! is_string($value)) {
            return [];
        }

        $ids = $this->splitIds($value);

        return in_array('all', $ids, true) ? ['all'] : $ids;
    }

    /**
     * The ids in a comma or space separated option value
     *
     * @return array<int, string>
     */
    protected function splitIds(string $value): array
    {
        return array_values(array_unique(preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    /**
     * The ids given to --restore or --compare, or null for every row
     *
     * @return array<int, string>|null
     */
    protected function idList(string $option): ?array
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->splitIds($value);
    }

    /**
     * The model names given to --model
     *
     * @return array<int, string>
     */
    protected function modelFilter(): array
    {
        return isset($this->input) ? array_values(array_filter((array) $this->option('model'), 'strlen')) : [];
    }

    /**
     * The column names given to --column
     *
     * @return array<int, string>
     */
    protected function columnFilter(): array
    {
        return isset($this->input) ? array_values(array_filter((array) $this->option('column'), 'strlen')) : [];
    }

    /**
     * Does a model class match one of the --model names (the class or its basename)?
     *
     * @param  array<int, string>  $filter
     */
    protected function matchesFilter(string $class, array $filter): bool
    {
        foreach ($filter as $name) {
            $name = ltrim($name, '\\');

            if (strcasecmp($class, $name) === 0 || strcasecmp(class_basename($class), $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
