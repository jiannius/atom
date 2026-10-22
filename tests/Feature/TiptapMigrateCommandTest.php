<?php

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
    foreach (['MigratePost', 'MigrateOtherPost'] as $file) {
        require_once __DIR__.'/../Fixtures/MigrateApp/Models/'.$file.'.php';
    }

    // the model that still names the removed cast lives apart: it is scanned only by the tests that pass its --path
    require_once __DIR__.'/../Fixtures/MigrateRemovedCast/MigrateRemovedCast.php';

    $this->app->useAppPath(__DIR__.'/../Fixtures/MigrateApp');
    config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    foreach ([null, 'other'] as $connection) {
        Schema::connection($connection)->create($connection ? 'migrate_other_posts' : 'migrate_posts', function (Blueprint $t) {
            $t->id();
            $t->text('body')->nullable();
            $t->timestamps();
        });
    }

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
            ->expectsOutputToContain('#'.$id['lossy'].' body: tags: font | attributes: font@color, p@class:MsoNormal, p@style:font-family')
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

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Migrated 3 value(s)')->assertSuccessful();

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

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

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

        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $bodies = DB::table('migrate_posts')->pluck('body', 'id')->all();
        $backups = migrateBackupCount();

        $this->artisan('atom:tiptap-migrate --force')->expectsOutputToContain('Migrated 0 value(s)')->assertSuccessful();

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
        [$id] = migrateInsert([$original = serialize('<p>a</p>')]);
        EditorBackups::on()->create();
        DB::unprepared("CREATE TRIGGER migrate_fails BEFORE UPDATE OF body ON migrate_posts BEGIN SELECT RAISE(ABORT, 'update refused'); END");

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('update refused')
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
            ->assertSuccessful();

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

        $this->artisan('atom:tiptap-migrate --restore --force')->expectsOutputToContain('the row no longer exists')->assertSuccessful();
    });
});

describe('atom:tiptap-migrate --accept', function () {
    it('converts the flagged rows it names, and only those', function () {
        $id = migrateFixture();
        [$other] = migrateInsert(['<p class="x">second lossy</p>']);

        $this->artisan("atom:tiptap-migrate --force --accept={$id['lossy']}")->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id['lossy'])))->toBeTrue();
        expect(migrateIsDocument(migrateBody($other)))->toBeFalse();
        expect(DB::table(EditorBackups::TABLE)->where('row_key', (string) $id['lossy'])->value('accepted_lossy'))->toEqual(1);
    });

    it('takes "all"', function () {
        $id = migrateFixture();
        [$other] = migrateInsert(['<p class="x">second lossy</p>']);

        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertSuccessful();

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
            ->assertSuccessful();

        foreach (['object', 'placeholder', 'script'] as $name) {
            expect(migrateBody($id[$name]))->toBe($before[$id[$name]]);
        }

        $this->artisan('atom:tiptap-migrate --force --accept=all')->assertSuccessful();

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
            ->expectsOutputToContain('#'.$id['object'].' body: a serialized value that holds an object or null')
            ->assertSuccessful();

        gc_collect_cycles();

        expect(MigrateGadget::$touched)->toBeFalse();
    });

    it('names a value that carries the parser placeholder, and never expands it', function () {
        $id = migrateFixture();

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('#'.$id['placeholder'].' body: it carries the HTML parser\'s reserved placeholder')
            ->assertSuccessful();
    });

    it('names a value with nothing to convert', function () {
        $id = migrateFixture();

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('#'.$id['script'].' body: it has nothing to convert')
            ->assertSuccessful();
    });

    it('names a value over the size limit', function () {
        config(['atom.editor.render_max_bytes' => 1000]);
        [$id] = migrateInsert(['<p>'.str_repeat('long text ', 400).'</p>']);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('#'.$id.' body: it has nothing to convert, or is over the size or nesting limits')
            ->assertSuccessful();

        expect(migrateIsDocument(migrateBody($id)))->toBeFalse();
    });

    it('names a serialized JSON document the editor cannot take', function () {
        [$id] = migrateInsert([serialize('{"type":"doc","content":[{"type":"paragraph","attrs":{"x":1e999}}]}')]);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('#'.$id.' body: it has nothing to convert, or is over the size or nesting limits')
            ->assertSuccessful();

        expect(migrateBackupCount())->toBe(0);
    });

    it('names JSON that is not a document, and reads text that only starts with a bracket as HTML', function () {
        [$json, $bracket] = migrateInsert(['{"a":1}', '[gallery] <img src="https://cdn.test/editor/a.jpg">']);

        $this->artisan('atom:tiptap-migrate --force')
            ->expectsOutputToContain('#'.$json.' body: it is JSON, but not a Tiptap document')
            ->assertSuccessful();

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
            ->assertSuccessful();

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
        'divs become paragraphs' => ['<div>one</div><div>two</div>'],
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
            ->expectsOutputToContain('flagged  #'.$id.' body: '.$loses)
            ->assertSuccessful();
    })->with([
        'a class' => ['<p class="MsoNormal">word</p>', 'attributes: p@class:MsoNormal'],
        'a style property' => ['<span style="color:red;font-family:Arial">c</span>', 'attributes: span@style:font-family'],
        'a script' => ['<p>x</p><script>alert(1)</script>', 'tags: script'],
        'an event handler' => ['<p onclick="x()">click</p>', 'attributes: p@onclick'],
        'a font tag' => ['<font color="red">red</font>', 'tags: font | attributes: font@color'],
        'a caption' => ['<table><caption>Cap</caption><tr><td>a</td></tr></table>', 'tags: caption'],
        'a video' => ['<video src="a.mp4">fallback</video>', 'tags: video | attributes: video@src=a.mp4'],
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
        $this->artisan('atom:tiptap-migrate --accept=all --force')->assertSuccessful();
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

        $this->artisan('atom:tiptap-migrate --prune-backups --no-interaction')
            ->expectsOutputToContain('pass --force')
            ->assertFailed();

        expect(migrateBackupCount())->toBe(1);
    });

    it('asks first, and deletes nothing on a no', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups')
            ->expectsConfirmation('Delete the backups?', 'no')
            ->expectsOutputToContain('Nothing was deleted')
            ->assertSuccessful();

        expect(migrateBackupCount())->toBe(1);
    });

    it('deletes the backups and drops the table on a yes', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups')
            ->expectsConfirmation('Delete the backups?', 'yes')
            ->expectsOutputToContain('Deleted 1 backup(s)')
            ->assertSuccessful();

        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();
    });

    it('takes --force instead of a prompt', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        $this->artisan('atom:tiptap-migrate --prune-backups --force --no-interaction')->assertSuccessful();

        expect(Schema::hasTable(EditorBackups::TABLE))->toBeFalse();
    });

    it('is the only thing that removes a backup: restore and a re-run leave them', function () {
        migrateInsert([serialize('<p>one</p>')]);
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --restore --force')->assertSuccessful();
        $this->artisan('atom:tiptap-migrate --force')->assertSuccessful();

        expect(migrateBackupCount())->toBe(2);
    });

    it('says there is nothing to prune when there are no backups', function () {
        $this->artisan('atom:tiptap-migrate --prune-backups --force')->expectsOutputToContain('There are no backups')->assertSuccessful();
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
        $this->artisan('atom:tiptap-migrate --prune-backups --force')->assertSuccessful();

        expect(Schema::connection('other')->hasTable(EditorBackups::TABLE))->toBeFalse();
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
});
