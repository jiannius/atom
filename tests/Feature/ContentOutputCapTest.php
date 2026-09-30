<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Tiptap\Content;

/**
 * sanitize() holds its OUTPUT to the tag limit, and sanitizeRefuses() agrees.
 * A document of N nodes prints up to 4N tags (an empty table is one node and
 * `<table><tbody></tbody></table>`), so a document under the node limit could
 * be stored, and printed on every view, at four times the tags the input
 * limit allowed.
 */

/**
 * @return array<string, mixed>
 */
function emptyTables(int $count): array
{
    return ['type' => 'doc', 'content' => array_fill(0, $count, ['type' => 'table'])];
}

/**
 * @return array<string, mixed>
 */
function plainParagraphs(int $count): array
{
    return ['type' => 'doc', 'content' => array_fill(0, $count, ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]])];
}

describe('Content::sanitize: the output tag cap', function () {
    it('refuses a document that prints more tags than the limit, though it is under the node limit', function () {
        $this->mock(ExceptionHandler::class)->shouldNotReceive('report');
        Log::spy();
        // 5000 nodes (the doc and 4999 tables), which the input limit allows: 19,996 tags out
        $json = json_encode(emptyTables(Content::SANITIZE_MAX_TAGS - 1));

        expect(strlen($json))->toBeLessThan(Content::SANITIZE_MAX_BYTES);
        expect(substr_count(Content::render($json), '<'))->toBe(19996);
        expect(Content::sanitize($json))->toBe('');
        expect(Content::sanitizeRefuses($json))->toBeTrue();
        Log::shouldNotHaveReceived('warning');
    });

    it('counts tags, not nodes: a document of about as many nodes is accepted when it prints fewer', function () {
        // 2499 paragraphs and their text: 4999 nodes, 150 KB and 4998 tags
        $json = json_encode(plainParagraphs(2499));

        expect(substr_count(Content::sanitize($json, maxBytes: 1 << 20), '<'))->toBe(4998);
        expect(Content::sanitizeRefuses($json, maxBytes: 1 << 20))->toBeFalse();
    });

    it('accepts output at the limit and refuses one tag over, and follows $maxTags', function () {
        $atLimit = json_encode(emptyTables(1250));
        $over = json_encode(emptyTables(1251));

        expect(substr_count(Content::sanitize($atLimit), '<'))->toBe(Content::SANITIZE_MAX_TAGS);
        expect(Content::sanitize($over))->toBe('');
        expect(Content::sanitizeRefuses($atLimit))->toBeFalse();
        expect(Content::sanitizeRefuses($over))->toBeTrue();
        expect(Content::sanitize($over, maxTags: 5004))->not->toBe('');
        expect(Content::sanitizeRefuses($over, maxTags: 5004))->toBeFalse();
    });

    it('holds HTML to it as well, where the parser adds tags', function () {
        // three tags in, four out: `<p><b>a</b>x` prints `<p><strong>a</strong>x</p>`
        $html = str_repeat('<p><b>a</b>x', 1300);

        expect(substr_count($html, '<'))->toBe(3900);
        expect(Content::sanitize($html))->toBe('');
        expect(Content::sanitizeRefuses($html))->toBeTrue();
        expect(substr_count(Content::sanitize($html, maxTags: 5200), '<'))->toBe(5200);
        expect(Content::sanitizeRefuses($html, maxTags: 5200))->toBeFalse();
    });

    it('agrees with sanitizeRefuses() for every input, at several limits', function (string $input) {
        foreach ([1, 3, 8, 50, 400, 5000] as $maxTags) {
            $clean = Content::sanitize($input, maxTags: $maxTags);
            $refuses = Content::sanitizeRefuses($input, maxTags: $maxTags);

            // a refusal comes back empty, and a message with something in it is never a refusal
            expect($clean !== '' && $refuses)->toBeFalse("sanitize() kept it but sanitizeRefuses() refused, at {$maxTags}");
            expect(substr_count($clean, '<'))->toBeLessThanOrEqual($maxTags);

            if ($clean === '' && ! $refuses) {
                expect(Content::render($input))->toBe('');
            }
        }
    })->with([
        'empty tables' => [json_encode(emptyTables(300))],
        'paragraphs' => [json_encode(plainParagraphs(300))],
        'an empty document' => ['{"type":"doc","content":[]}'],
        'list items' => ['<ul>'.str_repeat('<li>a', 100)],
        'a chat message' => ['<p>Hello <strong>team</strong> <a href="https://example.com/">link</a></p><ul><li><p>one</p></li></ul>'],
        'a script only' => ['<script>alert(1)</script>'],
        'blank' => ['   '],
    ]);

    it('does not cap render(), which reads stored content under its own limits', function () {
        $json = json_encode(emptyTables(1500));

        expect(Content::sanitize($json))->toBe('');
        expect(substr_count(Content::render($json), '<'))->toBe(6000);
    });

    it('lets realistic chat and editor content through: the output is about as many tags as the input', function () {
        $paragraph = ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Hello '],
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/']]]],
        ]];
        $cell = fn () => ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'cell']]]]];
        $table = ['type' => 'table', 'content' => array_fill(0, 20, ['type' => 'tableRow', 'content' => array_fill(0, 20, $cell())])];
        $article = json_encode(['type' => 'doc', 'content' => array_merge(array_fill(0, 300, $paragraph), [$table])]);

        // a long article: 300 paragraphs of marked text and a 20 x 20 table
        expect(strlen($article))->toBeLessThan(Content::SANITIZE_MAX_BYTES);
        expect(Content::sanitizeRefuses($article))->toBeFalse();
        expect(substr_count(Content::sanitize($article), '<'))->toBeLessThan(Content::SANITIZE_MAX_TAGS);
    });
});
