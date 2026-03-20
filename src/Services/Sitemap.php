<?php

namespace Jiannius\Atom\Services;

class Sitemap
{
    public $urls = [];

    /**
     * Push a URL to the sitemap
     */
    public function push($urls, $freq = 'monthly')
    {
        foreach ((array) $urls as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $priority = 1 - (substr_count($path, '/')/10);
            
            $this->urls[] = [
                'url' => $url,
                'added' => time(),
                'lastmod' => now()->toAtomString(),
                'priority' => $priority,
                'changefreq' => $freq,
            ];
        }

        return $this;
    }

    /**
     * Generate the sitemap response
     */
    public function response()
    {
        return response()->view('atom::sitemap', [
            'urls' => $this->urls,
        ])->header('Content-Type', 'text/xml');
    }
}
