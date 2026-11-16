<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class SuccessButton extends Rule
{
    public const ID = 'success-button';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Actions';

    /**
     * The tones a button should not wear
     *
     * @var array<int, string>
     */
    protected const TONES = ['success', 'info'];

    public function title(): string
    {
        return 'A button in the success or info tone';
    }

    public function fix(): string
    {
        return 'A positive next step is the primary (`<atom:button variant="primary">`); success and info are tones for `<atom:status>`, `<atom:toast>` and `<atom:callout>`.';
    }

    public function rule(): string
    {
        return 'Avoid `success` and `info` buttons';
    }

    public function detect(): string
    {
        return 'An `<atom:button>` whose static `variant` or `color` is `success` or `info`. A bound value (`:variant="$tone"`) is not read.';
    }

    /**
     * It reads one tag's attributes, never its place in the tree
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('button') as $button) {
            foreach (['variant', 'color'] as $attribute) {
                $value = $outline->value($button, $attribute);

                if (in_array($value, static::TONES, true)) {
                    yield [$button['offset'], '`'.$attribute.'="'.$value.'"`'];

                    break;
                }
            }
        }
    }
}
