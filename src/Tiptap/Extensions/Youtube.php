<?php

namespace Jiannius\Atom\Tiptap\Extensions;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

class Youtube extends Node
{
    public static $name = 'youtube';

    public function addOptions()
    {
        return ['HTMLAttributes' => []];
    }

    public function addAttributes()
    {
        return [
            'src' => [
                'parseHTML' => fn ($node) => $node->getAttribute('src') ?: null,
            ],
            'start' => ['default' => null],
        ];
    }

    public function parseHTML()
    {
        return [['tag' => 'div[data-youtube-video] iframe']];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        $src = $node->attrs->src ?? '';
        $embed = static::embedUrl($src);

        if (!empty($node->attrs->start) && is_numeric($node->attrs->start) && (int) $node->attrs->start > 0) {
            $embed .= (str_contains($embed, '?') ? '&' : '?').'start='.(int) $node->attrs->start;
        }

        return [
            'div',
            ['data-youtube-video' => true],
            ['iframe', HTML::mergeAttributes(
                ['src' => $embed, 'width' => '640', 'height' => '480', 'frameborder' => '0', 'allowfullscreen' => 'true'],
                $this->options['HTMLAttributes'],
            ), 0],
        ];
    }

    /**
     * Convert any YouTube URL form to an /embed/ URL.
     */
    public static function embedUrl(string $url): string
    {
        if (preg_match('/(?:youtu\.be\/|v=|\/embed\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return $url;
    }
}
