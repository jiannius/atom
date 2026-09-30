<?php

use Illuminate\Support\Facades\Blade;

/**
 * Prop values that reach an HTML attribute must be escaped, and a value that
 * becomes a URL the browser loads must not carry a script scheme.
 *
 * Every assertion parses the rendered HTML instead of matching strings: a
 * string check passes on `data-title="x" onmouseover="..."` as happily as on
 * the safe form, whereas a parsed element either grew an extra attribute or it
 * did not.
 */

/**
 * Parse rendered markup and return every element matching a tag name.
 *
 * @return list<DOMElement>
 */
function attributeEscapingElements(string $html, string $tag): array
{
    $document = new DOMDocument;

    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
    libxml_clear_errors();

    return iterator_to_array($document->getElementsByTagName($tag));
}

/**
 * The attribute names an element ended up with.
 *
 * @return list<string>
 */
function attributeEscapingNames(DOMElement $element): array
{
    return array_values(array_map(fn ($attribute) => $attribute->name, iterator_to_array($element->attributes)));
}

const ATTRIBUTE_ESCAPING_HOSTILE = <<<'TEXT'
x" onmouseover="alert(1)" data-x='<b>&amp;</b>
TEXT;

describe('sharer', function () {
    it('keeps a hostile title and url inside their attributes', function () {
        $buttons = attributeEscapingElements(Blade::render(
            '<atom:sharer :url="$url" :title="$title" />',
            ['url' => 'https://example.com/?a=1&b="2"'.ATTRIBUTE_ESCAPING_HOSTILE, 'title' => ATTRIBUTE_ESCAPING_HOSTILE],
        ), 'button');

        $shareButtons = array_values(array_filter($buttons, fn ($button) => $button->hasAttribute('data-sharer')));

        expect($shareButtons)->toHaveCount(6);

        foreach ($shareButtons as $button) {
            expect(attributeEscapingNames($button))
                ->not->toContain('onmouseover')
                ->not->toContain('data-x')
                ->and($button->getAttribute('data-title'))->toBe(ATTRIBUTE_ESCAPING_HOSTILE)
                ->and($button->getAttribute('data-url'))->toBe('https://example.com/?a=1&b="2"'.ATTRIBUTE_ESCAPING_HOSTILE);
        }
    });

    it('does not let the title or url open a tag in the surrounding markup', function () {
        $html = Blade::render(
            '<atom:sharer :url="$url" :title="$title" />',
            ['url' => '"><script>alert(1)</script>', 'title' => '"><img src=x onerror=alert(1)>'],
        );

        expect(attributeEscapingElements($html, 'script'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'img'))->toBeEmpty();
    });

    it('hands the share script the exact title and url, not a double-encoded copy', function () {
        $title = 'Tom & Jerry: "Fish & Chips" 你好，世界 — 50% off';
        $url = 'https://example.com/产品?q=茶&lang=zh_CN&x=a b';

        $html = Blade::render('<atom:sharer :url="$url" :title="$title" />', compact('url', 'title'));

        // The raw markup carries one level of entity encoding...
        expect($html)
            ->toContain('data-title="Tom &amp; Jerry: &quot;Fish &amp; Chips&quot; 你好，世界 — 50% off"')
            ->not->toContain('&amp;amp;');

        // ...which is what getAttribute() (all sharer.js reads) decodes back to the original.
        $button = attributeEscapingElements($html, 'button')[0];

        expect($button->getAttribute('data-title'))->toBe($title)
            ->and($button->getAttribute('data-url'))->toBe($url);
    });

    it('keeps the copy-link handler a single well-formed expression for a hostile url', function () {
        $html = Blade::render('<atom:sharer :url="$url" />', ['url' => ATTRIBUTE_ESCAPING_HOSTILE]);

        $handlers = array_filter(
            attributeEscapingElements($html, 'button'),
            fn ($button) => $button->hasAttribute('x-on:click.stop'),
        );

        expect($handlers)->toHaveCount(1);

        $handler = array_values($handlers)[0];

        expect(attributeEscapingNames($handler))->not->toContain('onmouseover')
            ->and($handler->getAttribute('x-on:click.stop'))->toStartWith('$clipboard(')
            // Js::from() hex-escapes quotes and angle brackets, so the payload cannot end the JS string.
            ->and($handler->getAttribute('x-on:click.stop'))->not->toContain('"')
            ->and($handler->getAttribute('x-on:click.stop'))->not->toContain('<');
    });
});

describe('embed', function () {
    it('keeps a hostile image source inside its src attribute', function () {
        $src = 'https://cdn.test/a"onerror="alert(1)"x=\'<>&.jpg';

        $images = attributeEscapingElements(Blade::render('<atom:embed :src="$src" />', compact('src')), 'img');

        expect($images)->toHaveCount(1)
            ->and(attributeEscapingNames($images[0]))->not->toContain('onerror')
            ->and($images[0]->getAttribute('src'))->toBe($src);
    });

    it('renders a legitimate image url with a query string exactly once-encoded', function () {
        $src = 'https://cdn.test/产品/photo.jpg?v=1&size=large';

        $html = Blade::render('<atom:embed :src="$src" />', compact('src'));

        expect($html)->toContain('src="https://cdn.test/产品/photo.jpg?v=1&amp;size=large"')
            ->and(attributeEscapingElements($html, 'img')[0]->getAttribute('src'))->toBe($src);
    });

    it('accepts http, https, relative and protocol-relative sources', function (string $src, string $tag) {
        $elements = attributeEscapingElements(Blade::render('<atom:embed :src="$src" />', compact('src')), $tag);

        expect($elements)->toHaveCount(1);
    })->with([
        'http image' => ['http://cdn.test/a.png', 'img'],
        'https image' => ['https://cdn.test/a.png', 'img'],
        'relative image' => ['/storage/a.png', 'img'],
        'bare relative image' => ['storage/a.png', 'img'],
        'protocol-relative image' => ['//cdn.test/a.png', 'img'],
        'https video' => ['https://cdn.test/a.mp4', 'video'],
        'youtube' => ['https://www.youtube.com/watch?v=abc', 'iframe'],
        'uppercase scheme' => ['HTTPS://cdn.test/a.png', 'img'],
        // a colon that is not a scheme (a space or slash comes first) is just a file name
        'colon after a space' => ['Report 2024: final.png', 'img'],
        'colon after a slash' => ['/files/report:final.png', 'img'],
        'colon in a query' => ['/files/a.png?next=a:b', 'img'],
        'colon in a relative segment' => ['docs/v1:2/a.png', 'img'],
        'space inside a file name' => ['/files/my photo.png', 'img'],
        // a URL that is already entity-encoded (the & in a query) is still a plain URL
        'encoded ampersand in a query' => ['https://cdn.test/a.png?x=1&amp;y=2', 'img'],
        'several encoded ampersands' => ['/files/a.png?x=1&amp;y=2&amp;z=3', 'img'],
    ]);

    it('drops a script-scheme source for every embed type', function (string $src) {
        $html = Blade::render('<atom:embed :src="$src" />', compact('src'));

        expect(attributeEscapingElements($html, 'img'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'iframe'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'source'))->toBeEmpty()
            ->and(strtolower($html))->not->toContain('javascript')
            // Falls back to the generic file icon rather than a broken element.
            ->and($html)->toContain('text-muted');
    })->with([
        'image' => ['javascript:alert(1)//x.jpg'],
        'video' => ['javascript:alert(1)//x.mp4'],
        'youtube' => ['javascript:/watch/;alert(1)'],
        'mixed case' => ['JaVaScRiPt:alert(1)//x.jpg'],
        'tab inside the scheme' => ["java\tscript:alert(1)//x.jpg"],
        'newline inside the scheme' => ["java\nscript:alert(1)//x.jpg"],
        'leading control chars' => ["\x01 javascript:alert(1)//x.jpg"],
        'vbscript' => ['vbscript:msgbox(1)//x.jpg'],
        'data html' => ['data:text/html,<script>alert(1)</script>//x.jpg'],
        'decimal entity' => ['&#106;avascript:alert(1)//x.jpg'],
        'padded decimal entity' => ['&#0000106avascript:alert(1)//x.jpg'],
        'hex entity' => ['&#x6A;avascript:alert(1)//x.jpg'],
        'entity for the colon' => ['javascript&colon;alert(1)//x.jpg'],
        'entity for the tab' => ['java&Tab;script:alert(1)//x.jpg'],
        'entity for the newline' => ['java&NewLine;script:alert(1)//x.mp4'],
        'entity for the whole scheme' => ['&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)//x.jpg'],
        'double-encoded entity' => ['&amp;#106;avascript:alert(1)//x.jpg'],
        'triple-encoded entity' => ['&amp;amp;#106;avascript:alert(1)//x.jpg'],
        'entity youtube shape' => ['&#106;avascript:/watch/;alert(1)'],
        'named entity encoded twice' => ['javascript&amp;colon;alert(1)//x.jpg'],
        'named entity encoded three times' => ['javascript&amp;amp;colon;alert(1)//x.mp4'],
        'encoded past the decode limit' => ['javascript&'.str_repeat('amp;', 6).'colon;alert(1)//x.jpg'],
    ]);

    it('drops an entity-encoded script scheme even when the host prints attributes without double encoding', function (string $src) {
        Blade::withoutDoubleEncoding();

        try {
            $html = Blade::render('<atom:embed :src="$src" />', compact('src'));
        } finally {
            Blade::withDoubleEncoding();
        }

        expect(attributeEscapingElements($html, 'img'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'iframe'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'source'))->toBeEmpty()
            ->and($html)->not->toContain('avascript');
    })->with([
        'entity for the colon' => ['javascript&colon;alert(1)//x.jpg'],
        'entity for the tab' => ['java&Tab;script:alert(1)//x.mp4'],
        'decimal entity' => ['&#106;avascript:alert(1)//x.jpg'],
        'hex entity' => ['&#x6A;avascript:alert(1)//x.mp4'],
        'youtube shape' => ['&#106;avascript:/watch/;alert(1)'],
    ]);

    it('keeps a hostile video source inside its source element', function () {
        $src = 'https://cdn.test/a"onerror="alert(1)"x=\'<>&.mp4';

        $html = Blade::render('<atom:embed :src="$src" />', compact('src'));
        $sources = attributeEscapingElements($html, 'source');

        expect($sources)->toHaveCount(1)
            ->and(attributeEscapingNames($sources[0]))->toBe(['src', 'type'])
            ->and($sources[0]->getAttribute('src'))->toBe($src)
            ->and(attributeEscapingNames(attributeEscapingElements($html, 'video')[0]))->not->toContain('onerror');
    });

    it('keeps a hostile YouTube source inside its iframe', function () {
        $src = 'https://www.youtube.com/watch"onerror="alert(1)"x=\'<>&';

        $html = Blade::render('<atom:embed :src="$src" />', compact('src'));
        $frames = attributeEscapingElements($html, 'iframe');

        expect($frames)->toHaveCount(1)
            ->and(attributeEscapingNames($frames[0]))->not->toContain('onerror')
            ->and($frames[0]->getAttribute('src'))->toBe($src);
    });
});

describe('error', function () {
    it('shows a validation message that echoes user input as text', function () {
        $message = 'The value "<img src=x onerror=alert(1)>" & <script>alert(2)</script> is not valid.';

        $html = Blade::render('<atom:error :errors="[$message]" />', compact('message'));

        expect(attributeEscapingElements($html, 'img'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'script'))->toBeEmpty()
            ->and(attributeEscapingElements($html, 'li')[0]->textContent)->toBe($message);
    });

    it('renders a plain message with an ampersand and CJK text once-encoded', function () {
        $html = Blade::render('<atom:error :errors="[$message]" />', ['message' => 'Tom & Jerry 不能为空']);

        expect($html)->toContain('Tom &amp; Jerry 不能为空')->not->toContain('&amp;amp;');
    });
});
