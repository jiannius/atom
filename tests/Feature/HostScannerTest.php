<?php

use Jiannius\Atom\Services\HostScanner;
use Jiannius\Atom\Services\UpgradeCheck;

/**
 * A scanner that reads only the directories it names: the hook discover()
 * asks, and the walk and skip rules every scanner shares.
 */
function hostScannerFor(array $directories): HostScanner
{
    return new class($directories) extends HostScanner
    {
        public function __construct(protected array $named) {}

        protected function directories(): array
        {
            return $this->named;
        }

        /**
         * @return array<int, string>
         */
        public function files(string $root): array
        {
            $this->root = $root;
            $this->skipped = [];

            return array_map(fn (array $file) => $file[1], iterator_to_array($this->discover($root), false));
        }

        /**
         * @return array<int, array{path: string, reason: string}>
         */
        public function skippedPaths(): array
        {
            return $this->skipped;
        }
    };
}

function hostScannerTree(): string
{
    $root = sys_get_temp_dir().'/atom-host-scanner-'.bin2hex(random_bytes(6));

    foreach ([
        'app/Thing.php' => '<?php // a comment',
        'resources/views/page.blade.php' => '<atom:page />',
        'resources/views/vendor/atom/own.blade.php' => '<atom:page />',
        'resources/views/vendor/other/skipped.blade.php' => '<p />',
        'resources/views/livewire/app/vendor/supplier.blade.php' => '<atom:table />',
        'routes/web.php' => '<?php',
        'tests/ExampleTest.php' => '<?php',
        'node_modules/pkg/index.js' => '',
    ] as $path => $contents) {
        @mkdir(dirname($root.'/'.$path), 0777, true);
        file_put_contents($root.'/'.$path, $contents);
    }

    return $root;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/atom-host-scanner-*') ?: [] as $dir) {
        exec('rm -rf '.escapeshellarg($dir));
    }
});

it('reads the directories its hook names and no others', function () {
    $root = hostScannerTree();

    expect(hostScannerFor(['app'])->files($root))->toBe(['app/Thing.php'])
        ->and(hostScannerFor(['routes', 'tests'])->files($root))->toBe(['routes/web.php', 'tests/ExampleTest.php']);
});

it('keeps the shared skip rules whatever the hook names', function () {
    $root = hostScannerTree();

    expect(hostScannerFor(['resources'])->files($root))->toBe([
        'resources/views/livewire/app/vendor/supplier.blade.php',
        'resources/views/page.blade.php',
        'resources/views/vendor/atom/own.blade.php',
    ]);
});

it('skips a named directory that is missing or a symlink', function () {
    $root = hostScannerTree();
    symlink($root.'/app', $root.'/linked');
    $scanner = hostScannerFor(['linked', 'missing']);

    expect($scanner->files($root))->toBe([])
        ->and($scanner->skippedPaths())->toBe([['path' => 'linked', 'reason' => 'symlink']]);
});

it('defaults the hook to the DIRECTORIES constant, and UpgradeCheck is a HostScanner', function () {
    $scanner = new class extends HostScanner
    {
        public function named(): array
        {
            return $this->directories();
        }
    };

    expect($scanner->named())->toBe(HostScanner::DIRECTORIES)
        ->and(UpgradeCheck::DIRECTORIES)->toBe(HostScanner::DIRECTORIES)
        ->and(new UpgradeCheck)->toBeInstanceOf(HostScanner::class);
});
