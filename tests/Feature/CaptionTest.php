<?php

// <atom:caption> used to print a literal class list and never its bag, so an
// x-show, an id or a margin passed to it vanished without a trace (atom#58).
it('prints the caller attributes on the caption element', function () {
    $html = renderBlade('<atom:caption id="hint" class="mt-2" x-show="open" data-test="x">Help text</atom:caption>');

    $captions = domQuery($html, '//*[@data-atom-caption]');

    expect($captions)->toHaveCount(1);

    $caption = $captions[0];

    expect($caption->getAttribute('id'))->toBe('hint')
        ->and($caption->getAttribute('x-show'))->toBe('open')
        ->and($caption->getAttribute('data-test'))->toBe('x')
        ->and(domClasses($caption))->toContain('mt-2', 'text-sm', 'text-zinc-500', 'dark:text-zinc-400')
        ->and(trim($caption->textContent))->toBe('Help text');
});

it('keeps the default look when the caller passes nothing', function () {
    $caption = domQuery(renderBlade('<atom:caption>Help</atom:caption>'), '//*[@data-atom-caption]')[0];

    expect(domClasses($caption))->toBe(['text-sm', 'text-zinc-500', 'dark:text-zinc-400']);
});
