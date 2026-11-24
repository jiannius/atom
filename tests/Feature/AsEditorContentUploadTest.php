<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Casts\AsEditorContent;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

beforeEach(function () {
    // Livewire ships a null directory and falls back to livewire-tmp itself: do not force it here.
    config(['livewire.temporary_file_upload.directory' => null]);

    Storage::fake('legacy-editor-images');
    config(['filesystems.default' => 'legacy-editor-images']);
});

class LegacyEditorCastModel extends Model
{
    protected $casts = ['body' => AsEditorContent::class];
}

function legacySet(mixed $value): mixed
{
    return (new AsEditorContent)->set(new LegacyEditorCastModel, 'body', $value, []);
}

function legacyHtml(string ...$srcs): string
{
    return '<p>x</p>'.implode('', array_map(fn ($src) => '<img src="'.$src.'">', $srcs));
}

function legacyPreviewSrc(string $name): string
{
    return 'https://app.test/livewire-abc123/preview-file/'.$name;
}

function legacyPng(): string
{
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function legacyUpload(string $contents, ?string $name = null): string
{
    $name ??= strtolower(str()->random(40)).'.bin';

    FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($name, false), $contents);

    return $name;
}

describe('AsEditorContent temporary uploads', function () {
    it('persists a real upload under the default (null) temp directory, with an extension from its mime', function () {
        $name = legacyUpload(legacyPng());

        $stored = unserialize(legacySet(legacyHtml(legacyPreviewSrc($name).'?expires=1&signature=abc')));

        expect($stored)->not->toContain('preview-file')
            ->and($stored)->toContain('.png')
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toHaveCount(1)
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeFalse();
    });

    it('leaves a src that names a file outside Livewire\'s temp directory alone', function (string $file) {
        $private = storage_path('app/private');
        @mkdir($private, 0777, true);
        file_put_contents($private.'/legacy-receipt-456.jpg', legacyPng());

        $html = legacyHtml('https://x.test/livewire-x/preview-file/'.$file);

        try {
            expect(unserialize(legacySet($html)))->toBe($html)
                ->and(file_exists($private.'/legacy-receipt-456.jpg'))->toBeTrue()
                ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty();
        } finally {
            @unlink($private.'/legacy-receipt-456.jpg');
        }
    })->with([
        'a private file' => 'legacy-receipt-456.jpg',
        'dot dot' => '..',
        'traversal' => '../legacy-receipt-456.jpg',
        'encoded traversal' => '..%2Flegacy-receipt-456.jpg',
        'double encoded' => '%252e%252e',
        'a directory name' => 'livewire-tmp',
        'backslash' => '..\\legacy-receipt-456.jpg',
        'nul byte' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.bin%00.jpg',
    ]);

    it('does not read a well-named file that sits beside the temp directory instead of in it', function () {
        $name = str_repeat('d', 40).'.jpg';
        $private = storage_path('app/private');
        @mkdir($private, 0777, true);
        file_put_contents($private.'/'.$name, legacyPng());
        $html = legacyHtml(legacyPreviewSrc($name));

        try {
            expect(unserialize(legacySet($html)))->toBe($html)
                ->and(file_exists($private.'/'.$name))->toBeTrue()
                ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty();
        } finally {
            @unlink($private.'/'.$name);
        }
    });

    it('refuses a temp file whose real path leaves the temp directory', function () {
        $storage = FileUploadConfiguration::storage();
        file_put_contents($storage->path('outside-legacy.png'), legacyPng());

        $name = str_repeat('e', 40).'.bin';
        $directory = $storage->path(FileUploadConfiguration::path('', false));
        @mkdir($directory, 0777, true);
        symlink($storage->path('outside-legacy.png'), $directory.'/'.$name);
        $html = legacyHtml(legacyPreviewSrc($name));

        expect(unserialize(legacySet($html)))->toBe($html)
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty();
    });

    it('leaves a temp upload that is not an image as it is', function (string $contents) {
        $name = legacyUpload($contents);
        $html = legacyHtml(legacyPreviewSrc($name));

        expect(unserialize(legacySet($html)))->toBe($html)
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty()
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeTrue();
    })->with([
        'text' => 'plain text',
        'html' => '<html><script>alert(1)</script></html>',
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    ]);

    it('leaves an image type it does not store alone', function () {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagebmp($image);
        $html = legacyHtml(legacyPreviewSrc(legacyUpload(ob_get_clean())));

        expect(unserialize(legacySet($html)))->toBe($html)
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty();
    });

    it('leaves a corrupt image alone without reporting it', function () {
        $html = legacyHtml(legacyPreviewSrc(legacyUpload(substr(legacyPng(), 0, 40))));

        expect(unserialize(legacySet($html)))->toBe($html)
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toBeEmpty();
    });

    it('persists the same temp upload used twice in one document once', function () {
        $name = legacyUpload(legacyPng());
        $src = legacyPreviewSrc($name);

        $other = 'https://elsewhere.test/livewire-zzz999/preview-file/'.$name.'?expires=1';

        $stored = unserialize(legacySet(legacyHtml($src, $other)));

        expect($stored)->not->toContain('preview-file')
            ->and(Storage::disk('legacy-editor-images')->allFiles())->toHaveCount(1);
    });

    it('deletes the Livewire meta sidecar with the temp file', function () {
        $name = legacyUpload(legacyPng());
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($name.'.json', false), '{}');

        legacySet(legacyHtml(legacyPreviewSrc($name)));

        expect(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name.'.json', false)))->toBeFalse();
    });

    it('finds uploads under a non-default temp directory', function () {
        config(['livewire.temporary_file_upload.directory' => 'custom/uploads']);

        $name = legacyUpload(legacyPng());
        $stored = unserialize(legacySet(legacyHtml(legacyPreviewSrc($name))));

        expect($stored)->not->toContain('preview-file')
            ->and(FileUploadConfiguration::path($name, false))->toStartWith('custom/uploads/');
    });

    it('does not use a tiptapStoreImage hook, which only the Tiptap cast honours', function () {
        $model = new class extends Model {
            protected $casts = ['body' => AsEditorContent::class];
            public static int $calls = 0;
            public function tiptapStoreImage($tmpPath, $key) { static::$calls++; return 'https://cdn/x.png'; }
        };

        $html = legacyHtml(legacyPreviewSrc(legacyUpload(legacyPng())));
        $stored = unserialize((new AsEditorContent)->set($model, 'body', $html, []));

        expect($model::$calls)->toBe(0)->and($stored)->not->toContain('preview-file');
    });
});
