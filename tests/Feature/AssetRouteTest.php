<?php

// `{file}` is one URL segment, so `.` and `..` reach the route as they are: both name a directory that exists under
// dist/assets, which `file_exists()` accepted and `response()->file()` then failed on.
it('answers 404 for a path that names a directory, not a 500', function (string $path) {
    $this->get('/atom/'.$path)->assertNotFound();
})->with(['.', '..', '%2e', '%2e%2e']);

it('answers 404 for a file that does not exist', function () {
    $this->get('/atom/no-such-file.js')->assertNotFound();
});

it('serves a built asset', function () {
    $url = app('atom')->asset()->version('atom.css');

    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});
