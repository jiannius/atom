<?php

use Illuminate\Database\Eloquent\Model;
use Jiannius\Atom\Casts\AsTiptapContent;

beforeEach(function () {
    config(['livewire.temporary_file_upload.directory' => 'livewire-tmp']);
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
            public function tiptapStoreImage($tmpPath, $key) { return 'https://cdn/overridden.png'; }
        };

        $dir = config('livewire.temporary_file_upload.directory');
        $tmp = storage_path('app/private/'.$dir);
        if (! is_dir($tmp)) { mkdir($tmp, 0777, true); }
        file_put_contents($tmp.'/test-temp.png', 'x');

        $json = json_encode(['type' => 'doc', 'content' => [
            ['type' => 'image', 'attrs' => ['src' => '/livewire-abc/preview-file/test-temp.png']],
        ]]);

        $out = json_decode((new AsTiptapContent)->set($model, 'body', $json, []), true);

        expect($out['content'][0]['attrs']['src'])->toBe('https://cdn/overridden.png');
    });
});
