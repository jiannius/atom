<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Casts\AsEditorContent;
use Jiannius\Atom\Casts\AsTiptapContent;
use Jiannius\Atom\Tiptap\Content;
use Jiannius\Atom\Tiptap\Extensions\Youtube;
use Jiannius\Atom\Tiptap\StyleValue;

/**
 * Stand-in for a gadget class: if unserialize() ever instantiates it, the flag
 * flips (__wakeup on load, __destruct on release).
 */
class HardeningGadget
{
    public static bool $touched = false;

    public $payload = 'x';

    public function __wakeup()
    {
        self::$touched = true;
    }

    public function __destruct()
    {
        self::$touched = true;
    }
}

class HardeningTiptapModel extends Model
{
    protected $casts = ['body' => AsTiptapContent::class];
}

class HardeningEditorModel extends Model
{
    protected $casts = ['body' => AsEditorContent::class];
}

beforeEach(function () {
    HardeningGadget::$touched = false;
});

/**
 * A serialized gadget. Building it instantiates the real class, so the flag is
 * reset afterwards: only an unserialize() under test may set it.
 */
function hardeningPayload(bool $nested = false): string
{
    $gadget = new HardeningGadget;
    $payload = serialize($nested ? ['a' => ['b' => $gadget]] : $gadget);
    unset($gadget);
    gc_collect_cycles();
    HardeningGadget::$touched = false;

    return $payload;
}

function hardeningTiptapGet(mixed $value): mixed
{
    return (new AsTiptapContent)->get(new HardeningTiptapModel, 'body', $value, []);
}

function hardeningTiptapSet(mixed $value): mixed
{
    return (new AsTiptapContent)->set(new HardeningTiptapModel, 'body', $value, []);
}

function hardeningEditorGet(mixed $value): mixed
{
    return (new AsEditorContent)->get(new HardeningEditorModel, 'body', $value, []);
}

function hardeningDoc(array $content): string
{
    return Content::render(json_encode(['type' => 'doc', 'content' => $content]));
}

function hardeningText(array $marks, string $text = 'x'): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => $marks, 'text' => $text]]];
}

describe('E-S2 object injection: reading stored values', function () {
    it('AsTiptapContent::get never instantiates a serialized object', function () {
        $value = hardeningTiptapGet(hardeningPayload());
        gc_collect_cycles();

        expect($value)->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('AsTiptapContent::get refuses an object nested inside a serialized array', function () {
        $value = hardeningTiptapGet(hardeningPayload(nested: true));
        gc_collect_cycles();

        expect($value)->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('AsEditorContent::get never instantiates a serialized object', function () {
        $value = hardeningEditorGet(hardeningPayload());
        gc_collect_cycles();

        expect($value)->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('Content::unserialize never instantiates a serialized object', function () {
        [$serialized, $value] = Content::unserialize(hardeningPayload());
        gc_collect_cycles();

        expect($serialized)->toBeTrue();
        expect($value)->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('still reads legacy serialized HTML, arrays and false', function () {
        expect(hardeningTiptapGet(serialize('<p>legacy</p>')))->toBe('<p>legacy</p>');
        expect(hardeningEditorGet(serialize('<p>legacy</p>')))->toBe('<p>legacy</p>');
        expect(hardeningTiptapGet(serialize(['type' => 'doc', 'content' => []])))->toBe(['type' => 'doc', 'content' => []]);
        expect(hardeningEditorGet('b:0;'))->toBeFalse();
    });

    it('still returns JSON and raw HTML unchanged', function () {
        expect(hardeningTiptapGet('{"type":"doc","content":[]}'))->toBe('{"type":"doc","content":[]}');
        expect(hardeningTiptapGet('<p>raw html</p>'))->toBe('<p>raw html</p>');
        expect(hardeningEditorGet('<p>raw html</p>'))->toBe('<p>raw html</p>');
    });
});

describe('E-S2 object injection: storing client values', function () {
    it('AsTiptapContent::set refuses a serialized object from the browser', function () {
        $stored = hardeningTiptapSet(hardeningPayload());
        gc_collect_cycles();

        expect($stored)->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('AsTiptapContent::set refuses a serialized array or scalar', function () {
        expect(hardeningTiptapSet(serialize(['a' => 1])))->toBeNull();
        expect(hardeningTiptapSet('i:5;'))->toBeNull();
    });

    it('what set stores is never something get would turn into an object', function () {
        $stored = hardeningTiptapSet(hardeningPayload());

        expect(hardeningTiptapGet($stored))->toBeNull();
        expect(HardeningGadget::$touched)->toBeFalse();
    });

    it('AsTiptapContent::set unwraps a serialized HTML string (legacy raw copy)', function () {
        expect(hardeningTiptapSet(serialize('<p>legacy</p>')))->toBe('<p>legacy</p>');
    });

    it('AsTiptapContent::set still stores JSON docs, arrays and HTML', function () {
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph']]]);

        expect(hardeningTiptapSet($json))->toBe($json);
        expect(hardeningTiptapSet(['type' => 'doc', 'content' => [['type' => 'paragraph']]]))->toBe($json);
        expect(hardeningTiptapSet('<p>hello <strong>world</strong></p>'))->toBe('<p>hello <strong>world</strong></p>');
    });

    it('AsEditorContent round-trips HTML and a client serialized string stays a string', function () {
        $set = fn ($v) => (new AsEditorContent)->set(new HardeningEditorModel, 'body', $v, []);

        expect(hardeningEditorGet($set('<p>hi</p>')))->toBe('<p>hi</p>');

        $hostile = hardeningPayload();

        expect(hardeningEditorGet($set($hostile)))->toBe($hostile);
        expect(HardeningGadget::$touched)->toBeFalse();
    });
});

describe('E-S2 object injection: atom:tiptap-migrate', function () {
    beforeEach(function () {
        $dir = app_path('Models');
        @mkdir($dir, 0777, true);
        $this->modelFile = $dir.'/HardeningMigrateItem.php';

        file_put_contents($this->modelFile, <<<'PHP'
<?php
namespace App\Models;

class HardeningMigrateItem extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'hardening_migrate_items';
    protected $guarded = [];
    protected $casts = ['body' => \Jiannius\Atom\Casts\AsTiptapContent::class];
}
PHP);
        require_once $this->modelFile;

        Schema::create('hardening_migrate_items', function ($table) {
            $table->id();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    });

    afterEach(function () {
        Schema::dropIfExists('hardening_migrate_items');
        @unlink($this->modelFile);
    });

    it('does not instantiate a serialized object and still converts legacy serialized HTML', function () {
        DB::table('hardening_migrate_items')->insert(['body' => hardeningPayload()]);
        DB::table('hardening_migrate_items')->insert(['body' => serialize('<p>legacy</p>')]);
        gc_collect_cycles();
        $this->artisan('atom:tiptap-migrate')->assertSuccessful();
        gc_collect_cycles();

        expect(HardeningGadget::$touched)->toBeFalse();

        $rows = DB::table('hardening_migrate_items')->orderBy('id')->pluck('body');
        expect(json_decode($rows[1], true)['type'])->toBe('doc');
    });
});

describe('E-S3 YouTube embed URLs', function () {
    it('renders no iframe for a hostile src', function (mixed $src) {
        $html = hardeningDoc([['type' => 'youtube', 'attrs' => ['src' => $src]]]);

        expect($html)->not->toContain('<iframe')->not->toContain('javascript');
    })->with([
        'javascript scheme' => ['javascript:alert(1)'],
        'other origin' => ['https://evil.example.com/embed/dQw4w9WgXcQ'],
        'other origin with a watch id' => ['https://evil.example.com/watch?v=dQw4w9WgXcQ'],
        'youtube as a subdomain prefix of another host' => ['https://youtube.com.evil.example.com/watch?v=dQw4w9WgXcQ'],
        'youtube only in the query string' => ['https://evil.example.com/?u=https://youtu.be/dQw4w9WgXcQ'],
        'youtube only in userinfo' => ['https://youtube.com@evil.example.com/watch?v=dQw4w9WgXcQ'],
        'data uri' => ['data:text/html,<script>alert(1)</script>'],
        'ftp scheme' => ['ftp://youtube.com/watch?v=dQw4w9WgXcQ'],
        'no id' => ['https://www.youtube.com/'],
        'short id' => ['https://www.youtube.com/watch?v=abc'],
        'array src' => [['x' => 'y']],
        'empty' => [''],
    ]);

    it('renders no iframe for a hostile iframe in legacy HTML', function () {
        $html = Content::render('<div data-youtube-video><iframe src="https://evil.example.com/x"></iframe></div>');

        expect($html)->not->toContain('<iframe');
    });

    it('still embeds every real YouTube form as an https embed', function (string $src, string $expected) {
        $html = hardeningDoc([['type' => 'youtube', 'attrs' => ['src' => $src]]]);

        expect($html)->toContain('<iframe')->toContain('src="'.$expected.'"');
    })->with([
        'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'watch with extra params' => ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=9', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'short link' => ['https://youtu.be/dQw4w9WgXcQ?t=5', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'bare host' => ['youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'http (output is https)' => ['http://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        'nocookie' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ]);

    it('embedUrl returns null instead of echoing a foreign url', function () {
        expect(Youtube::embedUrl('https://evil.example.com/x'))->toBeNull();
    });
});

describe('E-S4 style values', function () {
    it('drops a hostile font size', function (string $size) {
        $html = hardeningDoc([hardeningText([['type' => 'textStyle', 'attrs' => ['fontSize' => $size]]])]);

        expect($html)->not->toContain('position')->not->toContain('font-size')->not->toContain('data-font-size');
    })->with([
        'declaration injection' => ['1rem; position: fixed; inset: 0'],
        'function' => ['calc(100vw)'],
        'expression' => ['expression(alert(1))'],
        'unit-less' => ['20'],
        'huge' => ['999rem'],
        'zero' => ['0px'],
        'other unit' => ['100vw'],
        'negative' => ['-1rem'],
    ]);

    it('drops a hostile image width and float', function () {
        $html = hardeningDoc([['type' => 'image', 'attrs' => [
            'src' => 'a.png',
            'width' => '100%; position: fixed; top: 0',
            'float' => 'left; position: fixed',
        ]]]);

        expect($html)->not->toContain('position')->not->toContain('data-width')->not->toContain('data-float');
    });

    it('drops an out-of-range or non-numeric image width, and a non-enum float', function (string $width, string $float) {
        $html = hardeningDoc([['type' => 'image', 'attrs' => ['src' => 'a.png', 'width' => $width, 'float' => $float]]]);

        expect($html)->not->toContain('width:')->not->toContain('float:');
    })->with([
        'over 100 percent' => ['500%', 'inherit'],
        'calc' => ['calc(100vw)', 'inline-start'],
        'vw' => ['100vw', 'LEFT'],
    ]);

    it('drops a hostile text colour', function (string $color) {
        $html = hardeningDoc([hardeningText([['type' => 'textStyle', 'attrs' => ['color' => $color]]])]);

        expect($html)->not->toContain('position')->not->toContain('style=');
    })->with([
        'declaration injection' => ['red; position: fixed; inset: 0'],
        'url' => ['url(https://evil.example.com/x.png)'],
        'var' => ['var(--x)'],
        'unknown name' => ['notacolor'],
        'hex with junk' => ['#fff; position: fixed'],
        'rgb with junk' => ['rgb(0,0,0); position: fixed'],
        'expression' => ['expression(alert(1))'],
    ]);

    it('drops a hostile highlight colour', function (string $color) {
        $html = hardeningDoc([hardeningText([['type' => 'highlight', 'attrs' => ['color' => $color]]])]);

        expect($html)->not->toContain('position')->not->toContain('style=')->not->toContain('data-color');
    })->with([
        'declaration injection' => ['yellow; position: fixed; inset: 0'],
        'url' => ['url(https://evil.example.com/x.png)'],
    ]);

    it('a non-string style attribute is dropped, not a crash', function () {
        $html = hardeningDoc([hardeningText([['type' => 'textStyle', 'attrs' => ['fontSize' => ['x'], 'color' => ['y']]]])]);

        expect($html)->toContain('x');
    });

    it('still renders legitimate styles', function () {
        $html = hardeningDoc([
            hardeningText([['type' => 'textStyle', 'attrs' => ['fontSize' => '18px', 'color' => '#ff0000']]], 'a'),
            hardeningText([['type' => 'textStyle', 'attrs' => ['fontSize' => '1.25rem', 'color' => 'rgba(10, 20, 30, 0.5)']]], 'b'),
            hardeningText([['type' => 'textStyle', 'attrs' => ['color' => 'hsl(200, 50%, 40%)']]], 'c'),
            hardeningText([['type' => 'textStyle', 'attrs' => ['color' => 'rebeccapurple']]], 'd'),
            hardeningText([['type' => 'highlight', 'attrs' => ['color' => '#ffff00']]], 'e'),
            ['type' => 'image', 'attrs' => ['src' => 'a.png', 'width' => '320px', 'float' => 'right']],
            ['type' => 'image', 'attrs' => ['src' => 'b.png', 'width' => '50%', 'float' => 'none']],
        ]);

        expect($html)
            ->toContain('font-size: 18px')
            ->toContain('color: #ff0000')
            ->toContain('font-size: 1.25rem')
            ->toContain('color: rgba(10, 20, 30, 0.5)')
            ->toContain('color: hsl(200, 50%, 40%)')
            ->toContain('color: rebeccapurple')
            ->toContain('background-color: #ffff00')
            ->toContain('data-color="#ffff00"')
            ->toContain('width: 320px')
            ->toContain('float: right')
            ->toContain('width: 50%')
            ->toContain('float: none');
    });

    it('a legacy HTML colour and image survive HTML to JSON to HTML', function () {
        $editor = new \Tiptap\Editor(['extensions' => Content::extensions()]);
        $json = $editor->setContent('<p><span style="color: #00ff00">g</span></p><img src="a.png" data-float="left" data-width="40%">')->getJSON();
        $html = Content::render($json);

        expect($html)->toContain('color: #00ff00')->toContain('float: left')->toContain('width: 40%');
    });
});

describe('StyleValue', function () {
    it('accepts the legitimate colour forms', function (string $color) {
        expect(StyleValue::color($color))->toBe($color);
    })->with(['#fff', '#FFFA', '#a1b2c3', '#a1b2c3d4', 'rgb(1,2,3)', 'rgb(1 2 3 / 50%)', 'rgba(1, 2, 3, .5)', 'rgb(10%, 20%, 30%)', 'hsl(120deg, 50%, 50%)', 'hsla(0.5turn 50% 50% / 0.3)', 'Red', 'transparent']);

    it('rejects everything else', function (mixed $color) {
        expect(StyleValue::color($color))->toBeNull();
    })->with(['#ff', '#fffff', 'rgb(1,2)', 'rgb(1,2,3,4,5)', 'rgb(1,2,3)}', 'red;', 'red !important', 'url(x)', '', null, 12, [[]]]);
});
