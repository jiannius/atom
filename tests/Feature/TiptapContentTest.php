<?php

use Jiannius\Atom\Tiptap\Content;

function renderDoc(array $content): string
{
    return Content::render(json_encode(['type' => 'doc', 'content' => $content]));
}

describe('Content::render', function () {
    it('renders image float/align/width as data-attrs + style', function () {
        $html = renderDoc([['type' => 'image', 'attrs' => ['src' => 'a.png', 'float' => 'left', 'width' => '50%']]]);

        expect($html)
            ->toContain('data-float="left"')
            ->toContain('data-width="50%"')
            ->toContain('float: left')
            ->toContain('width: 50%');
    });

    it('renders fontSize preset as a class and custom as inline style', function () {
        $preset = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'textStyle', 'attrs' => ['fontSize' => 'lg']]], 'text' => 'x']]]]);
        $custom = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'textStyle', 'attrs' => ['fontSize' => '1.25rem']]], 'text' => 'x']]]]);

        expect($preset)->toContain('text-lg');
        expect($custom)->toContain('font-size: 1.25rem');
    });

    it('renders youtube as an embed iframe', function () {
        $html = renderDoc([['type' => 'youtube', 'attrs' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']]]);

        expect($html)
            ->toContain('data-youtube-video')
            ->toContain('youtube.com/embed/dQw4w9WgXcQ');
    });

    it('renders youtube with start time and default dimensions', function () {
        $html = renderDoc([['type' => 'youtube', 'attrs' => ['src' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'start' => 30]]]);

        expect($html)
            ->toContain('start=30')
            ->toContain('width="640"')
            ->toContain('height="480"');
    });

    it('renders mention as span.mention with the label', function () {
        $html = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'mention', 'attrs' => ['id' => '1', 'label' => 'Alice']]]]]);

        expect($html)
            ->toContain('class="mention"')
            ->toContain('data-type="mention"')
            ->toContain('@Alice');
    });

    it('escapes malicious mention label to prevent XSS', function () {
        $html = renderDoc([['type' => 'paragraph', 'content' => [['type' => 'mention', 'attrs' => ['id' => '99', 'label' => '<img src=x onerror=alert(1)>']]]]]);

        expect($html)
            ->not->toContain('<img src=x')
            ->toContain('&lt;img');
    });

    it('renders highlight, link, text-align and tables', function () {
        $html = renderDoc([
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'H']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'marks' => [['type' => 'highlight', 'attrs' => ['color' => '#ff0']]], 'text' => 'hl'],
                ['type' => 'text', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://x.com']]], 'text' => 'lnk'],
            ]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'c']]]]]]]]],
        ]);

        expect($html)
            ->toContain('text-align: center')
            ->toContain('background-color: #ff0')
            ->toContain('href="https://x.com"')
            ->toContain('<table>')
            ->toContain('<td');
    });

    it('round-trips legacy HTML (renders it as-is structurally)', function () {
        expect(Content::render('<p>legacy <strong>bold</strong></p>'))
            ->toContain('<strong>bold</strong>');
    });

    it('renders empty for empty input', function () {
        expect(Content::render(''))->toBe('');
        expect(Content::render(null))->toBe('');
    });
});

it('the <atom:tiptap.content> component renders stored JSON', function () {
    $json = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]]]]);
    $html = renderBlade('<atom:tiptap.content :content="$c" />', ['c' => $json]);

    expect($html)
        ->toContain('editor-content')
        ->toContain('<p>hello</p>');
});
