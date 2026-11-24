<?php

/**
 * The json-ld value is host data (a post title, a product name): whatever it holds, it must stay inside its own
 * script element. `<!--<script>` is the shape that matters: after it the HTML parser keeps the element open past `</script>`
 * (the "script data double escaped" state) and the rest of the page is swallowed.
 */
it('encodes json-ld so a value cannot close, extend or comment out the script element', function () {
    $headline = '<!--<script>x</script>--></script><img src=x onerror=alert(1)>';

    $html = renderBlade('<atom:html :vite="false" :fonts="false" :jsonld="$jsonld">x</atom:html>', ['jsonld' => [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $headline,
        'name' => "A & B 'q' \"d\"",
        'url' => 'https://example.com/a/b',
    ]]);

    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

    expect($m)->toHaveCount(2);

    // nothing in the payload can be read as a tag, a comment or an entity by the HTML parser
    expect($m[1])->not->toContain('<')->not->toContain('>')->not->toContain('&');

    // and it still decodes to the data that went in, with the slashes of a URL left alone
    $data = json_decode($m[1], true);

    expect($data['headline'])->toBe($headline)
        ->and($data['name'])->toBe("A & B 'q' \"d\"")
        ->and($m[1])->toContain('https://example.com/a/b');

    // none of the payload's markup reached the page outside the (encoded) script text
    expect($html)->not->toContain('<img src=x')->not->toContain('<script>x');
});
