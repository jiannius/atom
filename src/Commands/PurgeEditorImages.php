<?php

namespace Jiannius\Atom\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Casts\AsEditorContent;
use Jiannius\Atom\Casts\AsTiptapContent;
use Throwable;

class PurgeEditorImages extends Command
{
    protected $signature = 'atom:purge-editor-images
        {--force}
        {--dry-run : List what would be moved and deleted, change nothing}
        {--grace=24 : Keep files modified within this many hours (0 disables), they may be about to be referenced}
        {--path=* : Also scan the models in this directory (repeatable, absolute or relative to base_path())}';
    protected $description = 'Purge unused editor images from the disk';

    /**
     * Everything that stopped a model, column, directory or disk from being
     * read. The run is aborted before any file is touched when this is not
     * empty: an image we could not look for is not an image nobody references.
     *
     * @var array<int, string>
     */
    protected array $problems = [];

    /**
     * The casts found on at least one model column, by name: 'legacy' for
     * AsEditorContent and 'tiptap' for AsTiptapContent.
     *
     * @var array<string, bool>
     */
    protected array $castsInUse = [];

    /**
     * The editor columns found, by model class: column => 'legacy'|'tiptap'.
     *
     * @var array<string, array<string, string>>
     */
    protected array $scanned = [];

    /**
     * The names to look for inside every stored value, needle => file name: each
     * listed file's name, plus its rawurlencode()d and urlencode()d forms.
     *
     * @var array<string, string>
     */
    protected array $needles = [];

    /**
     * The file that declared each class found, for saying where a failure is.
     *
     * @var array<string, string>
     */
    protected array $classFiles = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('force')) {
            if ($this->option('dry-run')) {
                $this->error('--force and --dry-run cannot be combined.');
                return self::FAILURE;
            }

            if (!$this->confirm('Are you sure you want to purge ALL editor images? This action cannot be undone.')) {
                $this->info('Purge cancelled.');
                return self::SUCCESS;
            }

            $this->emptyPurgedFolder();

            return self::SUCCESS;
        }

        $grace = $this->option('grace');

        if (!is_numeric($grace) || $grace < 0) {
            $this->error('--grace must be a number of hours, 0 or more.');
            return self::FAILURE;
        }

        $this->problems = [];
        $this->castsInUse = [];
        $this->scanned = [];

        // list the files BEFORE reading the database: an image saved while the
        // scan runs is then not in the list, so it can never be called unreferenced
        $snapshots = $this->snapshotDisks();
        $this->needles = $this->buildNeedles($snapshots);
        $this->classFiles = [];
        $images = $this->getImagesFromModels();

        $this->reportScanned();

        foreach (array_keys($this->getDisks()) as $name) {
            if (isset($snapshots[$name]['error'])) {
                $this->problems[] = 'disk ['.$name.']: '.$snapshots[$name]['error'];
            }
        }

        if (count($this->problems)) {
            $this->error('Aborted, nothing was moved or deleted. These could not be read:');
            foreach ($this->problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        if (empty($this->castsInUse)) {
            $this->warn('No model column casts to AsEditorContent or AsTiptapContent. Nothing was moved or deleted.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $cutoff = (float) $grace > 0 ? time() - (float) $grace * 3600 : null;
        $referenced = $images->flip();
        $handled = 0;
        $recent = 0;
        $failed = 0;

        foreach ($this->getDisks() as $name => $disk) {
            foreach ($snapshots[$name]['files'] as $path) {
                // file names are unique (random-timestamp.ext), and a src may or may not carry
                // the disk's folder (a CDN origin path, a url config), so match on the name alone
                if ($referenced->has(basename($path))) {
                    continue;
                }

                try {
                    if ($cutoff !== null && $disk->lastModified($path) > $cutoff) {
                        $recent++;
                        continue;
                    }

                    if ($dry) {
                        $this->line('Would delete: ['.$name.'] '.$path);
                        $handled++;
                        continue;
                    }

                    // move the unused file to editor-purged folder just in case we need it later,
                    // and only delete it once that copy is safely written
                    $this->moveToPurgedFolder($path, $disk);
                    $disk->delete($path);

                    $this->info('Deleted: ['.$name.'] '.$path);
                    $handled++;
                }
                catch (Throwable $e) {
                    $this->error('Kept ['.$name.'] '.$path.': '.$e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info($dry
            ? 'Dry run: '.$handled.' file(s) would be deleted. Nothing was changed.'
            : 'Purge completed: '.$handled.' file(s) deleted.'
        );

        if ($recent) {
            $this->line($recent.' unreferenced file(s) kept because they were modified within the last '.$grace.' hour(s) (--grace).');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * List the files of every disk a cast writes to, by disk name. A disk that
     * cannot be listed is recorded, not thrown: it only matters if a model uses
     * that cast.
     *
     * @return array<string, array{disk?: Filesystem, files?: Collection, error?: string}>
     */
    protected function snapshotDisks(): array
    {
        $snapshots = [];

        foreach ([AsEditorContent::class, AsTiptapContent::class] as $cast) {
            $name = null;

            try {
                $name = $cast::diskName();

                if (isset($snapshots[$name])) {
                    continue;
                }

                $snapshots[$name] = ['disk' => $cast::disk(), 'files' => $this->getStorageFiles($cast::disk())];
            }
            catch (Throwable $e) {
                $snapshots[$name ?? $cast] = ['error' => $e->getMessage()];
            }
        }

        return $snapshots;
    }

    /**
     * Say which models and columns were scanned
     */
    protected function reportScanned(): void
    {
        $this->info('Scanned '.count($this->scanned).' model(s) with editor content columns:');

        foreach ($this->scanned as $class => $columns) {
            $this->line('  '.$class.': '.collect($columns)->map(fn ($kind, $column) => $column.' ('.$kind.')')->join(', '));
        }
    }

    /**
     * Get the disks to scan, by name: the disk each cast that is in use writes to
     *
     * @return array<string, Filesystem>
     */
    public function getDisks(): array
    {
        $disks = [];

        if ($this->castsInUse['legacy'] ?? false) {
            $disks[AsEditorContent::diskName()] = AsEditorContent::disk();
        }

        if ($this->castsInUse['tiptap'] ?? false) {
            $disks[AsTiptapContent::diskName()] = AsTiptapContent::disk();
        }

        return $disks;
    }

    /**
     * The needles for every listed file, needle => file name
     *
     * @param  array<string, array{files?: Collection}>  $snapshots
     * @return array<string, string>
     */
    protected function buildNeedles(array $snapshots): array
    {
        $needles = [];

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot['files'] ?? [] as $path) {
                $name = basename($path);

                foreach ([$name, rawurlencode($name), urlencode($name)] as $needle) {
                    $needles[$needle] = $name;
                }
            }
        }

        return $needles;
    }

    /**
     * The backstop that decides deletion: which listed files have their name
     * anywhere inside a stored value, whatever the format around it (HTML, JSON,
     * a CSS url(), a poster attribute, a truncated document). The raw value, the
     * decoded value and their percent-decoded forms are all searched. A name is
     * random(20)-timestamp.ext, so this can only over-keep. A name found once is
     * not searched for again.
     *
     * @return array<int, string>
     */
    protected function findNamesIn(mixed $raw, mixed $decoded): array
    {
        $haystacks = [];

        foreach ([$raw, $decoded] as $value) {
            for ($i = 0; is_string($value) && $i < 4; $i++) {
                $haystacks[] = $value;
                $value = rawurldecode($value);
            }
        }

        $haystacks = array_unique($haystacks);
        $found = [];

        foreach ($this->needles as $needle => $name) {
            if (isset($found[$name])) {
                unset($this->needles[$needle]);
                continue;
            }

            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $needle)) {
                    $found[$name] = true;
                    unset($this->needles[$needle]);
                    break;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Get all files in the editor folder
     */
    public function getStorageFiles(?Filesystem $disk = null): Collection
    {
        // both casts use the same folder rule, so the folder follows the disk given
        $disk ??= $this->getDisk();
        $folder = collect([data_get($disk->getConfig(), 'folder'), 'editor'])->filter()->join('/');

        return collect($disk->files($folder));
    }

    /**
     * Get every file name the models reference. A model or column that cannot
     * be read is recorded in $problems, never skipped.
     */
    public function getImagesFromModels(): Collection
    {
        $images = collect();
        $reader = new AsTiptapContent;

        foreach ($this->getModels() as $class) {
            try {
                $model = $this->resolveModel($class);

                if (!$model) {
                    continue;
                }

                $columns = $this->getEditorColumns($model);

                if (empty($columns)) {
                    continue;
                }

                $this->scanned[$class] = $columns;

                foreach ($this->getRows($model, array_keys($columns)) as $row) {
                    foreach (array_keys($columns) as $column) {
                        try {
                            $raw = $row->getRawOriginal($column);
                            $sources = $reader->imageSources($row, $column, $raw);

                            foreach ($this->findNamesIn($raw, $reader->get($row, $column, $raw, [])) as $name) {
                                $images->push($name);
                            }

                            foreach ($sources as $src) {
                                $clean = preg_replace('/[?#].*$/', '', $src);

                                $images->push(basename($clean));
                                $images->push(basename(rawurldecode($clean)));
                            }
                        }
                        catch (Throwable $e) {
                            $this->problems[] = $class.'::'.$column.' (row '.($row->getKey() ?? '?').'): '.$e->getMessage();
                        }
                    }
                }
            }
            catch (Throwable $e) {
                $this->problems[] = $class.': '.$e->getMessage();
            }
        }

        return $images->filter()->unique()->values();
    }

    /**
     * Every row of a model that has a value in one of the columns, trashed and
     * globally scoped rows included, read a chunk at a time
     *
     * @param  array<int, string>  $columns
     */
    protected function getRows(Model $model, array $columns): iterable
    {
        $key = $model->getKeyName();
        $chunkable = $key && $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $key);

        $query = $model->newQuery()
            ->withoutGlobalScopes()
            ->select($chunkable ? array_values(array_unique([...$columns, $key])) : $columns)
            ->where(function ($q) use ($columns) {
                foreach ($columns as $column) {
                    $q->orWhereNotNull($column);
                }
            });

        return $chunkable ? $query->lazyById(500, $key) : $query->cursor();
    }

    /**
     * Get the editor content columns of a model, as column => 'legacy'|'tiptap',
     * and remember which casts were seen
     *
     * @return array<string, string>
     */
    protected function getEditorColumns(Model $model): array
    {
        $columns = [];

        foreach ($model->getCasts() as $column => $cast) {
            $class = is_string($cast) ? str($cast)->before(':')->toString() : null;

            if (!$class) {
                continue;
            }

            if (is_a($class, AsEditorContent::class, true)) {
                $columns[$column] = 'legacy';
                $this->castsInUse['legacy'] = true;
            }
            elseif (is_a($class, AsTiptapContent::class, true)) {
                $columns[$column] = 'tiptap';
                $this->castsInUse['tiptap'] = true;
            }
        }

        return $columns;
    }

    /**
     * Instantiate a model class. Returns null for something that is
     * legitimately not a model (trait, interface, enum, abstract class) and
     * throws for a class that cannot be loaded, since that could be a model we
     * cannot see.
     */
    protected function resolveModel(string $class): ?Model
    {
        if (!class_exists($class)) {
            if (trait_exists($class) || interface_exists($class)) {
                return null;
            }

            throw new \RuntimeException('the class declared in '.($this->classFiles[$class] ?? 'its file').' could not be loaded (autoload)');
        }

        if (!is_subclass_of($class, Model::class)) {
            return null;
        }

        if ((new \ReflectionClass($class))->isAbstract()) {
            return null;
        }

        return app($class);
    }

    /**
     * The directories to scan for models: app/Models plus any --path given
     *
     * @return array<int, string>
     */
    protected function getModelPaths(): array
    {
        $paths = [app_path('Models')];

        foreach ((array) $this->option('path') as $path) {
            if (trim((string) $path) === '') {
                $this->problems[] = '--path: empty (it would scan the whole project)';
                continue;
            }

            $paths[] = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

            if (!is_dir(last($paths))) {
                $this->problems[] = '--path '.$path.': not a directory';
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Get the classes declared in the PHP files under the model directories,
     * subdirectories included. A file that declares no class, enum, trait or
     * interface (a helpers file) is skipped. A directory or file that cannot be
     * read is recorded in $problems.
     */
    public function getModels(): Collection
    {
        $models = [];

        foreach ($this->getModelPaths() as $path) {
            if (!is_dir($path)) {
                continue;
            }

            try {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($files as $file) {
                    if (!$file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    try {
                        foreach ($this->getDeclaredClasses($file->getPathname()) as $class) {
                            $models[] = $class;
                            $this->classFiles[$class] = $file->getPathname();
                        }
                    }
                    catch (Throwable $e) {
                        $this->problems[] = $file->getPathname().': '.$e->getMessage();
                    }
                }
            }
            catch (Throwable $e) {
                $this->problems[] = $path.': '.$e->getMessage();
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        return collect($models);
    }

    /**
     * The fully qualified name of every class, enum, trait and interface a PHP
     * file declares (an abstract base and the model beside it, say), or an empty
     * array when it declares none
     *
     * @return array<int, string>
     */
    protected function getDeclaredClasses(string $file): array
    {
        $source = file_get_contents($file);

        if ($source === false) {
            throw new \RuntimeException('could not be read');
        }

        $tokens = token_get_all($source);
        $count = count($tokens);
        $namespace = '';
        $declared = [];
        $declarations = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];

                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                        $namespace .= $next[1];
                    }
                    elseif ($next === ';' || $next === '{') {
                        break;
                    }
                }
            }

            if (!in_array($token[0], $declarations, true)) {
                continue;
            }

            // Foo::class is not a declaration
            $previous = $this->getSignificantToken($tokens, $i, -1);

            if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
                continue;
            }

            // the name follows; `new class {` and `new class extends X` have none
            $name = $this->getSignificantToken($tokens, $i, 1);

            if (is_array($name) && $name[0] === T_STRING) {
                $declared[] = ltrim($namespace.'\\'.$name[1], '\\');
            }
        }

        return $declared;
    }

    /**
     * The nearest token before ($step -1) or after ($step 1) an index that is
     * not whitespace or a comment
     *
     * @param  array<int, array|string>  $tokens
     */
    protected function getSignificantToken(array $tokens, int $index, int $step): array|string|null
    {
        for ($i = $index + $step; isset($tokens[$i]); $i += $step) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $tokens[$i];
            }
        }

        return null;
    }

    /**
     * Get the disk AsEditorContent writes to
     */
    public function getDisk(): Filesystem
    {
        return AsEditorContent::disk();
    }

    /**
     * Move the file to the purged folder. Throws when the copy cannot be made,
     * so the caller never deletes a file it has no backup of.
     */
    public function moveToPurgedFolder(string $path, ?Filesystem $disk = null): void
    {
        $content = ($disk ?? $this->getDisk())->get($path);

        if ($content === null || $content === false) {
            throw new \RuntimeException('could not read the file to back it up');
        }

        if (!Storage::disk('local')->put('editor-purged/'.basename($path), $content)) {
            throw new \RuntimeException('could not write the backup');
        }
    }

    /**
     * Empty the purged folder
     */
    public function emptyPurgedFolder(): void
    {
        $path = storage_path('app/private/editor-purged');

        if (is_dir($path)) {
            $files = glob($path . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $this->info('All files in editor-purged have been deleted.');
        } else {
            $this->info('editor-purged folder does not exist.');
        }
    }
}
