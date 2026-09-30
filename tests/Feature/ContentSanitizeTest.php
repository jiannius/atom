<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jiannius\Atom\Tiptap\Content;
use Jiannius\Atom\Tiptap\Extensions\AtomImage;

/**
 * Content::sanitize(): editor / chat HTML from the browser is untrusted. It has
 * to come back holding only what the schema can serialise.
 */

/**
 * Everything the schema can emit, and no more. Returns one line per violation,
 * so an empty array means the markup is schema-only: an allow-list of tags, of
 * attributes per tag, of style properties, of class names, and of href / src
 * shapes. Checking the output structurally (rather than grepping for known bad
 * strings) is what makes an unknown bypass show up.
 *
 * @return array<int, string>
 */
function sanitisedViolations(string $html): array
{
    $tags = [
        'p', 'br', 'hr', 'strong', 'em', 's', 'u', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'span', 'mark', 'sub', 'sup',
        'table', 'tbody', 'thead', 'tr', 'th', 'td', 'img', 'div', 'iframe',
    ];

    $attributes = [
        'a' => ['href', 'target', 'rel'],
        'span' => ['class', 'style', 'data-type', 'data-id', 'data-label', 'data-font-size'],
        'mark' => ['style', 'data-color'],
        'p' => ['style'],
        'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
        'code' => ['class'],
        'ol' => ['start'],
        'th' => ['colspan', 'rowspan', 'colwidth'],
        'td' => ['colspan', 'rowspan', 'colwidth'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'style', 'data-float', 'data-align', 'data-width'],
        'div' => ['data-youtube-video'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen'],
    ];

    $styleProperties = ['color', 'font-size', 'text-align', 'background-color', 'float', 'width', 'margin-left', 'margin-right'];

    $violations = [];
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

    foreach ($dom->getElementsByTagName('*') as $el) {
        $tag = $el->nodeName;

        if ($tag === 'html' || $tag === 'body') {
            continue;
        }

        if (! in_array($tag, $tags, true)) {
            $violations[] = "tag <{$tag}>";

            continue;
        }

        foreach ($el->attributes as $attr) {
            $name = $attr->nodeName;
            $value = $attr->nodeValue;

            if (! in_array($name, $attributes[$tag] ?? [], true)) {
                $violations[] = "<{$tag} {$name}>";

                continue;
            }

            if ($name === 'href' && ! preg_match('#^(https?:|mailto:|tel:)#i', $value)) {
                $violations[] = "href {$value}";
            }

            if ($name === 'src' && $tag === 'img' && ! AtomImage::isSafeSource($value)) {
                $violations[] = "img src {$value}";
            }

            if ($name === 'src' && $tag === 'iframe' && ! preg_match('#^https://www\.youtube(-nocookie)?\.com/embed/[\w-]{11}(\?start=\d+)?$#', $value)) {
                $violations[] = "iframe src {$value}";
            }

            // the class values each tag may carry: a mention, a font-size preset, a code language
            $classPattern = ['code' => '/^language-[\w+#.-]+$/', 'span' => '/^(mention|text-(xs|sm|base|lg|xl))$/'][$tag] ?? '/^(?!)/';

            if ($name === 'class' && ! preg_match($classPattern, $value)) {
                $violations[] = "<{$tag} class={$value}>";
            }

            if ($name === 'data-font-size' && ! preg_match('/^(xs|sm|md|lg|xl|\d{1,3}(\.\d+)?(px|em|rem))$/i', $value)) {
                $violations[] = "data-font-size {$value}";
            }

            if ($name === 'style') {
                foreach (array_filter(array_map('trim', explode(';', $value))) as $declaration) {
                    $property = trim(explode(':', $declaration)[0]);

                    if (! in_array($property, $styleProperties, true) || preg_match('/url\(|expression|@import|javascript/i', $declaration)) {
                        $violations[] = "style {$declaration}";
                    }
                }
            }
        }

        if ($tag === 'iframe' && $el->parentNode?->nodeName !== 'div') {
            $violations[] = 'iframe outside the youtube wrapper';
        }
    }

    return $violations;
}

function sanitiseDoc(array $content): string
{
    return Content::sanitize(json_encode(['type' => 'doc', 'content' => $content]));
}

function sanitiseText(array $marks, string $text = 'x'): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => $marks, 'text' => $text]]];
}

/**
 * Hostile HTML, as a client calling $wire.submit() could send it.
 *
 * @return array<string, array{0: string}>
 */
dataset('hostile html', [
    'script tag' => ['<p>hi</p><script>alert(1)</script>'],
    'script in a paragraph' => ['<p>a<script>alert(document.cookie)</script>b</p>'],
    'img onerror' => ['<p>a</p><img src=x onerror=alert(1)>'],
    'onclick / onmouseover' => ['<p onclick="x()">a <strong onmouseover="y()">b</strong></p>'],
    'javascript: link' => ['<p><a href="javascript:alert(1)">x</a></p>'],
    'javascript: link, mixed case and whitespace' => ['<p><a href=" jAvA&#x09;&#x0A;script:alert(1)">x</a></p>'],
    'vbscript: link' => ['<p><a href="vbscript:msgbox(1)">x</a></p>'],
    'data: link' => ['<p><a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a></p>'],
    'attribute breakout in href' => ['<p><a href="https://a.com&quot; onclick=&quot;alert(1)">x</a></p>'],
    'link class and target' => ['<p><a href="https://a.com" class="fixed inset-0" target="_top" rel="opener">x</a></p>'],
    'style injection on a paragraph' => ['<p style="position:fixed;top:0;left:0;width:100%;height:100%">x</p>'],
    'style injection on a span' => ['<p><span style="color:red;position:fixed;background:url(javascript:alert(1))">x</span></p>'],
    'css expression colour' => ['<p><span style="color: expression(alert(1))">x</span></p>'],
    'font size injection' => ['<p><span style="font-size:99999px;position:fixed">x</span></p>'],
    'highlight injection' => ['<p><mark data-color="red;position:fixed" style="background-color:url(x);position:fixed">x</mark></p>'],
    'text-align injection' => ['<p style="text-align:center;position:fixed">x</p><p style="text-align:evil">y</p>'],
    'class injection' => ['<p class="fixed inset-0 z-50 bg-white">x</p><h2 class="text-4xl">y</h2>'],
    'code language class' => ['<pre><code class="language-php fixed inset-0 z-50">x</code></pre>'],
    'table style / class' => ['<table style="position:fixed" class="x" onclick="1"><tbody><tr><td style="background:red" onclick="1">c</td></tr></tbody></table>'],
    'image style and handlers' => ['<img src="/ok.png" style="position:fixed;width:100vw" onerror="alert(1)" onload="alert(2)" class="fixed">'],
    'image javascript: src' => ['<img src="javascript:alert(1)">'],
    'image data:svg src' => ['<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+">'],
    'hostile iframe' => ['<iframe src="https://evil.example/phish"></iframe>'],
    'iframe javascript:' => ['<iframe src="javascript:alert(1)"></iframe><iframe srcdoc="<script>alert(1)</script>"></iframe>'],
    'youtube wrapper with another origin' => ['<div data-youtube-video><iframe src="https://evil.example/x"></iframe></div>'],
    'youtube wrapper with javascript:' => ['<div data-youtube-video><iframe src="javascript:alert(1)"></iframe></div>'],
    'youtube lookalike host' => ['<div data-youtube-video><iframe src="https://youtube.com.evil.example/embed/dQw4w9WgXcQ"></iframe></div>'],
    'svg onload' => ['<svg onload=alert(1)><a xlink:href="javascript:alert(1)">z</a></svg>'],
    'math / object / embed' => ['<math><mi xlink:href="javascript:1">x</mi></math><object data="x"></object><embed src="x">'],
    'form and inputs' => ['<form action="https://evil.example"><input name=x><button>go</button></form>'],
    'style / link / meta / base' => ['<style>body{display:none}</style><link rel="stylesheet" href="x"><meta http-equiv="refresh" content="0"><base href="//evil.example">'],
    'unknown and custom elements' => ['<foo bar="1"><custom-el onclick="x">hi</custom-el></foo>'],
    'nested and mismatched tags' => ['<p><b><i>x</p></b></i><<script>alert(1)//<</script>>'],
    'unclosed tags' => [str_repeat('<p><strong><a href="javascript:1">', 200).'x'],
    'comments and conditional comments' => ['<p>a<!--[if IE]><script>alert(1)</script><![endif]--></p><![CDATA[<script>alert(1)</script>]]>'],
    'mention attributes' => ['<p><span data-type="mention" data-id=\'"><script>alert(1)</script>\' data-label=\'<img src=x onerror=alert(1)>\' onclick="x" style="position:fixed">@z</span></p>'],
    'text entities that decode to markup' => ['<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
    'bare text with markup characters' => ['1 < 2 <script>alert(1)</script> & 3 > 2'],
    'NUL bytes' => ["<p>a\0<scr\0ipt>alert(1)</scr\0ipt></p>"],
    'invalid UTF-8' => ["<p>\xff\xfe<img src=x onerror=alert(1)></p>"],
    'deeply nested blocks' => [str_repeat('<blockquote>', 2000).'x'.str_repeat('</blockquote>', 2000)],
    'deeply nested divs' => [str_repeat('<div>', 20000).'<script>alert(1)</script>'.str_repeat('</div>', 20000)],
    'deeply nested lists' => [str_repeat('<ul><li>', 2000).'<img src=x onerror=alert(1)>'],
    'huge attribute value' => ['<p><a href="https://a.com/'.str_repeat('a', 200000).'" onclick="x">x</a></p>'],
]);

describe('Content::sanitize: hostile input', function () {
    it('comes back schema-only', function (string $html) {
        $clean = Content::sanitize($html);

        expect(sanitisedViolations($clean))->toBe([]);
        expect($clean)->not->toMatch('/<script|<style|<svg|<form|<object|<embed|<iframe|javascript:|vbscript:|data:text/i');
    })->with('hostile html');

    it('stays schema-only when the result is sanitised again', function (string $html) {
        $again = Content::sanitize(Content::sanitize($html));

        expect(sanitisedViolations($again))->toBe([]);
    })->with('hostile html');

    it('keeps text that looks like markup as escaped text', function () {
        $clean = Content::sanitize('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');

        expect($clean)->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');
    });

    it('drops the dangerous part and keeps the harmless text around it', function () {
        expect(Content::sanitize('<p onclick="x()">a <strong onmouseover="y()">b</strong></p><script>alert(1)</script>'))
            ->toBe('<p>a <strong>b</strong></p>');
        expect(Content::sanitize('<p><a href="javascript:alert(1)">click</a></p>'))->toBe('<p>click</p>');
        expect(Content::sanitize('<p><a href="data:text/html;base64,AAAA">click</a></p>'))->toBe('<p>click</p>');
    });

    it('drops an iframe in HTML, YouTube ones included', function () {
        expect(Content::sanitize('<iframe src="https://evil.example/phish"></iframe>'))->toBe('');
        expect(Content::sanitize('<div data-youtube-video><iframe src="https://evil.example/x"></iframe></div>'))->toBe('');
        expect(Content::sanitize('<div data-youtube-video><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe></div>'))->toBe('');
    });

    it('keeps a YouTube embed from a JSON document, rebuilt from the video id', function () {
        $clean = sanitiseDoc([['type' => 'youtube', 'attrs' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&x="onload=alert(1)']]]);

        expect($clean)->toContain('youtube.com/embed/dQw4w9WgXcQ')->not->toContain('onload');
        expect(sanitisedViolations($clean))->toBe([]);
    });

    it('drops an image source that is not http(s), relative or a raster data: URI', function () {
        expect(Content::sanitize('<img src="javascript:alert(1)">'))->not->toContain('javascript');
        expect(Content::sanitize('<img src=" JaVa&#x09;Script:alert(1)">'))->not->toContain('cript:');
        expect(Content::sanitize('<img src="data:image/svg+xml;base64,AAAA">'))->not->toContain('svg');
        expect(Content::sanitize('<img src="/storage/a.png">'))->toContain('src="/storage/a.png"');
        expect(Content::sanitize('<img src="https://cdn.example/a.png">'))->toContain('src="https://cdn.example/a.png"');
        expect(Content::sanitize('<img src="data:image/png;base64,AAAA">'))->toContain('src="data:image/png;base64,AAAA"');
    });

    it('keeps the text around NUL bytes and invalid UTF-8', function () {
        expect(Content::sanitize("<p>a\0b</p>"))->toBe('<p>ab</p>');
        expect(Content::sanitize("<p>ok \xff\xfe fine</p>"))->toContain('ok')->toContain('fine');
        expect(Content::sanitize("<p>\xff<img src=x onerror=alert(1)></p>"))->not->toContain('onerror');
    });

    it('escapes a mention label and id', function () {
        $clean = Content::sanitize('<p><span data-type="mention" data-id=\'"><b>\' data-label=\'<img src=x onerror=alert(1)>\'>@z</span></p>');

        expect($clean)->not->toContain('<img')->not->toContain('<b>');
    });
});

describe('Content::sanitize: hostile JSON documents', function () {
    it('drops style, class and script values that fail the allow-lists', function () {
        $clean = sanitiseDoc([
            ['type' => 'image', 'attrs' => ['src' => '/a.png', 'float' => 'left;position:fixed', 'width' => '100vw;position:fixed', 'align' => 'x;position:fixed']],
            sanitiseText([['type' => 'textStyle', 'attrs' => ['fontSize' => '99px;position:fixed', 'color' => 'red;position:fixed']]]),
            sanitiseText([['type' => 'highlight', 'attrs' => ['color' => 'red;position:fixed']]]),
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center;position:fixed'], 'content' => [['type' => 'text', 'text' => 'x']]],
            sanitiseText([['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)', 'class' => 'fixed inset-0', 'target' => '_top', 'rel' => 'opener']]]),
            ['type' => 'codeBlock', 'attrs' => ['language' => 'php fixed inset-0'], 'content' => [['type' => 'text', 'text' => 'x']]],
            ['type' => 'youtube', 'attrs' => ['src' => 'javascript:alert(1)']],
            ['type' => 'youtube', 'attrs' => ['src' => 'https://evil.example/x']],
            ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
        ]);

        expect(sanitisedViolations($clean))->toBe([]);
        expect($clean)->not->toContain('fixed')->not->toContain('javascript')->not->toContain('evil.example')->not->toContain('<iframe');
    });

    it('accepts a document passed as an array or a JSON string', function () {
        $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hi']]]]];

        expect(Content::sanitize($doc))->toBe('<p>hi</p>');
        expect(Content::sanitize(json_encode($doc)))->toBe('<p>hi</p>');
    });

    it('drops non-scalar attributes without throwing', function () {
        $clean = sanitiseDoc([['type' => 'image', 'attrs' => ['src' => ['x'], 'alt' => ['y']]]]);

        expect(sanitisedViolations($clean))->toBe([]);
    });
});

/**
 * What the chat composer's own editor (the JS engine) produced for each of these,
 * captured from getHTML() in the browser, so the fixtures are the real output and
 * not a guess at it.
 *
 * @return array<string, array{0: string}>
 */
dataset('chat html', [
    'plain paragraph' => ['<p>Hello there, can you check the invoice?</p>'],
    'marks' => ['<p>Hello <strong>bold</strong> <em>italic</em> <s>strike</s> <u>under</u> <code>code</code> x<sub>2</sub> y<sup>3</sup></p>'],
    'links' => ['<p>see <a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/a?b=1&amp;c=2">this link</a> and <a target="_blank" rel="noopener noreferrer nofollow" href="mailto:a@b.com">mail</a></p>'],
    'lists' => ['<ul><li><p>one</p></li><li><p>two</p><ul><li><p>nested</p></li></ul></li></ul><ol><li><p>a</p></li><li><p>b</p></li></ol><p></p>'],
    'blocks' => ['<h2>Heading</h2><blockquote><p>quoted</p></blockquote><pre><code class="language-php">echo 1 &lt; 2;</code></pre><hr><p>line<br>break</p>'],
    'characters that need escaping, CJK and emoji' => ['<p>1 &lt; 2 &amp;&amp; 3 &gt; 2 &lt;script&gt;alert(1)&lt;/script&gt; &quot;q&quot; 中文 😀</p>'],
    'text align and colour' => ['<p style="text-align: center;"><span style="color: rgb(255, 0, 0);">red</span> <mark data-color="#ffff00" style="background-color: #ffff00;">hl</mark></p>'],
    'image' => ['<p></p><img src="/storage/a.png" alt="a"><p></p>'],
    'mentions' => ['<p>hi <span class="mention" data-type="mention" data-id="7" data-label="Alice">@Alice</span> and <span class="mention" data-type="mention" data-id="0" data-label="Zero &amp; &lt;b&gt;">@Zero &amp; &lt;b&gt;</span></p>'],
    'empty paragraphs' => ['<p></p><p>after</p>'],
]);

describe('Content::sanitize: legitimate chat output', function () {
    it('round-trips unchanged', function (string $html) {
        expect(Content::sanitize($html))->toBe($html);
    })->with('chat html');

    it('is schema-only', function (string $html) {
        expect(sanitisedViolations(Content::sanitize($html)))->toBe([]);
    })->with('chat html');

    it('gives the same result as render()', function (string $html) {
        expect(Content::sanitize($html))->toBe(Content::render($html));
    })->with('chat html');

    it('drops the mention popup marker the JS engine adds and keeps the mention', function () {
        $js = '<p><span class="mention" data-type="mention" data-id="7" data-label="Alice" data-mention-suggestion-char="@">@Alice</span></p>';

        expect(Content::sanitize($js))
            ->toBe('<p><span class="mention" data-type="mention" data-id="7" data-label="Alice">@Alice</span></p>');
    });

    it('keeps a mention id of "0"', function () {
        expect(Content::sanitize('<p><span data-type="mention" data-id="0" data-label="Zero">@Zero</span></p>'))
            ->toContain('data-id="0"');
    });

    it('keeps the client-supplied mention id as sent, which the host has to check', function () {
        expect(Content::sanitize('<p><span data-type="mention" data-id="99999" data-label="Somebody else">@x</span></p>'))
            ->toContain('data-id="99999"');
    });
});

describe('Content::sanitize: input that is not HTML', function () {
    it('returns an empty string for input that is not a string or a document', function (mixed $input) {
        expect(Content::sanitize($input))->toBe('');
    })->with([
        'null' => [null],
        'int' => [123],
        'float' => [1.5],
        'true' => [true],
        'false' => [false],
        'object' => [new stdClass],
        'closure' => [fn () => 1],
        'empty array' => [[]],
        'array that is not a document' => [['a' => 1]],
        'list' => [[1, 2, 3]],
        'empty string' => [''],
        'whitespace' => ["  \n\t "],
    ]);

    it('treats JSON that is not a document as text, and does not report it', function (string $input, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(Content::sanitize($input))->toBe($expected);
    })->with([
        'a number' => ['42', '42'],
        'true' => ['true', 'true'],
        'null' => ['null', 'null'],
        'a list' => ['[1,2,3]', '[1,2,3]'],
        'an object with no type' => ['{"foo":"bar"}', '{&quot;foo&quot;:&quot;bar&quot;}'],
    ]);

    it('casts a Stringable to a string', function () {
        expect(Content::sanitize(Str::of('<p onclick="x()">hi</p><script>alert(1)</script>')))->toBe('<p>hi</p>');
    });

    it('returns a string for corrupt input, without reporting the ones it can read as text', function (mixed $input) {
        expect(Content::sanitize($input))->toBeString();
    })->with([
        'truncated JSON' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","te'],
        'document with an unknown node' => ['{"type":"doc","content":[{"type":"nope","attrs":{"a":[1]}}]}'],
        'nested array of junk' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [1, 'x', null]]]]],
        'lone angle bracket' => ['<'],
        'lone entity' => ['&#'],
        'binary' => [random_bytes(2048)],
        'invalid UTF-8' => ["\xc3\x28\xa0\xa1\xe2\x28\xa1"],
        'NUL bytes' => [str_repeat("\0", 100)],
        'unclosed pre tags' => [str_repeat('<pre>', 2000).'x'],
        'JSON nested past the depth limit' => [str_repeat('[', 600).str_repeat(']', 600)],
    ]);

    it('reports an unexpected failure and returns an empty string', function () {
        $this->mock(ExceptionHandler::class)->shouldReceive('report')->atLeast()->once();
        $broken = new class implements Stringable
        {
            public function __toString(): string
            {
                throw new RuntimeException('boom');
            }
        };

        expect(Content::sanitize($broken))->toBe('');
    });
});

/**
 * Documents only a hostile client can send. tiptap-php throws on every one, so
 * before they were validated each one called report() on every render.
 *
 * @return array<string, array{0: string}>
 */
dataset('malformed documents', [
    'doc with no content' => ['{"type":"doc"}'],
    'root that is not a doc' => ['{"type":"paragraph"}'],
    'type that is a number' => ['{"type":5}'],
    'content that is a string' => ['{"type":"doc","content":"x"}'],
    'content that is an object' => ['{"type":"doc","content":{"a":1}}'],
    'child that is a string' => ['{"type":"doc","content":["x"]}'],
    'node with no type' => ['{"type":"doc","content":[{"content":[]}]}'],
    'type that is an array' => ['{"type":"doc","content":[{"type":["paragraph"]}]}'],
    'text that is an array' => ['{"type":"doc","content":[{"type":"text","text":["x"]}]}'],
    'text that is a number' => ['{"type":"doc","content":[{"type":"text","text":5}]}'],
    'marks that is a string' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":"bold"}]}]}'],
    'marks that is an object' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":{"type":"bold"}}]}]}'],
    'mark whose type is an array' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":["bold"]}]}]}]}'],
]);

describe('Content: malformed documents are a silent refusal', function () {
    it('renders empty from render() and sanitize(), without a report or a log line', function (string $json) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        expect(Content::render($json))->toBe('');
        expect(Content::sanitize($json))->toBe('');
        expect(Content::sanitizeRefuses($json))->toBeTrue();
        Log::shouldNotHaveReceived('warning');
    })->with('malformed documents');

    it('renders a colwidth that is a list of numbers, and a heading with a level', function () {
        $html = Content::render('{"type":"doc","content":[{"type":"heading","attrs":{"level":3},"content":[{"type":"text","text":"h"}]},{"type":"table","content":[{"type":"tableRow","content":[{"type":"tableCell","attrs":{"colwidth":[120,null]},"content":[{"type":"paragraph"}]}]}]}]}');

        expect($html)->toContain('<h3>h</h3>')->toContain('<table>');
    });
});

/**
 * Attribute shapes tiptap-php throws on. The rest of such a document is real
 * content, so they are repaired, not refused: the document still renders.
 *
 * @return array<string, array{0: string, 1: string}> [document, what still renders]
 */
dataset('repairable documents', [
    'heading level as an object' => ['{"type":"doc","content":[{"type":"heading","attrs":{"level":{"a":1}},"content":[{"type":"text","text":"h"}]}]}', '<h1>h</h1>'],
    'heading level as null' => ['{"type":"doc","content":[{"type":"heading","attrs":{"level":null},"content":[{"type":"text","text":"h"}]}]}', '<h1>h</h1>'],
    'heading with no attrs' => ['{"type":"doc","content":[{"type":"heading","content":[{"type":"text","text":"h"}]}]}', '<h1>h</h1>'],
    'heading with no level' => ['{"type":"doc","content":[{"type":"heading","attrs":{"textAlign":"left"},"content":[{"type":"text","text":"h"}]}]}', '<h1>h</h1>'],
    'mention label as an array' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"before "},{"type":"mention","attrs":{"id":"1","label":["a"]}}]}]}', 'before '],
    'mention id as an array, no label' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"mention","attrs":{"id":["x"]}}]}]}', '<p>'],
    'mention id as an object, no label' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"mention","attrs":{"id":{"a":1}}}]}]}', '<p>'],
    'table colwidth as a string' => ['{"type":"doc","content":[{"type":"table","content":[{"type":"tableRow","content":[{"type":"tableCell","attrs":{"colwidth":"x"},"content":[{"type":"paragraph","content":[{"type":"text","text":"cell"}]}]}]}]}]}', 'cell'],
    'table colwidth as an object' => ['{"type":"doc","content":[{"type":"table","content":[{"type":"tableRow","content":[{"type":"tableCell","attrs":{"colwidth":{"a":1}},"content":[{"type":"paragraph","content":[{"type":"text","text":"cell"}]}]}]}]}]}', 'cell'],
    'attrs that is a string' => ['{"type":"doc","content":[{"type":"paragraph","attrs":"x","content":[{"type":"text","text":"kept"}]}]}', '<p>kept</p>'],
    'attrs that is a list' => ['{"type":"doc","content":[{"type":"paragraph","attrs":[1,2],"content":[{"type":"text","text":"kept"}]}]}', '<p>kept</p>'],
]);

describe('Content: documents with a bad attribute are repaired, not blanked', function () {
    it('still renders the content, from render() and sanitize(), without a report or a log line', function (string $json, string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        expect(Content::render($json))->toContain($expected);
        expect(Content::sanitize($json))->toContain($expected);
        expect(Content::sanitizeRefuses($json))->toBeFalse();
        Log::shouldNotHaveReceived('warning');
    })->with('repairable documents');
});

/**
 * tiptap-php's Minify swaps every <pre> for %MINIFYHTML<md5(REQUEST_TIME)>N% and
 * str_replace()s them back, in order. The hash is guessable, so a placeholder
 * written inside a <pre> is expanded again, once per level of nesting.
 * depth 5, 10 per level: ~1.8 KB in, ~300 KB out, ~27 MB and 0.4 s unguarded;
 * depth 8 exhausts a 512 MB limit.
 */
function placeholderBomb(int $depth = 5, int $perLevel = 10, ?string $hash = null): string
{
    $hash ??= md5((string) $_SERVER['REQUEST_TIME']);
    $html = '';

    for ($i = 0; $i < $depth; $i++) {
        $html .= '<pre>'.($i < $depth - 1 ? str_repeat('%MINIFYHTML'.$hash.($i + 1).'%', $perLevel) : 'a').'</pre>';
    }

    return $html;
}

/**
 * Run a callback and report how long it took and how much memory its peak added.
 *
 * @return array{0: mixed, 1: float, 2: float} [result, seconds, MB]
 */
function measured(callable $callback): array
{
    gc_collect_cycles();
    memory_reset_peak_usage();
    $base = memory_get_usage();
    $started = microtime(true);

    $result = $callback();

    return [$result, microtime(true) - $started, (memory_get_peak_usage() - $base) / 1048576];
}

/**
 * Run a callback and report the CPU time it used (user + system) rather than the
 * wall clock. A busy machine (the whole suite, another run alongside) stretches wall
 * time without the code doing any more work, which made a bound on it flaky; CPU
 * time moves far less, so a bound on it still trips on a genuine slowdown.
 *
 * @return array{0: mixed, 1: float} [result, CPU seconds]
 */
function measuredCpu(callable $callback): array
{
    $cpu = function (): float {
        $usage = getrusage();

        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6 + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
    };

    $before = $cpu();
    $result = $callback();

    return [$result, $cpu() - $before];
}

describe('Content: the <pre> placeholder bomb', function () {
    it('is refused by sanitize() without being parsed', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        [$clean, $seconds, $mb] = measured(fn () => Content::sanitize($html));

        expect($clean)->toBe('');
        expect($mb)->toBeLessThan(5.0);
        expect($seconds)->toBeLessThan(0.5);
        expect(Content::sanitizeRefuses($html))->toBeTrue();
    })->with([
        'nested, ten per level' => [placeholderBomb()],
        'nested, deeper' => [placeholderBomb(depth: 6)],
        'one pre, then the placeholder repeated' => ['<pre>'.str_repeat('a', 1000).'</pre>'.str_repeat('%MINIFYHTML'.'x'.'0%', 500)],
        'lower case' => [strtolower(placeholderBomb())],
        'mixed case' => [str_replace('MINIFYHTML', 'MiniFyHtml', placeholderBomb())],
        'the bare token in a paragraph' => ['<p>MINIFYHTML</p>'],
    ]);

    it('is refused by render() without being parsed, and does not report', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        [$html, $seconds, $mb] = measured(fn () => Content::render(placeholderBomb()));

        expect($html)->toBe('');
        expect($mb)->toBeLessThan(5.0);
        expect($seconds)->toBeLessThan(0.5);
    });

    it('is refused by <atom:tiptap.content>, as a prop and as a slot', function () {
        [$prop, $seconds, $mb] = measured(fn () => renderBlade('<atom:tiptap.content :content="$html" />', ['html' => placeholderBomb()]));
        [$slot] = measured(fn () => renderBlade('<atom:tiptap.content>'.placeholderBomb().'</atom:tiptap.content>'));

        expect($prop)->not->toContain('<pre')->not->toContain('MINIFYHTML');
        expect($slot)->not->toContain('<pre')->not->toContain('MINIFYHTML');
        expect($mb)->toBeLessThan(10.0);
        expect($seconds)->toBeLessThan(1.0);
    });

    it('cannot be planted in a document, whose text never reaches the minifier', function () {
        $token = '%MINIFYHTML'.md5((string) $_SERVER['REQUEST_TIME']).'0%';
        $doc = json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat($token, 20)]]],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => str_repeat($token, 20)]]],
        ]]);

        [$rendered, $seconds, $mb] = measured(fn () => Content::render($doc));
        [$sanitised] = measured(fn () => Content::sanitize($doc));

        expect($rendered)->toContain($token)->and(strlen($rendered))->toBeLessThan(5000);
        expect($sanitised)->toBe($rendered);
        expect($mb)->toBeLessThan(5.0);
        expect($seconds)->toBeLessThan(0.5);
    });

    it('is refused by the tiptap-migrate command guard', function () {
        expect(Content::carriesPlaceholder(placeholderBomb()))->toBeTrue();
        expect(Content::carriesPlaceholder('<pre>a</pre><p>b</p>'))->toBeFalse();
    });
});

describe('Content::sanitize: limits', function () {
    it('refuses input over the byte limit, silently', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $big = '<p>'.str_repeat('a', Content::SANITIZE_MAX_BYTES).'</p>';

        expect(Content::sanitize($big))->toBe('');
        expect(Content::sanitizeRefuses($big))->toBeTrue();
    });

    it('accepts input at the byte limit and refuses one byte over', function () {
        $html = '<p>'.str_repeat('a', 997).'</p>';

        expect(Content::sanitize($html, maxBytes: strlen($html)))->toBe($html);
        expect(Content::sanitize($html, maxBytes: strlen($html) - 1))->toBe('');
        expect(Content::sanitizeRefuses($html, maxBytes: strlen($html)))->toBeFalse();
        expect(Content::sanitizeRefuses($html, maxBytes: strlen($html) - 1))->toBeTrue();
    });

    it('refuses input over the tag limit, silently, and accepts it at the limit', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $atLimit = str_repeat('<p>a', Content::SANITIZE_MAX_TAGS);
        $over = $atLimit.'<p>a';

        expect(Content::sanitize($atLimit))->not->toBe('');
        expect(Content::sanitizeRefuses($atLimit))->toBeFalse();
        expect(Content::sanitize($over))->toBe('');
        expect(Content::sanitizeRefuses($over))->toBeTrue();
        expect(Content::sanitize($over, maxTags: Content::SANITIZE_MAX_TAGS + 1))->not->toBe('');
    });

    it('says nothing is refused for input that is only empty or not HTML', function (mixed $input) {
        expect(Content::sanitizeRefuses($input))->toBeFalse();
    })->with([[null], [''], ['  '], [123], [[]], ['<p>hi</p>']]);

    it('refuses the input that cost 212 MB and 2.7 s before the tag limit, at once', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $html = str_repeat('<p>a', 65536);

        [$clean, $seconds, $mb] = measured(fn () => Content::sanitize($html));

        expect($clean)->toBe('');
        expect($seconds)->toBeLessThan(0.5);
        expect($mb)->toBeLessThan(5.0);
    });

    /**
     * The costliest shapes per tag, each built to the tag limit. Measured at the
     * limit: 16 MB and 0.2 s for the worst; the bounds here leave room for a
     * slower machine, but not for the 212 MB an unbounded input cost.
     */
    it('parses the worst-case shapes at the limit within a memory and time bound', function (string $html) {
        $tags = substr_count($html, '<');

        [$clean, $seconds, $mb] = measured(fn () => Content::sanitize($html));

        expect($tags)->toBeLessThanOrEqual(Content::SANITIZE_MAX_TAGS);
        expect(strlen($html))->toBeLessThanOrEqual(Content::SANITIZE_MAX_BYTES);
        expect($clean)->not->toBe('');
        expect(sanitisedViolations($clean))->toBe([]);
        expect($mb)->toBeLessThan(40.0);
        expect($seconds)->toBeLessThan(5.0);
    })->with([
        'unclosed paragraphs' => [str_repeat('<p>a', Content::SANITIZE_MAX_TAGS)],
        'paragraph, bold, text' => [str_repeat('<p><b>a</b>x', intdiv(Content::SANITIZE_MAX_TAGS, 3))],
        'paragraph with a link' => [str_repeat('<p><a href="https://example.com/x">a</a>', intdiv(Content::SANITIZE_MAX_TAGS, 3))],
        'unclosed table cells' => ['<table><tbody>'.str_repeat('<tr><td>a<td>b', intdiv(Content::SANITIZE_MAX_TAGS - 2, 3))],
        'pre blocks' => [str_repeat('<pre>a</pre>', intdiv(Content::SANITIZE_MAX_TAGS, 2))],
        'list items' => ['<ul>'.str_repeat('<li>a', Content::SANITIZE_MAX_TAGS - 1)],
        'line breaks' => ['<p>'.str_repeat('a<br>', Content::SANITIZE_MAX_TAGS - 1)],
        'images' => [str_repeat('<img src="/a.png">', Content::SANITIZE_MAX_TAGS)],
    ]);

    it('refuses output more than four times the byte limit', function () {
        $html = str_repeat('<a href=x>a</a>b', 50);
        $out = Content::sanitize($html, maxBytes: 100000);

        expect(strlen($out))->toBeGreaterThan(strlen($html) * 4);
        expect(Content::sanitize($html, maxBytes: strlen($html)))->toBe('');
        expect(Content::sanitize($html, maxBytes: intdiv(strlen($out), 4) + 1))->toBe($out);
    });

    it('lets a long, realistic chat message through unchanged', function () {
        $paragraph = '<p>Hello <strong>team</strong>, the <a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/some/path?x=1&amp;y=2">report</a> is ready. '
            .'<span class="mention" data-type="mention" data-id="7" data-label="Alice">@Alice</span> can you check <em>section 3</em> and <code>total</code>?</p>';
        $html = str_repeat($paragraph, 100).'<ul>'.str_repeat('<li><p>item</p></li>', 200).'</ul>';

        expect(strlen($html))->toBeLessThan(Content::SANITIZE_MAX_BYTES);
        expect(substr_count($html, '<'))->toBeLessThan(Content::SANITIZE_MAX_TAGS);
        expect(Content::sanitize($html))->toBe($html);
    });
});

describe('Content::render on untrusted HTML', function () {
    it('is what <atom:tiptap.content> does with an HTML string, so it needs no sanitising to print', function () {
        $html = renderBlade('<atom:tiptap.content :content="$html" />', [
            'html' => '<p onclick="x()">a</p><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">j</a>',
        ]);

        expect($html)->toContain('<p>a</p>')->not->toContain('<script')->not->toContain('onerror')->not->toContain('onclick')->not->toContain('javascript:');
    });

    it('also cleans the slot form', function () {
        $html = renderBlade('<atom:tiptap.content><p onclick="x()">a</p><script>alert(1)</script></atom:tiptap.content>');

        expect($html)->toContain('<p>a</p>')->not->toContain('<script')->not->toContain('onclick');
    });
});

/**
 * A distinct value per call, so the once-per-value log dedupe of one test cannot
 * hide the warning another test expects.
 */
function uniqueHtml(string $html): string
{
    return $html.'<!-- '.uniqid('', true).' -->';
}

describe('Content::render: limits on stored content', function () {
    it('renders the content the limits are meant to let through', function () {
        $prices = '<table><tbody>'.str_repeat('<tr><td>Item</td><td>RM 10.00</td><td>Note</td><td>a</td><td>b</td><td>c</td></tr>', 1000).'</tbody></table>';
        $article = str_repeat('<p>Lorem ipsum <strong>dolor</strong> sit amet, consectetur adipiscing elit.</p>', 3000);

        expect(substr_count($prices, '<'))->toBeGreaterThan(10000);
        expect(substr_count($article, '<'))->toBeGreaterThan(10000);
        expect(substr_count(Content::render($prices), '<tr>'))->toBe(1000);
        expect(substr_count(Content::render($article), '<p>'))->toBe(3000);
    });

    it('refuses HTML over the tag limit, warns once with the reason, and does not report', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $html = uniqueHtml(str_repeat('<p>a', Content::RENDER_MAX_TAGS + 1));

        expect(Content::render($html))->toBe('');
        expect(Content::render($html))->toBe('');
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], 'tags') && $context['bytes'] === strlen($html) && $context['max_tags'] === Content::RENDER_MAX_TAGS);
    });

    it('renders HTML at exactly the tag limit', function () {
        Log::spy();

        expect(Content::render(str_repeat('<p>a', Content::RENDER_MAX_TAGS)))->not->toBe('');
        Log::shouldNotHaveReceived('warning');
    });

    it('takes its limits from atom.editor.render_max_bytes and render_max_tags', function () {
        Log::spy();
        $html = uniqueHtml(str_repeat('<p>a', 100));

        expect(Content::render($html))->not->toBe('');

        config(['atom.editor.render_max_tags' => 50]);
        expect(Content::render($html))->toBe('');

        config(['atom.editor.render_max_tags' => 500]);
        expect(Content::render($html))->not->toBe('');

        config(['atom.editor.render_max_bytes' => 100]);
        expect(Content::render($html))->toBe('');
        expect(Content::render(uniqueHtml(str_repeat('<p>a', 100))))->toBe('');
        Log::shouldHaveReceived('warning')->twice();
    });

    it('refuses a document over the node limit, however small its bytes per node', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $json = json_encode(['type' => 'doc', 'content' => array_fill(0, 95000, ['type' => 'paragraph'])]);

        [$html, $seconds, $mb] = measured(fn () => Content::render($json));

        expect(strlen($json))->toBeLessThan(2 * 1024 * 1024);
        expect($html)->toBe('');
        expect($mb)->toBeLessThan(10.0);
        expect($seconds)->toBeLessThan(0.5);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], 'nodes'));
    });

    it('counts marks and text nodes as well as blocks, and accepts a document at the limit', function () {
        config(['atom.editor.render_max_tags' => 1 + 3 * 10]);
        $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'bold']]]]];
        $doc = fn (int $paragraphs) => json_encode(['type' => 'doc', 'content' => array_fill(0, $paragraphs, $paragraph)]);

        expect(Content::render($doc(10)))->not->toBe('');
        expect(Content::render($doc(11)))->toBe('');
    });

    it('holds a document at the default limit to a memory and time bound', function (array $children) {
        $json = json_encode(['type' => 'doc', 'content' => $children]);

        [$html, $seconds, $mb] = measured(fn () => Content::render($json));

        expect($html)->not->toBe('');
        expect($mb)->toBeLessThan(80.0);
        expect($seconds)->toBeLessThan(5.0);
    })->with([
        'paragraphs' => [array_fill(0, Content::RENDER_MAX_TAGS - 1, ['type' => 'paragraph'])],
        'links' => [array_fill(0, intdiv(Content::RENDER_MAX_TAGS - 1, 3), ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]]]])],
    ]);

    it('holds HTML at the default limit to a memory and time bound', function (string $html) {
        [$out, $seconds, $mb] = measured(fn () => Content::render($html));

        expect($out)->not->toBe('');
        expect($mb)->toBeLessThan(80.0);
        expect($seconds)->toBeLessThan(5.0);
    })->with([
        'unclosed paragraphs' => [str_repeat('<p>a', Content::RENDER_MAX_TAGS)],
        'list items' => ['<ul>'.str_repeat('<li>a', Content::RENDER_MAX_TAGS - 1)],
        'links' => [str_repeat('<p><a href="https://example.com/x">a</a>', intdiv(Content::RENDER_MAX_TAGS, 3))],
    ]);

    it('logs the same refusal for the <atom:tiptap.content> component and prints nothing', function () {
        Log::spy();
        $html = uniqueHtml(str_repeat('<p>a', Content::RENDER_MAX_TAGS + 1));

        $out = renderBlade('<atom:tiptap.content :content="$html" />', ['html' => $html]);

        expect($out)->toContain('editor-content')->not->toContain('<p>');
        Log::shouldHaveReceived('warning')->once();
    });

    it('refuses output over four times the byte limit and warns', function () {
        Log::spy();
        $html = str_repeat('<a href=x>a</a>b', 50).uniqid('', true);
        config(['atom.editor.render_max_bytes' => strlen($html)]);

        expect(Content::render($html))->toBe('');
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], 'output'));
    });

    it('does not warn when sanitize() refuses', function () {
        Log::spy();

        expect(Content::sanitize(str_repeat('<p>a', Content::SANITIZE_MAX_TAGS + 1)))->toBe('');
        expect(Content::sanitize(placeholderBomb()))->toBe('');
        Log::shouldNotHaveReceived('warning');
    });
});

describe('Content: whitespace the minifier is quadratic on is collapsed, not refused', function () {
    /**
     * The inputs are the size at which an unguarded run costs seconds: 20000
     * characters is ~2.7 s, 120 KB is 98 s. The content around the run must still render.
     */
    it('renders the content, quickly, silently, and without a warning', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        [$out, $seconds] = measured(fn () => Content::render($html));

        expect($out)->toContain('a')->toContain('b');
        expect($seconds)->toBeLessThan(0.3);
        Log::shouldNotHaveReceived('warning');
    })->with([
        'a run of spaces' => ['<p>a'.str_repeat(' ', 20000).'b</p>'],
        'a run of spaces before a tag' => ['<p>a'.str_repeat(' ', 20000).'<b>b</b></p>'],
        'a run of tabs and newlines' => ['<p>a'.str_repeat("\t\n", 10000).'b</p>'],
        'a run of no-break spaces' => ['<p>a'.str_repeat("\u{00A0}", 20000).'b</p>'],
        'a run of ideographic spaces' => ['<p>a'.str_repeat("\u{3000}", 20000).'b</p>'],
        'a run mixing ascii and unicode spaces' => ['<p>a'.str_repeat(" \u{00A0}\t", 7000).'b</p>'],
        'a run between blocks' => ['<p>a</p>'.str_repeat(' ', 20000).'<p>b</p>'],
    ]);

    it('renders 120 KB of spaces in well under a second (it took 98 s)', function () {
        $html = '<p>a'.str_repeat(' ', 120000).'b</p>';

        [$out, $seconds] = measured(fn () => Content::render($html));

        expect($out)->toBe('<p>a b</p>');
        expect($seconds)->toBeLessThan(0.5);
        expect(Content::sanitize($html))->toBe('<p>a b</p>');
        expect(Content::sanitizeRefuses($html))->toBeFalse();
    });

    it('passes a run of the allowed length on byte for byte, and collapses one space longer', function () {
        expect(Content::sanitize('<p>a'.str_repeat(' ', 32).'b</p>'))->toBe('<p>a'.str_repeat(' ', 32).'b</p>');
        expect(Content::sanitize('<p>a'.str_repeat(' ', 33).'b</p>'))->toBe('<p>a b</p>');
    });

    it('holds a run just under the limit, repeated across a 2 MB document, to a time bound', function () {
        $html = str_repeat('<p>a'.str_repeat(' ', 32).'b</p>', intdiv(2000000, 40));

        // CPU time, not wall clock, and 5 s rather than 3: the work is about 2.5 s on an idle
        // machine, so 3 s of wall clock failed under full-suite load (3.29 s seen) while
        // proving nothing more. A quadratic regression on 2 MB would be far past 5 s.
        [$out, $seconds] = measuredCpu(fn () => Content::sanitize($html, 3000000, 100000));

        expect($out)->not->toBe('');
        expect($seconds)->toBeLessThan(5.0);
    });

    it('renders real legacy markup: pretty-printed nested HTML with long indentation', function () {
        $indent = str_repeat(' ', 300);
        $html = "<div>\n{$indent}<div>\n{$indent}{$indent}<h2>Heading</h2>\n{$indent}{$indent}<p>First paragraph with <strong>bold</strong>.</p>\n"
            ."{$indent}{$indent}<ul>\n{$indent}{$indent}{$indent}<li>one</li>\n{$indent}{$indent}{$indent}<li>two</li>\n{$indent}{$indent}</ul>\n"
            ."{$indent}</div>\n</div>";

        $out = Content::render($html);

        expect($out)->toContain('<h2>Heading</h2>')->toContain('<strong>bold</strong>')->toContain('one')->toContain('two');
        expect(strlen($out))->toBeLessThan(500);
    });

    it('renders pasted Word style markup with runs of tabs and newlines between tags', function () {
        $html = "<p>One</p>\n\n\n".str_repeat("\t", 100)."<p>Two</p>".str_repeat("\r\n", 100)."<p>Three</p>";

        expect(Content::render($html))->toBe('<p>One</p><p>Two</p><p>Three</p>');
    });

    it('leaves the inside of a pre block alone, however long the runs in it', function () {
        $code = str_repeat(str_repeat(' ', 300)."call();\n", 20);
        $html = '<p>before   '.str_repeat(' ', 500).'after</p><pre><code>'.$code.'</code></pre>';

        $out = Content::render($html);

        expect($out)->toContain(str_repeat(' ', 300).'call();')->toContain('<p>before after</p>');
        expect(substr_count($out, str_repeat(' ', 300).'call();'))->toBe(20);
    });

    it('leaves 120 KB of spaces inside a pre block intact, and quickly', function () {
        $html = '<pre><code>a'.str_repeat(' ', 120000).'b</code></pre>';

        [$out, $seconds] = measured(fn () => Content::render($html));

        expect($out)->toContain('a'.str_repeat(' ', 120000).'b');
        expect($seconds)->toBeLessThan(0.5);
    });
});

describe('Content: <pre> input the minifier is quadratic or fatal on', function () {
    /**
     * Refused before parsing. The sizes are the smallest that cross each rule, so an
     * unguarded run costs a second or two.
     */
    it('is refused quickly, silently, with a warning from render()', function (string $html, string $reason) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $html = uniqueHtml($html);

        [$out, $seconds] = measured(fn () => Content::render($html));

        expect($out)->toBe('');
        expect($seconds)->toBeLessThan(0.3);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($context['reason'], $reason));
    })->with([
        'a pre block past the regex backtrack limit' => ['<pre>'.str_repeat('a', 600000).'</pre>', 'pre'],
        'an unclosed pre past the regex backtrack limit' => ['<pre>'.str_repeat('a', 600000), 'pre'],
        'many pre blocks in a long input' => [str_repeat('<pre>a</pre>', 9000), 'pre'],
        'many pre tags with no end' => [str_repeat('<pre a', 4000), 'pre'],
    ]);

    it('closes many unclosed pre tags instead of refusing them, and renders quickly', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $html = uniqueHtml('<p>before</p>'.str_repeat('<pre>a', 2000).str_repeat('b', 50000));

        [$out, $seconds] = measured(fn () => Content::render($html));

        expect($out)->toContain('before')->toContain('<pre>');
        expect($seconds)->toBeLessThan(0.3);
        Log::shouldNotHaveReceived('warning');
    });

    /**
     * Minify needs the literal `</pre>` to close a block, so anything else is an unclosed
     * `<pre>` to it. Sized so an unguarded run costs a few seconds (about 2.5 s here; the
     * reviewer's 131 KB shape took 10 s).
     */
    it('treats a block whose end is not exactly </pre> as unclosed, and renders quickly', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        [$out, $seconds] = measured(fn () => Content::render(uniqueHtml($html)));
        [$clean, $cleanSeconds] = measured(fn () => Content::sanitize($html, 300000, 100000));

        expect($out)->toContain('<pre>')->toContain('x');
        expect($clean)->toContain('x');
        expect($seconds)->toBeLessThan(0.3);
        expect($cleanSeconds)->toBeLessThan(0.3);
        Log::shouldNotHaveReceived('warning');
    })->with([
        'a closing tag with no >' => [str_repeat('<pre>x</pre ', 4000)],
        'a closing tag that is another element' => [str_repeat('<pre>x</prex>', 4000)],
        'a closing tag with a space before the >' => [str_repeat('<pre>x</pre >', 4000)],
        'a closing tag cut off by the next tag' => [str_repeat('<pre>x</pre<p>', 4000)],
    ]);

    it('still takes </PRE> in any case as the end of a block', function () {
        $html = str_repeat('<PRE>x</PRE>', 200);

        expect(substr_count(Content::render($html), '<pre>'))->toBe(200);
    });

    it('lets through a document with a few unclosed pre tags and a real code block', function () {
        $html = '<p>a</p><pre>x</pre><p>b</p><pre>unclosed'.str_repeat('<p>more</p>', 500);

        $out = Content::render($html);

        expect($out)->toContain('<p>a</p>')->toContain('x')->toContain('unclosed');
    });

    it('lets through the number of code blocks a long article has', function () {
        $html = str_repeat('<p>text</p><pre><code>'.str_repeat('code line'."\n", 40).'</code></pre>', 200);

        expect(strlen($html))->toBeLessThan(100000);
        expect(substr_count(Content::render($html), '<pre>'))->toBe(200);
    });

    it('allows a single unclosed pre in a long document', function () {
        expect(Content::render('<p>a</p><pre>x'.str_repeat('<p>a</p>', 1000)))->not->toBe('');
    });

    it('lets through a pre block just under the length limit', function () {
        $out = Content::render('<pre>'.str_repeat('a', 400000).'</pre>');

        expect($out)->toContain('<pre>');
    });
});
