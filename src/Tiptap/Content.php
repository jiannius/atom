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
     * count (an HTML tag, or a node of a JSON document), not the byte count:
     * about 3 KB of memory per tag, worst case, so both are held. The measured
     * worst case at the limit is ~16 MB and ~0.2 s; a long chat message is a
     * few KB and a few dozen tags.
     */
    public const SANITIZE_MAX_BYTES = 131072;

    public const SANITIZE_MAX_TAGS = 5000;

    /**
     * Default limits for render(), which reads stored content, so a long
     * document is legitimate: 2 MB and 20000 tags (worst case about 64 MB for HTML, up to about 82 MB for a JSON document).
     * A host raises or lowers them with `atom.editor.render_max_bytes` and
     * `atom.editor.render_max_tags`.
     */
    public const RENDER_MAX_BYTES = 2097152;

    public const RENDER_MAX_TAGS = 20000;

    /** Output larger than this many times the byte limit is refused. */
    protected const OUTPUT_FACTOR = 4;

    /**
     * A run of whitespace longer than this, outside a `<pre>`, is collapsed to
     * one space before parsing. tiptap-php's minifier trims with `^\s+|\s+$`
     * and `\s+(<tag`, which rescan a run from every position in it: quadratic,
     * so 120 KB of spaces took 98 s. HTML collapses whitespace itself, so this
     * changes nothing a reader sees, and content with no such run is passed on
     * byte for byte. Inside a `<pre>` nothing is touched: the minifier swaps
     * the block for a placeholder before it trims.
     */
    protected const MAX_WHITESPACE_RUN = 32;

    /**
     * A `<pre>` block longer than this is refused: past ~1M characters the
     * minifier's regex hits pcre.backtrack_limit and returns null, which is a
     * TypeError on every view. Real code blocks are a few KB.
     */
    protected const MAX_PRE_LENGTH = 500000;

    /**
     * Unclosed `<pre>` tags times the length they scan to. The minifier's
     * regex rescans to the end of the input from each one, so cost is their
     * product: 5000 unclosed tags in 130 KB took 8 s. Under 30 million (~0.4 s)
     * they are left alone; over it, one `</pre>` is appended, as the HTML
     * parser would close them at the end anyway, which leaves one block that
     * is scanned once. A `<pre` with no `>` after it can't be fixed that way
     * and counts against the same budget.
     */
    protected const MAX_UNCLOSED_PRE_COST = 30000000;

    /**
     * `<pre>` tags times the input length. The minifier swaps each for a
     * placeholder and str_replace()s them all back over the whole input, so
     * cost is their product: 10000 blocks in 120 KB took 2.3 s. 500 million is
     * ~1 s: 250 code blocks in a 2 MB document, far past real content.
     */
    protected const MAX_PRE_COST = 500000000;

    /**
     * Refusals already logged in this process, so a value that is rendered
     * many times in a request logs once.
     *
     * @var array<string, true>
     */
    protected static array $logged = [];

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
     * Stored content is untrusted, so a value the renderer refuses renders
     * empty rather than taking the page down: over the render limits
     * (`atom.editor.render_max_bytes` / `render_max_tags`, default 2 MB and
     * 20000 tags), a `<pre>` or whitespace run past what the parser survives,
     * carrying tiptap-php's reserved placeholder, or not a valid document.
     * A refusal is logged as a warning (once per value per process) so a host
     * can notice blanked content, except an invalid document, which only a
     * hostile client can store. Only an unexpected failure is reported.
     */
    public static function render(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return static::convert(
            $value,
            (int) config('atom.editor.render_max_bytes', static::RENDER_MAX_BYTES),
            (int) config('atom.editor.render_max_tags', static::RENDER_MAX_TAGS),
            logRefusal: true,
        );
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
     * Refusals are silent, so a client cannot flood the log; only an
     * unexpected failure is reported.
     *
     * Mention ids and labels are kept as sent: they are escaped, but not
     * checked. A host that acts on a mention (a notification, a link to a
     * record) must look the id up itself, scoped to what the user may mention.
     *
     * @param  int  $maxBytes  Refuse anything larger. Output over 4x this is refused too,
     *                         so a small $maxBytes also caps the output at 4x that.
     * @param  int  $maxTags  Refuse HTML with more `<` characters, or a document with more
     *                        nodes and marks, than this. Cost tracks this, not the byte
     *                        count: about 3 KB of memory per tag at worst, so 5000 tags is
     *                        ~16 MB and ~0.2 s. Raise both only for fields that hold long
     *                        documents.
     */
    public static function sanitize(mixed $html, int $maxBytes = self::SANITIZE_MAX_BYTES, int $maxTags = self::SANITIZE_MAX_TAGS): string
    {
        return static::convert($html, $maxBytes, $maxTags);
    }

    /**
     * Whether sanitize() would refuse this input without parsing it: over the
     * byte or tag limit, a whitespace run or `<pre>` the parser can't survive,
     * tiptap-php's reserved placeholder, or an invalid document. False for
     * input that is merely empty or not a string, so a host can tell a refused
     * message ("too long") from an empty one.
     */
    public static function sanitizeRefuses(mixed $html, int $maxBytes = self::SANITIZE_MAX_BYTES, int $maxTags = self::SANITIZE_MAX_TAGS): bool
    {
        $text = static::input($html);

        return $text !== null && static::inspect($text, $maxBytes, $maxTags)[1] !== null;
    }

    /**
     * Whether an HTML string carries tiptap-php's reserved `<pre>` placeholder
     * prefix, which no real content has and which must never reach the parser
     * (see inspect()).
     */
    public static function carriesPlaceholder(string $html): bool
    {
        return stripos($html, 'MINIFYHTML') !== false;
    }

    /**
     * Parse content through the extension set and serialise it back to HTML.
     * A refused value comes back empty without a report (render() logs a
     * warning); a value the parser cannot handle is reported and comes back
     * empty.
     */
    protected static function convert(mixed $value, int $maxBytes, int $maxTags, bool $logRefusal = false): string
    {
        try {
            $text = static::input($value);

            if ($text === null) {
                return '';
            }

            [$content, $refusal] = static::inspect($text, $maxBytes, $maxTags);

            if ($content === null) {
                return static::refused($logRefusal, $text, $refusal, $maxBytes, $maxTags);
            }

            // a document with no <body> has no content, and tiptap-php's parser throws on it
            if (is_string($content) && ! static::hasBody($content)) {
                return '';
            }

            $editor = (new Editor(['extensions' => static::extensions()]))->setContent($content);

            $document = $editor->getDocument();

            if (static::dedupeMarks($document)) {
                $editor->setContent($document);
            }

            $html = $editor->getHTML();

            if (strlen($html) > $maxBytes * static::OUTPUT_FACTOR) {
                return static::refused($logRefusal, $text, 'output of '.strlen($html).' bytes', $maxBytes, $maxTags);
            }

            return $html;
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * The empty result of a refusal. render() logs it as a warning, once per
     * value per process, with which limit tripped; sanitize() stays silent.
     */
    protected static function refused(bool $log, string $text, ?string $reason, int $maxBytes, int $maxTags): string
    {
        $key = md5($text);

        if ($log && $reason !== null && $reason !== 'invalid document' && ! isset(static::$logged[$key])) {
            if (count(static::$logged) >= 100) {
                static::$logged = [];
            }

            static::$logged[$key] = true;

            \Illuminate\Support\Facades\Log::warning('Content::render() refused stored content and rendered it empty.', [
                'reason' => $reason,
                'bytes' => strlen($text),
                'max_bytes' => $maxBytes,
                'max_tags' => $maxTags,
                'sha1' => sha1($text),
            ]);
        }

        return '';
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
     * What to hand the parser, and why it was refused when it is not.
     *
     * A Tiptap document (JSON with a `type` key) goes through as an array once
     * it is a well-formed document within the node limit, with the attributes
     * tiptap-php can't take repaired. Everything else is HTML, held to the
     * limits, normalised where the parser would be slow on real content, and
     * refused only when it can't be made safe:
     *
     * - it carries the token Minify uses for its `<pre>` placeholders:
     *   tiptap-php replaces each `<pre>` with `%MINIFYHTML<md5(REQUEST_TIME)>N%`
     *   and str_replace()s them back, the hash is guessable, and a placeholder
     *   written inside a `<pre>` expands again for every level of nesting, so
     *   a few KB exhausts memory. Only HTML reaches Minify; a document's text
     *   never does;
     * - a `<pre>` block, or a count of `<pre>` tags, that Minify's regexes fail
     *   on or take quadratic time on (see normalisePre());
     * - whitespace runs are collapsed rather than refused (see
     *   collapseWhitespace()).
     *
     * @return array{0: string|array<string, mixed>|null, 1: string|null} [content, refusal]
     */
    protected static function inspect(string $text, int $maxBytes, int $maxTags): array
    {
        if (strlen($text) > $maxBytes) {
            return [null, 'bytes: '.strlen($text).' over '.$maxBytes];
        }

        // a document of N nodes has at most ~2N objects (a node, its attrs), so this only
        // spares json_decode() a document that is over the node limit anyway
        if ($text[0] === '{' && substr_count($text, '{') > $maxTags * 3) {
            return [null, "nodes: over {$maxTags}"];
        }

        $decoded = json_decode($text, true);
        $isJson = json_last_error() === JSON_ERROR_NONE;

        if ($isJson && is_array($decoded) && isset($decoded['type'])) {
            $problem = static::documentProblem($decoded, $maxTags);

            return $problem === null ? [$decoded, null] : [null, $problem];
        }

        $problem = match (true) {
            static::carriesPlaceholder($text) => 'placeholder: the input carries tiptap-php\'s reserved token',
            ($tags = substr_count($text, '<')) > $maxTags => "tags: {$tags} over {$maxTags}",
            default => null,
        };

        if ($problem === null) {
            [$text, $problem] = static::normalisePre($text);
        }

        $collapsed = $problem === null ? static::collapseWhitespace($text) : null;

        if ($problem === null && $collapsed === null) {
            $problem = 'pre: the input is too large for the parser';
        }

        if ($problem !== null) {
            return [null, $problem];
        }

        // the parser takes any string that decodes as JSON for JSON: an empty comment makes it HTML
        return [$isJson ? '<!---->'.$collapsed : $collapsed, null];
    }

    /**
     * Why a decoded Tiptap document is refused, or null when it is a
     * well-formed document within the node limit. The root must be a `doc`
     * with a `content` list; every node needs a string `type`, and `content`,
     * `marks` and `text` must be the right shape where present. tiptap-php
     * throws on the wrong shape, and only a hostile client can send one, so
     * that is a refusal (no report), not an unexpected failure. Attributes are
     * repaired in place instead (see normaliseAttributes()). Nodes and marks
     * are counted against $maxNodes because cost tracks them.
     *
     * @param  array<mixed>  $doc
     */
    protected static function documentProblem(array &$doc, int $maxNodes): ?string
    {
        if (($doc['type'] ?? null) !== 'doc' || ! isset($doc['content'])) {
            return 'invalid document';
        }

        $count = 0;

        if (! static::validNode($doc, $count, $maxNodes)) {
            return $count > $maxNodes ? "nodes: over {$maxNodes}" : 'invalid document';
        }

        return null;
    }

    /**
     * Whether $node is a well-formed node, counting it and everything under it
     * and repairing its attributes. Stops as soon as $count passes $maxNodes.
     *
     * @param  array<mixed>  $node
     */
    protected static function validNode(array &$node, int &$count, int $maxNodes): bool
    {
        if (++$count > $maxNodes || array_is_list($node) || ! is_string($node['type'] ?? null) || $node['type'] === '') {
            return false;
        }

        if (isset($node['text']) && ! is_string($node['text'])) {
            return false;
        }

        static::normaliseAttributes($node);

        foreach (['content', 'marks'] as $key) {
            if (! isset($node[$key])) {
                continue;
            }

            if (! is_array($node[$key]) || ($node[$key] !== [] && ! array_is_list($node[$key]))) {
                return false;
            }

            foreach (array_keys($node[$key]) as $i) {
                if (! is_array($node[$key][$i]) || ! static::validNode($node[$key][$i], $count, $maxNodes)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Repair the attributes tiptap-php throws on, instead of refusing the
     * document (the rest of it is real content): an `attrs` that is not a keyed
     * array is dropped, a table `colwidth` that is not a list of scalars is
     * dropped, a mention `label` or `id` that is an array is dropped, and a heading
     * with no usable `level` becomes level 1, which renders. Other odd values
     * are left for the renderer to drop.
     *
     * @param  array<mixed>  $node
     */
    protected static function normaliseAttributes(array &$node): void
    {
        $attributes = $node['attrs'] ?? [];

        if (! is_array($attributes) || ($attributes !== [] && array_is_list($attributes))) {
            $attributes = [];
        }

        if (isset($attributes['colwidth']) && (! is_array($attributes['colwidth']) || ! array_is_list($attributes['colwidth']) || array_filter($attributes['colwidth'], fn ($width) => ! is_scalar($width) && $width !== null))) {
            unset($attributes['colwidth']);
        }

        foreach (['label', 'id'] as $key) {
            if (is_array($attributes[$key] ?? null)) {
                unset($attributes[$key]);
            }
        }

        if ($node['type'] === 'heading' && ! is_scalar($attributes['level'] ?? null)) {
            $attributes['level'] = 1;
        }

        if ($attributes === []) {
            unset($node['attrs']);
        } else {
            $node['attrs'] = $attributes;
        }
    }

    /**
     * Whether libxml gives this HTML a <body>, the only part tiptap-php's
     * parser reads. It builds one only when something belongs in it: a script,
     * a style, a comment, a bare <meta>, <link>, <base> or <title>, an empty
     * `<html>` or nothing at all leaves it without, and the parser then throws
     * a TypeError, on every call. Asked before setContent() so that input is an
     * empty message, not a failure to report. Whitespace is collapsed
     * first, Unicode spaces included, because the parser's minifier does the
     * same: a value of no-break spaces is bodyless to the parser though libxml
     * would keep it as text. (Running the minifier itself here would double
     * its cost, 0.6 s on 2 MB.)
     */
    protected static function hasBody(string $html): bool
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;
            $document->loadHTML('<?xml encoding="utf-8" ?>'.trim(preg_replace('/\s+/u', ' ', $html) ?? $html));

            return $document->getElementsByTagName('body')->length > 0;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Keep one mark of each type on every node, the last one winning, as a
     * ProseMirror mark set does. tiptap-php's serialiser pairs opening and
     * closing tags through a stack and loses count when a node carries the
     * same mark twice (`<code><code>c`, or a document with two `bold` marks
     * on one text), which is an ErrorException on every call. Returns whether
     * anything was removed.
     *
     * @param  array<mixed>  $node
     */
    protected static function dedupeMarks(array &$node): bool
    {
        $changed = false;

        if (isset($node['marks']) && is_array($node['marks'])) {
            $byType = [];

            foreach ($node['marks'] as $mark) {
                $byType[is_array($mark) ? (string) ($mark['type'] ?? '') : ''] = $mark;
            }

            if (count($byType) !== count($node['marks'])) {
                $node['marks'] = array_values($byType);
                $changed = true;
            }
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $changed = static::dedupeMarks($node['content'][$i]) || $changed;
                }
            }
        }

        return $changed;
    }

    /**
     * Collapse every run of whitespace longer than MAX_WHITESPACE_RUN, outside
     * a `<pre>` block, to a single space, in one linear pass. PHP compiles the
     * minifier's `\s` with Unicode properties (the `u` flag), so a no-break or
     * other Unicode space counts as part of a run. The `<pre>` alternative is
     * the minifier's own pattern, so the blocks it will set aside are the ones
     * left alone. Null when the regex fails.
     */
    protected static function collapseWhitespace(string $html): ?string
    {
        return preg_replace_callback(
            '/(<pre\b[^>]*+>.*?<\/pre>)|[\s\x{0085}\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]++/isu',
            fn (array $match) => isset($match[1]) || strlen($match[0]) <= static::MAX_WHITESPACE_RUN ? $match[0] : ' ',
            $html,
        );
    }

    /**
     * Make the `<pre>` blocks in this HTML safe for Minify's `<pre>` regex, or
     * say why they can't be. A block is measured from `<pre` to the first
     * `</pre>` after it, the way the regex takes it.
     *
     * - A block over MAX_PRE_LENGTH is refused (the regex fails on it).
     * - Many blocks in a long input are refused (MAX_PRE_COST): every
     *   placeholder is swapped back over the whole input.
     * - Unclosed `<pre>` tags are left alone while they are few. Past
     *   MAX_UNCLOSED_PRE_COST a `</pre>` is appended, which is what the HTML
     *   parser does at the end of the input anyway, so the tags become one
     *   block that is scanned once.
     * - A `<pre` that no `>` follows scans to the end of the input; that many
     *   of them is refused.
     *
     * @return array{0: string, 1: string|null} [html, refusal]
     */
    protected static function normalisePre(string $text): array
    {
        $lower = strtolower($text);
        $count = substr_count($lower, '<pre');

        if ($count === 0) {
            return [$text, null];
        }

        $length = strlen($text);
        $offset = 0;
        $blocks = 0;
        $unclosedFrom = null;

        while (($start = strpos($lower, '<pre', $offset)) !== false) {
            $end = strpos($lower, '</pre>', $start);

            if ($end === false) {
                $unclosedFrom = $start;

                break;
            }

            if ($end - $start > static::MAX_PRE_LENGTH) {
                return [$text, 'pre: a block over '.static::MAX_PRE_LENGTH.' characters'];
            }

            $blocks++;
            $offset = $end + 6;
        }

        if ($unclosedFrom !== null) {
            $unclosed = substr_count($lower, '<pre', $unclosedFrom);

            if ($unclosed * ($length - $unclosedFrom) > static::MAX_UNCLOSED_PRE_COST) {
                $text .= '</pre>';
                $lower .= '</pre>';
                $length += 6;
                $blocks++;
            }

            if ($length - $unclosedFrom > static::MAX_PRE_LENGTH) {
                return [$text, 'pre: an unclosed block over '.static::MAX_PRE_LENGTH.' characters'];
            }
        }

        if ($blocks * $length > static::MAX_PRE_COST) {
            return [$text, "pre: {$blocks} blocks in {$length} bytes"];
        }

        // each `<pre` scans on to the next `>`: fine for a real tag, quadratic for many without one
        $cost = 0;
        $offset = 0;
        $gt = -1;

        while (($start = strpos($lower, '<pre', $offset)) !== false) {
            if ($gt < $start) {
                $gt = strpos($lower, '>', $start);
            }

            if ($gt === false) {
                $cost += substr_count($lower, '<pre', $start) * ($length - $start);

                break;
            }

            $cost += $gt - $start;
            $offset = $start + 4;
        }

        return $cost > static::MAX_UNCLOSED_PRE_COST
            ? [$text, 'pre: tags with no end']
            : [$text, null];
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
