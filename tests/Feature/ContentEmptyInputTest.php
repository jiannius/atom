<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Tiptap\Content;

/**
 * libxml only builds a <body> when something belongs in it, and tiptap-php reads
 * the body without checking. A script, a style, a comment, a bare <meta> or
 * nothing at all used to leave it with none, so every such value (client-sent,
 * for sanitize()) was a TypeError that convert() reported. It is an empty
 * message, not a failure: a silent '' with no report and no log line.
 */
dataset('input that parses to nothing', [
    'script only' => ['<script>x</script>'],
    'two scripts and a style' => ['<script>a</script><style>b{}</style><script>c</script>'],
    'style only' => ['<style>p{color:red}</style>'],
    'comment only' => ['<!-- nothing to see -->'],
    'empty comment' => ['<!---->'],
    'whitespace only' => ["   \n\t  "],
    'no-break spaces only' => ["\u{00A0}\u{00A0}"],
    'empty html element' => ['<html></html>'],
    'html with an empty head' => ['<html><head></head></html>'],
    'head with a title only' => ['<head><title>t</title></head>'],
    'head with a script only' => ['<head><script>x</script></head>'],
    'lone meta' => ['<meta charset="utf-8">'],
    'lone link' => ['<link rel="stylesheet" href="x.css">'],
    'lone base' => ['<base href="/">'],
    'lone title' => ['<title>t</title>'],
    'doctype only' => ['<!DOCTYPE html>'],
    'doctype and empty html' => ['<!DOCTYPE html><html></html>'],
    'xml declaration' => ['<?xml version="1.0"?>'],
    'empty body' => ['<body></body>'],
    'script in an empty body' => ['<body><script>x</script></body>'],
    'stray closing tag' => ['</p>'],
    'script with a payload' => ['<script>alert(document.cookie)</script>'],
    'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
]);

describe('Content: input that parses to nothing is an empty message', function () {
    it('returns an empty string from sanitize() and render() with no report and no log line', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        expect(Content::sanitize($html))->toBe('')
            ->and(Content::render($html))->toBe('')
            ->and(Content::sanitizeRefuses($html))->toBeFalse();

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    })->with('input that parses to nothing');

    it('still returns the content beside a head-only element', function (string $html, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($html))->toBe($expected)
            ->and(Content::render($html))->toBe($expected);
    })->with([
        'script before a paragraph' => ['<script>x</script><p>hi</p>', '<p>hi</p>'],
        'title before a paragraph' => ['<title>t</title><p>hi</p>', '<p>hi</p>'],
        'meta before a paragraph' => ['<meta charset="utf-8"><p>hi</p>', '<p>hi</p>'],
        'a whole document' => ['<!DOCTYPE html><html><head><title>t</title></head><body><p>hi</p></body></html>', '<p>hi</p>'],
        'comment around a paragraph' => ['<!-- a --><p>hi</p><!-- b -->', '<p>hi</p>'],
    ]);

    it('still treats a string that decodes as JSON but is not a document as text', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize('42'))->toBe('42');
    });
});

/**
 * The same mark twice on one node unbalances tiptap-php's mark stack. Two
 * shapes throw an ErrorException, reported on every call (`<code>><code>c` and
 * `<p><strong>a<em><strong>c</strong></em></strong></p>`); simple repeats
 * (`<code><code>c</code></code>`, two `bold` marks) did not throw but printed
 * nested duplicates. Marks of one type are folded into one, which repairs both.
 */
describe('Content: the same mark twice on one node is not a failure', function () {
    it('renders it once, from HTML and from a document, without a report', function (string $input, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        expect(Content::sanitize($input))->toBe($expected)
            ->and(Content::render($input))->toBe($expected);

        Log::shouldNotHaveReceived('warning');
    })->with([
        'nested code' => ['<code><code>c</code></code>', '<code>c</code>'],
        'nested bold' => ['<p><strong><strong>x</strong></strong></p>', '<p><strong>x</strong></p>'],
        'a document with two bold marks' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"bold"},{"type":"bold"}]}]}]}', '<p><strong>x</strong></p>'],
        'a document with two code marks and an italic' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"code"},{"type":"code"},{"type":"italic"}]}]}]}', '<p><code><em>x</em></code></p>'],
    ]);

    it('does not report the shape the fuzzer found', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize('<code>><code>c'))->toContain('c');
    });
});

/**
 * A seeded fuzz over HTML the parser can meet: random tag soup (head-only tags,
 * raw-text elements left unclosed, Unicode whitespace, stray angle brackets,
 * repeated marks) and a real document with pieces cut out and tags pushed in.
 * Whatever comes in, sanitize() and render() may return anything, but neither
 * may throw, report or log a warning. Deterministic (fixed seed, own
 * Randomizer), so a failure names an input that fails every time.
 */
describe('Content: random HTML never reaches report()', function () {
    it('returns from sanitize() and render() without a report or a warning', function () {
        $tags = ['html', 'head', 'body', 'title', 'base', 'link', 'meta', 'script', 'style', 'noscript', 'template', 'frameset', 'svg', 'math',
            'iframe', 'form', 'textarea', 'table', 'tr', 'td', 'p', 'div', 'span', 'a', 'img', 'br', 'hr', 'pre', 'code', 'ul', 'li', 'h1', 'h2',
            'blockquote', 'b', 'i', 'em', 'strong', 'u', 's', 'sub', 'sup', 'mark', 'font', '!-- x --', '![CDATA[x]]', '?xml version="1.0"?', '!doctype html'];
        $attributes = ['', '', '', ' href="javascript:1"', ' style="color:red"', ' onclick="x"', ' class="a b"', ' colspan="x"', ' data-type="mention"', ' src="x"', ' /'];
        $texts = ['x', ' ', "\n", '&amp;', '&nbsp;', '&#0;', '&', '<', '>', '"', '你好', "\0", "\xC3", '{"type":"doc"}', '42', "\u{00A0}", "\u{3000}",
            "\u{2003}", "\u{0085}", "\x0B", "\r\n", "\u{FEFF}", '<<', '</', '<>', '<!', '<!-', '<?', '<a', '<a b="'];
        $corpus = '<h2 style="text-align:center">Hi</h2><p>Some <strong>bold</strong> <em>it</em> <a href="https://x.test/?a=1&b=2" target="_blank">link</a> <span data-type="mention" data-id="1" data-label="Al">@Al</span></p><ul><li><p>a</p></li></ul><pre><code>echo 1;</code></pre><table><tbody><tr><th colspan="2">h</th></tr><tr><td>1</td><td>2</td></tr></tbody></table><img src="https://x.test/a.png" alt="a"><blockquote><p>q</p></blockquote><hr><p><mark data-color="#ff0">m</mark><sub>2</sub><u>u</u><s>s</s><code>c</code></p>';

        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(20260930));
        $pick = fn (array $list) => $list[$random->getInt(0, count($list) - 1)];

        $reports = [];
        $current = '';
        $this->mock(ExceptionHandler::class)->shouldReceive('report')->andReturnUsing(function ($e) use (&$reports, &$current) {
            $reports[$current] = get_class($e).': '.$e->getMessage();
        });
        Log::spy();

        for ($i = 0; $i < 3000; $i++) {
            $html = '';

            if ($i % 2) {
                $chars = mb_str_split($corpus);

                for ($cuts = $random->getInt(1, 10); $cuts > 0; $cuts--) {
                    $at = $random->getInt(0, count($chars) - 1);

                    match ($random->getInt(0, 2)) {
                        0 => array_splice($chars, $at, $random->getInt(1, 40)),
                        1 => array_splice($chars, $at, 0, [$pick($texts)]),
                        default => array_splice($chars, $at, 0, ['<'.$pick($tags).'>']),
                    };
                }

                $html = implode('', $chars);
            } else {
                for ($part = $random->getInt(1, 9); $part > 0; $part--) {
                    $html .= match ($random->getInt(0, 3)) {
                        0 => '<'.$pick($tags).$pick($attributes).'>',
                        1 => '</'.$pick($tags).'>',
                        2 => '<'.$pick($tags).$pick($attributes).'/>',
                        default => $pick($texts),
                    };
                }
            }

            $current = $html;
            Content::sanitize($html);
            Content::render($html);
        }

        expect($reports)->toBe([]);
        Log::shouldNotHaveReceived('warning');
    });
});

describe('Content: the marks that really threw', function () {
    it('does not report the two shapes that threw, and prints balanced HTML', function (string $html, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($html))->toBe($expected)
            ->and(Content::render($html))->toBe($expected);
    })->with([
        'code, text, code' => ['<code>><code>c', '<code>&gt;</code><code>c</code>'],
        'strong around em around strong' => ['<p><strong>a<em><strong>c</strong></em></strong></p>', '<p><strong>a</strong><strong><em>c</em></strong></p>'],
    ]);
});

/**
 * Folding marks must not lose what the marks said. Two textStyle marks (one
 * per nested span, or a colour and a size in a document) are one style, and a
 * link keeps a valid href a later invalid one would otherwise blank.
 */
function marksDocument(array $marks): string
{
    return json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 't', 'marks' => $marks]]]]]);
}

describe('Content: folded marks keep their attributes', function () {
    it('keeps the colour of an outer span when an inner span adds nothing', function (string $html) {
        expect(Content::sanitize($html))->toContain('color: red')
            ->and(Content::render($html))->toContain('color: red');
    })->with([
        'colour outside, size inside' => ['<span style="color:red"><span style="font-size:20px">t'],
        'size outside, colour inside' => ['<span style="font-size:20px"><span style="color:red">t'],
    ]);

    it('merges the attributes of two textStyle marks', function () {
        $json = marksDocument([['type' => 'textStyle', 'attrs' => ['color' => 'red']], ['type' => 'textStyle', 'attrs' => ['fontSize' => '20px']]]);

        expect(Content::sanitize($json))->toContain('color: red')->toContain('font-size: 20px')
            ->and(Content::render($json))->toContain('color: red')->toContain('font-size: 20px');
    });

    it('lets a later value win only for the same attribute', function () {
        $json = marksDocument([['type' => 'textStyle', 'attrs' => ['color' => 'red', 'fontSize' => '20px']], ['type' => 'textStyle', 'attrs' => ['color' => 'blue']]]);

        expect(Content::sanitize($json))->toContain('color: blue')->toContain('font-size: 20px')->not->toContain('red');
    });

    it('never lets a later null blank an earlier value', function () {
        $json = marksDocument([['type' => 'textStyle', 'attrs' => ['color' => 'red']], ['type' => 'textStyle', 'attrs' => ['color' => null]]]);

        expect(Content::sanitize($json))->toContain('color: red');
    });

    it('lets a later highlight colour win', function () {
        $json = marksDocument([['type' => 'highlight', 'attrs' => ['color' => 'red']], ['type' => 'highlight', 'attrs' => ['color' => 'blue']]]);

        expect(Content::sanitize($json))->toContain('background-color: blue')->not->toContain('red');
    });

    it('keeps the first valid href of a link', function (array $hrefs, string $expected) {
        $marks = array_map(fn ($href) => ['type' => 'link', 'attrs' => $href === null ? [] : ['href' => $href]], $hrefs);
        $json = marksDocument($marks);

        $html = Content::sanitize($json);

        expect($html)->toContain('href="'.$expected.'"')
            ->and(substr_count($html, '<a '))->toBe(1)
            ->and(Content::render($json))->toBe($html);
    })->with([
        'valid, then valid' => [['https://a.test/', 'https://b.test/'], 'https://a.test/'],
        'valid, then invalid' => [['https://a.test/', 'javascript:alert(1)'], 'https://a.test/'],
        'invalid, then valid' => [['javascript:alert(1)', 'https://b.test/'], 'https://b.test/'],
        'valid, then none' => [['https://a.test/', null], 'https://a.test/'],
        'none, then valid' => [[null, 'https://b.test/'], 'https://b.test/'],
    ]);

    it('drops the href when no link mark has a valid one', function () {
        $html = Content::sanitize(marksDocument([['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']], ['type' => 'link', 'attrs' => ['href' => 'vbscript:x']]]));

        expect($html)->not->toContain('javascript')->not->toContain('vbscript');
    });
});
