<?php

namespace Jiannius\Atom\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jiannius\Atom\Casts\AsTiptapContent;
use Jiannius\Atom\Services\EditorBackups;
use Throwable;

/**
 * Finds the models a command scans: every class declared under app/Models
 * (subdirectories included) plus any --path directory. The command declares a
 * repeatable --path option. Anything it could not read is recorded in
 * $problems, never skipped, because a model we cannot see is not a model that
 * holds nothing.
 */
trait DiscoversModels
{
    /**
     * The cast v4 removed, by name: a column still cast as it is not read
     */
    protected const REMOVED_CAST = 'Jiannius\\Atom\\Casts\\AsEditorContent';

    /**
     * Everything that stopped a model, column, directory or disk from being
     * read. The run is aborted before any file is touched when this is not
     * empty: an image we could not look for is not an image nobody references.
     *
     * @var array<int, string>
     */
    protected array $problems = [];

    /**
     * The file that declared each class found, for saying where a failure is.
     *
     * @var array<string, string>
     */
    protected array $classFiles = [];

    /**
     * Extra model directories for a caller that has no --path option to read
     *
     * @var array<int, string>
     */
    protected array $modelPaths = [];

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
     * The columns of a model cast as AsTiptapContent and the ones still cast as
     * the removed AsEditorContent, in cast order. Records nothing: what a
     * removed column means is the command's to say.
     *
     * @return array{tiptap: array<int, string>, removed: array<int, string>}
     */
    protected function castColumns(Model $model): array
    {
        $columns = ['tiptap' => [], 'removed' => []];

        foreach ($model->getCasts() as $column => $cast) {
            $class = is_string($cast) ? Str::before($cast, ':') : '';

            if ($class === '') {
                continue;
            }

            if (strcasecmp(ltrim($class, '\\'), self::REMOVED_CAST) === 0) {
                $columns['removed'][] = $column;
            }
            elseif (is_a($class, AsTiptapContent::class, true)) {
                $columns['tiptap'][] = $column;
            }
        }

        return $columns;
    }

    /**
     * Whether a model casts any column as AsTiptapContent, or as the removed AsEditorContent
     */
    protected function hasEditorColumn(Model $model): bool
    {
        $columns = $this->castColumns($model);

        return (bool) ($columns['tiptap'] || $columns['removed']);
    }

    /**
     * Note a model's connection in $names, by its resolved name (so the default
     * connection is one, whether a model names it or not), as the name to hand
     * EditorBackups::on(). A connection this config does not define cannot hold
     * backups this config wrote: a model without an editor column on one is
     * warned about and skipped (returns false), one with a column throws, since
     * its rows cannot be read and the caller records the problem.
     *
     * @param  array<string, string|null>  $names
     * @param  bool|\Closure(): bool  $hasEditorColumn  what counts as an editor column is the caller's rule
     */
    protected function addConnection(array &$names, Model $model, string $class, bool|\Closure $hasEditorColumn): bool
    {
        try {
            $names[$model->getConnection()->getName()] = $model->getConnectionName();

            return true;
        }
        catch (\InvalidArgumentException $e) {
            if ($hasEditorColumn instanceof \Closure ? $hasEditorColumn() : $hasEditorColumn) {
                throw $e;
            }

            if (isset($this->output)) {
                $this->warn($class.': '.EditorBackups::describe($e).' (no editor column, so it is skipped)');
            }

            return false;
        }
    }

    /**
     * The connections that may hold backups, by their resolved name: the
     * default one and every connection a discovered model uses. A connection
     * that cannot be resolved is a problem, never skipped.
     *
     * @return array<int, string|null> the name to hand EditorBackups::on()
     */
    protected function connectionNames(): array
    {
        $names = [DB::connection()->getName() => null];

        foreach ($this->getModels() as $class) {
            try {
                $model = $this->resolveModel($class);

                if ($model) {
                    $this->addConnection($names, $model, $class, fn () => $this->hasEditorColumn($model));
                }
            }
            catch (Throwable $e) {
                $this->problems[] = $class.': '.EditorBackups::describe($e);
            }
        }

        return array_values($names);
    }

    /**
     * The directories to scan for models: app/Models plus any --path given
     *
     * @return array<int, string>
     */
    protected function getModelPaths(): array
    {
        $paths = [app_path('Models')];

        foreach ([...$this->modelPaths, ...(isset($this->input) ? (array) $this->option('path') : [])] as $path) {
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
}
