<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Tiptap\Content;

/**
 * Content::resanitize(): stored HTML that an earlier sanitize() wrote is cleaned
 * again, and the YouTube embeds that sanitize() itself prints are kept. The rows
 * below marked "old output" are what 3.29.13 and earlier printed for a hostile
 * JSON document (captured from that code), which is what a host has in its table.
 */

/**
 * @return string the pattern resanitize() sets an embed aside with
 */
function youtubeEmbedPattern(): string
{
    return (new ReflectionClassConstant(Content::class, 'YOUTUBE_EMBED'))->getValue();
}

/**
 * The HTML sanitize() prints for a JSON document holding these blocks: what a
 * stored row with an embed really looks like.
 *
 * @param  array<int, array<string, mixed>>  $blocks
 */
function embedRow(array $blocks): string
{
    return Content::sanitize(json_encode(['type' => 'doc', 'content' => $blocks]));
}

function youtubeBlock(string $src = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', mixed $start = null): array
{
    return ['type' => 'youtube', 'attrs' => ['src' => $src, 'start' => $start]];
}

function textBlock(string $text): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
}

/**
 * Whether the output holds something shaped like resanitize()'s own placeholder
 * (a row that seeds one itself is checked some other way).
 */
function placeholderLeft(string $html): bool
{
    return (bool) preg_match('/ATOMEMBED[0-9a-f]{32}x[0-9]+x/', $html);
}

/**
 * What a row may not hold after resanitize(), read from the parsed markup and not
 * by grepping (a label may say "onerror" as text): a tag the schema does not
 * have, an event handler or `srcdoc` attribute, a leftover placeholder. The
 * exact embeds atom prints are taken out first, so a row that keeps them is
 * checked for everything else.
 *
 * @return array<int, string>
 */
function resanitizeLeftovers(string $html): array
{
    $html = preg_replace(youtubeEmbedPattern(), '', $html);
    $allowed = ['p', 'br', 'hr', 'strong', 'em', 's', 'u', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'span', 'mark', 'sub', 'sup', 'table', 'tbody', 'thead', 'tr', 'th', 'td', 'img'];
    $found = [];

    if (placeholderLeft($html)) {
        $found[] = 'placeholder';
    }

    // a tag name that is not a plain one (`<https:`, `<mention>`) is not in the schema either
    preg_match_all('/<\/?([^>\s\/]*)/', $html, $names);

    foreach (array_unique($names[1]) as $name) {
        if (! in_array($name, $allowed, true)) {
            $found[] = "tag <{$name}>";
        }
    }

    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

    foreach ($dom->getElementsByTagName('*') as $element) {
        foreach ($element->attributes as $attribute) {
            if (str_starts_with($attribute->name, 'on') || $attribute->name === 'srcdoc') {
                $found[] = "attribute {$attribute->name}";
            }
        }
    }

    return array_values(array_unique($found));
}

/** The exact embed the Youtube extension prints for this video. */
const GENUINE_EMBED = '<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>';

/**
 * What 3.29.13 printed for a hostile document (the zero in `alt`, `title` or
 * `height`, a mention `id`, or a cell's span). `src` breaks out of the tag.
 *
 * @return array<string, array{0: string}>
 */
dataset('old hostile output', [
    'image src with a script' => ['<img><https://a.test/x.png"><script>alert(1)</script>>'],
    'image src with an iframe srcdoc' => ['<img><https://a.test/x.png"><iframe srcdoc="<script>alert(1)</script>"></iframe>>'],
    'table cell colwidth' => ['<table><tbody><tr><td><<img src=x onerror=alert(1)>><p>cell</p></td></tr></tbody></table>'],
    'mention label' => ['<p>hi <span><mention><mention><<img src=x onerror=alert(1)>>@&lt;img src=x onerror=alert(1)&gt;</span></p>'],
    'an iframe on its own' => ['<p>a</p><iframe srcdoc="<script>alert(1)</script>"></iframe>'],
    'a script on its own' => ['<p>a</p><script>alert(1)</script><p>b</p>'],
]);

/**
 * @return array<string, array{0: string}>
 */
dataset('genuine rows', [
    'a bare embed' => fn () => embedRow([youtubeBlock()]),
    'an embed between paragraphs' => fn () => embedRow([textBlock('before'), youtubeBlock(), textBlock('after')]),
    'with a start time' => fn () => embedRow([textBlock('a'), youtubeBlock(start: 42)]),
    'nocookie' => fn () => embedRow([youtubeBlock('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', '90')]),
    'two embeds and a short link' => fn () => embedRow([youtubeBlock(), textBlock('mid'), youtubeBlock('https://youtu.be/aaaaaaaaaaa', 7), textBlock('end')]),
    'in a quote' => fn () => embedRow([['type' => 'blockquote', 'content' => [textBlock('q'), youtubeBlock()]]]),
    'no embed at all' => ['<p>Hello <strong>team</strong> <a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/">link</a></p><ul><li><p>one</p></li></ul>'],
]);

describe('Content::resanitize: a genuine row', function () {
    it('comes back byte for byte, embeds included', function (string $row) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::resanitize($row))->toBe($row);
    })->with('genuine rows');

    it('holds the embed that sanitize() prints to exactly the shape resanitize() sets aside', function () {
        $matched = 0;

        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://m.youtube.com/shorts/dQw4w9WgXcQ'] as $src) {
            foreach ([null, 1, 42, '90', 100000] as $start) {
                $row = embedRow([youtubeBlock($src, $start)]);

                expect(preg_match(youtubeEmbedPattern(), $row, $m))->toBe(1);
                expect($m[0])->toBe($row);
                $matched++;
            }
        }

        expect($matched)->toBe(20);
    });

    it('does not differ between calls (the placeholder is per call and never survives)', function () {
        $row = embedRow([textBlock('a'), youtubeBlock(), textBlock('b')]);

        expect(Content::resanitize($row))->toBe(Content::resanitize($row));
        expect(placeholderLeft(Content::resanitize($row)))->toBeFalse();
    });
});

describe('Content::resanitize: a hostile row', function () {
    it('is cleaned, without a report', function (string $row) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $clean = Content::resanitize($row);

        expect(resanitizeLeftovers($clean))->toBe([]);
    })->with('old hostile output');

    it('is cleaned when it sits beside a genuine embed, and the embed is kept', function (string $hostile) {
        $row = '<p>first</p>'.GENUINE_EMBED.$hostile.GENUINE_EMBED.'<p>last</p>';
        $clean = Content::resanitize($row);

        expect(resanitizeLeftovers($clean))->toBe([]);
        expect(substr_count($clean, GENUINE_EMBED))->toBe(2);
        expect($clean)->toContain('<p>first</p>')->toContain('<p>last</p>');
    })->with('old hostile output');

    it('keeps no iframe when the row is one old hostile image', function () {
        // the row the README used to skip: it holds an iframe, so it looked like an embed
        $row = '<img><https://a.test/x.png"><iframe srcdoc="<script>alert(1)</script>"></iframe>>';

        expect(Content::resanitize($row))->not->toContain('iframe')->not->toContain('script');
    });
});

/**
 * An iframe that is not exactly what atom prints is hostile or foreign. Each
 * one is sanitized away; none is put back.
 *
 * @return array<string, array{0: string}>
 */
dataset('lookalike embeds', [
    'another host' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.evil.test/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'the host as a subdomain of another' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com.evil.test/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a user-info host' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com@evil.test/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'an extra srcdoc' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" srcdoc="<script>alert(1)</script>" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'an extra onload' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true" onload="alert(1)"></iframe></div>'],
    'an extra attribute on the div' => ['<div data-youtube-video="true" onclick="alert(1)"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'srcdoc after the closing tag' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true" srcdoc=x></iframe></div>'],
    'http, not https' => ['<div data-youtube-video="true"><iframe src="http://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'an id that is too long' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQx" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'an id that is too short' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXc" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a query that is not a start' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a start that is not digits' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=1&quot; onload=&quot;x" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a path after the id' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ/evil" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a different size' => ['<div data-youtube-video="true"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="9999" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'no div' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe>'],
    'upper case' => ['<DIV data-youtube-video="true"><IFRAME src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></IFRAME></DIV>'],
    'a space added' => ['<div data-youtube-video="true"><iframe  src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
    'a javascript url' => ['<div data-youtube-video="true"><iframe src="javascript:alert(1)" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>'],
]);

describe('Content::resanitize: an embed that is not exactly what atom prints', function () {
    it('is cleaned away, and the rest of the row is kept', function (string $lookalike) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(preg_match(youtubeEmbedPattern(), $lookalike))->toBe(0);

        $clean = Content::resanitize('<p>keep</p>'.$lookalike);

        expect($clean)->toBe('<p>keep</p>');
    })->with('lookalike embeds');

    it('does not become one by sitting beside a genuine embed', function (string $lookalike) {
        $clean = Content::resanitize(GENUINE_EMBED.$lookalike);

        expect($clean)->toBe(GENUINE_EMBED);
    })->with('lookalike embeds');

    it('is not put back when an exact embed sits where atom never prints one', function () {
        // inside an attribute it is text once sanitized, and inside a <pre> (or after an unclosed one) it is left alone
        foreach ([
            '<p>a</p><img src="/a.png" alt=\''.GENUINE_EMBED.'\'>',
            '<p>a</p><pre>'.GENUINE_EMBED.'</pre>',
            '<p>a</p><PRE class="x">'.GENUINE_EMBED.'</PRE>',
            '<p>a</p><pre>unclosed '.GENUINE_EMBED,
            '<p>a</p><pre>one</pre>'.GENUINE_EMBED.'<pre>'.GENUINE_EMBED.'</pre>',
        ] as $row) {
            $clean = Content::resanitize($row);

            // only the one outside every <pre> may survive
            expect(substr_count($clean, '<iframe'))->toBe(str_contains($row, '</pre>'.GENUINE_EMBED) ? 1 : 0);
            expect(placeholderLeft($clean))->toBeFalse();
        }
    });
});

/**
 * A subclass whose placeholder is known, which no caller of Content can ever
 * have: it stands in for an attacker who guessed the token, to show what the
 * belt-and-braces check does.
 */
class ResanitizeKnownToken extends Content
{
    protected static function embedToken(): string
    {
        return 'ATOMEMBED'.str_repeat('0', 32);
    }
}

describe('Content::resanitize: the placeholder cannot be forged', function () {
    it('cleans a hostile row that holds the word, in any form, instead of refusing it', function (string $seed) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $hostile = '<img><https://a.test/x.png"><iframe srcdoc="<script>alert(1)</script>"></iframe>>';
        $clean = Content::resanitize('<p>before</p>'.$seed.$hostile);

        expect($clean)->not->toBe('');
        // the seed is the row's own text and stays as text; the rest is what is checked
        expect(resanitizeLeftovers(str_ireplace('ATOMEMBED', 'seed', $clean)))->toBe([]);
        expect($clean)->toContain('<p>before</p>')->not->toContain('<iframe')->not->toContain('<script');
    })->with([
        'the word' => ['<p>ATOMEMBED</p>'],
        'lower case' => ['<p>atomembed</p>'],
        'a forged token' => ['<p>ATOMEMBED00000000000000000000000000000000x0x</p>'],
        'a forged token, lower case' => ['<p>atomembedffffffffffffffffffffffffffffffffx0x</p>'],
        'in an attribute' => ['<img src="/a.png" alt="ATOMEMBEDff">'],
        'in a pre' => ['<pre>ATOMEMBEDff</pre>'],
    ]);

    it('round-trips a genuine row that has the word in its text', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $row = embedRow([textBlock('the ATOMEMBED project, and atomembed too'), youtubeBlock(), textBlock('ATOMEMBED00000000000000000000000000000000x0x')]);

        expect($row)->toContain('ATOMEMBED00000000000000000000000000000000x0x');
        expect(Content::resanitize($row))->toBe($row);
    });

    it('cannot restore anything from a token seeded in the row', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $lookalike = '<div data-youtube-video="true"><iframe src="https://www.youtube.evil.test/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>';
        $seeded = '<p>ATOMEMBED00000000000000000000000000000000x0x</p><p>ATOMEMBED00000000000000000000000000000000x1x</p>';

        // a seeded token with no real embed beside it restores nothing
        $clean = Content::resanitize($seeded.$lookalike);

        expect($clean)->not->toContain('<iframe')->not->toContain('<div');

        // and beside a real one, only the real embed comes back, once
        $clean = Content::resanitize($seeded.GENUINE_EMBED.$lookalike);

        expect(substr_count($clean, '<iframe'))->toBe(1);
        expect(substr_count($clean, GENUINE_EMBED))->toBe(1);
    });

    it('refuses a row that already holds this call\'s own token, which chance cannot produce', function () {
        $forged = '<p>ATOMEMBED'.str_repeat('0', 32).'x0x</p>';

        expect(ResanitizeKnownToken::resanitize($forged.GENUINE_EMBED))->toBe('');
        expect(ResanitizeKnownToken::resanitize('<p>ATOMEMBED'.str_repeat('0', 32).'</p>'))->toBe('');
        // the same rows, with a token nobody knows, are just rows
        expect(Content::resanitize($forged.GENUINE_EMBED))->toContain(GENUINE_EMBED);
    });

    it('takes only its own token out of the output', function () {
        // the row's own "ATOMEMBED..." text stays: stripping removes this call's keys and nothing else
        $text = 'keep ATOMEMBED00000000000000000000000000000000x0x and ATOMEMBED here';
        $row = '<p>'.$text.'</p>'.GENUINE_EMBED;
        $clean = Content::resanitize($row);

        expect($clean)->toBe($row);
        expect($clean)->toContain($text);

        // an embed inside an attribute is removed as this call's token, the surrounding text is kept
        $clean = Content::resanitize('<img src="/a.png" alt="keep ATOMEMBED '.str_replace('"', '&quot;', GENUINE_EMBED).' end">');

        expect($clean)->toContain('keep ATOMEMBED')->toContain('end')->not->toContain('<iframe');
        expect(placeholderLeft($clean))->toBeFalse();
    });

    it('never leaves a placeholder in the output', function (string $row) {
        expect(placeholderLeft(Content::resanitize($row)))->toBeFalse();
    })->with('genuine rows');

    it('bails out of a row over $maxBytes before matching anything', function () {
        $row = str_repeat('<p>a</p>', 100).GENUINE_EMBED;

        expect(Content::resanitize($row, maxBytes: strlen($row) - 1))->toBe('');
        expect(Content::resanitize($row, maxBytes: strlen($row)))->toBe($row);
    });
});

describe('Content::resanitize: every case', function () {
    it('is idempotent', function (string $row) {
        $once = Content::resanitize($row);

        expect(Content::resanitize($once))->toBe($once);
    })->with('genuine rows');

    it('is idempotent on a hostile row', function (string $row) {
        $once = Content::resanitize($row);

        expect(Content::resanitize($once))->toBe($once);
    })->with('old hostile output');

    it('returns an empty string for empty, blank and unparseable input', function (string $row) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::resanitize($row))->toBe('');
    })->with(['', '   ', '<script>alert(1)</script>', '<!-- c -->']);

    it('honours the limits it is given', function () {
        $row = embedRow([textBlock('a'), youtubeBlock()]);

        expect(Content::resanitize($row, maxBytes: 10))->toBe('');
        expect(Content::resanitize($row))->toBe($row);
    });
});

class ResanitizeMessage extends Model
{
    protected $table = 'messages';

    protected $guarded = [];

    public $timestamps = false;
}

/**
 * The README's backfill, run as written, over a table of rows.
 */
describe('Content::resanitize: the documented backfill', function () {
    function readmeBackfill(): string
    {
        $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');
        $section = substr($readme, strpos($readme, '### Upgrading to 3.29.14'));

        preg_match('/```php\n(use Jiannius\\\\Atom\\\\Tiptap\\\\Content;.*?)```/s', $section, $match);

        return $match[1] ?? '';
    }

    it('is the snippet the README shows, with no iframe skip', function () {
        $code = readmeBackfill();

        expect($code)
            ->toContain('use Jiannius\Atom\Tiptap\Content;')
            ->toContain('Content::resanitize($message->body)')
            ->toContain('if (! is_string($message->body)) {')
            ->toContain("\$clean === ''")
            ->toContain('$review[] = $message->getKey();')
            ->not->toContain('iframe')
            ->not->toContain('data-youtube-video');
    });

    it('cleans the hostile rows, keeps the genuine ones, lists what it could not write, and is safe to run twice', function () {
        Schema::create('messages', function ($table) {
            $table->id();
            $table->text('body')->nullable();
        });

        $rows = [
            'genuine' => embedRow([textBlock('watch'), youtubeBlock(start: 30)]),
            'clean' => '<p>Hello <strong>x</strong></p>',
            'image script' => '<img><https://a.test/x.png"><script>alert(1)</script>>',
            'image iframe' => '<img><https://a.test/x.png"><iframe srcdoc="<script>alert(1)</script>"></iframe>>',
            'cell' => '<table><tbody><tr><td><<img src=x onerror=alert(1)>><p>cell</p></td></tr></tbody></table>',
            'lookalike' => '<p>a</p><div data-youtube-video="true"><iframe src="https://www.youtube.evil.test/embed/dQw4w9WgXcQ" width="640" height="480" frameborder="0" allowfullscreen="true"></iframe></div>',
            'script only' => '<script>alert(1)</script>',
            'empty' => '',
            'null' => null,
        ];

        foreach ($rows as $body) {
            ResanitizeMessage::create(['body' => $body]);
        }

        $run = function () {
            $code = str_replace(['Message::query()', 'logger()->info('], ['\ResanitizeMessage::query()', '(fn (...$arguments) => null)('], readmeBackfill());
            $code = preg_replace('/^use .*;$/m', '', $code);
            eval('use Jiannius\Atom\Tiptap\Content; '.$code);

            return $review;
        };

        $review = $run();
        $stored = ResanitizeMessage::pluck('body', 'id')->values()->all();
        $byName = array_combine(array_keys($rows), $stored);

        expect($byName['genuine'])->toBe($rows['genuine']);
        expect($byName['clean'])->toBe($rows['clean']);
        expect($byName['empty'])->toBe('');
        expect($byName['null'])->toBeNull();

        foreach (['image script', 'image iframe', 'cell'] as $name) {
            expect($byName[$name])->not->toBe($rows[$name]);
            expect(resanitizeLeftovers($byName[$name]))->toBe([]);
        }

        expect($byName['lookalike'])->toBe('<p>a</p>');

        // nothing printable to keep: not written, and listed for a person to look at
        expect($byName['script only'])->toBe($rows['script only']);
        expect($review)->toBe([array_search('script only', array_keys($rows)) + 1]);

        $before = ResanitizeMessage::pluck('body')->all();
        $run();

        expect(ResanitizeMessage::pluck('body')->all())->toBe($before);
    });
});
