<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Two casts write into an "editor" folder: AsEditorContent (legacy, HTML) on the
 * default disk, AsTiptapContent (JSON) on config('atom.editor.disk'). The purge
 * command must treat an image either one references as referenced, and must
 * never mistake "could not read" for "not referenced".
 */
beforeEach(function () {
    foreach (['PurgeStatus', 'PurgeLegacyPost', 'PurgeTiptapArticle', 'PurgeScopedPost', 'PurgeMultiClass', 'Blog/PurgeBlogNote'] as $file) {
        require_once __DIR__.'/../Fixtures/PurgeApp/Models/'.$file.'.php';
    }

    require_once __DIR__.'/../Fixtures/PurgeExtra/Notes/Models/PurgeModuleNote.php';

    $this->app->useAppPath(__DIR__.'/../Fixtures/PurgeApp');

    // Tiptap writes to its own disk and folder; the legacy cast to the default disk
    Storage::fake('legacy_disk');
    Storage::fake('tiptap_disk', ['folder' => 'tenant']);
    Storage::fake('local');
    config(['filesystems.default' => 'legacy_disk', 'atom.editor.disk' => 'tiptap_disk']);

    foreach (['purge_legacy_posts' => 'body', 'purge_tiptap_articles' => 'doc', 'purge_blog_notes' => 'body', 'purge_module_notes' => 'doc', 'purge_multi_things' => 'doc'] as $table => $column) {
        Schema::create($table, function (Blueprint $t) use ($column) {
            $t->id();
            $t->text($column)->nullable();
        });
    }

    Schema::create('purge_scoped_posts', function (Blueprint $t) {
        $t->id();
        $t->text('body')->nullable();
        $t->boolean('published')->default(true);
        $t->softDeletes();
    });
});

function purgeTiptapDoc(string ...$srcs): string
{
    return json_encode(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]],
        ['type' => 'blockquote', 'content' => array_map(fn ($src) => ['type' => 'image', 'attrs' => ['src' => $src]], $srcs)],
    ]]);
}

function purgeLegacyHtml(string ...$srcs): string
{
    return serialize('<p>hi</p>'.implode('', array_map(fn ($src) => '<img src="'.$src.'" alt="">', $srcs)));
}

/**
 * Put files on a fake disk, dated three days ago so they are past the default
 * 24 hour grace period.
 */
function purgeEditorFiles(string $disk, string $folder, string ...$names): void
{
    foreach ($names as $name) {
        Storage::disk($disk)->put($folder.'/'.$name, 'bytes-of-'.$name);
        touch(Storage::disk($disk)->path($folder.'/'.$name), time() - 3 * 86400);
    }
}

describe('atom:purge-editor-images', function () {
    it('keeps an image only a Tiptap JSON document references, and purges the orphan beside it', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'tiptap-kept.jpg', 'tiptap-orphan.jpg');
        DB::table('purge_tiptap_articles')->insert(['doc' => purgeTiptapDoc('https://cdn.test/tenant/editor/tiptap-kept.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/tiptap-kept.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/tiptap-orphan.jpg');
    });

    it('keeps an image a legacy serialized-HTML row references', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'legacy-kept.jpg', 'legacy-orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/legacy-kept.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/legacy-kept.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/legacy-orphan.jpg');
    });

    it('purges images nothing references and backs each one up first', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'legacy-orphan.jpg');
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'tiptap-orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/other.jpg')]);
        DB::table('purge_tiptap_articles')->insert(['doc' => purgeTiptapDoc()]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertMissing('editor/legacy-orphan.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/tiptap-orphan.jpg');
        expect(Storage::disk('local')->get('editor-purged/legacy-orphan.jpg'))->toBe('bytes-of-legacy-orphan.jpg');
        expect(Storage::disk('local')->get('editor-purged/tiptap-orphan.jpg'))->toBe('bytes-of-tiptap-orphan.jpg');
    });

    it('lets either cast reference a file on the other cast\'s disk', function () {
        // a Tiptap column still holding legacy serialized HTML, whose image lives on the legacy disk
        purgeEditorFiles('legacy_disk', 'editor', 'old-in-tiptap-column.jpg');
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'new.jpg');
        DB::table('purge_tiptap_articles')->insert([
            ['doc' => purgeLegacyHtml('https://cdn.test/editor/old-in-tiptap-column.jpg')],
            ['doc' => purgeTiptapDoc('https://cdn.test/tenant/editor/new.jpg')],
        ]);
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/old-in-tiptap-column.jpg');
        Storage::disk('tiptap_disk')->assertExists('tenant/editor/new.jpg');
    });

    it('scans models in subdirectories of app/Models', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'nested-kept.jpg', 'nested-orphan.jpg');
        DB::table('purge_blog_notes')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/nested-kept.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/nested-kept.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/nested-orphan.jpg');
    });

    it('treats an src with a query string as the same file', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'versioned.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/versioned.jpg?v=3')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/versioned.jpg');
    });

    it('does not abort on a truncated Tiptap value, and still protects the names visible in it', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'still-visible.jpg', 'orphan.jpg');
        DB::table('purge_tiptap_articles')->insert(['doc' => '{"type":"doc","content":[{"type":"image","attrs":{"src":"https://cdn.test/tenant/editor/still-visible.jpg']);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/still-visible.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/orphan.jpg');
    });

    it('aborts without deleting anything when a stored value is a serialized object', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        // Content::unserialize refuses classes, so an object reads back as null: unreadable, not "no images"
        DB::table('purge_legacy_posts')->insert(['body' => serialize(new stdClass)]);

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('PurgeLegacyPost::body')
            ->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('aborts without deleting anything when a serialized value cannot be unserialized', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => 's:99:"<p>truncated']);

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('PurgeLegacyPost::body')
            ->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('aborts without deleting anything when a model cannot be queried', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        Schema::drop('purge_blog_notes');

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('PurgeBlogNote')
            ->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('lists what it would delete on --dry-run and changes nothing', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'legacy-orphan.jpg');
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'tiptap-kept.jpg', 'tiptap-orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);
        DB::table('purge_tiptap_articles')->insert(['doc' => purgeTiptapDoc('https://cdn.test/tenant/editor/tiptap-kept.jpg')]);

        $this->artisan('atom:purge-editor-images --dry-run')
            ->expectsOutputToContain('Would delete: [legacy_disk] editor/legacy-orphan.jpg')
            ->expectsOutputToContain('Would delete: [tiptap_disk] tenant/editor/tiptap-orphan.jpg')
            ->doesntExpectOutputToContain('tiptap-kept.jpg')
            ->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/legacy-orphan.jpg');
        Storage::disk('tiptap_disk')->assertExists('tenant/editor/tiptap-orphan.jpg');
        Storage::disk('local')->assertMissing('editor-purged/legacy-orphan.jpg');
    });

    it('deletes nothing when it finds no editor column at all', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        $this->app->useAppPath(__DIR__.'/../Fixtures/NoSuchApp');

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('keeps a file it cannot back up', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);
        // make the backup disk unwritable
        config(['filesystems.disks.local.root' => '/dev/null/unwritable']);
        app('filesystem')->forgetDisk('local');

        $this->artisan('atom:purge-editor-images')->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('resolves disk and folder through the casts, not its own copy of the rule', function () {
        expect(\Jiannius\Atom\Casts\AsTiptapContent::diskName())->toBe('tiptap_disk');
        expect(\Jiannius\Atom\Casts\AsTiptapContent::folder())->toBe('tenant/editor');
        expect(\Jiannius\Atom\Casts\AsEditorContent::diskName())->toBe('legacy_disk');
        expect(\Jiannius\Atom\Casts\AsEditorContent::folder())->toBe('editor');
    });
});

describe('atom:purge-editor-images matching', function () {
    it('matches on the file name, so a src without the disk folder still counts', function () {
        // tiptap_disk has folder "tenant": the file is tenant/editor/cdn-kept.jpg, the src has no "tenant"
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'cdn-kept.jpg', 'cdn-orphan.jpg');
        DB::table('purge_tiptap_articles')->insert(['doc' => purgeTiptapDoc('https://cdn.test/editor/cdn-kept.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/cdn-kept.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/cdn-orphan.jpg');
    });

    it('matches a percent-encoded src against the decoded file name', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'my photo.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/my%20photo.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/my photo.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('reads soft-deleted rows and rows a global scope hides', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'trashed-kept.jpg', 'hidden-kept.jpg', 'orphan.jpg');
        DB::table('purge_scoped_posts')->insert([
            ['body' => purgeLegacyHtml('https://cdn.test/editor/trashed-kept.jpg'), 'published' => 1, 'deleted_at' => now()],
            ['body' => purgeLegacyHtml('https://cdn.test/editor/hidden-kept.jpg'), 'published' => 0, 'deleted_at' => null],
        ]);
        expect(\App\Models\PurgeScopedPost::count())->toBe(0);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/trashed-kept.jpg');
        Storage::disk('legacy_disk')->assertExists('editor/hidden-kept.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('sees an unquoted src in legacy HTML', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'unquoted.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize('<img src=https://cdn.test/editor/unquoted.jpg>')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/unquoted.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('sees every URL of a srcset in legacy HTML', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'set-1x.jpg', 'set-2x.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize('<img srcset="https://cdn.test/editor/set-1x.jpg 1x, https://cdn.test/editor/set-2x.jpg 2x">')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/set-1x.jpg');
        Storage::disk('legacy_disk')->assertExists('editor/set-2x.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('sees an href pointing at an editor file in legacy HTML', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'linked.pdf', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize("<a href='https://cdn.test/editor/linked.pdf'>report</a>")]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/linked.pdf');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('sees a link mark href inside a Tiptap document', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'report.pdf', 'orphan.jpg');
        DB::table('purge_tiptap_articles')->insert(['doc' => json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [[
                'type' => 'text', 'text' => 'report',
                'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://cdn.test/tenant/editor/report.pdf']]],
            ]]],
        ]])]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/report.pdf');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/orphan.jpg');
    });

    it('does not read a legacy column that starts with a bracket as a Tiptap document', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'bracket.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => '[gallery] <img src="https://cdn.test/editor/bracket.jpg">']);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/bracket.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });
});

describe('atom:purge-editor-images timing', function () {
    it('keeps an unreferenced file newer than the grace period, and purges it with --grace=0', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'old-orphan.jpg');
        Storage::disk('legacy_disk')->put('editor/fresh-orphan.jpg', 'fresh');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('1 unreferenced file(s) kept')
            ->assertSuccessful();

        Storage::disk('legacy_disk')->assertMissing('editor/old-orphan.jpg');
        Storage::disk('legacy_disk')->assertExists('editor/fresh-orphan.jpg');

        $this->artisan('atom:purge-editor-images --grace=0')->assertSuccessful();

        Storage::disk('legacy_disk')->assertMissing('editor/fresh-orphan.jpg');
    });

    it('lists only what is past the grace period on --dry-run', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'old-orphan.jpg');
        Storage::disk('legacy_disk')->put('editor/fresh-orphan.jpg', 'fresh');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $this->artisan('atom:purge-editor-images --dry-run')
            ->expectsOutputToContain('Would delete: [legacy_disk] editor/old-orphan.jpg')
            ->doesntExpectOutputToContain('Would delete: [legacy_disk] editor/fresh-orphan.jpg')
            ->assertSuccessful();
    });

    it('rejects a --grace that is not a number of hours', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');

        $this->artisan('atom:purge-editor-images --grace=soon')->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('never calls a file unreferenced that appeared while the database was being read', function () {
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);
        // an editor image saved by a request that runs mid-scan; --grace=0 so only the listing order protects it
        \App\Models\PurgeLegacyPost::retrieved(function () {
            Storage::disk('legacy_disk')->put('editor/saved-during-scan.jpg', 'new');
            touch(Storage::disk('legacy_disk')->path('editor/saved-during-scan.jpg'), time() - 3 * 86400);
        });

        $this->artisan('atom:purge-editor-images --grace=0')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/saved-during-scan.jpg');
    });
});

describe('atom:purge-editor-images scope', function () {
    it('reports the models and columns it scanned', function () {
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $this->artisan('atom:purge-editor-images --dry-run')
            ->expectsOutputToContain('Scanned 5 model(s) with editor content columns')
            ->expectsOutputToContain('App\Models\PurgeTiptapArticle: doc (tiptap)')
            ->expectsOutputToContain('App\Models\PurgeLegacyPost: body (legacy)')
            ->expectsOutputToContain('App\Models\Blog\PurgeBlogNote: body (legacy)')
            ->assertSuccessful();
    });

    it('skips a plain PHP file in app/Models that declares no class', function () {
        // tests/Fixtures/PurgeApp/Models/helpers.php holds only a function (and a ::class use)
        expect(file_exists(__DIR__.'/../Fixtures/PurgeApp/Models/helpers.php'))->toBeTrue();
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('scans models in an extra --path directory', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'module-kept.jpg', 'module-orphan.jpg');
        DB::table('purge_module_notes')->insert(['doc' => purgeTiptapDoc('https://cdn.test/tenant/editor/module-kept.jpg')]);
        DB::table('purge_tiptap_articles')->insert(['doc' => purgeTiptapDoc()]);

        $this->artisan('atom:purge-editor-images', ['--path' => [realpath(__DIR__.'/../Fixtures/PurgeExtra')]])
            ->expectsOutputToContain('Modules\Notes\Models\PurgeModuleNote: doc (tiptap)')
            ->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/module-kept.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/module-orphan.jpg');
    });

    it('aborts without deleting anything when a disk cannot be listed', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml()]);

        $disk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('getConfig')->andReturn([]);
        $disk->shouldReceive('files')->andThrow(new RuntimeException('listing exploded'));
        Storage::set('legacy_disk', $disk);

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('disk [legacy_disk]: listing exploded')
            ->assertFailed();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/orphan.jpg');
    });

    it('finds a model declared after another class in the same file', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'multi-kept.jpg', 'multi-orphan.jpg');
        DB::table('purge_multi_things')->insert(['doc' => purgeTiptapDoc('https://cdn.test/tenant/editor/multi-kept.jpg')]);

        $this->artisan('atom:purge-editor-images')
            ->expectsOutputToContain('App\Models\PurgeMultiThing: doc (tiptap)')
            ->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/multi-kept.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/multi-orphan.jpg');
    });

    it('rejects an empty --path instead of scanning the whole project', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');

        $this->artisan('atom:purge-editor-images', ['--path' => ['']])
            ->expectsOutputToContain('--path: empty')
            ->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('aborts when a model directory cannot be read', function () {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores directory permissions');
        }

        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        $dir = sys_get_temp_dir().'/a2-purge-'.uniqid();
        mkdir($dir.'/locked', 0777, true);
        chmod($dir.'/locked', 0);

        try {
            $this->artisan('atom:purge-editor-images', ['--path' => [$dir]])
                ->expectsOutputToContain($dir)
                ->assertFailed();
        }
        finally {
            chmod($dir.'/locked', 0755);
            rmdir($dir.'/locked');
            rmdir($dir);
        }

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });

    it('aborts when a --path is not a directory', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');

        $this->artisan('atom:purge-editor-images --path=no/such/dir')
            ->expectsOutputToContain('not a directory')
            ->assertFailed();

        Storage::disk('legacy_disk')->assertExists('editor/orphan.jpg');
    });
});

describe('atom:purge-editor-images JSON in either column', function () {
    // <atom:editor> is an alias of <atom:tiptap> and emits JSON, so a host still on AsEditorContent stores serialize(<JSON>)
    it('keeps an image referenced from serialized Tiptap JSON in a legacy column', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'json-in-legacy.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize(purgeTiptapDoc('https://cdn.test/editor/json-in-legacy.jpg'))]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/json-in-legacy.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps an image referenced from serialized Tiptap JSON in a tiptap column', function () {
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'serialized-json.jpg', 'orphan.jpg');
        DB::table('purge_tiptap_articles')->insert(['doc' => serialize(purgeTiptapDoc('https://cdn.test/tenant/editor/serialized-json.jpg'))]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/serialized-json.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/orphan.jpg');
    });

    it('keeps an image referenced from raw Tiptap JSON in a legacy column', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'raw-json-in-legacy.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeTiptapDoc('https://cdn.test/editor/raw-json-in-legacy.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/raw-json-in-legacy.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });
});

describe('atom:purge-editor-images name anywhere in the value', function () {
    it('keeps an image referenced from a CSS url()', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'background.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize('<div style="background:url(https://cdn.test/editor/background.jpg)">x</div>')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/background.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps an image referenced from a poster attribute', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'poster.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize('<video poster="https://cdn.test/editor/poster.jpg"></video>')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/poster.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps an image whose name is double percent-encoded in the value', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'my photo.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/my%2520photo.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/my photo.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });
});

describe('atom:purge-editor-images names in odd forms', function () {
    it('keeps an image whose name is stored form-encoded, a space as a plus', function () {
        // rawurldecode() leaves a "+" alone, so only the urlencode()d needle can find this
        purgeEditorFiles('legacy_disk', 'editor', 'a b.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => serialize('<p>see /files?name=a+b.jpg</p>')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/a b.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a file whose name holds a delimiter, written out or percent-encoded', function () {
        // "sp ace (1).jpg" cannot be a token, so it is searched as a substring; its encoded form is a token
        purgeEditorFiles('legacy_disk', 'editor', 'sp ace (1).jpg', 'enc ((2)).jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert([
            ['body' => serialize('<img src="https://cdn.test/editor/sp ace (1).jpg">')],
            ['body' => serialize('<img src="https://cdn.test/editor/'.rawurlencode('enc ((2)).jpg').'">')],
        ]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/sp ace (1).jpg');
        Storage::disk('legacy_disk')->assertExists('editor/enc ((2)).jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a name that a prefix, a suffix or a JSON unicode escape is glued to', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'pre-glued.jpg', 'under-glued.jpg', 'sfx-glued.jpg', 'esc-glued.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert([
            ['body' => serialize('<img src="https://cdn.test/thumb-pre-glued.jpg">')],
            ['body' => serialize('<img src="https://cdn.test/thumb_under-glued.jpg">')],
            ['body' => serialize('<img src="https://cdn.test/sfx-glued.jpg.webp"><img src="x/sfx-glued.jpg-2x">')],
            ['body' => serialize('{"src":"https:\\u002f\\u002fcdn.test\\u002feditor\\u002fesc-glued.jpg"}')],
        ]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        foreach (['pre-glued', 'under-glued', 'sfx-glued', 'esc-glued'] as $kept) {
            Storage::disk('legacy_disk')->assertExists('editor/'.$kept.'.jpg');
        }

        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a non-ASCII name that a truncated JSON document holds as \\uXXXX', function (bool $upper) {
        // the document is cut off, so it cannot be decoded: only the json_encode()d form of the name can find it
        purgeEditorFiles('tiptap_disk', 'tenant/editor', 'tr-ünï.jpg', 'orphan.jpg');
        $document = substr(json_encode(['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'https://cdn.test/tenant/editor/tr-ünï.jpg']]]]), 0, -5);

        if ($upper) {
            $document = preg_replace_callback('/\\\\u[0-9a-f]{4}/', fn ($m) => '\\u'.strtoupper(substr($m[0], 2)), $document);
        }

        expect($document)->toContain($upper ? '\\u00FC' : '\\u00fc');
        expect(json_decode($document))->toBeNull();
        DB::table('purge_tiptap_articles')->insert(['doc' => $document]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('tiptap_disk')->assertExists('tenant/editor/tr-ünï.jpg');
        Storage::disk('tiptap_disk')->assertMissing('tenant/editor/orphan.jpg');
    })->with(['lower-case hex' => [false], 'upper-case hex' => [true]]);

    it('keeps both files when one name is the other\'s form-encoded name', function () {
        // "a b.jpg" urlencodes to "a+b.jpg", the other file's name: the value can stand for either, so both stay
        purgeEditorFiles('legacy_disk', 'editor', 'a b.jpg', 'a+b.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => purgeLegacyHtml('https://cdn.test/editor/a+b.jpg')]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/a b.jpg');
        Storage::disk('legacy_disk')->assertExists('editor/a+b.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a name beside CJK punctuation, curly quotes and a non-ASCII file name', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'cjk-kept.jpg', 'cjk-quoted.jpg', '日本語.png', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert([
            ['body' => serialize('图片：https://cdn.test/editor/cjk-kept.jpg，请看')],
            ['body' => serialize('“cjk-quoted.jpg”')],
            ['body' => serialize('<img src="https://cdn.test/editor/日本語.png">')],
        ]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        foreach (['cjk-kept.jpg', 'cjk-quoted.jpg', '日本語.png'] as $kept) {
            Storage::disk('legacy_disk')->assertExists('editor/'.$kept);
        }

        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a name behind a JSON control escape', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'after-newline.jpg', 'after-tab.jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert([
            ['body' => serialize('{"type":"text","text":"line\\nafter-newline.jpg"}')],
            ['body' => serialize('{"type":"text","text":"a\\tafter-tab.jpg"}')],
        ]);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/after-newline.jpg');
        Storage::disk('legacy_disk')->assertExists('editor/after-tab.jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('keeps a spaced name inside a value that is urlencoded whole', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'my photo (1).jpg', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(['body' => '%3Cimg+src%3D%22%2Feditor%2Fmy+photo+%281%29.jpg']);

        $this->artisan('atom:purge-editor-images')->assertSuccessful();

        Storage::disk('legacy_disk')->assertExists('editor/my photo (1).jpg');
        Storage::disk('legacy_disk')->assertMissing('editor/orphan.jpg');
    });

    it('says how far a long scan has got', function () {
        purgeEditorFiles('legacy_disk', 'editor', 'orphan.jpg');
        DB::table('purge_legacy_posts')->insert(array_fill(0, 1001, ['body' => purgeLegacyHtml()]));

        $this->artisan('atom:purge-editor-images --dry-run')
            ->expectsOutputToContain('App\Models\PurgeLegacyPost: 1000 row(s) read')
            ->assertSuccessful();
    });
});
