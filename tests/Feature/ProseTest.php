<?php

use Illuminate\Support\Facades\Exceptions;
use Jiannius\Atom\Tiptap\Markdown;

/**
 * <atom:prose> (issue #160): one typography wrapper over three sources, in this order: a markdown string (converted and
 * sanitised), stored content (Content::render()) and the slot (the host's own markup).
 */
describe('<atom:prose>', function () {
    it('renders markdown as the editor schema inside the content wrapper', function () {
        $html = renderBlade('<atom:prose :markdown="$md" />', ['md' => "# Title\n\nSome **bold** text.\n\n- one\n- two"]);

        expect($html)
            ->toContain('class="editor-content editor-prose')
            ->toContain('data-atom-prose')
            ->toContain('data-size="base"')
            ->toContain('<h1>Title</h1>')
            ->toContain('<strong>bold</strong>')
            ->toContain('<li>one</li>')
            ->not->toContain('data-fallback');
    });

    it('converts markdown cut mid-character, with nothing reported', function () {
        Exceptions::fake();

        $html = Markdown::toHtml("**hello** \xE2\x82 trunc");

        expect($html)->toContain('<strong>hello</strong>')->toContain('trunc');
        Exceptions::assertNothingReported();
    });

    it('loads the content stylesheet', function () {
        expect(renderBlade('<atom:prose markdown="x" />'))->toContain('tiptap')->toContain('rel="stylesheet"');
    });

    it('takes size sm for a chat bubble and reads any other size as base', function (?string $size, string $expected) {
        $html = renderBlade('<atom:prose :markdown="\'x\'" :size="$size" />', ['size' => $size]);

        expect($html)->toContain('data-size="'.$expected.'"');
    })->with([['sm', 'sm'], [null, 'base'], ['base', 'base'], ['xl', 'base'], ['"><script>', 'base']]);

    it('keeps the host\'s own classes and attributes on the wrapper', function () {
        $html = renderBlade('<atom:prose markdown="x" class="px-4" data-testid="reply" />');

        expect($html)->toContain('px-4')->toContain('data-testid="reply"')->toContain('editor-prose');
    });

    it('prints script, handlers and unsafe links in markdown as text or nothing', function () {
        $html = renderBlade('<atom:prose :markdown="$md" />', ['md' => "<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[a](javascript:alert(1)) ![b](https://evil.test/p.png)"]);

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        expect($dom->getElementsByTagName('script')->length)->toBe(0)
            ->and($dom->getElementsByTagName('img')->length)->toBe(0)
            ->and($dom->getElementsByTagName('a')->length)->toBe(0)
            ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('prints the text as it is, escaped, when the markdown is over the limit', function () {
        $md = "<script>alert(1)</script>\n# not a heading\n".str_repeat('word ', Markdown::MAX_BYTES / 4);

        $html = renderBlade('<atom:prose :markdown="$md" />', ['md' => $md]);

        expect(strlen($md))->toBeGreaterThan(Markdown::MAX_BYTES)
            ->and($html)->toContain('data-fallback')
            ->toContain('whitespace-pre-line')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->toContain('# not a heading')
            ->not->toContain('<script')
            ->not->toContain('<h1>');
    });

    it('prints the text as it is when the sanitiser refuses what the converter printed', function () {
        $rows = intdiv(Markdown::SANITIZE_MAX_TAGS, 4) + 100;
        $md = "| a |\n|---|\n".str_repeat("| 1 |\n", $rows);

        $html = renderBlade('<atom:prose :markdown="$md" />', ['md' => $md]);

        expect(Markdown::toHtml($md))->toBeNull()
            ->and($html)->toContain('data-fallback')->not->toContain('<table')->toContain('| 1 |');
    });

    it('renders nothing inside the wrapper for empty markdown, and never the slot beside it', function () {
        $html = renderBlade('<atom:prose markdown=""><p>slot</p></atom:prose>');

        expect($html)->toContain('data-atom-prose')->not->toContain('slot')->not->toContain('data-fallback');
    });

    it('renders stored content through Content::render()', function () {
        $doc = json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Stored']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ]]);

        $html = renderBlade('<atom:prose :content="$doc" size="sm" />', ['doc' => $doc]);

        expect($html)->toContain('<p>Stored</p>')->toContain('data-size="sm"')->not->toContain('javascript:');
    });

    it('reads markdown before content, and content before the slot', function () {
        expect(renderBlade('<atom:prose markdown="**m**" content="c"><p>slot</p></atom:prose>'))->toContain('<strong>m</strong>')->not->toContain('slot')
            ->and(renderBlade('<atom:prose content="<p>c</p>"><p>slot</p></atom:prose>'))->toContain('<p>c</p>')->not->toContain('slot');
    });

    it('prints the slot as written, the host\'s own markup', function () {
        $html = renderBlade('<atom:prose><p>Hello <b>there</b></p></atom:prose>');

        expect($html)->toContain('editor-prose')->toContain('<p>Hello <b>there</b></p>');
    });

    it('sits in a timeline message bubble', function () {
        $html = renderBlade('<atom:timeline kind="message"><atom:timeline.item speaker="Assistant"><atom:prose markdown="Hi **there**" size="sm" /></atom:timeline.item></atom:timeline>');

        expect($html)->toContain('data-atom-timeline-bubble')->toContain('data-atom-prose')->toContain('<strong>there</strong>');
    });

    it('prints markdown only through the sanitiser, never through a raw echo of its source', function () {
        // the one unescaped echo in the component prints $html, which only Markdown::toHtml() and Content::render() produce
        $source = file_get_contents(__DIR__.'/../../components/prose.blade.php');

        expect(substr_count($source, '{!!'))->toBe(1)
            ->and($source)->toContain('{!! $html !!}')
            ->and($source)->not->toContain('{!! $markdown')
            ->and($source)->not->toContain('{!! $content')
            ->and($source)->not->toContain('{!! $text');
    });

    it('has docs demos that render, the markdown one in a timeline message', function () {
        $markdown = view('atom::docs.demos.prose.markdown')->render();

        expect($markdown)->toContain('data-atom-prose')->toContain('data-atom-timeline-bubble')->toContain('<table')->toContain('<ol>')->not->toContain('data-fallback')
            ->and(view('atom::docs.demos.prose.content')->render())->toContain('<h2>Site notes</h2>')
            ->and(view('atom::docs.demos.prose.slot')->render())->toContain('<strong>your own</strong>');
    });
});
