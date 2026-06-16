<?php

use Jiannius\Atom\Tiptap\Content;
use Tiptap\Editor;

it('converts legacy HTML to a tiptap JSON doc', function () {
    $editor = new Editor(['extensions' => Content::extensions()]);
    $json = $editor->setContent('<p>hello <strong>world</strong></p>')->getJSON();
    $doc = json_decode($json, true);

    expect($doc['type'])->toBe('doc')
        ->and($doc['content'][0]['type'])->toBe('paragraph');
});

it('round-trips custom nodes through HTML->JSON->HTML', function () {
    // an HTML img with atom data-attrs should parse back into JSON attrs
    $editor = new Editor(['extensions' => Content::extensions()]);
    $json = $editor->setContent('<img src="a.png" data-float="left" data-width="50%">')->getJSON();
    $doc = json_decode($json, true);

    // find the image node attrs
    $img = collect($doc['content'])->firstWhere('type', 'image');
    expect($img['attrs']['float'])->toBe('left')
        ->and($img['attrs']['width'])->toBe('50%');
});
