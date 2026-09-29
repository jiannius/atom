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
        $embed = is_string($src) ? static::embedUrl($src) : null;

        if ($embed === null) {
            return null;
        }

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
     * Convert a YouTube URL to a canonical https /embed/ URL, or null when it
     * is not one. Mirrors the JS extension: only youtube.com, youtu.be and
     * youtube-nocookie.com hosts (optionally www./m./music.) produce an embed,
     * so a stored src can never point an iframe at another origin or scheme.
     * The output is rebuilt from the 11-character video id, never echoed. Input
     * over http or scheme-less is accepted (the JS extension does), the output
     * is always https.
     */
    public static function embedUrl(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^(?:www|m|music)\./', '', $host);
        $path = $parts['path'] ?? '';
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str($parts['query'] ?? '', $query);

            $id = preg_match('#^/(?:embed|v|shorts)/([^/]+)#', $path, $m)
                ? $m[1]
                : ($query['v'] ?? null);
        }

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }

        return ($host === 'youtube-nocookie.com' ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com').'/embed/'.$id;
    }
}
