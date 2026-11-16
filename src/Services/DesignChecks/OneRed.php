<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class OneRed extends ToneAndConfirmRule
{
    public const ID = 'one-red';

    public const SEVERITY = DesignCheck::REVIEW;

    public function title(): string
    {
        return 'A visible destructive button that is not the tint';
    }

    public function fix(): string
    {
        return 'Use `<atom:button type="delete">`, or `<atom:button variant="danger" inverted>` for a danger that is not a delete: the solid fill belongs to the confirm dialog\'s button.';
    }

    public function rule(): string
    {
        return 'A visible destructive button is the tint';
    }

    public function detect(): string
    {
        return 'An `<atom:button variant="danger">` (or `error`) with no `inverted` and no `type="delete"` that is not inside an `<atom:modal>` (a hand-built confirm dialog\'s button), and an `<atom:button variant="ghost">` with `color="danger"` or a `text-danger` class (one that only shows on hover is not red at rest). A bound variant, color or type is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('button') as $button) {
            if ($outline->has($button, 'type') && in_array($outline->value($button, 'type'), [null, 'delete'], true)) {
                continue;
            }

            if (! $outline->has($button, 'variant')) {
                continue;
            }

            $variant = $outline->value($button, 'variant');

            if (in_array($variant, ['danger', 'error'], true)) {
                if (! $outline->has($button, 'inverted') && $outline->inside($button, 'atom:modal') === null) {
                    yield [$button['offset'], '`variant="'.$variant.'"` is the solid fill: use `type="delete"` or `inverted`'];
                }

                continue;
            }

            if ($variant !== 'ghost') {
                continue;
            }

            if (in_array($outline->value($button, 'color'), ['danger', 'error'], true)) {
                yield [$button['offset'], 'a ghost with `color="danger"`: use `type="delete"` or `variant="danger" inverted`'];

                continue;
            }

            foreach ($outline->classes($button) as $class) {
                if (preg_match('/^(?:dark:)?'.preg_quote('text-', '/').'danger/', $class)) {
                    yield [$button['offset'], 'a ghost in a red text class: use `type="delete"` or `variant="danger" inverted`'];

                    break;
                }
            }
        }
    }
}
