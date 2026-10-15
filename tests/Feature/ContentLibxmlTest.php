<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Tiptap\Content;

/**
 * Content must print the same thing whichever libxml PHP was built against. The
 * 2.9 series (Ubuntu 22.04 and 24.04, the Debian php images) and 2.14 and later
 * (a new HTML5-style tokenizer) differ on input the parser has no rule for, and
 * four of the differences were failures on 2.9: a processing instruction named
 * like a tag the schema parses, loose top-level text, NUL, and the depth a tree
 * may reach. The expected strings below are what BOTH print; each is checked
 * with no report and with every PHP warning thrown, as a host does.
 */
describe('Content: a processing instruction is dropped, not parsed', function () {
    it('drops one named like a tag the schema parses, with no report', function (string $html, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        withWarningsThrown(function () use ($html, $expected) {
            expect(Content::sanitize($html))->toBe($expected)
                ->and(Content::render($html))->toBe($expected)
                ->and(Content::sanitizeRefuses($html))->toBeFalse();
        });

        Log::shouldNotHaveReceived('warning');
    })->with([
        'span' => ['<p>x<?span></p>', '<p>x</p>'],
        'td' => ['<p>x<?td></p>', '<p>x</p>'],
        'a' => ['<p>x<?a></p>', '<p>x</p>'],
        'img' => ['<p>x<?img></p>', '<p>x</p>'],
        'p, which printed a nested paragraph' => ['<p>x<?p></p>', '<p>x</p>'],
        'one with data, named like a tag the parser knows (the text after its > stays)' => ['<p>x<?a href="https://x.test/" target="_blank">y</p>', '<p>x y</p>'],
        'one in the middle of a word, which it separates' => ['<p>x<?xml version="1.0"?>y</p>', '<p>x y</p>'],
        'one after the last element' => ['<p>x</p><?php echo 1 ?>', '<p>x</p>'],
        'one cut off by the end of the input' => ['<p>x</p><?span', '<p>x</p>'],
        'the xml declaration of a pasted document' => ['<?xml version="1.0" encoding="UTF-8"?><p>x</p>', '<p>x</p>'],
    ]);

    it('cannot join what is around it into a tag or into the reserved placeholder', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        // removing `<?x>` outright would leave `<` beside `?y>`, or `<` beside `img src=...>`, or MINI beside FYHTML
        $out = withWarningsThrown(fn () => Content::sanitize('<p><<?x>?y></p><p><<?x>img src=x onerror=1></p><p>MINI<?x>FYHTML0</p>'));

        expect($out)->not->toContain('<?')->not->toContain('<img')->toContain('?y')->toContain('MINI FYHTML0');
        expect(Content::sanitizeRefuses('<p>MINI<?x>FYHTML0</p>'))->toBeFalse();
    });

    it('still prints a < ? that is escaped text', function () {
        expect(Content::sanitize('<p>a &lt;?php b</p>'))->toBe('<p>a &lt;?php b</p>');
    });
});

/**
 * libxml 2.9 wraps loose leading top-level text in an implied paragraph, as
 * well as the text around a block; 2.14 and later leave it bare. A paragraph
 * round-trips, so what an earlier pass wrote is not rewritten by the next:
 * resanitize() of a stored row is idempotent on both.
 */
describe('Content: loose top-level text is a paragraph', function () {
    it('wraps text and the inline nodes that go with it', function (string $html, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($html))->toBe($expected)
            ->and(Content::render($html))->toBe($expected);
    })->with([
        'a number' => ['42', '<p>42</p>'],
        'an entity' => ['&gt;', '<p>&gt;</p>'],
        'text around a block' => ['a<p>b</p>c', '<p>a</p><p>b</p><p>c</p>'],
        'text beside a hard break' => ['a<br>b', '<p>a<br>b</p>'],
        'text beside a mention' => ['hi <span data-type="mention" data-id="1" data-label="Al">@Al</span> there', '<p>hi <span class="mention" data-type="mention" data-id="1" data-label="Al">@Al</span> there</p>'],
        'text with a mark' => ['a <strong>b</strong>', '<p>a <strong>b</strong></p>'],
        'text and a trailing block' => ['a<ul><li>b</li></ul>', '<p>a</p><ul><li>b</li></ul>'],
        'text between two blocks' => ['<h2>h</h2>a<h2>i</h2>', '<h2>h</h2><p>a</p><h2>i</h2>'],
        'text after an image, which stays where it was' => ['a<img src="https://x.test/a.png">b', '<p>a<img src="https://x.test/a.png">b</p>'],
    ]);

    it('leaves an image on its own, and blocks with whitespace between them, as they were', function (string $html, string $expected) {
        expect(Content::sanitize($html))->toBe($expected);
    })->with([
        'a lone image' => ['<img src="https://x.test/a.png" alt="a">', '<img src="https://x.test/a.png" alt="a">'],
        'two blocks and a newline' => ["<p>a</p>\n<p>b</p>", '<p>a</p><p>b</p>'],
        'two blocks and a space' => ['<p>a</p> <p>b</p>', '<p>a</p><p>b</p>'],
    ]);

    it('leaves a stored JSON document exactly as it is', function () {
        $document = '{"type":"doc","content":[{"type":"text","text":"x"}]}';

        expect(Content::render($document))->toBe('x');
    });

    it('is idempotent: a second pass changes nothing', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        $once = Content::sanitize($html);

        expect($once)->not->toBe('')
            ->and(Content::sanitize($once))->toBe($once)
            ->and(Content::resanitize($html))->toBe($once)
            ->and(Content::resanitize($once))->toBe($once);
    })->with([
        'a number' => ['42'],
        'an entity' => ['&gt;'],
        'text around a block' => ['a<p>b</p>c'],
        'text and a hard break' => ['a<br>b'],
        'an old image row' => ['<img><https://a.test/x.png">>'],
    ]);
});

/**
 * tiptap-php takes any string that decodes as JSON for a JSON document and reads it
 * with none of the checks a document gets here (attributes repaired, limits, shape),
 * and a document with a zero in its attributes prints other attributes as raw tags. So
 * a string that was not JSON when inspect() looked at it must never reach tiptap-php as
 * JSON, however the steps after that (an instruction replaced, a whitespace run
 * collapsed) changed it. It is read as the HTML it is: text.
 */
describe('Content: input that was not JSON is never read as a JSON document', function () {
    $document = '{"type":"doc","content":[{"type":"image","attrs":{"src":"https://x.test/a.png","alt":0.0,"title":"img src=x onerror=alert(1) x"}}]}';

    dataset('not a document', [
        'an instruction before it' => ['<?x>'.$document],
        'an instruction after it' => [$document.'<?x>'],
        'an instruction between its tokens' => [str_replace('"type":"doc",', '"type":"doc",<?x>', $document)],
        'an instruction inside one of its tokens' => [str_replace('"type":"doc"', '"type":<?x>"doc"', $document)],
        'a run of no-break spaces between its tokens' => [str_replace('"type":"doc",', '"type":"doc",'.str_repeat("\u{00A0}", 40), $document)],
        'a run of ideographic spaces' => [str_replace('"type":"doc",', '"type":"doc",'.str_repeat("\u{3000}", 40), $document)],
        'a run of spaces and no-break spaces' => [str_replace('"type":"doc",', '"type":"doc",'.str_repeat(" \u{00A0}", 40), $document)],
    ]);

    it('comes back as text: no image, no tag but a paragraph, no report', function (string $input) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        foreach ([Content::sanitize($input), Content::render($input)] as $out) {
            expect($out)->toStartWith('<p>{&quot;type&quot;:')
                ->and($out)->not->toContain('<img')
                ->and(domQuery($out, '//body//*[not(self::p)]'))->toBe([]);
        }
    })->with('not a document');

    it('is the same document that the plain string is, when nothing changes it', function () use ($document) {
        expect(Content::sanitize($document))->toStartWith('<img src="https://x.test/a.png"')
            ->and(Content::sanitize($document))->toContain('alt="0"');
    });

    it('reads a document with a bare type and no content as text, with no report', function (string $input) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($input))->toBe('<p>{&quot;type&quot;:&quot;doc&quot;}</p>')
            ->and(Content::render($input))->toBe('<p>{&quot;type&quot;:&quot;doc&quot;}</p>');
    })->with([
        'an instruction after it' => ['{"type":"doc"}<?x>'],
        'an instruction before it' => ['<?x>{"type":"doc"}'],
    ]);

    it('still takes a real document, spaces around it and between its tokens included, with every check', function () use ($document) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $spaced = "  \n".str_replace('"type":"doc",', "\"type\":\"doc\",\n  ", $document)."\n";

        expect(Content::sanitize($spaced))->toBe(Content::sanitize($document));
    });
});

describe('Content: NUL', function () {
    it('drops it, so a NUL is the same on every libxml (2.9 read it as a space, 2.14 skips it)', function (string $html, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($html))->toBe($expected)
            ->and(Content::render($html))->toBe($expected);
    })->with([
        'in text' => ["<p>a\0b</p>", '<p>ab</p>'],
        'in a tag name' => ["<p>a</p\0>", '<p>a</p>'],
        'between a < and the ? of an instruction' => ["<p>x<\0?span></p>", '<p>x</p>'],
        'around a space' => ["<p>a \0 b</p>", '<p>a  b</p>'],
    ]);

    it('cannot be used to build the reserved placeholder', function () {
        $html = "<p>MINI\0FYHTML0</p>";

        expect(Content::sanitize($html))->toBe('')
            ->and(Content::sanitizeRefuses($html))->toBeTrue();
    });
});

/**
 * libxml stops building a tree at 256 levels, and 2.9 builds one more level than
 * 2.14 and later do: that one is enough for tiptap-php's serialiser to pass
 * json_encode()'s depth of 512, which raises a warning (an ErrorException in a
 * host, so a report() on every call) and printed nothing. Deeper than
 * Content::MAX_DEPTH is refused, the same on both.
 */
describe('Content: a document nested too deeply is refused', function () {
    it('refuses nested unclosed pre tags past the limit, silently from sanitize()', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $html = str_repeat('<pre>', 2000);

        withWarningsThrown(function () use ($html) {
            expect(Content::sanitize($html))->toBe('')
                ->and(Content::sanitizeRefuses($html))->toBeTrue();
        });

        Log::shouldNotHaveReceived('warning');
    });

    it('refuses the depth libxml 2.9 fails on, with no report and no warning', function (string $open, string $close) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        foreach ([256, 257, 300] as $levels) {
            $html = str_repeat($open, $levels).'x'.str_repeat($close, $levels);

            withWarningsThrown(function () use ($html) {
                expect(Content::sanitize($html))->toBe('')
                    ->and(Content::sanitizeRefuses($html))->toBeTrue();
            });
        }
    })->with([
        'pre' => ['<pre>', '</pre>'],
        'blockquote' => ['<blockquote>', '</blockquote>'],
        'list' => ['<ul><li>', '</li></ul>'],
    ]);

    it('logs the refusal from render(), once, with the reason', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $html = str_repeat('<pre>', 2000).'<!-- '.uniqid('', true).' -->';

        withWarningsThrown(function () use ($html) {
            expect(Content::render($html))->toBe('')
                ->and(Content::render($html))->toBe('');
        });

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], 'depth'));
    });

    it('refuses a stored JSON document nested past the limit too', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $node = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x '.uniqid('', true)]]];

        for ($i = 0; $i < 210; $i++) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }

        $document = json_encode(['type' => 'doc', 'content' => [$node]]);

        withWarningsThrown(function () use ($document) {
            expect(Content::render($document))->toBe('')
                ->and(Content::sanitizeRefuses($document))->toBeTrue();
        });

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], 'depth'));
    });

    it('still renders real nesting, a long way under the limit', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $html = str_repeat('<blockquote>', 150).'<p>x</p>'.str_repeat('</blockquote>', 150);

        $out = withWarningsThrown(fn () => Content::sanitize($html));

        expect(substr_count($out, '<blockquote>'))->toBe(150);
    });
});

describe('Content: the harness sees a vendor warning', function () {
    it('throws a warning as an exception, and puts the handler back', function () {
        expect(fn () => withWarningsThrown(fn () => trigger_error('a vendor warning', E_USER_WARNING)))->toThrow(ErrorException::class, 'a vendor warning');

        $handler = set_error_handler(fn () => false);
        restore_error_handler();

        expect($handler)->not->toBeNull();
    });
});
