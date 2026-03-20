@php echo '<?xml version="1.0" encoding="UTF-8"?>' @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
    @foreach ($urls as $url)
        <url>
            <loc>{{ data_get($url, 'url') }}</loc>
            <lastmod>{{ data_get($url, 'lastmod') }}</lastmod>
            <changefreq>{{ data_get($url, 'changefreq') }}</changefreq>
            <priority>{{ data_get($url, 'priority') }}</priority>
        </url>
    @endforeach
</urlset>
