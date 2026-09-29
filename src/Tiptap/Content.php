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
    /** The default size limit for sanitize(), in bytes. */
    public const SANITIZE_MAX_BYTES = 262144;

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
     * Stored content is untrusted, so a document the renderer cannot handle
     * (e.g. non-scalar attrs) is reported and renders empty rather than
     * taking the page down.
     */
    public static function render(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return static::convert($value);
    }

    /**
     * Clean HTML (or a Tiptap JSON document) that came from the browser, so it
     * is safe to store and to print. It is parsed through the same schema and
     * the same hardened extension set as render(), and what comes back is the
     * schema's own serialisation: nodes and marks the editor can produce, only
     * href / src values that pass the allow-lists, and none of the input's
     * attributes. Scripts, event handlers, style / class values that fail the
     * allow-lists, javascript: / data: links, non-YouTube iframes and unknown
     * tags are all dropped.
     *
     * Never throws. Input that is not a string or array, is larger than
     * $maxBytes, or cannot be parsed is reported and comes back as ''; so does
     * input with nothing of the schema in it, so a host that must not accept
     * an empty message should check for '' after cleaning.
     *
     * Mention ids and labels are kept as sent: they are escaped, but not
     * checked. A host that acts on a mention (a notification, a link to a
     * record) must look the id up itself, scoped to what the user may mention.
     *
     * @param  int  $maxBytes  Refuse anything larger. Parsing costs roughly 250
     *                         bytes of memory per input byte, so raise this only
     *                         for fields that hold long documents.
     */
    public static function sanitize(mixed $html, int $maxBytes = self::SANITIZE_MAX_BYTES): string
    {
        try {
            if (is_array($html)) {
                $html = json_encode($html, JSON_THROW_ON_ERROR);
            }

            if (! is_string($html)) {
                return '';
            }

            if (strlen($html) > $maxBytes) {
                throw new \LengthException('Editor HTML of '.strlen($html)." bytes exceeds the {$maxBytes} byte limit.");
            }

            // a NUL byte or invalid UTF-8 makes the parser's minifier return null
            $html = trim(str_replace("\0", '', mb_scrub($html, 'UTF-8')));

            return $html === '' ? '' : static::convert($html);
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Parse content through the extension set and serialise it back to HTML.
     * A document the parser cannot handle is reported and comes back empty.
     */
    protected static function convert(mixed $value): string
    {
        try {
            return (new Editor(['extensions' => static::extensions()]))
                ->setContent($value)
                ->getHTML();
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
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
