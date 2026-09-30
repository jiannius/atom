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
    /**
     * What splits a stored value into tokens: any run of characters that cannot
     * be in an ASCII name (letters, digits, and . _ - % +, which are what a
     * name, a rawurlencode()d name and a urlencode()d name are made of). Every
     * non-ASCII byte splits, so a name beside a fullwidth colon, a curly quote,
     * an NBSP, an ideographic space or an emoji is still a token; a name that
     * holds one is searched for as a substring instead.
     */
    protected const TOKEN_SPLIT = '/[^A-Za-z0-9._%+\-]+/';

    /**
     * A needle made only of token characters can be found as a token
     */
    protected const TOKEN_ONLY = '/^[A-Za-z0-9._%+\-]+$/D';

    /**
     * A JSON escape (\uXXXX, \n \t \r \b \f) glued to a name would hide it
     * from a token, so a variant of the value with each one read as a slash is
     * scanned too
     */
    protected const JSON_ESCAPES = '/\\\\(?:u[0-9a-fA-F]{4}|[nrtbf])/';

    /**
     * Inside a token, a name may begin after one of these, or end before one
     * (thumb-NAME, NAME.webp, NAME-2x), so those spans are looked up too
     */
    protected const TOKEN_BOUNDARIES = '-_.+';

    /**
     * Say how far a long scan has got every this many rows
     */
    protected const PROGRESS_EVERY = 1000;

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
     * The needles with no delimiter character in them, needle => the file names
     * it stands for (usually one: two files collide when one's name is the
     * other's urlencode()d form, a b.jpg and a+b.jpg). A value is split into
     * tokens on delimiters, so these are found by hash lookup.
     *
     * @var array<string, array<int, string>>
     */
    protected array $tokenNeedles = [];

    /**
     * The needles with a delimiter character in them (a name with a space, a
     * bracket or a backslash, say), needle => the file names it stands for. A
     * token can never hold one, so these are searched for as plain substrings.
     * They are rare.
     *
     * @var array<string, array<int, string>>
     */
    protected array $substringNeedles = [];

    /**
     * Every needle of each file name still being looked for, name => needles.
     *
     * @var array<string, array<int, string>>
     */
    protected array $needleNames = [];

    /**
     * The lengths the token needles come in, length => true.
     *
     * @var array<int, bool>
     */
    protected array $needleLengths = [];

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
        $this->indexNeedles($this->buildNeedles($snapshots));
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
     * The needles for every listed file, needle => the file names it stands for
     * (a list: two files can share a needle, and a hit must keep both): the name, its
     * rawurlencode()d and urlencode()d forms, and, for a name JSON would write
     * differently (non-ASCII, a quote, a backslash), the way json_encode() writes
     * it, in both hex cases, since a truncated JSON document holds that form
     * and cannot be decoded
     *
     * @param  array<string, array{files?: Collection}>  $snapshots
     * @return array<string, array<int, string>>
     */
    protected function buildNeedles(array $snapshots): array
    {
        $needles = [];

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot['files'] ?? [] as $path) {
                $name = basename($path);
                $forms = [$name, rawurlencode($name), urlencode($name)];
                $json = json_encode($name, JSON_UNESCAPED_SLASHES);

                if ($json !== false && substr($json, 1, -1) !== $name) {
                    $json = substr($json, 1, -1);
                    $forms[] = $json;
                    $forms[] = preg_replace_callback('/\\\\u[0-9a-f]{4}/', fn ($hex) => '\\u'.strtoupper(substr($hex[0], 2)), $json);
                }

                foreach ($forms as $needle) {
                    if (! in_array($name, $needles[$needle] ?? [], true)) {
                        $needles[$needle][] = $name;
                    }
                }
            }
        }

        return $needles;
    }

    /**
     * Sort the needles into the ones found by token lookup and the ones that
     * have to be searched for as substrings
     *
     * @param  array<string, array<int, string>>  $needles  needle => file names
     */
    protected function indexNeedles(array $needles): void
    {
        $this->tokenNeedles = [];
        $this->substringNeedles = [];
        $this->needleNames = [];
        $this->needleLengths = [];

        foreach ($needles as $needle => $names) {
            $needle = (string) $needle;

            if ($needle === '') {
                continue;
            }

            foreach ($names as $name) {
                $this->needleNames[$name][] = $needle;
            }

            if (preg_match(self::TOKEN_ONLY, $needle)) {
                $this->tokenNeedles[$needle] = $names;
                $this->needleLengths[strlen($needle)] = true;
            }
            else {
                $this->substringNeedles[$needle] = $names;
            }
        }
    }

    /**
     * The backstop that decides deletion: which listed files have their name
     * inside a stored value, whatever the format around it (HTML, JSON, a CSS
     * url(), a poster attribute, a truncated document). The raw value, the
     * decoded value and their percent-decoded forms are all searched. A name is
     * random(20)-timestamp.ext, so this can only over-keep. A name found once is
     * not searched for again.
     *
     * Each form is split once into tokens (see TOKEN_SPLIT) and every token is
     * looked up in a hash of the needles, so the cost follows the length of the
     * value, not the number of files. A needle that holds a delimiter cannot be
     * a token, so those alone are searched for as substrings.
     *
     * @return array<int, string>
     */
    protected function findNamesIn(mixed $raw, mixed $decoded): array
    {
        $haystacks = [];

        foreach ([$raw, $decoded] as $value) {
            foreach (is_string($value) ? $this->decodedForms($value) : [] as $form) {
                $haystacks[] = $form;

                if (str_contains($form, '\\')) {
                    $unescaped = preg_replace(self::JSON_ESCAPES, '/', $form);

                    // null when PCRE gives up: that variant is skipped, the form itself is still searched
                    if (is_string($unescaped)) {
                        $haystacks[] = $unescaped;
                    }
                }
            }
        }

        $found = [];

        foreach (array_unique($haystacks) as $haystack) {
            if (empty($this->tokenNeedles) && empty($this->substringNeedles)) {
                break;
            }

            foreach ($this->findTokenNamesIn($haystack) as $name) {
                $found[$name] = true;
            }

            foreach ($this->substringNeedles as $needle => $names) {
                if (str_contains($haystack, (string) $needle)) {
                    foreach ($names as $name) {
                        $found[$name] = true;
                    }
                }
            }

            $this->forgetNames(array_keys($found));
        }

        return array_keys($found);
    }

    /**
     * A value and its percent-decoded forms, three levels deep, each level read
     * both ways: rawurldecode() and urldecode() (a "+" is a space), since a value
     * may be rawurlencode()d or urlencode()d, or a mix, level by level. Both give
     * the same string unless there is a "+", so a value without one has one form
     * per level.
     *
     * @return array<int, string>
     */
    protected function decodedForms(string $value): array
    {
        // nothing to decode without a "%", and a "+" is the only thing urldecode() changes
        if (! str_contains($value, '%')) {
            return str_contains($value, '+') ? [$value, urldecode($value)] : [$value];
        }

        $forms = [$value => true];
        $level = [$value];

        for ($i = 0; $i < 3; $i++) {
            $next = [];

            foreach ($level as $form) {
                foreach ([rawurldecode($form), urldecode($form)] as $decoded) {
                    $next[$decoded] = true;
                }
            }

            $level = array_map('strval', array_keys($next));

            foreach ($level as $form) {
                $forms[$form] = true;
            }
        }

        return array_map('strval', array_keys($forms));
    }

    /**
     * The file names whose token needle is a token of the string, or a span of
     * one that begins at its start or after a boundary and ends at its end or
     * before a boundary. Falls back to searching every token needle as a
     * substring if the string cannot be split.
     *
     * @return array<int, string>
     */
    protected function findTokenNamesIn(string $haystack): array
    {
        $tokens = preg_split(self::TOKEN_SPLIT, $haystack, -1, PREG_SPLIT_NO_EMPTY);
        $names = [];

        if ($tokens === false) {
            foreach ($this->tokenNeedles as $needle => $needleNames) {
                if (str_contains($haystack, (string) $needle)) {
                    array_push($names, ...$needleNames);
                }
            }

            return $names;
        }

        $min = $this->needleLengths ? min(array_keys($this->needleLengths)) : PHP_INT_MAX;
        $max = $this->needleLengths ? max(array_keys($this->needleLengths)) : 0;

        // a token repeated in the value is looked up once (numeric ones become int keys)
        foreach (array_keys(array_flip($tokens)) as $token) {
            $token = (string) $token;
            $length = strlen($token);

            if ($length < $min) {
                continue;
            }

            if (strpbrk($token, self::TOKEN_BOUNDARIES) === false) {
                if (isset($this->tokenNeedles[$token])) {
                    array_push($names, ...$this->tokenNeedles[$token]);
                }

                continue;
            }

            // where a span may begin and end: the token's edges and either side of each boundary
            $starts = [0];
            $ends = [];

            for ($i = 0; $i < $length; $i++) {
                if (str_contains(self::TOKEN_BOUNDARIES, $token[$i])) {
                    $starts[] = $i + 1;
                    $ends[] = $i;
                }
            }

            $ends[] = $length;
            $first = 0;
            $lastEnd = count($ends);

            foreach ($starts as $start) {
                // ends only grow, so the first end that can be long enough only moves right
                while ($first < $lastEnd && $ends[$first] - $start < $min) {
                    $first++;
                }

                for ($i = $first; $i < $lastEnd; $i++) {
                    $span = $ends[$i] - $start;

                    if ($span > $max) {
                        break;
                    }

                    if (isset($this->needleLengths[$span]) && isset($this->tokenNeedles[$sub = substr($token, $start, $span)])) {
                        array_push($names, ...$this->tokenNeedles[$sub]);
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Stop looking for files already found. A needle a still-unfound file shares
     * stays in the search, minus the found name.
     *
     * @param  array<int, string>  $names
     */
    protected function forgetNames(array $names): void
    {
        foreach ($names as $name) {
            foreach ($this->needleNames[$name] ?? [] as $needle) {
                foreach (['tokenNeedles', 'substringNeedles'] as $index) {
                    if (! isset($this->{$index}[$needle])) {
                        continue;
                    }

                    $this->{$index}[$needle] = array_values(array_diff($this->{$index}[$needle], [$name]));

                    if (empty($this->{$index}[$needle])) {
                        unset($this->{$index}[$needle]);
                    }
                }
            }

            unset($this->needleNames[$name]);
        }
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

                $read = 0;

                foreach ($this->getRows($model, array_keys($columns)) as $row) {
                    if (++$read % self::PROGRESS_EVERY === 0) {
                        $this->info('  '.$class.': '.$read.' row(s) read');
                    }

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
