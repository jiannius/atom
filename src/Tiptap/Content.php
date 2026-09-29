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
     * document is legitimate: 2 MB and 20000 tags (about 70 MB worst case).
     * A host raises or lowers them with `atom.editor.render_max_bytes` and
     * `atom.editor.render_max_tags`.
     */
    public const RENDER_MAX_BYTES = 2097152;

    public const RENDER_MAX_TAGS = 20000;

    /** Output larger than this many times the byte limit is refused. */
    protected const OUTPUT_FACTOR = 4;

    /**
     * A run of whitespace longer than this is refused. tiptap-php's minifier
     * trims with `^\s+|\s+$` and `\s+(<tag`, which rescan a run from every
     * position in it: quadratic, so 120 KB of spaces took 98 s.
     */
    protected const MAX_WHITESPACE_RUN = 256;

    /**
     * A `<pre>` block longer than this is refused: past ~1M characters the
     * minifier's regex hits pcre.backtrack_limit and returns null, which is a
     * TypeError on every view.
     */
    protected const MAX_PRE_LENGTH = 500000;

    /**
     * Unclosed `<pre>` tags times the length they scan to. The minifier's
     * regex rescans to the end of the input from each one, so cost is their
     * product: 5000 unclosed tags in 130 KB took 8 s. 30 million is ~0.4 s.
     */
    protected const MAX_UNCLOSED_PRE_COST = 30000000;

    /**
     * `<pre>` tags times the input length. The minifier swaps each for a
     * placeholder and str_replace()s them all back over the whole input, so
     * cost is their product: 10000 blocks in 120 KB took 2.3 s. 250 million is
     * ~0.5 s.
     */
    protected const MAX_PRE_COST = 250000000;

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
     * A refusal is logged as a warning (once per value per request) so a host
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

            $html = (new Editor(['extensions' => static::extensions()]))
                ->setContent($content)
                ->getHTML();

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
     * it is a well-formed document within the node limit. Everything else is
     * HTML, held to the limits, and refused when the parser can't survive it:
     *
     * - it carries the token Minify uses for its `<pre>` placeholders:
     *   tiptap-php replaces each `<pre>` with `%MINIFYHTML<md5(REQUEST_TIME)>N%`
     *   and str_replace()s them back, the hash is guessable, and a placeholder
     *   written inside a `<pre>` expands again for every level of nesting, so
     *   a few KB exhausts memory. Only HTML reaches Minify; a document's text
     *   never does;
     * - a whitespace run, a `<pre>` block or a run of unclosed `<pre>` tags
     *   that Minify's regexes take quadratic time on, or fail on.
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
            static::longestWhitespaceRun($text) > static::MAX_WHITESPACE_RUN => 'whitespace: a run over '.static::MAX_WHITESPACE_RUN.' characters',
            default => static::preProblem($text),
        };

        if ($problem !== null) {
            return [null, $problem];
        }

        // the parser takes any string that decodes as JSON for JSON: an empty comment makes it HTML
        return [$isJson ? '<!---->'.$text : $text, null];
    }

    /**
     * Why a decoded Tiptap document is refused, or null when it is a
     * well-formed document within the node limit. The root must be a `doc`
     * with a `content` list; every node needs a string `type`, and `content`,
     * `marks`, `attrs` and `text` must be the right shape where present.
     * tiptap-php throws on the wrong shape, and only a hostile client can send
     * one, so that is a refusal (no report), not an unexpected failure.
     * Nodes and marks are counted against $maxNodes because cost tracks them.
     *
     * @param  array<mixed>  $doc
     */
    protected static function documentProblem(array $doc, int $maxNodes): ?string
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
     * Whether $node is a well-formed node, counting it and everything under it.
     * Stops as soon as $count passes $maxNodes.
     *
     * @param  array<mixed>  $node
     */
    protected static function validNode(array $node, int &$count, int $maxNodes): bool
    {
        if (++$count > $maxNodes || array_is_list($node) || ! is_string($node['type'] ?? null) || $node['type'] === '') {
            return false;
        }

        if (isset($node['text']) && ! is_string($node['text'])) {
            return false;
        }

        if (isset($node['attrs']) && ! static::validAttributes($node['attrs'], $node['type'])) {
            return false;
        }

        if ($node['type'] === 'heading' && ! isset($node['attrs'])) {
            return false;
        }

        foreach (['content' => false, 'marks' => true] as $key => $isMarks) {
            if (! isset($node[$key])) {
                continue;
            }

            if (! is_array($node[$key]) || ($node[$key] !== [] && ! array_is_list($node[$key]))) {
                return false;
            }

            foreach ($node[$key] as $child) {
                if (! is_array($child) || ! static::validNode($child, $count, $maxNodes)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A node's attributes are a keyed array. tiptap-php throws on three shapes
     * a client can send, all of which the editor never writes, so they are
     * refused rather than reported: a heading with no `level`, a table
     * `colwidth` that is not a list of scalars, and a mention `label` that is
     * an array. Other odd values are tolerated: the renderer drops them.
     */
    protected static function validAttributes(mixed $attributes, string $type): bool
    {
        if (! is_array($attributes) || ($attributes !== [] && array_is_list($attributes))) {
            return false;
        }

        if ($type === 'heading' && ! is_scalar($attributes['level'] ?? null)) {
            return false;
        }

        if (isset($attributes['colwidth']) && (! is_array($attributes['colwidth']) || ! array_is_list($attributes['colwidth']) || array_filter($attributes['colwidth'], fn ($width) => ! is_scalar($width) && $width !== null))) {
            return false;
        }

        return ! is_array($attributes['label'] ?? null);
    }

    /**
     * The length of the longest run of whitespace, in one linear pass. PHP
     * compiles the minifier's `\s` with Unicode properties (the `u` flag), so
     * a no-break or other Unicode space counts, and is folded to a plain space
     * first.
     */
    protected static function longestWhitespaceRun(string $text): int
    {
        $text = preg_replace('/[\x{0085}\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]/u', ' ', $text) ?? $text;
        $whitespace = " \t\n\r\f\v";
        $length = strlen($text);
        $longest = 0;
        $i = 0;

        while ($i < $length) {
            $i += strcspn($text, $whitespace, $i);

            if ($i >= $length) {
                break;
            }

            $run = strspn($text, $whitespace, $i);
            $longest = max($longest, $run);
            $i += $run;

            if ($longest > static::MAX_WHITESPACE_RUN) {
                break;
            }
        }

        return $longest;
    }

    /**
     * Why the `<pre>` blocks in this HTML would break Minify's `<pre>` regex,
     * or null. A block is measured from `<pre` to the first `</pre` after it,
     * the way the regex takes it; a `<pre` with no closing tag scans to the
     * end, and every later one does too.
     */
    protected static function preProblem(string $text): ?string
    {
        $length = strlen($text);
        $offset = 0;

        if (stripos($text, '<pre') !== false && substr_count(strtolower($text), '<pre') * $length > static::MAX_PRE_COST) {
            return 'pre: '.substr_count(strtolower($text), '<pre').' blocks';
        }

        while (($start = stripos($text, '<pre', $offset)) !== false) {
            $end = stripos($text, '</pre', $start);

            if ($end === false) {
                $unclosed = 1 + substr_count(strtolower($text), '<pre', $start + 4);
                $scanned = $length - $start;

                return match (true) {
                    $scanned > static::MAX_PRE_LENGTH => 'pre: an unclosed block over '.static::MAX_PRE_LENGTH.' characters',
                    $unclosed * $scanned > static::MAX_UNCLOSED_PRE_COST => "pre: {$unclosed} unclosed tags",
                    default => null,
                };
            }

            if ($end - $start > static::MAX_PRE_LENGTH) {
                return 'pre: a block over '.static::MAX_PRE_LENGTH.' characters';
            }

            $offset = $end + 5;
        }

        return null;
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
