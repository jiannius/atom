<?php

namespace Jiannius\Atom\Tests\Support;

use FilesystemIterator;
use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Services\DesignChecks\Rule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The design check's fixture hosts (tests/Fixtures/DesignHost): one mini host per check, named for its id, where a line
 * marked `{{-- expect: <id> --}}` (Blade) or `// expect: <id>` (PHP) must be found and every other line must not.
 *
 *  - The marker trails the line it describes, or sits alone on the line above it. `x2` after an id expects two findings
 *    there, and several ids are separated by commas: `{{-- expect: palette-colour x2, shadow --}}`.
 *  - A host named `_...` is a fixture of the harness itself, not a check's: `_clean`, `_markers` and `_skips`.
 *
 * A check adds a directory here and a class in src/Services/DesignChecks/, and nothing else: DesignCheckFixturesTest finds both.
 */
class DesignHost
{
    public static function root(): string
    {
        return realpath(__DIR__.'/../Fixtures/DesignHost');
    }

    public static function path(string $name): string
    {
        return static::root().'/'.$name;
    }

    /**
     * The hosts there are, by directory name
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        $names = array_map('basename', glob(static::root().'/*', GLOB_ONLYDIR) ?: []);
        sort($names);

        return $names;
    }

    /**
     * Scan a fixture host with some checks (all of them when `$only` is null)
     *
     * @param  array<int, string>|null  $only
     * @return array<string, mixed>
     */
    public static function scan(string $name, ?array $only = null): array
    {
        return (new DesignCheck)->scan(static::path($name), $only);
    }

    /**
     * What a scan found, as `file:line:id` counted
     *
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    public static function found(array $result): array
    {
        $found = [];

        foreach ($result['checks'] as $id => $check) {
            foreach ($check['findings'] as $finding) {
                $key = $finding['file'].':'.$finding['line'].':'.$id;
                $found[$key] = ($found[$key] ?? 0) + 1;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * What the `expect:` markers of a host ask for, as `file:line:id` counted
     *
     * @return array<string, int>
     */
    public static function expected(string $dir): array
    {
        $expected = [];

        foreach (static::files($dir) as $relative) {
            $source = file_get_contents($dir.'/'.$relative);
            $lines = explode("\n", $source);
            $blade = str_ends_with($relative, '.blade.php');
            $pattern = $blade ? '/\{\{--\s*expect:\s*(.*?)\s*--\}\}/' : '/(?:\/\/|#|\/\*)\s*expect:\s*(.*?)\s*(?:\*\/)?$/m';

            if (! preg_match_all($pattern, $source, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($found as $match) {
                $line = substr_count($source, "\n", 0, $match[0][1]) + 1;
                $start = strrpos(substr($source, 0, $match[0][1]), "\n");
                $before = trim(substr($source, $start === false ? 0 : $start + 1, $match[0][1] - ($start === false ? 0 : $start + 1)));
                $target = $line;

                if ($before === '') {
                    $target = $line + 1;

                    while (isset($lines[$target - 1]) && trim($lines[$target - 1]) === '') {
                        $target++;
                    }
                }

                foreach (explode(',', $match[1][0]) as $item) {
                    if (! preg_match('/^\s*([a-z][a-z0-9-]*)(?:\s+x(\d+))?\s*$/', $item, $parts)) {
                        throw new \InvalidArgumentException($relative.':'.$line.': cannot read `expect: '.$match[1][0].'`');
                    }

                    $key = $relative.':'.$target.':'.$parts[1];
                    $expected[$key] = ($expected[$key] ?? 0) + (int) ($parts[2] ?? 1);
                }
            }
        }

        ksort($expected);

        return $expected;
    }

    /**
     * The Blade and PHP files of a host, from its root
     *
     * @return array<int, string>
     */
    public static function files(string $dir): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), '.php')) {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($dir) + 1));
            }
        }

        sort($files);

        return $files;
    }

    /**
     * A temp host with these files, by path from its root; remove() it when done
     *
     * @param  array<string, string>  $files
     */
    public static function temp(array $files = []): string
    {
        $dir = sys_get_temp_dir().'/atom-design-check-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        foreach ($files as $path => $content) {
            @mkdir(dirname($dir.'/'.$path), 0777, true);
            file_put_contents($dir.'/'.$path, $content);
        }

        return $dir;
    }

    /**
     * A temp host holding atom's pattern pages (resources/views/docs/patterns) as its `resources/views/`; remove() it when done
     */
    public static function patterns(): string
    {
        $files = [];

        foreach (glob(dirname(__DIR__, 2).'/resources/views/docs/patterns/*.blade.php') as $path) {
            $files['resources/views/'.basename($path)] = file_get_contents($path);
        }

        return static::temp($files);
    }

    /**
     * What one check finds in one Blade source: its findings
     *
     * @return array<int, array<string, mixed>>
     */
    public static function scanSource(string $source, string $id): array
    {
        $dir = static::temp(['resources/views/a.blade.php' => $source]);

        try {
            return (new DesignCheck)->scan($dir, [$id])['checks'][$id]['findings'];
        } finally {
            static::remove($dir);
        }
    }

    /**
     * The notes of what one check finds in one Blade source, by line
     *
     * @return array<int, ?string>
     */
    public static function notes(string $source, string $id): array
    {
        return array_column(static::scanSource($source, $id), 'note', 'line');
    }

    /**
     * The marker problems of a scan as [file, id, problem], without their lines
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array{0: string, 1: ?string, 2: string}>
     */
    public static function problems(array $result): array
    {
        return array_map(fn ($problem) => [$problem['file'], $problem['id'] ?? null, $problem['problem']], $result['markers']);
    }

    /**
     * A check made in a test, to hold the runner to what it promises one. `ID` is a constant, so each probe is its own
     * class, compiled with its id. It flags every match of `pattern` (`<atom:button` by default) with the note "a match", leaves its
     * file in the context under its id, and hands `finish()` a third element.
     *
     * @param  array<string, mixed>  $behaviour  kinds, structural, throws, throwsInFinish, finish, pattern, noNote
     */
    public static function probe(string $id, array $behaviour = []): Rule
    {
        return eval('return new class($behaviour) extends \Jiannius\Atom\Services\DesignChecks\Rule {
            public const ID = '.var_export($id, true).';
            public const SEVERITY = "info";
            public const GROUP = "Copy and state";
            public function __construct(protected array $behaviour) {}
            public function title(): string { return "Probe"; }
            public function fix(): string { return "Use `<atom:card>`."; }
            public function rule(): string { return "r"; }
            public function detect(): string { return "a probe"; }
            public function kinds(): array { return $this->behaviour["kinds"] ?? ["blade"]; }
            public function structural(): bool { return $this->behaviour["structural"] ?? true; }
            public function find(\Jiannius\Atom\Services\DesignChecks\File $file, \Jiannius\Atom\Services\DesignChecks\Context $context): iterable {
                if ($this->behaviour["throws"] ?? false) { throw new \RuntimeException("boom"); }
                $context->add(static::ID, $file->path);
                preg_match_all($this->behaviour["pattern"] ?? "/<atom:button/", $file->text(), $found, PREG_OFFSET_CAPTURE);
                foreach ($found[0] as [, $offset]) { yield ($this->behaviour["noNote"] ?? false) ? [$offset] : [$offset, "a match", ["seen" => $file->path]]; }
            }
            public function finish(array $findings, \Jiannius\Atom\Services\DesignChecks\Context $context): array {
                if ($this->behaviour["throwsInFinish"] ?? false) { throw new \RuntimeException("late"); }
                return ($this->behaviour["finish"] ?? fn ($findings) => $findings)($findings, $context);
            }
        };');
    }

    /**
     * A fresh copy of a fixture host in a temp directory
     */
    public static function copy(string $name): string
    {
        $dir = sys_get_temp_dir().'/atom-design-check-'.bin2hex(random_bytes(6));
        static::copyTree(static::path($name), $dir);

        return $dir;
    }

    public static function copyTree(string $from, string $to): void
    {
        @mkdir($to, 0777, true);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);

            $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
    }

    public static function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    /**
     * Every file in a tree with its hash, to prove nothing changed
     *
     * @return array<string, string>
     */
    public static function snapshot(string $dir): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[substr($file->getPathname(), strlen($dir) + 1)] = md5_file($file->getPathname()).filemtime($file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
