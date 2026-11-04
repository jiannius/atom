<?php

namespace Jiannius\Atom\Tiptap;

use Illuminate\Support\Str;

class Markdown
{
    /**
     * The longest markdown source that is converted: 32 KB, about five thousand words, which is a long answer. Over it
     * the caller falls back to plain text. The converter's memory follows the source, at worst about 1.4 KB for every
     * byte of it (a line of nothing but `[`: 46 MB at this size, 184 MB at 128 KB, a fatal under PHP's 128 MB default),
     * so this is the bound that keeps an answer somebody else wrote from taking the page down.
     */
    public const MAX_BYTES = 32768;

    /**
     * Limits for the sanitiser that reads what the converter printed. Markdown prints about three times its size at
     * worst (escaped `<p>` is nine bytes of HTML for three), so the byte limit is four times MAX_BYTES; the tag limit
     * leaves room for a table of a few hundred cells (a cell is four tags once the schema wraps its text in a paragraph),
     * about 30 MB at worst.
     */
    public const SANITIZE_MAX_BYTES = 131072;

    public const SANITIZE_MAX_TAGS = 10000;

    /**
     * How deep blocks may nest (quotes in lists in quotes). Past this the converter reads the rest as text.
     */
    public const MAX_NESTING = 16;

    /**
     * The converter step on its own: GitHub-flavoured markdown to HTML that is NOT yet safe to print (a task list prints an
     * `<input>`, a table column's alignment prints a `style`), only hardened: raw HTML is escaped to text, a link with an
     * unsafe scheme loses its address (the text stays), an image becomes its alt text, and blocks stop nesting at
     * MAX_NESTING. toHtml() always runs the sanitiser over this.
     */
    public static function convert(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => static::MAX_NESTING,
        ], [new MarkdownTextImages]);
    }

    /**
     * Markdown to schema-only HTML, or null when the caller should show the text as it is: the source is too long,
     * the converter fails, or the sanitiser refuses or empties what it printed. Null is never returned for empty
     * input, which is '' (nothing to show).
     *
     * The converter escapes raw HTML (`html_input`), drops unsafe link schemes (`allow_unsafe_links`), reads the markdown
     * images as their alt text and stops nesting at MAX_NESTING. What it prints is then sanitised by Content::sanitize(),
     * the gate for HTML from outside, so a gap in the converter is not a gap in the page.
     */
    public static function toHtml(mixed $markdown): ?string
    {
        if (is_array($markdown) || (is_object($markdown) && ! $markdown instanceof \Stringable)) {
            return '';
        }

        $source = (string) $markdown;

        if (trim($source) === '') {
            return '';
        }

        if (strlen($source) > static::MAX_BYTES) {
            return null;
        }

        try {
            $html = static::convert($source);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $clean = Content::sanitize($html, static::SANITIZE_MAX_BYTES, static::SANITIZE_MAX_TAGS);

        return $clean === '' ? null : $clean;
    }
}
