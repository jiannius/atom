<?php

namespace Jiannius\Atom\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

        foreach (isset($this->input) ? (array) $this->option('path') : [] as $path) {
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
