<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Commands\MigrateTiptapContent;
use Jiannius\Atom\Services\EditorBackups;
use Jiannius\Atom\Tiptap\Content;

/**
 * Stand-in for a gadget class: if unserialize() ever instantiates it, the flag flips.
 */
class MigrateGadget
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

const MIGRATE_OLD = '2020-01-01 00:00:00';

beforeEach(function () {
    foreach (['MigratePost', 'MigrateOtherPost', 'MigrateSecondPost', 'MigrateUuidPost', 'MigratePrefixedPost', 'MigrateScopedPost', 'MigrateChildPost', 'MigrateNamedConnectionPost', 'MigrateTwoColumnPost'] as $file) {
        require_once __DIR__.'/../Fixtures/MigrateApp/Models/'.$file.'.php';
    }

    // the model that still names the removed cast lives apart: it is scanned only by the tests that pass its --path
    require_once __DIR__.'/../Fixtures/MigrateRemovedCast/MigrateRemovedCast.php';

    $this->app->useAppPath(__DIR__.'/../Fixtures/MigrateApp');
    config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    config(['database.connections.prefixed' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'px_']]);

    foreach ([null, 'other'] as $connection) {
        Schema::connection($connection)->create($connection ? 'migrate_other_posts' : 'migrate_posts', function (Blueprint $t) {
            $t->id();
            $t->text('body')->nullable();
            $t->timestamps();
        });
    }

    foreach (['migrate_second_posts', 'migrate_named_connection_posts'] as $table) {
        Schema::create($table, function (Blueprint $t) {
            $t->id();
            $t->text('body')->nullable();
            $t->timestamps();
        });
    }

    Schema::create('migrate_two_column_posts', function (Blueprint $t) {
        $t->id();
        $t->text('body')->nullable();
        $t->text('summary')->nullable();
    });

    Schema::create('migrate_uuid_posts', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->text('body')->nullable();
    });

    Schema::connection('prefixed')->create('migrate_prefixed_posts', function (Blueprint $t) {
        $t->id();
        $t->text('body')->nullable();
    });

    Schema::create('migrate_scoped_posts', function (Blueprint $t) {
        $t->id();
        $t->text('body')->nullable();
        $t->boolean('published')->default(true);
        $t->softDeletes();
    });

    Schema::create('migrate_removed_casts', function (Blueprint $t) {
        $t->id();
        $t->text('body')->nullable();
    });

    MigrateGadget::$touched = false;
});

/**
 * Insert rows and give back their ids, in order
 *
 * @param  array<int, string|null>  $bodies
 * @return array<int, int>
 */
function migrateInsert(array $bodies, ?string $connection = null, string $table = 'migrate_posts'): array
{
    return array_map(fn ($body) => DB::connection($connection)->table($table)->insertGetId([
        'body' => $body,
        'created_at' => MIGRATE_OLD,
        'updated_at' => MIGRATE_OLD,
    ]), $bodies);
}

function migrateRemovedCastPath(): string
{
    return realpath(__DIR__.'/../Fixtures/MigrateRemovedCast');
}

function migrateBody(int $id, ?string $connection = null, string $table = 'migrate_posts'): ?string
{
    return DB::connection($connection)->table($table)->where('id', $id)->value('body');
}

function migrateIsDocument(?string $value): bool
{
    $decoded = json_decode((string) $value, true);

    return is_array($decoded) && ($decoded['type'] ?? null) === 'doc';
}

function migrateBackupCount(?string $connection = null): int
{
    return Schema::connection($connection)->hasTable(EditorBackups::TABLE)
        ? DB::connection($connection)->table(EditorBackups::TABLE)->count()
        : 0;
}

/**
 * A fixture table of the four kinds of value the command meets: legacy
 * serialized HTML, serialized JSON, lossy HTML and hostile payloads
 *
 * @return array<string, int> id by name
 */
function migrateFixture(): array
{
    $names = ['legacy', 'plain', 'serializedJson', 'lossy', 'object', 'placeholder', 'script', 'empty', 'already'];
    $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'in json']]]]]);

    $token = '%MINIFYHTML'.md5((string) ($_SERVER['REQUEST_TIME'] ?? time())).'0%';
    $object = serialize(new MigrateGadget);
    MigrateGadget::$touched = false;

    $ids = migrateInsert([
        serialize('<p>legacy <strong>serialized</strong> html</p>'),
        '<h2>Plain</h2><p>html</p>',
        serialize($json),
        '<p class="MsoNormal" style="font-family:Arial">lossy</p><font color="red">red</font>',
        $object,
        '<pre>a</pre><pre>'.str_repeat($token, 20).'</pre>',
        '<script>alert(1)</script>',
        '',
        $json,
    ]);

    return array_combine($names, $ids);
}

describe('atom:tiptap-migrate: the dry run', function () {
    it('is the default: it lists what it would do and writes nothing, not even the backup table', function () {
        $id = migrateFixture();
        $before = DB::table('migrate_posts')->orderBy('id')->get()->all();

        $this->artisan('atom:tiptap-migrate')
            ->expectsOutputToContain('Dry run, nothing was written')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id['lossy'].' body: tags: font | attributes: font@color, p@class:MsoNormal, p@style:font-family')
            ->assertSuccessful();

        expect(DB::table('migrate_posts')->orderBy('id')->get()->all())->toEqual($before);
        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();
    });

    it('keeps accepting --dry from 3.x scripts', function () {
        $id = migrateFixture();

        $this->artisan('atom:tiptap-migrate --dry')->expectsOutputToContain('Dry run')->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id['plain'])))->toBeFalse();
    });

    it('counts every kind of value', function () {
        migrateFixture();

        $this->artisan('atom:tiptap-migrate')
            ->expectsOutputToContain('3 clean, 1 flagged, 3 refused, 1 already JSON')
            ->assertSuccessful();
    });
});

describe('atom:tiptap-migrate --force', function () {
    it('converts clean rows and leaves flagged, hostile and empty rows untouched', function () {
        $id = migrateFixture();
        $before = DB::table('migrate_posts')->pluck('body', 'id')->all();

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Migrated 3 value(s)')->assertFailed();

        foreach (['legacy', 'plain', 'serializedJson'] as $name) {
            expect(migrateIsDocument(migrateBody($id[$name])))->toBeTrue($name);
        }

        expect(migrateBody($id['serializedJson']))->not->toStartWith('s:');

        foreach (['lossy', 'object', 'placeholder', 'script', 'empty', 'already'] as $name) {
            expect(migrateBody($id[$name]))->toBe($before[$id[$name]], $name);
        }

        expect(MigrateGadget::$touched)->toBeFalse();
    });

    it('does not touch updated_at and fires no model events', function () {
        $id = migrateFixture();
        $fired = false;
        \App\Models\MigratePost::updating(function () use (&$fired) {
            $fired = true;
        });
        \App\Models\MigratePost::saved(function () use (&$fired) {
            $fired = true;
        });

        $this->artisan('atom:tiptap-migrate --force')->assertFailed();

        expect(DB::table('migrate_posts')->where('id', $id['legacy'])->value('updated_at'))->toBe(MIGRATE_OLD);
        expect(DB::table('migrate_posts')->pluck('updated_at')->unique()->all())->toBe([MIGRATE_OLD]);
        expect($fired)->toBeFalse();
    });

    it('writes the converted JSON the AsTiptapContent cast reads back', function () {
        [$id] = migrateInsert([serialize('<p>hello</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $post = \App\Models\MigratePost::find($id);

        expect($post->body)->toBe(migrateBody($id));
        expect(Content::render($post->body))->toBe('<p>hello</p>');
    });

    it('is a no-op the second time: no new backup, no write', function () {
        migrateFixture();

        $this->artisan('atom:tiptap-migrate --force')->assertFailed();
        $bodies = DB::table('migrate_posts')->pluck('body', 'id')->all();
        $backups = migrateBackupCount();

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Nothing was migrated')->doesntExpectOutputToContain('The originals are in')->assertFailed();

        expect(DB::table('migrate_posts')->pluck('body', 'id')->all())->toBe($bodies);
        expect(migrateBackupCount())->toBe($backups)->toBe(3);
    });

    it('refuses --force with --dry', function () {
        $this->artisan('atom:tiptap-migrate --force --dry')->assertFailed();
    });
});

describe('atom:tiptap-migrate: the backup table', function () {
    it('holds the model, key, column, original, a batch and the hashes of both values', function () {
        $original = serialize('<p>keep me</p>');
        [$id] = migrateInsert([$original]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $row = DB::table(EditorBackups::TABLE)->first();

        expect($row->model)->toBe(\App\Models\MigratePost::class)
            ->and($row->row_key)->toBe((string) $id)
            ->and($row->column_name)->toBe('body')
            ->and($row->original)->toBe($original)
            ->and($row->encoding)->toBe('raw')
            ->and($row->original_hash)->toBe(hash('sha256', $original))
            ->and($row->migrated_hash)->toBe(hash('sha256', migrateBody($id)))
            ->and($row->accepted_lossy)->toEqual(0)
            ->and($row->batch)->toMatch('/^[0-9a-f-]{36}$/')
            ->and($row->migrated_at)->not->toBeNull()
            ->and($row->restored_at)->toBeNull();
    });

    it('is written before the update: an update that finds no backup is refused by the database', function () {
        [$id] = migrateInsert([serialize('<p>a</p>')]);
        // a trigger refuses any update of the column unless the row's current value is already in the backups
        EditorBackups::on()->create();
        DB::unprepared("CREATE TRIGGER migrate_needs_backup BEFORE UPDATE OF body ON migrate_posts
            WHEN NOT EXISTS (SELECT 1 FROM atom_editor_backups WHERE row_key = CAST(OLD.id AS TEXT) AND original = OLD.body)
            BEGIN SELECT RAISE(ABORT, 'no backup yet'); END");

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Migrated 1 value(s)')->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id)))->toBeTrue();
    });

    it('leaves no backup and no change when the update fails midway', function () {
        [$id] = migrateInsert([$original = serialize('<p>SECRETCONTENT</p>')]);
        EditorBackups::on()->create();
        DB::unprepared("CREATE TRIGGER migrate_fails BEFORE UPDATE OF body ON migrate_posts BEGIN SELECT RAISE(ABORT, 'update refused'); END");

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('QueryException (SQLSTATE')
            ->doesntExpectOutputToContain('SECRETCONTENT')
            ->assertFailed();

        expect(migrateBody($id))->toBe($original);
        expect(migrateBackupCount())->toBe(0);
    });

    it('leaves the row alone when it changed after it was read', function () {
        [$id] = migrateInsert(['<p>first</p>']);

        $command = new class extends MigrateTiptapContent
        {
            public static int $id = 0;

            protected function convert(string $html): ?string
            {
                // somebody saves the row between the read and the write
                DB::table('migrate_posts')->where('id', static::$id)->update(['body' => '<p>edited meanwhile</p>']);

                return parent::convert($html);
            }
        };
        $command::$id = $id;
        Artisan::registerCommand($command);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('changed while the command ran')
            ->assertFailed();

        expect(migrateBody($id))->toBe('<p>edited meanwhile</p>');
        expect(migrateBackupCount())->toBe(0);
    });

    it('keeps bytes that are not UTF-8, a NUL and a serialized payload byte for byte', function () {
        $values = [
            serialize("<p>caf\xE9 latin-1</p>"),
            "<p>nul\0inside</p>",
            serialize('<p>serialized</p>'),
        ];
        $ids = migrateInsert($values);

        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();

        $rows = DB::table(EditorBackups::TABLE)->orderBy('id')->get();
        expect($rows->pluck('encoding')->all())->toBe(['base64', 'base64', 'raw']);

        foreach ($ids as $i => $id) {
            expect(EditorBackups::original($rows[$i]))->toBe($values[$i]);
            expect(migrateIsDocument(migrateBody($id)))->toBeTrue();
        }
    });
});

describe('atom:tiptap-migrate --restore', function () {
    it('puts every original back byte for byte, whatever it held', function () {
        $values = [
            serialize('<p>legacy</p>'),
            serialize("<p>caf\xE9</p>"),
            "<p>nul\0</p>",
            '<h2>Plain</h2>',
            serialize(json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph']]])),
        ];
        $ids = migrateInsert($values);

        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();

        foreach ($ids as $id) {
            expect(migrateIsDocument(migrateBody($id)))->toBeTrue();
        }

        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('Restored 5 value(s)')->assertSuccessful();

        foreach ($ids as $i => $id) {
            expect(migrateBody($id))->toBe($values[$i]);
        }

        expect(DB::table(EditorBackups::TABLE)->whereNull('restored_at')->count())->toBe(0);
    });

    it('restores only the ids it is given', function () {
        [$one, $two] = migrateInsert([serialize('<p>one</p>'), serialize('<p>two</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan("atom:tiptap-migrate --restore={$one} --force")->assertSuccessful();

        expect(migrateBody($one))->toBe(serialize('<p>one</p>'));
        expect(migrateIsDocument(migrateBody($two)))->toBeTrue();
    });

    it('changes nothing without --force', function () {
        [$id] = migrateInsert([serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $migrated = migrateBody($id);

        $this->artisan('atom:tiptap-migrate --restore')
            ->expectsOutputToContain('Would restore')
            ->expectsOutputToContain('Run with --force')
            ->assertSuccessful();

        expect(migrateBody($id))->toBe($migrated);
    });

    it('skips a row edited after the migration, and restores it with --discard-edits', function () {
        [$id] = migrateInsert([$original = serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        DB::table('migrate_posts')->where('id', $id)->update(['body' => '{"type":"doc","content":[]}']);

        $this->artisan('atom:tiptap-migrate --restore --force')
            ->expectsOutputToContain('edited since the migration')
            ->assertFailed();

        expect(migrateBody($id))->toBe('{"type":"doc","content":[]}');

        $this->artisan('atom:tiptap-migrate --restore --force --discard-edits')->assertSuccessful();

        expect(migrateBody($id))->toBe($original);
    });

    it('does not list a restored backup again, and a re-migration makes a new one', function () {
        [$id] = migrateInsert([serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --restore --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('Restored 0 value(s)')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        expect(DB::table(EditorBackups::TABLE)->count())->toBe(2);
        expect(DB::table(EditorBackups::TABLE)->whereNull('restored_at')->count())->toBe(1);
        expect(migrateIsDocument(migrateBody($id)))->toBeTrue();
    });

    it('reports a row that is gone', function () {
        migrateInsert([serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        DB::table('migrate_posts')->delete();

        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('the row no longer exists')->assertFailed();
    });
});

describe('atom:tiptap-migrate --accept', function () {
    it('converts the flagged rows it names, and only those', function () {
        $id = migrateFixture();
        [$other] = migrateInsert(['<p class="x">second lossy</p>']);

        $this->artisan("atom:tiptap-migrate --force --accept={$id['lossy']}")->assertFailed();

        expect(migrateIsDocument(migrateBody($id['lossy'])))->toBeTrue();
        expect(migrateIsDocument(migrateBody($other)))->toBeFalse();
        expect(DB::table(EditorBackups::TABLE)->where('row_key', (string) $id['lossy'])->value('accepted_lossy'))->toEqual(1);
    });

    it('takes "all"', function () {
        $id = migrateFixture();
        [$other] = migrateInsert(['<p class="x">second lossy</p>']);

        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertFailed();

        expect(migrateIsDocument(migrateBody($id['lossy'])))->toBeTrue();
        expect(migrateIsDocument(migrateBody($other)))->toBeTrue();
        expect(DB::table(EditorBackups::TABLE)->where('accepted_lossy', true)->count())->toBe(2);
    });

    it('never converts a refused row, however it is asked', function () {
        $id = migrateFixture();
        $ids = implode(',', [$id['object'], $id['placeholder'], $id['script']]);
        $before = DB::table('migrate_posts')->pluck('body', 'id')->all();

        $this->artisan("atom:tiptap-migrate --force --accept={$ids}")
            ->expectsOutputToContain('a refused row can never be accepted')
            ->assertFailed();

        foreach (['object', 'placeholder', 'script'] as $name) {
            expect(migrateBody($id[$name]))->toBe($before[$id[$name]]);
        }

        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertFailed();

        foreach (['object', 'placeholder', 'script'] as $name) {
            expect(migrateBody($id[$name]))->toBe($before[$id[$name]]);
        }
    });

    it('says when an id matched nothing', function () {
        migrateFixture();

        $this->artisan('atom:tiptap-migrate --accept=99999')->expectsOutputToContain('no flagged row with id 99999')->assertSuccessful();
    });

    it('needs ids', function () {
        $this->artisan('atom:tiptap-migrate --accept')->assertFailed();
    });
});

describe('atom:tiptap-migrate: hostile and unconvertible values', function () {
    it('names a serialized object and never instantiates it', function () {
        $id = migrateFixture();
        gc_collect_cycles();

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id['object'].' body: a serialized value that holds an object or null')
            ->assertFailed();

        gc_collect_cycles();

        expect(MigrateGadget::$touched)->toBeFalse();
    });

    it('names a value that carries the parser placeholder, and never expands it', function () {
        $id = migrateFixture();

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id['placeholder'].' body: it carries the HTML parser\'s reserved placeholder')
            ->assertFailed();
    });

    it('names a value with nothing to convert', function () {
        $id = migrateFixture();

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id['script'].' body: it has nothing to convert')
            ->assertFailed();
    });

    it('names a value over the size limit', function () {
        config(['atom.editor.render_max_bytes' => 1000]);
        [$id] = migrateInsert(['<p>'.str_repeat('long text ', 400).'</p>']);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id.' body: it has nothing to convert, or is over the size or nesting limits')
            ->assertFailed();

        expect(migrateIsDocument(migrateBody($id)))->toBeFalse();
    });

    it('names a serialized JSON document the editor cannot take', function () {
        [$id] = migrateInsert([serialize('{"type":"doc","content":[{"type":"paragraph","attrs":{"x":1e999}}]}')]);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id.' body: it has nothing to convert, or is over the size or nesting limits')
            ->assertFailed();

        expect(migrateBackupCount())->toBe(0);
    });

    it('names JSON that is not a document, and reads text that only starts with a bracket as HTML', function () {
        [$json, $bracket] = migrateInsert(['{"a":1}', '[gallery] <img src="https://cdn.test/editor/a.jpg">']);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$json.' body: it is JSON, but not a Tiptap document')
            ->assertFailed();

        expect(migrateBody($json))->toBe('{"a":1}');
        expect(migrateIsDocument(migrateBody($bracket)))->toBeTrue();
    });

    it('names a value the parser fails on, and goes on', function () {
        $command = new class extends MigrateTiptapContent
        {
            protected function convert(string $html): ?string
            {
                if (str_contains($html, 'boom')) {
                    throw new \TypeError('the parser failed');
                }

                return parent::convert($html);
            }
        };
        Artisan::registerCommand($command);

        [$boom, $fine] = migrateInsert(['<p>boom</p>', '<p>fine</p>']);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('the HTML parser failed on it (TypeError)')
            ->assertFailed();

        expect(migrateBody($boom))->toBe('<p>boom</p>');
        expect(migrateIsDocument(migrateBody($fine)))->toBeTrue();
    });
});

describe('atom:tiptap-migrate: the round-trip report', function () {
    it('calls markup the editor keeps clean, whatever it renames', function (string $html) {
        migrateInsert([$html]);

        $this->artisan('atom:tiptap-migrate')->expectsOutputToContain('1 clean, 0 flagged')->assertSuccessful();
    })->with([
        'bold and italic aliases' => ['<p>Hello <b>bold</b> and <i>it</i></p>'],
        'a div becomes a paragraph' => ['<div>one</div>'],
        'alignment' => ['<p style="text-align:center">centered</p>'],
        'a table' => ['<table><tr><td>a</td><td>b</td></tr></table>'],
        'a link and an image' => ['<p><a href="https://example.com/a?b=1&amp;c=2">link</a></p><img src="https://cdn.test/editor/a.jpg" alt="x">'],
        'entities and no-break spaces' => ['<p>caf&eacute; &nbsp;x</p>'],
        'adjacent marks the editor merges' => ['<p><s>one</s><del>two</del></p>'],
        'plain text' => ['just text'],
    ]);

    it('flags what the editor would lose, and says what', function (string $html, string $loses) {
        [$id] = migrateInsert([$html]);

        $this->artisan('atom:tiptap-migrate')
            ->expectsOutputToContain('flagged  '.\App\Models\MigratePost::class.'#'.$id.' body: '.$loses)
            ->assertSuccessful();
    })->with([
        'a class' => ['<p class="MsoNormal">word</p>', 'attributes: p@class:MsoNormal'],
        'a style property' => ['<span style="color:red;font-family:Arial">c</span>', 'attributes: span@style:font-family'],
        'a script' => ['<p>x</p><script>alert(1)</script>', 'tags: script'],
        'an event handler' => ['<p onclick="x()">click</p>', 'attributes: p@onclick'],
        'a font tag' => ['<font color="red">red</font>', 'tags: font | attributes: font@color'],
        'a caption' => ['<table><caption>Cap</caption><tr><td>a</td></tr></table>', 'tags: caption'],
        'a video' => ['<video src="a.mp4">fallback</video>', 'tags: video | attributes: video@src=a.mp4'],
        'adjacent divs, which the editor fuses into one word' => ['<div>one</div><div>two</div>', 'text: missing "one", "two"; added "onetwo"'],
        'a space between two marks, which the editor drops' => ["<p><b>Hello</b>\n<i>world</i></p>", 'text: missing "Hello", "world"; added "Helloworld"'],
        'a center tag' => ['<center>middle</center>', 'tags: center'],
        'a figure and its caption' => ['<figure><img src="https://cdn.test/editor/a.jpg"><figcaption>Cap</figcaption></figure>', 'tags: figcaption, figure'],
        'an iframe' => ['<p>a</p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>', 'tags: iframe | attributes: iframe@src=https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ]);

    it('flags bytes the editor would change', function () {
        migrateInsert([serialize("<p>caf\xE9</p>")]);

        $this->artisan('atom:tiptap-migrate')->expectsOutputToContain('invalid UTF-8 bytes would be replaced')->assertSuccessful();
    });
});

describe('atom:tiptap-migrate --compare', function () {
    beforeEach(function () {
        $this->review = sys_get_temp_dir().'/atom-review-'.uniqid().'.jsonl';
    });

    afterEach(function () {
        @unlink($this->review);
    });

    it('writes one JSON line per migrated row, with the original, the new HTML and the diff', function () {
        $legacy = serialize("<p>legacy</p>\n<p class=\"x\">\"quoted\" & <b>bold</b></p>\n</script><p>line</p>");
        $ids = migrateInsert([$legacy, '<h2>Plain</h2>', serialize(json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'j']]]]]))]);

        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)
            ->expectsOutputToContain('Wrote 3 record(s) to '.$this->review)
            ->assertSuccessful();

        $lines = file($this->review, FILE_IGNORE_NEW_LINES);
        expect($lines)->toHaveCount(3);

        $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);

        foreach ($records as $record) {
            expect($record)->toHaveKeys([
                'backup_id', 'batch', 'migrated_at', 'model', 'key', 'column', 'accepted_lossy', 'changed_since_migration',
                'row_exists', 'original_kind', 'original_html', 'new_html', 'text', 'tags_lost', 'attributes_lost', 'notes', 'lossy',
            ]);
            expect($record['model'])->toBe(\App\Models\MigratePost::class)->and($record['column'])->toBe('body');
        }

        $first = $records[0];
        expect($first['key'])->toBe((string) $ids[0])
            ->and($first['original_kind'])->toBe('serialized-html')
            ->and($first['original_html'])->toBe("<p>legacy</p>\n<p class=\"x\">\"quoted\" & <b>bold</b></p>\n</script><p>line</p>")
            ->and($first['new_html'])->toBe(Content::render(migrateBody($ids[0])))
            ->and($first['lossy'])->toBeTrue()
            ->and($first['accepted_lossy'])->toBeTrue()
            ->and($first['attributes_lost'])->toBe(['p@class:x' => 1])
            ->and($first['changed_since_migration'])->toBeFalse();

        expect($records[1]['original_kind'])->toBe('html')
            ->and($records[1]['lossy'])->toBeFalse()
            ->and($records[1]['accepted_lossy'])->toBeFalse()
            ->and($records[1]['text']['equal'])->toBeTrue();

        expect($records[2]['original_kind'])->toBe('serialized-json')
            ->and($records[2]['original_json'])->toBeString();
    });

    it('keeps values verbatim as JSON strings, never as raw lines of HTML', function () {
        migrateInsert([serialize("<p>a</p>\n<p>b</p>\r\n<p>c\u{2028}d</p>")]);
        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        $contents = file_get_contents($this->review);
        expect(substr_count($contents, "\n"))->toBe(1)
            ->and($contents)->not->toContain("\r")
            ->and($contents)->not->toContain("\u{2028}");
        expect(json_decode($contents, true)['original_html'])->toBe("<p>a</p>\n<p>b</p>\r\n<p>c\u{2028}d</p>");
    });

    it('carries bytes that are not UTF-8 as base64 beside a scrubbed copy', function () {
        migrateInsert([$original = serialize("<p>caf\xE9</p>")]);
        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        $record = json_decode(file_get_contents($this->review), true, 512, JSON_THROW_ON_ERROR);
        expect($record['original_valid_utf8'])->toBeFalse()
            ->and(base64_decode($record['original_base64']))->toBe($original)
            ->and($record['notes'])->toContain('invalid UTF-8 bytes would be replaced');
    });

    it('flags a row edited after the migration', function () {
        [$id] = migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        DB::table('migrate_posts')->where('id', $id)->update(['body' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'edited']]]]])]);

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        $record = json_decode(file_get_contents($this->review), true);
        expect($record['changed_since_migration'])->toBeTrue()
            ->and($record['new_html'])->toBe('<p>edited</p>');
    });

    it('takes ids', function () {
        [$one, $two] = migrateInsert([serialize('<p>one</p>'), serialize('<p>two</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan("atom:tiptap-migrate --compare={$two} --output=".$this->review)->expectsOutputToContain('Wrote 1 record(s)')->assertSuccessful();

        expect(json_decode(file_get_contents($this->review), true)['key'])->toBe((string) $two);
    });

    it('never writes to the database', function () {
        migrateFixture();
        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertFailed();
        $bodies = DB::table('migrate_posts')->get()->all();
        $backups = DB::table(EditorBackups::TABLE)->get()->all();

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        expect($statements)->not->toBeEmpty();
        expect(array_filter($statements, fn ($sql) => preg_match('/^\s*(insert|update|delete|replace|create|drop|alter|truncate)\b/i', $sql)))->toBe([]);
        expect(DB::table('migrate_posts')->get()->all())->toEqual($bodies);
        expect(DB::table(EditorBackups::TABLE)->get()->all())->toEqual($backups);
    });

    it('does not create the backup table when there is none', function () {
        migrateFixture();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->expectsOutputToContain('Wrote 0 record(s)')->assertSuccessful();

        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();
    });

    it('will not overwrite a file', function () {
        file_put_contents($this->review, 'keep');

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertFailed();

        expect(file_get_contents($this->review))->toBe('keep');
    });

    it('defaults to a file under storage/app', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare')->expectsOutputToContain(storage_path('app/atom-editor-review-'))->assertSuccessful();

        foreach (glob(storage_path('app/atom-editor-review-*.jsonl')) as $file) {
            unlink($file);
        }
    });
});

describe('atom:tiptap-migrate --prune-backups', function () {
    it('refuses without a terminal and without --force', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups --no-interaction --force')
            ->expectsOutputToContain('pass --confirm-prune')
            ->assertFailed();

        expect(migrateBackupCount())->toBe(1);
    });

    it('asks first even with --force, and deletes nothing on a no', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups --force --confirm-prune')
            ->expectsConfirmation('Delete the backups?', 'no')
            ->expectsOutputToContain('Nothing was deleted')
            ->assertSuccessful();

        expect(migrateBackupCount())->toBe(1);
    });

    it('deletes the backups on a yes and leaves the empty table in place', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups')
            ->expectsConfirmation('Delete the backups?', 'yes')
            ->expectsOutputToContain('Deleted 1 backup(s)')
            ->assertSuccessful();

        expect(Schema::hasTable(EditorBackups::TABLE))->toBeTrue();
        expect(migrateBackupCount())->toBe(0);
    });

    it('takes --confirm-prune instead of a prompt when there is no terminal', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups --confirm-prune --no-interaction')->assertSuccessful();

        expect(migrateBackupCount())->toBe(0);
    });

    it('is the only thing that removes a backup: restore and a re-run leave them', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --restore --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        expect(migrateBackupCount())->toBe(2);
    });

    it('says there is nothing to prune when there are no backups', function () {
        $this->artisan('atom:tiptap-migrate --prune-backups --confirm-prune --no-interaction')->expectsOutputToContain('There are no backups')->assertSuccessful();
    });
});

describe('atom:tiptap-migrate --status', function () {
    it('fails while anything is left, and counts it', function () {
        migrateFixture();

        $this->artisan('atom:tiptap-migrate --status')
            ->expectsOutputToContain('Not yet migrated: 7 value(s)')
            ->expectsOutputToContain('Flagged (lossy, need --accept): 1 value(s)')
            ->expectsOutputToContain('Refused (need fixing by hand): 3 value(s)')
            ->assertFailed();
    });

    it('succeeds once every row is JSON', function () {
        migrateInsert([serialize('<p>one</p>'), '<p class="x">flagged</p>']);
        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --status')->expectsOutputToContain('Nothing left to migrate')->assertSuccessful();
    });

    it('fails on a flagged row until it is accepted', function () {
        migrateInsert(['<p class="x">flagged</p>']);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --status')->assertFailed();
    });

    it('reports a column still cast as the removed AsEditorContent', function () {
        DB::table('migrate_removed_casts')->insert(['body' => serialize('<p>x</p>')]);

        $this->artisan('atom:tiptap-migrate', ['--status' => true, '--path' => [migrateRemovedCastPath()]])
            ->expectsOutputToContain('App\Models\MigrateRemovedCast: body still cast as AsEditorContent')
            ->expectsOutputToContain('AsEditorContent casts left: 1')
            ->assertFailed();
    });

    it('accepts --for-v4 from 3.38 scripts: on v4 plain --status is already the gate', function () {
        DB::table('migrate_removed_casts')->insert(['body' => serialize('<p>x</p>')]);

        $this->artisan('atom:tiptap-migrate', ['--status' => true, '--for-v4' => true, '--path' => [migrateRemovedCastPath()]])->assertFailed();
        $this->artisan('atom:tiptap-migrate', ['--status' => true, '--for-v4' => true])->assertSuccessful();
        $this->artisan('atom:tiptap-migrate', ['--for-v4' => true])->expectsOutputToContain('--for-v4 only goes with --status')->assertFailed();
    });

    it('is callable as a method, for the upgrade gate', function () {
        migrateFixture();

        $status = app(MigrateTiptapContent::class)->status([migrateRemovedCastPath()]);

        expect($status['ready'])->toBeFalse()
            ->and($status['unmigrated'])->toBe(7)
            ->and($status['flagged'])->toBe(1)
            ->and($status['refused'])->toBe(3)
            ->and($status['rows']['migrated'])->toBe(1)
            ->and($status['legacy_casts'])->toHaveKey(\App\Models\MigrateRemovedCast::class)
            ->and($status['problems'])->toBe([]);

        DB::table('migrate_posts')->delete();

        $clean = app(MigrateTiptapContent::class)->status();

        expect($clean['unmigrated'])->toBe(0)->and($clean['legacy_casts'])->toBe([])->and($clean['ready'])->toBeTrue();
    });
});

describe('atom:tiptap-migrate: models and connections', function () {
    it('migrates a model on a connection that is not the default, with its backups beside it', function () {
        [$id] = migrateInsert([$original = serialize('<p>other</p>')], 'other', 'migrate_other_posts');

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id, 'other', 'migrate_other_posts')))->toBeTrue();
        expect(migrateBackupCount('other'))->toBe(1)->and(migrateBackupCount())->toBe(0);

        $this->artisan('atom:tiptap-migrate --compare --output='.($review = sys_get_temp_dir().'/atom-review-'.uniqid().'.jsonl'))
            ->expectsOutputToContain('Wrote 1 record(s)')
            ->assertSuccessful();
        unlink($review);

        $this->artisan('atom:tiptap-migrate --restore --force')->assertSuccessful();

        expect(migrateBody($id, 'other', 'migrate_other_posts'))->toBe($original);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --prune-backups --confirm-prune --no-interaction')->assertSuccessful();

        expect(migrateBackupCount('other'))->toBe(0);
    });

    it('limits a run to --model', function () {
        [$post] = migrateInsert([serialize('<p>a</p>')]);
        [$other] = migrateInsert([serialize('<p>b</p>')], 'other', 'migrate_other_posts');

        $this->artisan('atom:tiptap-migrate --force --model=MigrateOtherPost')->assertSuccessful();

        expect(migrateIsDocument(migrateBody($post)))->toBeFalse();
        expect(migrateIsDocument(migrateBody($other, 'other', 'migrate_other_posts')))->toBeTrue();
    });

    it('reads every row of a table larger than a chunk', function () {
        DB::table('migrate_posts')->insert(array_fill(0, 450, ['body' => serialize('<p>row</p>'), 'created_at' => MIGRATE_OLD, 'updated_at' => MIGRATE_OLD]));

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Migrated 450 value(s)')->assertSuccessful();

        expect(DB::table('migrate_posts')->count())->toBe(450);
        expect(migrateBackupCount())->toBe(450);
    });

    it('aborts a run that could not read a model, before writing anything', function () {
        [$id] = migrateInsert([serialize('<p>a</p>')]);

        $this->artisan('atom:tiptap-migrate --force --path=no/such/dir')
            ->expectsOutputToContain('not a directory')
            ->assertFailed();

        expect(migrateIsDocument(migrateBody($id)))->toBeFalse();
    });

    it('names the missing class when a model cannot be loaded, and fails the v4 gate', function () {
        $dir = realpath(__DIR__.'/../Fixtures/MigrateMissingParent');
        $autoload = function (string $class) use ($dir) {
            if ($class === 'App\\Models\\MissingParentModel') {
                require_once $dir.'/MissingParentModel.php';
            }
        };

        spl_autoload_register($autoload);

        try {
            $this->artisan('atom:tiptap-migrate', ['--status' => true, '--for-v4' => true, '--path' => [$dir]])
                ->expectsOutputToContain('App\\Models\\MissingParentModel: Error: Class "Vendor\\NotInstalled\\Models\\Base" not found')
                ->assertFailed();
        }
        finally {
            spl_autoload_unregister($autoload);
        }
    });
});

describe('atom:tiptap-migrate: describing a failure', function () {
    it('says what a missing class, interface, trait, enum, function or method is', function () {
        expect(EditorBackups::describe(new \Error('Class "X" not found')))->toBe('Error: Class "X" not found');
        expect(EditorBackups::describe(new \Error('Interface "X\\Y" not found')))->toBe('Error: Interface "X\\Y" not found');
        expect(EditorBackups::describe(new \Error('Trait "X" not found')))->toBe('Error: Trait "X" not found');
        expect(EditorBackups::describe(new \Error('Enum "X" not found')))->toBe('Error: Enum "X" not found');
        expect(EditorBackups::describe(new \Error('Call to undefined method App\\Models\\Post::nope()')))->toBe('Error: Call to undefined method App\\Models\\Post::nope()');
        expect(EditorBackups::describe(new \Error('Call to undefined function nope()')))->toBe('Error: Call to undefined function nope()');
    });

    it('says only the class of an Error that can quote a value', function () {
        enum MigrateDescribeStatus: string
        {
            case Open = 'open';
        }

        try {
            MigrateDescribeStatus::from('x@y.test');
        }
        catch (\ValueError $valueError) {
        }

        // PHP writes the value into this message unless zend.exception_ignore_args is on (it is on CI), so build it as a dev box does
        $unhandled = new \UnhandledMatchError("Unhandled match case 'x@y.test'");

        expect($valueError->getMessage())->toContain('x@y.test');
        expect($unhandled->getMessage())->toContain('x@y.test');
        expect(EditorBackups::describe($valueError))->toBe('ValueError');
        expect(EditorBackups::describe($unhandled))->toBe('UnhandledMatchError');
        expect(EditorBackups::describe(new \TypeError('f(): Argument #1 must be of type int')))->toBe('TypeError');
        expect(EditorBackups::describe(new \Error('x@y.test')))->toBe('Error');
        expect(EditorBackups::describe(new \Error("Class \"a\nforged line\" not found")))->toBe('Error');
        expect(EditorBackups::describe(new \Error("Class \"X\" not found\n")))->toBe('Error');
    });

    it('caps a missing-symbol message and scrubs invalid UTF-8', function () {
        $described = EditorBackups::describe(new \Error('Class "'.str_repeat('é', 500).'" not found'));

        expect(mb_strlen($described))->toBe(300);
        expect(mb_check_encoding(EditorBackups::describe(new \Error("Class \"bad \xFF byte\" not found")), 'UTF-8'))->toBeTrue();
    });

    it('still keeps SQL and bound values out of a database error', function () {
        $e = new \Illuminate\Database\QueryException('default', 'select * from users where email = ?', ['secret@example.com'], new \PDOException('SQLSTATE[23000]: secret@example.com'));
        $e->errorInfo = ['23000', 1062, 'Duplicate entry secret@example.com'];

        $described = EditorBackups::describe($e);

        expect($described)->toBe('QueryException (SQLSTATE 23000, driver code 1062)');
        expect($described)->not->toContain('secret')->not->toContain('select');
    });

    it('still shows only the class of any other exception', function () {
        expect(EditorBackups::describe(new \RuntimeException('secret value')))->toBe('RuntimeException');
    });
});

describe('atom:tiptap-migrate: the docs', function () {
    it('documents every option in the README and the Boost skill, and the workflow in UPGRADE', function () {
        $options = collect(app(MigrateTiptapContent::class)->getDefinition()->getOptions())
            ->keys()
            ->reject(fn ($name) => in_array($name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env', 'dry', 'path', 'model', 'output']))
            ->values();

        expect($options->all())->toContain('force', 'accept', 'restore', 'compare', 'prune-backups', 'status', 'discard-edits');

        $readme = file_get_contents(__DIR__.'/../../README.md');
        $skill = file_get_contents(__DIR__.'/../../resources/boost/skills/atom-components/SKILL.blade.php');
        $upgrade = file_get_contents(__DIR__.'/../../UPGRADE.md');

        foreach ($options as $option) {
            expect($readme)->toContain('--'.$option);
            expect($skill)->toContain('--'.$option);
        }

        foreach (['atom:tiptap-migrate', '--force', '--compare', '--prune-backups', 'AsTiptapContent', 'AsEditorContent', '<atom:tiptap*>', 'text-base'] as $needle) {
            expect($upgrade)->toContain($needle);
        }

        expect($readme)->not->toContain('## The `AsEditorContent` cast');
    });
});

describe('atom:tiptap-migrate: what the review found', function () {
    beforeEach(function () {
        $this->review = sys_get_temp_dir().'/atom-review-'.uniqid().'.jsonl';
    });

    afterEach(function () {
        @unlink($this->review);
    });

    it('flags a bare "<", which libxml 2.9.13 drops the text after on both sides of the comparison', function () {
        [$id] = migrateInsert(['<p>a < b and more</p>']);

        $this->artisan('atom:tiptap-migrate')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$id.' body: '."a bare '<' can drop text on older libxml")
            ->assertSuccessful();
    });

    it('does not flag a "<" that opens a tag, a closing tag or a comment', function () {
        migrateInsert(['<p>fine</p><!-- note --><br/>']);

        $this->artisan('atom:tiptap-migrate')->expectsOutputToContain('1 clean, 0 flagged')->assertSuccessful();
    });

    it('refuses JSON that would not fit the column, and says to change it to longText', function () {
        $command = new class extends MigrateTiptapContent
        {
            protected function columnCapacity(Model $model, string $column): ?int
            {
                return 300;
            }
        };
        Artisan::registerCommand($command);

        [$small, $big] = migrateInsert([serialize('<p>fits</p>'), serialize('<p>'.str_repeat('text ', 100).'</p>')]);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.'#'.$big.' body: the JSON (')
            ->assertFailed();

        expect(migrateIsDocument(migrateBody($small)))->toBeTrue();
        expect(migrateIsDocument(migrateBody($big)))->toBeFalse();

        $this->artisan('atom:tiptap-migrate --status')->expectsOutputToContain('Refused (need fixing by hand): 1')->assertFailed();
    });

    it('says "change it to longText first" in the reason', function () {
        $command = new class extends MigrateTiptapContent
        {
            protected function columnCapacity(Model $model, string $column): ?int
            {
                return 100;
            }
        };
        Artisan::registerCommand($command);
        migrateInsert([serialize('<p>'.str_repeat('text ', 100).'</p>')]);

        $this->artisan('atom:tiptap-migrate')->expectsOutputToContain('does not fit the column (it holds at most 100 bytes): change it to longText first')->assertSuccessful();
    });

    it('knows how much each column type holds', function (string $driver, string $typeName, string $type, ?int $capacity) {
        expect(MigrateTiptapContent::capacityOf($driver, $typeName, $type))->toBe($capacity);
    })->with([
        'mysql text' => ['mysql', 'text', 'text', 65535],
        'mariadb text' => ['mariadb', 'text', 'text', 65535],
        'mysql tinytext' => ['mysql', 'tinytext', 'tinytext', 255],
        'mysql mediumtext' => ['mysql', 'mediumtext', 'mediumtext', 16777215],
        'mysql longtext' => ['mysql', 'longtext', 'longtext', null],
        'mysql varchar' => ['mysql', 'varchar', 'varchar(255)', 255],
        'postgres text' => ['pgsql', 'text', 'text', null],
        'postgres varchar' => ['pgsql', 'varchar', 'character varying(120)', 120],
        'sqlite text' => ['sqlite', 'text', 'text', null],
        'sqlite varchar without a length' => ['sqlite', 'varchar', 'varchar', null],
    ]);

    it('looks the capacity up from the table, and sqlite holds anything', function () {
        $command = new class extends MigrateTiptapContent
        {
            public function capacity(Model $model, string $column): ?int
            {
                return $this->columnCapacity($model, $column);
            }
        };

        expect($command->capacity(new \App\Models\MigratePost, 'body'))->toBeNull();
    });

    it('never prints the content of a row in a failure, on the way in or on the way back', function () {
        [$id] = migrateInsert([serialize('<p>SECRETCONTENT</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        DB::unprepared("CREATE TRIGGER migrate_restore_fails BEFORE UPDATE OF body ON migrate_posts BEGIN SELECT RAISE(ABORT, 'refused SECRETCONTENT'); END");

        $this->artisan('atom:tiptap-migrate --restore --force')
            ->expectsOutputToContain('QueryException (SQLSTATE')
            ->doesntExpectOutputToContain('SECRETCONTENT')
            ->assertFailed();
    });

    it('refuses an empty --restore= and --compare=, and reads a bare one as every row', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate', ['--restore' => '', '--force' => true])->expectsOutputToContain('--restore= needs row ids')->assertFailed();
        $this->artisan('atom:tiptap-migrate', ['--compare' => ' , ', '--output' => $this->review])->expectsOutputToContain('--compare= needs row ids')->assertFailed();
        $this->artisan('atom:tiptap-migrate', ['--accept' => ''])->assertFailed();
        expect(file_exists($this->review))->toBeFalse();
        expect(DB::table(EditorBackups::TABLE)->whereNull('restored_at')->count())->toBe(1);

        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('Restored 1 value(s)')->assertSuccessful();
    });

    it('refuses --dry with --restore and with --prune-backups, which would otherwise write', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --dry --restore --force')->expectsOutputToContain('--dry cannot be combined with --restore')->assertFailed();
        $this->artisan('atom:tiptap-migrate --dry --prune-backups --confirm-prune --no-interaction')->expectsOutputToContain('--dry cannot be combined with --prune-backups')->assertFailed();

        expect(migrateBackupCount())->toBe(1);
        expect(DB::table(EditorBackups::TABLE)->whereNull('restored_at')->count())->toBe(1);
    });

    it('refuses a row id that is flagged in two models, lists both, and takes --model to choose', function () {
        $first = DB::table('migrate_posts')->insertGetId(['body' => '<p class="x">first</p>']);
        $second = DB::table('migrate_second_posts')->insertGetId(['body' => '<p class="x">second</p>']);
        expect($second)->toBe($first);

        $this->artisan("atom:tiptap-migrate --force --accept={$first}")
            ->expectsOutputToContain("id {$first}: ".\App\Models\MigratePost::class.'.body, '.\App\Models\MigrateSecondPost::class.'.body')
            ->assertFailed();

        expect(migrateIsDocument(migrateBody($first)))->toBeFalse();
        expect(migrateIsDocument(migrateBody($second, null, 'migrate_second_posts')))->toBeFalse();
        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();

        $this->artisan("atom:tiptap-migrate --force --accept={$first} --model=MigrateSecondPost")->assertSuccessful();

        expect(migrateIsDocument(migrateBody($second, null, 'migrate_second_posts')))->toBeTrue();
        expect(migrateIsDocument(migrateBody($first)))->toBeFalse();
    });

    it('does the same for --restore and --compare, and prints the model on every flagged line', function () {
        DB::table('migrate_posts')->insert(['id' => 7, 'body' => serialize('<p class="x">first</p>')]);
        DB::table('migrate_second_posts')->insert(['id' => 7, 'body' => serialize('<p class="x">second</p>')]);

        $this->artisan('atom:tiptap-migrate')
            ->expectsOutputToContain('flagged  '.\App\Models\MigratePost::class.'#7 body')
            ->expectsOutputToContain('flagged  '.\App\Models\MigrateSecondPost::class.'#7 body')
            ->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --restore=7 --force')->expectsOutputToContain('id 7: ')->assertFailed();
        $this->artisan('atom:tiptap-migrate', ['--compare' => '7', '--output' => $this->review])->expectsOutputToContain('id 7: ')->assertFailed();
        expect(migrateIsDocument(migrateBody(7)))->toBeTrue();

        $this->artisan('atom:tiptap-migrate --restore=7 --force --model=MigratePost')->assertSuccessful();

        expect(migrateBody(7))->toBe(serialize('<p class="x">first</p>'));
        expect(migrateIsDocument(migrateBody(7, null, 'migrate_second_posts')))->toBeTrue();
    });

    it('warns when --model matches no target, and --status is not ready then', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --status --model=NoSuchModel')
            ->expectsOutputToContain('--model NoSuchModel matched no model with an AsTiptapContent column')
            ->doesntExpectOutputToContain('Nothing left to migrate')
            ->assertFailed();

        $this->artisan('atom:tiptap-migrate --model=NoSuchModel')->expectsOutputToContain('Aborted, nothing was written')->assertFailed();
        $this->artisan('atom:tiptap-migrate --restore --model=NoSuchModel --force')->expectsOutputToContain('--model NoSuchModel matched no backup')->assertFailed();
        $this->artisan('atom:tiptap-migrate --status --column=nope')->expectsOutputToContain('--column nope matched no AsTiptapContent column')->assertFailed();
    });

    it('refuses a tampered backup on restore, and flags it in the review file', function () {
        [$id] = migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $migrated = migrateBody($id);
        DB::table(EditorBackups::TABLE)->update(['original' => '<p>somebody wrote this later</p>']);

        $this->artisan('atom:tiptap-migrate --restore --force')
            ->expectsOutputToContain('the backup does not match its own hash')
            ->assertFailed();

        expect(migrateBody($id))->toBe($migrated);
        expect(DB::table(EditorBackups::TABLE)->whereNull('restored_at')->count())->toBe(1);

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        $record = json_decode(file_get_contents($this->review), true);
        expect($record['backup_intact'])->toBeFalse()
            ->and($record['lossy'])->toBeTrue()
            ->and($record['notes'])->toContain('the backup does not match its own hash');
    });

    it('counts a connection once, whether a model names the default one or not', function () {
        [$one] = migrateInsert([serialize('<p>one</p>')]);
        DB::table('migrate_named_connection_posts')->insert(['body' => serialize('<p>two</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->expectsOutputToContain('Wrote 2 record(s)')->assertSuccessful();
        expect(file($this->review))->toHaveCount(2);

        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('Restored 2 value(s)')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --prune-backups --confirm-prune --no-interaction')->expectsOutputToContain('Deleted 4 backup(s)')->assertSuccessful();
    });

    it('writes the review file for its owner only', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        expect(fileperms($this->review) & 0777)->toBe(0600);
    });

    it('does not parse an original past the render limits when it compares', function () {
        [$id] = migrateInsert([serialize('<p>'.str_repeat('long text ', 50).'</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        config(['atom.editor.render_max_bytes' => 100]);

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->assertSuccessful();

        $record = json_decode(file_get_contents($this->review), true);
        expect($record['notes'])->toContain('too large to compare')
            ->and($record['lossy'])->toBeTrue()
            ->and($record['original_html'])->toContain('long text');
    });

    it('refuses a row key or model that a backup cannot hold as it is', function () {
        EditorBackups::on()->create();

        foreach ([str_repeat('k', 192), "bad\xFFkey"] as $key) {
            expect(fn () => EditorBackups::on()->store('batch', \App\Models\MigratePost::class, $key, 'body', '<p>x</p>', '{}', false))
                ->toThrow(\Jiannius\Atom\Services\EditorBackupException::class, 'row key');
        }

        expect(migrateBackupCount())->toBe(0);
    });

    it('migrates, restores, compares and accepts on a string primary key', function () {
        $uuid = '9d5b2a54-3f0e-4a4f-8a3b-5a0a5b0c1d11';
        DB::table('migrate_uuid_posts')->insert([
            ['id' => $uuid, 'body' => serialize('<p>uuid</p>')],
            ['id' => 'second-key', 'body' => '<p class="x">flagged</p>'],
        ]);

        $this->artisan('atom:tiptap-migrate --force --accept=second-key')->assertSuccessful();

        expect(migrateIsDocument(DB::table('migrate_uuid_posts')->where('id', $uuid)->value('body')))->toBeTrue();
        expect(migrateIsDocument(DB::table('migrate_uuid_posts')->where('id', 'second-key')->value('body')))->toBeTrue();
        expect(DB::table(EditorBackups::TABLE)->pluck('row_key')->sort()->values()->all())->toBe(['9d5b2a54-3f0e-4a4f-8a3b-5a0a5b0c1d11', 'second-key']);

        $this->artisan('atom:tiptap-migrate --compare='.$uuid.' --output='.$this->review)->expectsOutputToContain('Wrote 1 record(s)')->assertSuccessful();
        expect(json_decode(file_get_contents($this->review), true)['key'])->toBe($uuid);

        $this->artisan("atom:tiptap-migrate --restore={$uuid} --force")->assertSuccessful();

        expect(DB::table('migrate_uuid_posts')->where('id', $uuid)->value('body'))->toBe(serialize('<p>uuid</p>'));
    });

    it('works through a table prefix', function () {
        $id = DB::connection('prefixed')->table('migrate_prefixed_posts')->insertGetId(['body' => $original = serialize('<p>prefixed</p>')]);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id, 'prefixed', 'migrate_prefixed_posts')))->toBeTrue();
        expect(migrateBackupCount('prefixed'))->toBe(1);
        expect(DB::connection('prefixed')->getSchemaBuilder()->hasTable(EditorBackups::TABLE))->toBeTrue();
        expect(collect(DB::connection('prefixed')->select("select name from sqlite_master where type = 'table'"))->pluck('name')->all())->toContain('px_'.EditorBackups::TABLE);

        $this->artisan('atom:tiptap-migrate --compare --output='.$this->review)->expectsOutputToContain('Wrote 1 record(s)')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --restore --force')->assertSuccessful();

        expect(migrateBody($id, 'prefixed', 'migrate_prefixed_posts'))->toBe($original);
    });

    it('migrates soft-deleted and globally scoped rows', function () {
        DB::table('migrate_scoped_posts')->insert([
            ['body' => serialize('<p>trashed</p>'), 'published' => 1, 'deleted_at' => '2020-01-01 00:00:00'],
            ['body' => serialize('<p>hidden</p>'), 'published' => 0, 'deleted_at' => null],
            ['body' => serialize('<p>trashed and hidden</p>'), 'published' => 0, 'deleted_at' => '2020-01-01 00:00:00'],
        ]);
        expect(\App\Models\MigrateScopedPost::count())->toBe(0);

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        foreach (DB::table('migrate_scoped_posts')->pluck('body') as $body) {
            expect(migrateIsDocument($body))->toBeTrue();
        }

        expect(migrateBackupCount())->toBe(3);
    });

    it('scans a child model on its parent\'s table once', function () {
        migrateInsert([serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain(\App\Models\MigratePost::class.' (body)')
            ->doesntExpectOutputToContain('MigrateChildPost')
            ->assertSuccessful();

        expect(migrateBackupCount())->toBe(1);
        expect(DB::table(EditorBackups::TABLE)->value('model'))->toBe(\App\Models\MigratePost::class);
    });
});

describe('atom:tiptap-migrate: the recheck', function () {
    it('warns about and skips a model with no editor column on a connection this config does not define', function () {
        require_once __DIR__.'/../Fixtures/MigrateReporting/NoColumn/MigrateReportingNote.php';
        $path = realpath(__DIR__.'/../Fixtures/MigrateReporting/NoColumn');
        [$id] = migrateInsert([serialize('<p>one</p>')]);

        $this->artisan('atom:tiptap-migrate', ['--force' => true, '--path' => [$path]])->assertSuccessful();
        $this->artisan('atom:tiptap-migrate', ['--restore' => true, '--force' => true, '--path' => [$path]])
            ->expectsOutputToContain('Database connection [reporting] not configured')
            ->assertSuccessful();

        expect(migrateBody($id))->toBe(serialize('<p>one</p>'));

        $this->artisan('atom:tiptap-migrate', ['--force' => true, '--path' => [$path]])->assertSuccessful();
        $this->artisan('atom:tiptap-migrate', ['--compare' => true, '--output' => $review = sys_get_temp_dir().'/atom-review-'.uniqid().'.jsonl', '--path' => [$path]])->assertSuccessful();
        unlink($review);
        $this->artisan('atom:tiptap-migrate', ['--prune-backups' => true, '--confirm-prune' => true, '--no-interaction' => true, '--path' => [$path]])->assertSuccessful();

        expect(migrateBackupCount())->toBe(0);
    });

    it('still fails closed for a model WITH an editor column on a connection this config does not define', function () {
        require_once __DIR__.'/../Fixtures/MigrateReporting/WithColumn/MigrateReportingArticle.php';
        $path = realpath(__DIR__.'/../Fixtures/MigrateReporting/WithColumn');
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate', ['--prune-backups' => true, '--confirm-prune' => true, '--no-interaction' => true, '--path' => [$path]])
            ->expectsOutputToContain('Database connection [reporting] not configured')
            ->assertFailed();
        $this->artisan('atom:tiptap-migrate', ['--restore' => true, '--force' => true, '--path' => [$path]])->assertFailed();

        expect(migrateBackupCount())->toBe(1);
    });

    it('prunes only the --column it is given', function () {
        DB::table('migrate_two_column_posts')->insert(['body' => serialize('<p>body</p>'), 'summary' => serialize('<p>summary</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        expect(migrateBackupCount())->toBe(2);

        $this->artisan('atom:tiptap-migrate --prune-backups --model=MigrateTwoColumnPost --column=summary --confirm-prune --no-interaction')
            ->expectsOutputToContain('This deletes 1 backup(s)')
            ->expectsOutputToContain('Deleted 1 backup(s)')
            ->assertSuccessful();

        expect(DB::table(EditorBackups::TABLE)->pluck('column_name')->all())->toBe(['body']);

        $this->artisan('atom:tiptap-migrate --prune-backups --column=nothing --confirm-prune --no-interaction')
            ->expectsOutputToContain('matched no backup')
            ->assertSuccessful();

        expect(migrateBackupCount())->toBe(1);
    });

    it('knows what a key column holds, and only looks an id up in a column that could hold it', function () {
        $command = new class extends MigrateTiptapContent
        {
            public function kind(Model $model): string
            {
                return $this->keyKind($model);
            }

            public function holds(Model $model, string $id): bool
            {
                return $this->keyHolds($model, $id);
            }
        };

        $post = new \App\Models\MigratePost;
        $uuid = new \App\Models\MigrateUuidPost;

        expect($command->kind($post))->toBe('int')
            ->and($command->kind($uuid))->toBe('string')
            ->and($command->holds($post, '12'))->toBeTrue()
            ->and($command->holds($post, 'abc'))->toBeFalse()
            ->and($command->holds($post, '12abc'))->toBeFalse()
            ->and($command->holds($uuid, 'anything'))->toBeTrue();
    });

    it('does not look a non-numeric id up in an integer key, and a lookup that fails is a problem, not a crash', function () {
        $command = new class extends MigrateTiptapContent
        {
            public static array $asked = [];

            protected function presentIds(Model $model, string $key, string $column, array $ids): iterable
            {
                static::$asked[] = [$model::class, $ids];

                return [];
            }
        };
        Artisan::registerCommand($command);
        migrateInsert(['<p class="x">flagged</p>']);

        $this->artisan('atom:tiptap-migrate --force --accept=not-a-number')->assertSuccessful();

        // only the string-keyed model was asked, the integer-keyed ones were not
        expect(array_unique(array_column($command::$asked, 0)))->toBe([\App\Models\MigrateUuidPost::class]);

        $failing = new class extends MigrateTiptapContent
        {
            protected function presentIds(Model $model, string $key, string $column, array $ids): iterable
            {
                throw new \Illuminate\Database\QueryException('testing', 'select SECRETCONTENT', [], new \PDOException('SECRETCONTENT'));
            }
        };
        Artisan::registerCommand($failing);
        [$id] = migrateInsert(['<p class="y">flagged too</p>']);

        $this->artisan("atom:tiptap-migrate --force --accept={$id}")
            ->expectsOutputToContain('the ids could not be looked up (QueryException (SQLSTATE')
            ->doesntExpectOutputToContain('SECRETCONTENT')
            ->assertFailed();

        expect(migrateIsDocument(migrateBody($id)))->toBeFalse();
        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();
    });

    it('exits 1 when a restore skipped a value, with the model class gone', function () {
        EditorBackups::on()->create();
        EditorBackups::on()->store('batch', 'Gone\\Missing\\Model', '1', 'body', '<p>x</p>', '{}', false);

        $this->artisan('atom:tiptap-migrate --restore --force')
            ->expectsOutputToContain('the model class is not available')
            ->expectsOutputToContain('1 value(s) were skipped')
            ->assertFailed();
    });
});
