<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
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
        'span' => ['class', 'style', 'data-type', 'data-id', 'data-label'],
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

            if ($name === 'class' && ! preg_match('/^(mention|language-[\w+#.-]+)$/', $value)) {
                $violations[] = "class {$value}";
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

    it('strips NUL bytes and invalid UTF-8 and keeps the text around them', function () {
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

describe('Content::sanitize: never throws', function () {
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
        'empty string' => [''],
        'whitespace' => ["  \n\t "],
    ]);

    it('returns a string for corrupt input', function (mixed $input) {
        expect(Content::sanitize($input))->toBeString();
    })->with([
        'truncated JSON' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","te'],
        'JSON that is not a document' => ['{"foo":"bar"}'],
        'JSON list' => ['[1,2,3]'],
        'document with a non-array content' => ['{"type":"doc","content":"x"}'],
        'document with an unknown node' => ['{"type":"doc","content":[{"type":"nope","attrs":{"a":[1]}}]}'],
        'array of junk' => [['a' => 1]],
        'nested array of junk' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [1, 'x', null]]]]],
        'lone angle bracket' => ['<'],
        'lone entity' => ['&#'],
        'binary' => [random_bytes(2048)],
        'invalid UTF-8' => ["\xc3\x28\xa0\xa1\xe2\x28\xa1"],
        'NUL bytes' => [str_repeat("\0", 100)],
    ]);

    it('reports a refusal and returns an empty string', function () {
        $this->mock(ExceptionHandler::class)->shouldReceive('report')->atLeast()->once()->with(Mockery::type(LengthException::class));

        expect(Content::sanitize(str_repeat('a', 10), maxBytes: 5))->toBe('');
    });

    it('reports a document the parser cannot handle', function () {
        $this->mock(ExceptionHandler::class)->shouldReceive('report')->atLeast()->once();

        expect(Content::sanitize(['a' => 1]))->toBe('');
    });
});

describe('Content::sanitize: size limit', function () {
    it('refuses input over the limit and reports it', function () {
        $big = '<p>'.str_repeat('a', Content::SANITIZE_MAX_BYTES).'</p>';

        expect(Content::sanitize($big))->toBe('');
    });

    it('accepts input at the limit, and a raised limit', function () {
        $html = '<p>'.str_repeat('a', 1000).'</p>';

        expect(Content::sanitize($html, maxBytes: strlen($html)))->toBe($html);
        expect(Content::sanitize($html, maxBytes: strlen($html) - 1))->toBe('');
        expect(Content::sanitize($html, maxBytes: 10 * 1024 * 1024))->toBe($html);
    });

    it('cleans the largest input the default allows in reasonable time and memory', function () {
        $html = str_repeat('<p><b><i>x</i></b><script>alert(1)</script></p>', 5000);
        $before = memory_get_peak_usage();
        $started = microtime(true);

        $clean = Content::sanitize($html);

        expect(strlen($html))->toBeLessThan(Content::SANITIZE_MAX_BYTES);
        expect($clean)->not->toContain('<script');
        expect(microtime(true) - $started)->toBeLessThan(10.0);
        expect(memory_get_peak_usage() - $before)->toBeLessThan(120 * 1024 * 1024);
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
