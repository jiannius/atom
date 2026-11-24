<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Jiannius\Atom\Casts\AsTiptapContent;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

beforeEach(function () {
    // Livewire ships a null directory and falls back to livewire-tmp itself: do not force it here.
    config(['livewire.temporary_file_upload.directory' => null]);

    Storage::fake('editor-images');
    config(['atom.editor.disk' => 'editor-images']);
});

class TiptapCastModel extends Model
{
    protected $casts = ['body' => AsTiptapContent::class];
}

function castGet($value): mixed
{
    return (new AsTiptapContent)->get(new TiptapCastModel, 'body', $value, []);
}

function castSet($value): mixed
{
    return (new AsTiptapContent)->set(new TiptapCastModel, 'body', $value, []);
}

describe('AsTiptapContent', function () {
    it('stores a JSON string unchanged when there are no images', function () {
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hi']]]]]);

        expect(castSet($json))->toBe($json);
    });

    it('returns stored JSON as-is on get', function () {
        $json = '{"type":"doc","content":[]}';

        expect(castGet($json))->toBe($json);
    });

    it('dual-reads legacy serialized-HTML rows as HTML on get', function () {
        $legacy = serialize('<p>legacy <strong>html</strong></p>');

        expect(castGet($legacy))->toBe('<p>legacy <strong>html</strong></p>');
    });

    it('leaves non-temp image src untouched', function () {
        $json = json_encode([
            'type' => 'doc',
            'content' => [['type' => 'image', 'attrs' => ['src' => 'https://cdn.example.com/a.png']]],
        ]);

        expect(castSet($json))->toBe($json);
    });

    it('stores null for empty content', function () {
        expect(castSet(''))->toBeNull();
        expect(castSet(null))->toBeNull();
    });

    it('uses the model tiptapStoreImage override when present', function () {
        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public static array $received = [];
            public function tiptapStoreImage($tmpPath, $key) { static::$received = [$tmpPath, file_get_contents($tmpPath)]; return 'https://cdn/overridden.png'; }
        };

        $name = tiptapTemporaryUpload($png = tiptapPng());

        $out = json_decode((new AsTiptapContent)->set($model, 'body', tiptapDoc(tiptapPreviewSrc($name)), []), true);

        expect($out['content'][0]['attrs']['src'])->toBe('https://cdn/overridden.png')
            ->and($model::$received[1])->toBe($png)
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeFalse();
    });

    it('persists a real upload under the default (null) temp directory, with an extension from its mime', function () {
        $name = tiptapTemporaryUpload(tiptapPng());

        $out = json_decode(castSet(tiptapDoc(tiptapPreviewSrc($name).'?expires=1&signature=abc')), true);
        $src = $out['content'][0]['attrs']['src'];

        expect($src)->not->toContain('preview-file')
            ->and($src)->toEndWith('.png')
            ->and(Storage::disk('editor-images')->allFiles())->toHaveCount(1)
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeFalse();
    });

    it('leaves a temp upload that is not an image as it is', function () {
        $name = tiptapTemporaryUpload('plain text, not an image');
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect(castSet($doc))->toBe($doc)
            ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty()
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeTrue();
    });

    it('leaves a src that names a file outside Livewire\'s temp directory alone', function (string $file) {
        $private = storage_path('app/private');
        @mkdir($private, 0777, true);
        file_put_contents($private.'/receipt-123.jpg', tiptapPng());

        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public static int $calls = 0;
            public function tiptapStoreImage($tmpPath, $key) { static::$calls++; return 'https://cdn/stolen.png'; }
        };

        $doc = tiptapDoc('https://x.test/livewire-x/preview-file/'.$file);

        try {
            expect(castSet($doc))->toBe($doc)
                ->and((new AsTiptapContent)->set($model, 'body', $doc, []))->toBe($doc)
                ->and($model::$calls)->toBe(0)
                ->and(file_exists($private.'/receipt-123.jpg'))->toBeTrue()
                ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
        } finally {
            @unlink($private.'/receipt-123.jpg');
        }
    })->with([
        'a private file' => 'receipt-123.jpg',
        'dot dot' => '..',
        'dot' => '.',
        'empty' => '',
        'traversal' => '../receipt-123.jpg',
        'encoded traversal' => '..%2Freceipt-123.jpg',
        'double encoded' => '%252e%252e',
        'a directory name' => 'livewire-tmp',
        'backslash' => '..\\receipt-123.jpg',
        'nul byte' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.bin%00.jpg',
    ]);

    it('does not read a well-named file that sits beside the temp directory instead of in it', function () {
        $name = str_repeat('c', 40).'.jpg';
        $private = storage_path('app/private');
        @mkdir($private, 0777, true);
        file_put_contents($private.'/'.$name, tiptapPng());
        FileUploadConfiguration::storage()->put($name, tiptapPng());
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        try {
            expect(castSet($doc))->toBe($doc)
                ->and(file_exists($private.'/'.$name))->toBeTrue()
                ->and(FileUploadConfiguration::storage()->exists($name))->toBeTrue()
                ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
        } finally {
            @unlink($private.'/'.$name);
        }
    });

    it('never hands tiptapStoreImage a directory, even one named like a temp file', function () {
        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public static int $calls = 0;
            public function tiptapStoreImage($tmpPath, $key) { static::$calls++; return 'x'; }
        };

        $name = str_repeat('a', 40).'.bin';
        FileUploadConfiguration::storage()->makeDirectory(FileUploadConfiguration::path($name, false));
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect((new AsTiptapContent)->set($model, 'body', $doc, []))->toBe($doc)
            ->and($model::$calls)->toBe(0);
    });

    it('only reads a name with the shape Livewire generates, even when the file is in the temp directory', function (string $name) {
        tiptapTemporaryUpload(tiptapPng(), rawurldecode($name));
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect(castSet($doc))->toBe($doc)
            ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
    })->with([
        'a short token' => 'abc123.bin',
    ]);

    it('refuses a temp file whose real path leaves the temp directory', function () {
        $storage = FileUploadConfiguration::storage();
        file_put_contents($storage->path('outside.png'), tiptapPng());

        $name = str_repeat('b', 40).'.bin';
        $directory = $storage->path(FileUploadConfiguration::path('', false));
        @mkdir($directory, 0777, true);
        symlink($storage->path('outside.png'), $directory.'/'.$name);
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect(castSet($doc))->toBe($doc)
            ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
    });

    it('drops a client-sent serialized string over the size limit without unserializing it', function () {
        $big = str_repeat('O:1:"A":0:{}', 20000);
        $html = serialize('<p>'.str_repeat('a', Jiannius\Atom\Tiptap\Content::SANITIZE_MAX_BYTES).'</p>');

        expect(strlen($big))->toBeGreaterThan(Jiannius\Atom\Tiptap\Content::SANITIZE_MAX_BYTES)
            ->and(castSet($big))->toBeNull()
            ->and(castSet($html))->toBeNull();
    });

    it('drops an oversized string that starts like any serialized type', function (string $prefix) {
        $value = $prefix.str_repeat('x', Jiannius\Atom\Tiptap\Content::SANITIZE_MAX_BYTES + 1);

        expect(castSet($value))->toBeNull();
    })->with(['a:', 'b:', 'd:', 'i:', 's:', 'S:', 'O:', 'C:', 'E:', 'r:', 'R:', 'N;']);

    it('persists names Livewire may generate, whatever the client extension', function (string $name) {
        tiptapTemporaryUpload(tiptapPng(), $name);

        $out = json_decode(castSet(tiptapDoc(tiptapPreviewSrc($name))), true);

        expect($out['content'][0]['attrs']['src'])->toEndWith('.png')
            ->and(Storage::disk('editor-images')->allFiles())->toHaveCount(1);
    })->with([
        'bin' => str_repeat('a', 40).'.bin',
        'mixed case token' => 'aBcDeFgHiJkLmNoPqRsTuVwXyZaBcD.bin',
        'meta name' => 'aBcDeFgHiJkLmNoPqRsTuVwXyZ1234-meta'.'Zm9vX2Jhci5qcGc='.'-.bin',
        'meta name with slash and plus' => 'aBcDeFgHiJkLmNoPqRsTuVwXyZ1234-meta'.'Zm9v_YmFy+Lm=='.'-.bin',
        'empty extension' => str_repeat('a', 40).'.',
        'dashed extension' => str_repeat('a', 40).'.tar-gz',
        'underscore extension' => str_repeat('a', 40).'.my_ext',
        'long extension' => str_repeat('a', 40).'.abcdefghijklmnop',
    ]);

    it('finds uploads under a non-default temp directory', function () {
        config(['livewire.temporary_file_upload.directory' => 'custom/uploads']);

        $name = tiptapTemporaryUpload(tiptapPng());
        $out = json_decode(castSet(tiptapDoc(tiptapPreviewSrc($name))), true);

        expect($out['content'][0]['attrs']['src'])->toEndWith('.png')
            ->and(FileUploadConfiguration::path($name, false))->toStartWith('custom/uploads/')
            ->and(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name, false)))->toBeFalse();
    });

    it('persists the same temp upload used twice in one document to the same url', function () {
        $name = tiptapTemporaryUpload(tiptapPng());
        $src = tiptapPreviewSrc($name);
        $doc = json_encode(['type' => 'doc', 'content' => [
            ['type' => 'image', 'attrs' => ['src' => $src]],
            ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['src' => $src.'?expires=1']]]],
        ]]);

        $out = json_decode(castSet($doc), true);

        expect($out['content'][0]['attrs']['src'])->toEndWith('.png')
            ->and($out['content'][1]['content'][0]['attrs']['src'])->toBe($out['content'][0]['attrs']['src'])
            ->and(Storage::disk('editor-images')->allFiles())->toHaveCount(1);
    });

    it('deletes the Livewire meta sidecar with the temp file', function () {
        $name = tiptapTemporaryUpload(tiptapPng());
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($name.'.json', false), '{}');

        castSet(tiptapDoc(tiptapPreviewSrc($name)));

        expect(FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($name.'.json', false)))->toBeFalse();
    });

    it('leaves a corrupt image alone without reporting it', function () {
        $name = tiptapTemporaryUpload(substr(tiptapPng(), 0, 40));
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect(castSet($doc))->toBe($doc)
            ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
    });

    it('does not hand tiptapStoreImage a file that is not a stored image type', function (string $contents) {
        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public static int $calls = 0;
            public function tiptapStoreImage($tmpPath, $key) { static::$calls++; return 'https://cdn/x.png'; }
        };

        $doc = tiptapDoc(tiptapPreviewSrc(tiptapTemporaryUpload($contents)));

        expect((new AsTiptapContent)->set($model, 'body', $doc, []))->toBe($doc)
            ->and($model::$calls)->toBe(0);
    })->with([
        'text' => 'plain text',
        'html' => '<html><script>alert(1)</script></html>',
        'pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj",
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    ]);

    it('hands tiptapStoreImage a local copy with an extension taken from the content', function () {
        $model = new class extends Model {
            protected $casts = ['body' => AsTiptapContent::class];
            public static string $path = '';
            public function tiptapStoreImage($tmpPath, $key) { static::$path = $tmpPath; return 'https://cdn/a.png'; }
        };

        (new AsTiptapContent)->set($model, 'body', tiptapDoc(tiptapPreviewSrc(tiptapTemporaryUpload(tiptapPng()))), []);

        expect($model::$path)->toEndWith('.png')->and(file_exists($model::$path))->toBeFalse();
    });

    it('leaves an image type it does not store alone', function () {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagebmp($image);
        $name = tiptapTemporaryUpload(ob_get_clean());
        $doc = tiptapDoc(tiptapPreviewSrc($name));

        expect(castSet($doc))->toBe($doc)
            ->and(Storage::disk('editor-images')->allFiles())->toBeEmpty();
    });

    it('still unwraps a small serialized HTML string and keeps long plain HTML', function () {
        expect(castSet(serialize('<p>hi</p>')))->toBe('<p>hi</p>');

        $html = '<p>'.str_repeat('a', Jiannius\Atom\Tiptap\Content::SANITIZE_MAX_BYTES + 10).'</p>';

        expect(castSet($html))->toBe($html);
    });
});

function tiptapDoc(string $src): string
{
    return json_encode(['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => $src]]]]);
}

function tiptapPreviewSrc(string $name): string
{
    return 'https://app.test/livewire-abc123/preview-file/'.$name;
}

function tiptapPng(): string
{
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

/**
 * Put a file into Livewire's temp disk under a name Livewire would generate and return the name.
 */
function tiptapTemporaryUpload(string $contents, ?string $name = null): string
{
    $name ??= strtolower(str()->random(40)).'.bin';

    FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($name, false), $contents);

    return $name;
}
