<?php

namespace Jiannius\Atom\Tiptap;

use Jiannius\Atom\Tiptap\Extensions\AtomCodeBlock;
use Jiannius\Atom\Tiptap\Extensions\AtomColor;
use Jiannius\Atom\Tiptap\Extensions\AtomHighlight;
use Jiannius\Atom\Tiptap\Extensions\AtomImage;
use Jiannius\Atom\Tiptap\Extensions\AtomLink;
use Jiannius\Atom\Tiptap\Extensions\AtomMention;
use Jiannius\Atom\Tiptap\Extensions\AtomTextAlign;
use Jiannius\Atom\Tiptap\Extensions\FontSize;
use Jiannius\Atom\Tiptap\Extensions\Youtube;
use Tiptap\Editor;

class Content
{
    /**
     * Default limits for sanitize(): 128 KB and 5000 tags. Cost tracks the tag
     * count, not the byte count (about 3 KB of memory per tag, worst case), so
     * both are held. The measured worst case at the limit is ~17 MB and ~0.5 s;
     * a long chat message is a few KB and a few dozen tags.
     */
    public const SANITIZE_MAX_BYTES = 131072;

    public const SANITIZE_MAX_TAGS = 5000;

    /**
     * Limits for render(), which reads stored content: a long document is
     * legitimate here, so they are looser (worst case ~33 MB at 10000 tags).
     */
    protected const RENDER_MAX_BYTES = 2097152;

    protected const RENDER_MAX_TAGS = 10000;

    /** Output larger than this many times the byte limit is refused. */
    protected const OUTPUT_FACTOR = 4;

    /** The prefix of tiptap-php's `<pre>` placeholder (Tiptap\Utils\Minify). */
    protected const MINIFY_TOKEN = 'MINIFYHTML';

    /**
     * The full PHP extension set mirroring the JS engine. Used for both SSR
     * rendering and the HTML->JSON migration so fidelity is defined once.
     *
     * @return array<int, object>
     */
    public static function extensions(): array
    {
        return [
            new \Tiptap\Extensions\StarterKit(['codeBlock' => false]),
            new AtomCodeBlock,
            new \Tiptap\Marks\Underline,
            new \Tiptap\Marks\Subscript,
            new \Tiptap\Marks\Superscript,
            new AtomHighlight(['multicolor' => true]),
            new AtomLink,
            new \Tiptap\Marks\TextStyle,
            new AtomColor(['types' => ['textStyle']]),
            new FontSize(['types' => ['textStyle']]),
            new AtomTextAlign(['types' => ['heading', 'paragraph']]),
            new \Tiptap\Nodes\Table,
            new \Tiptap\Nodes\TableRow,
            new \Tiptap\Nodes\TableHeader,
            new \Tiptap\Nodes\TableCell,
            new AtomImage,
            new Youtube,
            new AtomMention,
        ];
    }

    /**
     * Render stored content (Tiptap JSON string/array, or legacy HTML) to HTML.
     * Stored content is untrusted, so a value the renderer refuses (over the
     * RENDER_MAX_* limits, or carrying tiptap-php's reserved placeholder) or
     * cannot handle (e.g. non-scalar attrs) renders empty rather than taking
     * the page down. Only an unexpected failure is reported.
     */
    public static function render(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return static::convert($value, static::RENDER_MAX_BYTES, static::RENDER_MAX_TAGS);
    }

    /**
     * Clean HTML that came from the browser (the chat composer's output), so it
     * is safe to store and to print. It is parsed through the same schema and
     * the same hardened extension set as render(), and what comes back is the
     * schema's own serialisation: nodes and marks the editor can produce, only
     * href / src values that pass the allow-lists, and none of the input's
     * attributes. Scripts, event handlers, style / class values that fail the
     * allow-lists, javascript: / data: links, non-YouTube iframes and unknown
     * tags are all dropped. It returns HTML: content a JSON editor
     * (`<atom:tiptap>`) sends is already rendered safely by render(), and
     * should be stored as JSON, not passed through here.
     *
     * A Tiptap JSON document (a string or an array with a `type` key) is
     * accepted too. Anything else, `'42'` and `'null'` included, is text.
     * A Stringable is cast to a string.
     *
     * '' comes back for empty input, for input that is refused and for input
     * that fails, so a host that must not store an empty message checks for ''.
     * To tell a refusal from an empty message, ask sanitizeRefuses().
     * Refusals (over $maxBytes, over $maxTags, or carrying the reserved
     * placeholder) are silent, so a client cannot flood the log; only an
     * unexpected failure is reported.
     *
     * Mention ids and labels are kept as sent: they are escaped, but not
     * checked. A host that acts on a mention (a notification, a link to a
     * record) must look the id up itself, scoped to what the user may mention.
     *
     * @param  int  $maxBytes  Refuse anything larger. Output over 4x this is refused too.
     * @param  int  $maxTags  Refuse HTML with more `<` characters than this. Cost tracks
     *                        the tag count, not the byte count: about 3 KB of memory per
     *                        tag at worst, so 5000 tags is ~17 MB and ~0.5 s. Raise both
     *                        only for fields that hold long documents.
     */
    public static function sanitize(mixed $html, int $maxBytes = self::SANITIZE_MAX_BYTES, int $maxTags = self::SANITIZE_MAX_TAGS): string
    {
        return static::convert($html, $maxBytes, $maxTags);
    }

    /**
     * Whether sanitize() would refuse this input without parsing it: over the
     * byte or tag limit, or carrying tiptap-php's reserved placeholder. False
     * for input that is merely empty or not a string, so a host can tell a
     * refused message ("too long") from an empty one.
     */
    public static function sanitizeRefuses(mixed $html, int $maxBytes = self::SANITIZE_MAX_BYTES, int $maxTags = self::SANITIZE_MAX_TAGS): bool
    {
        $text = static::input($html);

        return $text !== null && static::prepare($text, $maxBytes, $maxTags) === null;
    }

    /**
     * Whether an HTML string carries tiptap-php's reserved `<pre>` placeholder
     * prefix, which no real content has and which must never reach the parser
     * (see prepare()).
     */
    public static function carriesPlaceholder(string $html): bool
    {
        return stripos($html, static::MINIFY_TOKEN) !== false;
    }

    /**
     * Parse content through the extension set and serialise it back to HTML.
     * A refused value comes back empty and silently; a value the parser cannot
     * handle is reported and comes back empty.
     */
    protected static function convert(mixed $value, int $maxBytes, int $maxTags): string
    {
        try {
            $text = static::input($value);
            $content = $text === null ? null : static::prepare($text, $maxBytes, $maxTags);

            if ($content === null) {
                return '';
            }

            $html = (new Editor(['extensions' => static::extensions()]))
                ->setContent($content)
                ->getHTML();

            return strlen($html) > $maxBytes * static::OUTPUT_FACTOR ? '' : $html;
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Reduce a value to the string to parse: a Stringable is cast, an array is
     * accepted only as a document (a `type` key) and encoded, invalid UTF-8 is
     * scrubbed (the parser's minifier returns null on it, which is a
     * TypeError). Null when there is nothing to parse.
     */
    protected static function input(mixed $value): ?string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_array($value)) {
            $value = isset($value['type']) ? json_encode($value) : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim(mb_scrub($value, 'UTF-8'));

        return $value === '' ? null : $value;
    }

    /**
     * What to hand the parser, or null when the value is refused.
     *
     * A Tiptap document (JSON with a `type` key) goes through as an array. Everything
     * else is HTML, held to the limits, and refused if it carries the token
     * Minify uses for its `<pre>` placeholders: tiptap-php replaces each `<pre>`
     * with `%MINIFYHTML<md5(REQUEST_TIME)>N%` and str_replace()s them back, the
     * hash is guessable, and a placeholder written inside a `<pre>` expands
     * again for every level of nesting, so a few KB exhausts memory. Only
     * HTML reaches Minify; a document's text never does.
     *
     * @return string|array<string, mixed>|null
     */
    protected static function prepare(string $text, int $maxBytes, int $maxTags): string|array|null
    {
        if (strlen($text) > $maxBytes) {
            return null;
        }

        $decoded = json_decode($text, true);
        $isJson = json_last_error() === JSON_ERROR_NONE;

        if ($isJson && is_array($decoded) && isset($decoded['type'])) {
            return $decoded;
        }

        if (static::carriesPlaceholder($text) || substr_count($text, '<') > $maxTags) {
            return null;
        }

        // the parser takes any string that decodes as JSON for JSON: an empty comment makes it HTML
        return $isJson ? '<!---->'.$text : $text;
    }

    /**
     * Unserialize a stored legacy value without instantiating any class.
     * Legacy AsEditorContent rows are serialize()'d strings, never objects, so
     * classes are refused; a payload holding an object comes back as null.
     *
     * @return array{0: bool, 1: mixed} [whether $value was serialized, the value]
     */
    public static function unserialize(string $value): array
    {
        $data = @unserialize($value, ['allowed_classes' => false]);

        if ($data === false && $value !== 'b:0;') {
            return [false, null];
        }

        $hasObject = is_object($data);

        if (is_array($data)) {
            array_walk_recursive($data, function ($item) use (&$hasObject) {
                $hasObject = $hasObject || is_object($item);
            });
        }

        return [true, $hasObject ? null : $data];
    }
}
