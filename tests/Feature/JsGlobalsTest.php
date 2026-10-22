<?php

use Symfony\Component\Finder\Finder;

/*
 * V4-4c: atom adds nothing to the page's built-ins. The Array/Number/String prototype helpers and window.dd /
 * window.empty are gone; the helpers are `atom.arr.*`, `atom.str.*`, `atom.currency()` and `atom.empty()`.
 * The browser side (nothing left on the prototypes, every replacement present) is tests/e2e/js-globals.spec.js.
 */

/**
 * The text of every JS file atom bundles, by path under the package root.
 *
 * @return array<string, string>
 */
function atomJsSources(): array
{
    $files = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/resources/js')->name('*.js') as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

/**
 * Every line of a source, with the comments left out of the match (a `//` comment, a `/* ... *\/` run).
 */
function withoutJsComments(string $source): string
{
    $source = preg_replace('~/\*.*?\*/~s', '', $source);

    return preg_replace('~^\s*//.*$~m', '', $source);
}

/**
 * The patterns that change a built-in or a well-known global: `X.prototype.y =`, `X.prototype[...] =`,
 * `Object.defineProperty(X.prototype`, `Object.defineProperties(X.prototype`, `Object.assign(X.prototype`,
 * `X.prototype = `, and an assignment to window.dd / window.empty (dot, bracket or globalThis).
 *
 * @return array<string, string>
 */
function globalMutationPatterns(): array
{
    return [
        'prototype member assignment' => '/\.prototype(\.\w+|\[[^\]]+\])\s*(=|\+=)(?!=)/',
        'prototype assignment' => '/\.prototype\s*=(?!=)/',
        'defineProperty on a prototype' => '/Object\s*\.\s*(defineProperty|defineProperties)\s*\(\s*[\w.$]*\.prototype\b/',
        'assign onto a prototype' => '/Object\s*\.\s*assign\s*\(\s*[\w.$]*\.prototype\b/',
        'window.dd / window.empty' => '/\b(window|globalThis|self)\s*(\.\s*(dd|empty)|\[\s*[\'"](dd|empty)[\'"]\s*\])\s*=(?!=)/',
    ];
}

it('assigns to no built-in prototype and sets no window.dd or window.empty in resources/js', function () {
    $offenders = [];

    foreach (atomJsSources() as $path => $source) {
        foreach (globalMutationPatterns() as $rule => $pattern) {
            if (preg_match($pattern, withoutJsComments($source))) {
                $offenders[] = "{$path}: {$rule}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('has no prototypes folder and exposes the replacements on atom', function () {
    $root = dirname(__DIR__, 2).'/resources/js';

    expect(is_dir($root.'/prototypes'))->toBeFalse();

    $index = file_get_contents($root.'/helpers/index.js');

    foreach (['empty', 'arr', 'str', 'currency'] as $name) {
        expect($index)->toMatch('/^    '.$name.',$/m');
    }
});

it('catches each way of changing a built-in (the guard is not vacuous)', function (string $snippet) {
    $hit = collect(globalMutationPatterns())->contains(fn ($pattern) => preg_match($pattern, $snippet));

    expect($hit)->toBeTrue();
})->with([
    'member' => ["Array.prototype.pluck = function () {}"],
    'bracket' => ["String.prototype['slug'] = function () {}"],
    'defineProperty' => ["Object.defineProperty(Array.prototype, 'pluck', { value() {} })"],
    'defineProperties' => ["Object.defineProperties(Number.prototype, {})"],
    'assign' => ["Object.assign(String.prototype, { slug() {} })"],
    'window.dd' => ["window.dd = console.log.bind(console)"],
    'window.empty' => ["window.empty = helpers.empty"],
    'bracket global' => ["window['empty'] = () => true"],
    'globalThis' => ["globalThis.dd = () => {}"],
]);

it('leaves no call to a removed global helper in a blade or an Alpine factory', function () {
    // An Alpine expression in a blade is not compiled by the build, so a stale call only fails in the browser.
    $removed = 'unique|sum|where|toggle|pluck|firstWhere|toDateFormat|toDateString|toDatetimeString|toTimeString|fromNow|headline|nl2br|striptags|camel|slug|currency|limit';
    $root = dirname(__DIR__, 2);
    $offenders = [];

    $files = Finder::create()->files()->in([$root.'/components', $root.'/resources/views', $root.'/resources/js'])->name(['*.blade.php', '*.js']);

    foreach ($files as $file) {
        $source = $file->getContents();

        // helpers/str.js calls dayjs's own instance methods (`.fromNow()`) and helpers/arr.js defines the names
        if (str_starts_with($file->getRelativePathname(), 'helpers/') && str_ends_with($file->getRelativePathname(), '.js')) {
            continue;
        }

        // `->headline()` and `::slug()` are PHP and never match: the pattern needs a dot straight before the name
        preg_match_all('/(?<!atom\.str)(?<!atom\.arr)(?<!\barr)(?<!\bstr)(?<!classList)\.('.$removed.')\s*\(/', $source, $dotted);

        foreach ($dotted[0] as $call) {
            $offenders[] = $file->getRelativePathname().': '.$call;
        }

        // a bare empty( / dd( that is JS: the argument is not a PHP variable
        if (preg_match('/(?<![\w.$>])(empty|dd)\(\s*(?![$\s])/', withoutJsComments($source), $bare) && ! str_contains($source, 'import empty')) {
            $offenders[] = $file->getRelativePathname().': '.$bare[0];
        }
    }

    expect($offenders)->toBe([]);
});
