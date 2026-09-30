<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Tiptap\Content;

/**
 * Every attribute value a client can put in a Tiptap document (or an HTML
 * attribute) has to come out as valid markup: no report(), and no tag name
 * that is not in the schema's own set. tiptap-php interpolates some values
 * into a tag name (a heading's level) and reads an int 0 in an attribute array
 * as "content goes here", so a value it never meant to see reached the tag
 * name, and once the markup.
 */

/**
 * The tags the schema can emit.
 *
 * @return array<int, string>
 */
function fuzzTagSet(): array
{
    return [
        'p', 'br', 'hr', 'strong', 'em', 's', 'u', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'span', 'mark', 'sub', 'sup',
        'table', 'tbody', 'thead', 'tr', 'th', 'td', 'img', 'div', 'iframe',
    ];
}

/**
 * The tag names in a piece of HTML that are not `^[a-z][a-z0-9]*$` or not in
 * the schema's set. Read from the raw text, so `<https://...>` and `<h1e0>` show up.
 *
 * @return array<int, string>
 */
function fuzzBadTags(string $html): array
{
    preg_match_all('/<\/?([^>\s\/]*)/', $html, $matches);

    return array_values(array_unique(array_filter(
        $matches[1],
        fn (string $tag) => ! preg_match('/^[a-z][a-z0-9]*\z/', $tag) || ! in_array($tag, fuzzTagSet(), true),
    )));
}

/**
 * @return array<int, mixed>
 */
function fuzzValues(): array
{
    return [
        ' 1', "\n1", '+1', '1e0', '01', '1.0', 1.5, true, false, '6 ', 7, 0, -1, 2, '3', 6, null, '', 'x', '1x', 'h1', '1 2', '-0', '0x1', '1e2',
        '9999999999999999999', PHP_INT_MAX, 1e30, 0.1, [], [1], [0], ['a' => 1], [[1]], [null], ['<'], '<script>', '"><b>', "a\0b", '💥', str_repeat('9', 400),
        '1,2', [1, 'x'], [true], ['level' => 1], 'https://a.test/x.png', '#fff', 'left',
        // numbers that json_encode() cannot write, as raw JSON: see fuzzRawNumbers()
        '@@1e999', '@@-1e999', '@@1e400', '@@1.7976931348623157e308', '@@1e-999', '@@[1e999]', '@@{"a":1e999}',
    ];
}

/**
 * Every attribute name any extension reads, plus ones nothing reads.
 *
 * @return array<int, string>
 */
function fuzzAttributeNames(): array
{
    return [
        'level', 'start', 'colspan', 'rowspan', 'colwidth', 'language', 'textAlign', 'href', 'target', 'rel', 'class', 'style', 'color', 'fontSize',
        'src', 'alt', 'title', 'width', 'height', 'float', 'align', 'id', 'label', 'multicolor', 'type', 'data-x',
        // a key that starts with a NUL byte cannot be an object property
        "\0x", "\0",
    ];
}

/**
 * What a node has to carry to render at all, so the fuzzed attribute is
 * reached: an image with no `src`, a link with no `href` are dropped whole.
 *
 * @return array<string, array<string, mixed>>
 */
function fuzzBaseAttributes(): array
{
    return [
        'image' => ['src' => 'https://a.test/x.png'],
        'youtube' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        'mention' => ['id' => '1', 'label' => 'Ann'],
        'link' => ['href' => 'https://a.test/'],
        'heading' => ['level' => 2],
    ];
}

/**
 * A document holding one node, or one marked text, whose attributes are $attributes.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function fuzzDocument(string $type, array $attributes, bool $mark): array
{
    $attributes = $attributes + (fuzzBaseAttributes()[$type] ?? []);
    $text = ['type' => 'text', 'text' => 'x'];

    if ($mark) {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$text + ['marks' => [['type' => $type, 'attrs' => $attributes]]]]]]];
    }

    $node = ['type' => $type, 'attrs' => $attributes, 'content' => [$text]];

    return ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [$text]],
        $node,
        ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [$node]]]]]],
    ]];
}

/**
 * Turn the `"@@..."` strings in fuzzValues() into the raw JSON after the `@@`:
 * json_encode() will not write INF, and a huge exponent is INF once it is read.
 */
function fuzzRawNumbers(string|false $json): string
{
    return (string) preg_replace_callback('/"@@((?:[^"\\\\]|\\\\.)*)"/', fn (array $m) => stripcslashes($m[1]), (string) $json);
}

/**
 * Run every document through sanitize() (and render(), which parses the same way
 * under other limits), and collect what breaks.
 *
 * @param  iterable<string, array<string, mixed>>  $documents  label => document
 * @return array{0: int, 1: array<string, array<int, string>>, 2: array<string, string>} [count, bad tags by label, reports by label]
 */
function fuzzRun(iterable $documents, bool $renderToo = true): array
{
    $reports = [];
    $current = '';

    $handler = Mockery::mock(ExceptionHandler::class);
    app()->instance(ExceptionHandler::class, $handler);
    $handler->shouldReceive('report')->andReturnUsing(function ($e) use (&$reports, &$current) {
        $reports[$current] = get_class($e).': '.$e->getMessage();
    });

    $count = 0;
    $bad = [];

    foreach ($documents as $label => $document) {
        $current = $label;
        $json = fuzzRawNumbers(json_encode($document));
        $count++;

        foreach ($renderToo ? [Content::sanitize($json), Content::render($json)] : [Content::sanitize($json)] as $html) {
            if (($tags = fuzzBadTags($html)) !== []) {
                $bad[$label] = $tags;
            }
        }
    }

    return [$count, $bad, $reports];
}

/**
 * @return Generator<string, array<string, mixed>>
 */
function fuzzEveryAttribute(): Generator
{
    $types = [
        'node' => ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem', 'codeBlock', 'hardBreak', 'horizontalRule', 'table', 'tableRow', 'tableCell', 'tableHeader', 'image', 'youtube', 'mention'],
        'mark' => ['bold', 'italic', 'strike', 'code', 'underline', 'subscript', 'superscript', 'highlight', 'link', 'textStyle'],
    ];

    foreach ($types as $kind => $names) {
        foreach ($names as $type) {
            foreach (fuzzAttributeNames() as $name) {
                foreach (fuzzValues() as $i => $value) {
                    yield "{$type}.{$name} = ".json_encode($value) => fuzzDocument($type, [$name => $value], $kind === 'mark');
                }
            }
        }
    }
}

describe('Content: attribute fuzz, JSON documents', function () {
    it('never reports, and never prints a tag the schema does not have, for any value of any attribute', function () {
        [$count, $bad, $reports] = fuzzRun(fuzzEveryAttribute(), renderToo: false);

        expect($count)->toBeGreaterThan(20000);
        expect($reports)->toBe([]);
        expect($bad)->toBe([]);
    });

    it('holds for several attributes at once, on a seeded random draw', function () {
        $random = new Random\Randomizer(new Random\Engine\Mt19937(20260930));
        $values = fuzzValues();
        $names = fuzzAttributeNames();
        $types = ['heading', 'orderedList', 'tableCell', 'tableHeader', 'image', 'mention', 'codeBlock', 'paragraph', 'youtube'];

        $documents = (function () use ($random, $values, $names, $types) {
            for ($i = 0; $i < 4000; $i++) {
                $attributes = [];

                foreach (range(1, $random->getInt(1, 5)) as $ignored) {
                    $attributes[$names[$random->getInt(0, count($names) - 1)]] = $values[$random->getInt(0, count($values) - 1)];
                }

                yield "draw {$i}: ".json_encode($attributes) => fuzzDocument($types[$random->getInt(0, count($types) - 1)], $attributes, false);
            }
        })();

        [$count, $bad, $reports] = fuzzRun($documents);

        expect($count)->toBe(4000);
        expect($reports)->toBe([]);
        expect($bad)->toBe([]);
    });
});

/**
 * HTML templates with the fuzzed value in every attribute a parse rule reads.
 *
 * @return array<string, string> name => template, `{v}` is the value
 */
function fuzzHtmlTemplates(): array
{
    return [
        'heading' => '<h1 level="{v}" style="text-align:{v}">h</h1><h{v}>h</h{v}>',
        'ordered list' => '<ol start="{v}"><li>a</li></ol>',
        'table cell' => '<table><tr><td colspan="{v}" rowspan="{v}" data-colwidth="{v}">a</td><th colspan="{v}" data-colwidth="{v},{v}">b</th></tr></table>',
        'code block' => '<pre><code class="language-{v}">x</code></pre>',
        'link' => '<p><a href="{v}" target="{v}" rel="{v}" class="{v}">a</a></p>',
        'image' => '<img src="{v}" alt="{v}" title="{v}" width="{v}" height="{v}" data-float="{v}" data-align="{v}" data-width="{v}" style="{v}">',
        'mention' => '<p><span data-type="mention" data-id="{v}" data-label="{v}">@x</span></p>',
        'spans' => '<p style="text-align:{v};color:{v}"><span style="color:{v};font-size:{v}" data-font-size="{v}">a</span><mark data-color="{v}" style="background-color:{v}">b</mark></p>',
        'youtube' => '<div data-youtube-video><iframe src="{v}" start="{v}"></iframe></div>',
    ];
}

describe('Content: attribute fuzz, HTML', function () {
    it('never reports, and never prints a tag the schema does not have, for any attribute value', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        $count = 0;
        $bad = [];

        foreach (fuzzHtmlTemplates() as $name => $template) {
            foreach (fuzzValues() as $value) {
                $text = is_array($value) ? json_encode($value) : (string) json_encode($value);
                $value = is_string($value) ? $value : $text;
                $value = str_starts_with($value, '@@') ? substr($value, 2) : $value;
                $html = str_replace('{v}', htmlspecialchars($value, ENT_QUOTES), $template);
                $count++;

                if (($tags = fuzzBadTags(Content::sanitize($html))) !== []) {
                    $bad["{$name} = {$value}"] = $tags;
                }

                if (($tags = fuzzBadTags(Content::render($html))) !== []) {
                    $bad["{$name} = {$value} (render)"] = $tags;
                }
            }
        }

        expect($count)->toBeGreaterThan(400);
        expect($bad)->toBe([]);
    });

    it('does not print a tag name it was given', function (string $html) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');

        expect(fuzzBadTags(Content::sanitize($html)))->toBe([]);
    })->with([
        'h1e0' => ['<h1e0>a</h1e0>'],
        'h 1' => ['<h 1>a</h>'],
        'h01' => ['<h01>a</h01>'],
        'a url' => ['<https://a.test/x>a'],
        'a digit' => ['<1>a'],
    ]);
});

/**
 * A heading's level is interpolated into its tag name by tiptap-php, after a
 * loose in_array(): `" 1"`, `"+1"`, `"1e0"`, `"01"` and `"1.0"` all matched a
 * level, and `createElement('h 1')` throws (a report on every call, and an
 * empty result), while `<h1e0>` was stored and printed.
 *
 * @return array<string, array{0: mixed, 1: string}> [level, the tag that comes out]
 */
dataset('heading levels', [
    'leading space' => [' 1', 'h1'],
    'leading newline' => ["\n1", 'h1'],
    'plus sign' => ['+1', 'h1'],
    'exponent' => ['1e0', 'h1'],
    'leading zero' => ['01', 'h1'],
    'decimal string' => ['1.0', 'h1'],
    'float' => [1.5, 'h1'],
    'true' => [true, 'h1'],
    'trailing space' => ['6 ', 'h6'],
    'seven' => [7, 'h1'],
    'zero' => [0, 'h1'],
    'minus one' => [-1, 'h1'],
    'minus one as a string' => ['-1', 'h1'],
    'numeric but not a digit: plus three' => ['+3', 'h1'],
    'numeric but not a digit: padded three' => ['03', 'h1'],
    'numeric but not a digit: decimal two' => ['2.0', 'h1'],
    'numeric but not a digit: exponent four' => ['4e0', 'h1'],
    'two digits' => ['12', 'h1'],
    'a digit and a letter' => ['2x', 'h1'],
    'an empty string' => ['', 'h1'],
    'an int' => [3, 'h3'],
    'a digit string' => ['4', 'h4'],
    'a digit string in whitespace' => [" \t5\n", 'h5'],
    'the lowest' => [1, 'h1'],
    'the highest' => [6, 'h6'],
]);

describe('Content: a heading level that is not a whole number from 1 to 6', function () {
    it('becomes level 1, or the level it spells, without a report or an odd tag', function (mixed $level, string $tag) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => 'h']]]]]);

        expect(Content::sanitize($json))->toBe("<{$tag}>h</{$tag}>");
        expect(Content::render($json))->toBe("<{$tag}>h</{$tag}>");
        expect(Content::sanitizeRefuses($json))->toBeFalse();
        expect(fuzzBadTags(Content::sanitize($json)))->toBe([]);
        Log::shouldNotHaveReceived('warning');
    })->with('heading levels');

    it('is level 1 for a float, even a whole one written as 2.0', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $json = '{"type":"doc","content":[{"type":"heading","attrs":{"level":2.0},"content":[{"type":"text","text":"h"}]}]}';

        expect(Content::sanitize($json))->toBe('<h1>h</h1>');
    });

    it('is not a tag name in a heading with other attributes either', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $json = '{"type":"doc","content":[{"type":"heading","attrs":{"level":"1e0","textAlign":"center"},"content":[{"type":"text","text":"h"}]}]}';

        expect(Content::sanitize($json))->toBe('<h1 style="text-align: center;">h</h1>');
    });
});

/**
 * tiptap-php reads an int 0 in an attribute array as "content goes here"
 * (`in_array(0, $attributes, true)`): the element is opened bare, and every
 * string in the array is printed raw as a tag. So `alt: 0` on an image whose
 * `src` passes the URL check printed the src between angle brackets, which is
 * markup the client chose, script included.
 */
describe('Content: an attribute value of 0', function () {
    it('does not turn the other attributes into tags', function (string $attribute) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $src = 'https://a.test/x.png"><script>alert(1)</script>';
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => $src, $attribute => 0]]]]);

        foreach ([Content::sanitize($json), Content::render($json)] as $html) {
            expect($html)->toStartWith('<img src="https://a.test/x.png&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"');
            expect($html)->not->toContain('<script')->not->toContain('<https');
            expect(fuzzBadTags($html))->toBe([]);
        }
    })->with(['alt', 'title', 'height', 'width', 'float', 'align']);

    it('prints as "0" on the mention it was on', function () {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"mention","attrs":{"id":0,"label":"Zero"}}]}]}';

        expect(Content::sanitize($json))->toBe('<p><span class="mention" data-type="mention" data-id="0" data-label="Zero">@Zero</span></p>');
    });

    it('is dropped from a list start, a colspan and a rowspan, as it is from HTML, and kept in a colwidth', function () {
        $cell = fn (array $attributes) => json_encode(['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'attrs' => $attributes, 'content' => [['type' => 'paragraph']]]]]]]]]);
        $list = json_encode(['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['start' => 0], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]]]]);

        expect(Content::sanitize($list))->toBe('<ol><li><p></p></li></ol>');
        expect(Content::sanitize($cell(['colspan' => 0, 'rowspan' => 0])))->toBe('<table><tbody><tr><td><p></p></td></tr></tbody></table>');
        expect(Content::sanitize($cell(['colspan' => 2, 'rowspan' => 0])))->toBe('<table><tbody><tr><td colspan="2"><p></p></td></tr></tbody></table>');
        expect(Content::sanitize($cell(['colwidth' => [0, 120]])))->toContain('data-colwidth="0,120"');
    });
});

describe('Content: the whole-number attributes', function () {
    it('keeps a whole number, or digits, and drops anything else', function (mixed $value, ?string $expected) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'attrs' => ['colspan' => $value, 'rowspan' => $value], 'content' => [['type' => 'paragraph']]]]]]]]]);
        $html = Content::sanitize($json);

        expect($html)->toContain($expected === null ? '<td><p>' : "colspan=\"{$expected}\"");
    })->with([
        'an int' => [3, '3'],
        'a digit string' => ['4', '4'],
        'a padded digit string' => [' 5 ', '5'],
        'a float' => [1.5, null],
        'true' => [true, null],
        'text' => ['abc', null],
        'a negative number' => [-2, null],
        'a long digit string' => ['9999999999999999999', null],
        'an exponent' => ['1e3', null],
        'null' => [null, null],
    ]);

    it('keeps a list start that is a number, negative included, and drops the rest', function (mixed $start, string $expected) {
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['start' => $start], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]]]]);

        expect(Content::sanitize($json))->toBe($expected);
    })->with([
        'an int' => [3, '<ol start="3"><li><p></p></li></ol>'],
        'a digit string' => ['7', '<ol start="7"><li><p></p></li></ol>'],
        'a negative int' => [-2, '<ol start="-2"><li><p></p></li></ol>'],
        'a float' => [1.5, '<ol><li><p></p></li></ol>'],
        'text' => ['x', '<ol><li><p></p></li></ol>'],
        'a list' => [[1], '<ol><li><p></p></li></ol>'],
    ]);

    it('drops a colwidth with an element that is not a number, keeps nulls, and rounds a float', function () {
        $cell = fn (array $colwidth) => json_encode(['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'attrs' => ['colwidth' => $colwidth], 'content' => [['type' => 'paragraph']]]]]]]]]);

        expect(Content::sanitize($cell([120, null])))->toContain('data-colwidth="120,"');
        expect(Content::sanitize($cell(['120', 80])))->toContain('data-colwidth="120,80"');
        expect(Content::sanitize($cell([120, 'x'])))->not->toContain('data-colwidth');
        expect(Content::sanitize($cell([120.5])))->toContain('data-colwidth="121"');
        expect(Content::sanitize($cell([119.4, null, 80])))->toContain('data-colwidth="119,,80"');
        expect(Content::sanitize($cell([-3.6])))->toContain('data-colwidth="-4"');
        expect(Content::sanitize($cell([1e12 + 0.5])))->not->toContain('data-colwidth');
    });
});

/**
 * Where a parsed document has an int 0 as the value of an attribute, which is
 * what tiptap-php's serialiser mistakes for a content hole. Empty when none does.
 *
 * @param  array<mixed>  $node
 * @return array<int, string>
 */
function fuzzIntZeroAttributes(array $node, string $path = 'doc'): array
{
    $found = [];

    foreach ($node['attrs'] ?? [] as $name => $value) {
        if ($value === 0) {
            $found[] = "{$path}.{$name}";
        }
    }

    foreach ($node['marks'] ?? [] as $i => $mark) {
        $found = array_merge($found, fuzzIntZeroAttributes($mark, "{$path}.marks[{$i}]"));
    }

    foreach ($node['content'] ?? [] as $i => $child) {
        $found = array_merge($found, fuzzIntZeroAttributes($child, "{$path}[{$i}]"));
    }

    return $found;
}

describe('Content: the int 0 hole, other value types and the HTML path', function () {
    it('is not reachable from HTML: the parse never puts an int 0 in an attribute', function () {
        $editor = fn (string $html) => (new Tiptap\Editor(['extensions' => Content::extensions()]))->setContent($html)->getDocument();
        $found = [];

        foreach (fuzzHtmlTemplates() as $name => $template) {
            foreach (array_merge(fuzzValues(), ['0', '00', '0.0', '-0', '0,0', '0x0', ' 0', '+0']) as $value) {
                $text = is_string($value) ? $value : json_encode($value);
                $text = str_starts_with($text, '@@') ? substr($text, 2) : $text;
                $html = str_replace('{v}', htmlspecialchars($text, ENT_QUOTES), $template);
                $document = $editor($html);

                foreach (fuzzIntZeroAttributes($document) as $where) {
                    $found["{$name} = {$text}"][] = $where;
                }
            }
        }

        expect($found)->toBe([]);
    });

    it('prints HTML attributes of "0" as attributes', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $html = '<img src="/a.png" alt="0" title="0" width="0" height="0"><ol start="0"><li>a</li></ol>'
            .'<table><tr><td colspan="0" rowspan="0" data-colwidth="0">a</td></tr></table>'
            .'<p><span data-type="mention" data-id="0" data-label="0">@0</span></p>';

        foreach ([Content::sanitize($html), Content::render($html)] as $clean) {
            expect(fuzzBadTags($clean))->toBe([]);
            // the parser drops a "0" (`getAttribute() ?: null`), so only the mention id is left
            expect($clean)->toContain('data-id="0"');
        }
    });

    it('holds for JSON literals a PHP array cannot spell: 0.0, -0, -0.0, 0e0, false, null, {}, [], [0], {"a":0}, "0"', function () {
        $handler = Mockery::mock(ExceptionHandler::class);
        app()->instance(ExceptionHandler::class, $handler);
        $handler->shouldNotReceive('report');
        $count = 0;
        $bad = [];

        foreach (['node' => ['paragraph', 'heading', 'orderedList', 'tableCell', 'tableHeader', 'image', 'youtube', 'mention', 'codeBlock', 'listItem'], 'mark' => ['bold', 'link', 'textStyle', 'highlight', 'code']] as $kind => $types) {
            foreach ($types as $type) {
                foreach (fuzzAttributeNames() as $name) {
                    foreach (['0.0', '-0', '-0.0', '0e0', '0E-3', '1e-400', '-1e-400', 'false', 'null', '{}', '[]', '[0]', '{"a":0}', '[[0]]', '"0"', '[0.0]'] as $literal) {
                        $json = json_encode(fuzzDocument($type, [$name => '@@'], $kind === 'mark'));
                        $json = str_replace('"@@"', $literal, $json);
                        $count++;

                        foreach ([Content::sanitize($json), Content::render($json)] as $html) {
                            if (($tags = fuzzBadTags($html)) !== []) {
                                $bad["{$type}.{$name} = {$literal}"] = $tags;
                            }
                        }
                    }
                }
            }
        }

        expect($count)->toBeGreaterThan(3000);
        expect($bad)->toBe([]);
    });
});

describe('Content: HTML numeric attributes beside attributes that carry text', function () {
    it('never leave an int 0 for the serialiser, and never print a tag it was not given', function () {
        $handler = Mockery::mock(ExceptionHandler::class);
        app()->instance(ExceptionHandler::class, $handler);
        $handler->shouldNotReceive('report');
        $editor = fn (string $html) => (new Tiptap\Editor(['extensions' => Content::extensions()]))->setContent($html)->getDocument();
        $zeros = ['0', '00', '-0', '+0', '0.0', '0e0', '0x0', ' 0', '0 ', '0abc', 'abc', '', '1e-400', '0,0', '0,120'];
        $text = 'https://a.test/x.png"><b>x</b>';
        $shapes = [
            '<ol start="{z}" style="{t}" class="{t}" data-x="{t}"><li>a</li></ol>',
            '<table><tr><td colspan="{z}" rowspan="{z}" data-colwidth="{z}" style="{t}" class="{t}" title="{t}">a</td><th colspan="{z}" rowspan="{z}" data-colwidth="{z}" style="{t}">b</th></tr></table>',
            '<h1 style="text-align:{t}" class="{t}" level="{z}" data-level="{z}">h</h1>',
            '<h{z} style="{t}">h</h{z}>',
            '<p style="text-align:{t}" data-x="{z}">p</p>',
            '<img src="{t}" alt="{z}" title="{z}" width="{z}" height="{z}" data-width="{z}" data-float="{z}" style="{t}">',
            '<p><span data-type="mention" data-id="{z}" data-label="{t}">@x</span></p>',
            '<pre><code class="language-{z}">x</code></pre>',
        ];
        $count = 0;
        $found = [];

        foreach ($shapes as $shape) {
            foreach ($zeros as $zero) {
                $html = str_replace(['{z}', '{t}'], [htmlspecialchars($zero, ENT_QUOTES), htmlspecialchars($text, ENT_QUOTES)], $shape);
                $count++;

                foreach (fuzzIntZeroAttributes($editor($html)) as $where) {
                    $found["{$shape} with {$zero}"][] = $where;
                }

                foreach ([Content::sanitize($html), Content::render($html)] as $clean) {
                    if (($tags = fuzzBadTags($clean)) !== []) {
                        $found["{$shape} with {$zero}"][] = implode(',', $tags);
                    }
                }
            }
        }

        expect($count)->toBe(count($shapes) * count($zeros));
        expect($found)->toBe([]);
    });
});

describe('Content: a float that is zero in a JSON document', function () {
    it('is turned into "0" too, since the editor writes 0.0 back as 0', function (string $literal) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $json = '{"type":"doc","content":[{"type":"image","attrs":{"src":"https://a.test/x.png\"><script>alert(1)</script>","alt":'.$literal.'}}]}';

        foreach ([Content::sanitize($json), Content::render($json)] as $html) {
            expect($html)->toContain('alt="0"')->not->toContain('<script')->not->toContain('<https');
            expect(fuzzBadTags($html))->toBe([]);
        }
    })->with(['0.0', '-0.0', '0e0', '0E-3', '1e-400', '-1e-400', '0']);

    it('leaves a float that is not zero alone', function () {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"mention","attrs":{"id":0.5,"label":"Half"}}]}]}';

        expect(Content::sanitize($json))->toContain('data-id="0.5"');
    });
});

/**
 * `Editor::setContent()` writes the document with json_encode() and reads it back
 * as objects. A number that overflows to INF cannot be written (json_encode()
 * returns false), and a key that starts with a NUL byte cannot be an object
 * property (the read returns null). Both were a TypeError, so a report() on every
 * call, and from render() once per view of a stored row.
 *
 * @return array<string, array{0: string}>
 */
dataset('documents that cannot round-trip through JSON', [
    'an image alt that overflows' => ['{"type":"doc","content":[{"type":"image","attrs":{"src":"https://a.test/x.png","alt":1e999}}]}'],
    'a negative overflow' => ['{"type":"doc","content":[{"type":"paragraph","attrs":{"textAlign":-1e999}}]}'],
    'a huge exponent' => ['{"type":"doc","content":[{"type":"heading","attrs":{"level":1e400},"content":[{"type":"text","text":"h"}]}]}'],
    'an overflow in a list' => ['{"type":"doc","content":[{"type":"table","content":[{"type":"tableRow","content":[{"type":"tableCell","attrs":{"colwidth":[1e999]},"content":[{"type":"paragraph"}]}]}]}]}'],
    'an overflow in a mark' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"link","attrs":{"href":"https://a.test/","x":1e999}}]}]}]}'],
    'an overflow inside a nested object' => ['{"type":"doc","content":[{"type":"paragraph","attrs":{"a":{"b":{"c":1e999}}}}]}'],
    'a NUL-prefixed key on the document' => ['{"type":"doc","\u0000a":1,"content":[{"type":"paragraph"}]}'],
    'a NUL-prefixed key on a node' => ['{"type":"doc","content":[{"type":"paragraph","\u0000":1}]}'],
    'a NUL-prefixed attribute' => ['{"type":"doc","content":[{"type":"paragraph","attrs":{"\u0000x":"y"}}]}'],
    'a NUL-prefixed key in a mark' => ['{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"bold","attrs":{"\u0000":1}}]}]}]}'],
    'a NUL-prefixed key in a nested object' => ['{"type":"doc","content":[{"type":"paragraph","attrs":{"a":{"\u0000b":1}}}]}'],
]);

describe('Content: a document that cannot round-trip through JSON is a silent refusal', function () {
    it('renders empty from render() and sanitize(), without a report or a log line', function (string $json) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();

        expect(Content::render($json))->toBe('');
        expect(Content::sanitize($json))->toBe('');
        expect(Content::sanitizeRefuses($json))->toBeTrue();
        Log::shouldNotHaveReceived('warning');
    })->with('documents that cannot round-trip through JSON');

    it('does not report a PHP array holding INF or NAN either, which is not a document that can be parsed', function (float $number) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $array = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => ['a' => $number]]]];

        expect(Content::render($array))->toBe('');
        expect(Content::sanitize($array))->toBe('');
    })->with([INF, -INF, NAN]);

    it('keeps a document whose numbers are large but finite', function () {
        $json = '{"type":"doc","content":[{"type":"paragraph","attrs":{"a":1.7976931348623157e308,"b":1e-320}}]}';

        expect(Content::sanitize($json))->toBe('<p></p>');
    });
});

/**
 * A table cell whose `colspan` or `rowspan` was 0 opened its `<td>` bare, and a
 * string `colwidth` was then printed raw as a tag. Two attributes, so the
 * one-attribute fuzz cannot see it: the random draw and this test do.
 */
describe('Content: a zero span beside a string colwidth', function () {
    it('prints the width as an attribute', function (string $type, array $attributes) {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        $cell = ['type' => $type, 'attrs' => $attributes, 'content' => [['type' => 'paragraph']]];
        $json = json_encode(['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [$cell]]]]]]);

        foreach ([Content::sanitize($json), Content::render($json)] as $html) {
            expect(fuzzBadTags($html))->toBe([]);
            expect($html)->not->toContain('<b>')->not->toContain('<x');
        }
    })->with([
        'a cell, rowspan 0' => ['tableCell', ['rowspan' => 0, 'colwidth' => ['"><b>x</b>']]],
        'a cell, colspan 0' => ['tableCell', ['colspan' => 0, 'colwidth' => ['x y']]],
        'a header, both 0' => ['tableHeader', ['colspan' => 0, 'rowspan' => 0.0, 'colwidth' => ['<x>']]],
    ]);
});

/**
 * The README's backfill for rows that sanitize() wrote from a JSON document before
 * 3.29.14: sanitize() them again, except a row that holds a YouTube embed, which
 * HTML parsing drops. This is that logic; the test below keeps the README's
 * snippet to it.
 */
function backfilledBody(string $body): ?string
{
    if (str_contains($body, 'data-youtube-video') || stripos($body, '<iframe') !== false) {
        return null;
    }

    $clean = Content::sanitize($body);

    return $clean !== '' && $clean !== $body ? $clean : null;
}

describe('Content: the documented backfill', function () {
    it('cleans a row that holds stray markup, and leaves a YouTube row, a clean row and an empty row alone', function () {
        $hostile = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'kept']]]]]);
        $stray = '<p>kept</p><img><https://a.test/x.png"><script>alert(1)</script>><p>after</p>';
        $youtube = Content::sanitize('{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"watch"}]},{"type":"youtube","attrs":{"src":"https://www.youtube.com/watch?v=dQw4w9WgXcQ"}}]}');

        expect($youtube)->toContain('<iframe');
        // the reason for the guard: sanitizing it again would drop the embed
        expect(Content::sanitize($youtube))->not->toContain('<iframe');
        expect(backfilledBody($youtube))->toBeNull();

        $cleaned = backfilledBody($stray);

        expect($cleaned)->toBeString()->not->toContain('<script')->not->toContain('<https')->toContain('kept')->toContain('after');
        expect(fuzzBadTags($cleaned))->toBe([]);
        expect(backfilledBody($cleaned))->toBeNull();
        expect(backfilledBody('<p>Hello <strong>team</strong></p>'))->toBeNull();
        expect(backfilledBody(''))->toBeNull();
        expect(backfilledBody($hostile))->toBeString();
    });

    it('is what the README documents', function () {
        $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');
        $section = substr($readme, strpos($readme, '### Upgrading to 3.29.14'), 9000);

        expect($section)
            ->toContain('use Jiannius\Atom\Tiptap\Content;')
            ->toContain("str_contains(\$message->body, 'data-youtube-video')")
            ->toContain("stripos(\$message->body, '<iframe')")
            ->toContain('$clean !== \'\' && $clean !== $message->body');
    });
});
