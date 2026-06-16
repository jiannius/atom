<?php

namespace Jiannius\Atom\Tiptap;

use Jiannius\Atom\Tiptap\Extensions\AtomImage;
use Jiannius\Atom\Tiptap\Extensions\AtomMention;
use Jiannius\Atom\Tiptap\Extensions\FontSize;
use Jiannius\Atom\Tiptap\Extensions\Youtube;
use Tiptap\Editor;

class Content
{
    /**
     * The full PHP extension set mirroring the JS engine. Used for both SSR
     * rendering and the HTML->JSON migration so fidelity is defined once.
     *
     * @return array<int, object>
     */
    public static function extensions(): array
    {
        return [
            new \Tiptap\Extensions\StarterKit,
            new \Tiptap\Marks\Underline,
            new \Tiptap\Marks\Subscript,
            new \Tiptap\Marks\Superscript,
            new \Tiptap\Marks\Highlight(['multicolor' => true]),
            new \Tiptap\Marks\Link,
            new \Tiptap\Marks\TextStyle,
            new \Tiptap\Extensions\Color(['types' => ['textStyle']]),
            new FontSize(['types' => ['textStyle']]),
            new \Tiptap\Extensions\TextAlign(['types' => ['heading', 'paragraph']]),
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
     */
    public static function render(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return (new Editor(['extensions' => static::extensions()]))
            ->setContent($value)
            ->getHTML();
    }
}
