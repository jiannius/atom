<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ModalFooterPadding extends CopyFormsRule
{
    public const ID = 'modal-footer-padding';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Forms and modals';

    /**
     * A padding utility: `p-6`, `px-4`, `pt-2`, `p-[10px]`, behind any variant (`md:p-4`) or an important mark; not a
     * zero (`p-0`, `p-0!`, `p-[0px]`), which takes padding away
     */
    protected const PADDING = '/^(?:[\w\[\]&>*.-]+:)*!?-?(?:p|px|py|pt|pb|ps|pe|pl|pr)-(?!(?:0|\[0(?:px|r?em)?\])!?$)[\w.\/\[\]%-]+!?$/';

    public function title(): string
    {
        return 'A padded form footer';
    }

    public function fix(): string
    {
        return '`<atom:form.actions sticky>` in a modal or a slide-over, `<atom:form.actions bar>` on a page, or `<atom:form.modal>`, which draws the footer itself.';
    }

    public function rule(): string
    {
        return 'never a padded `form.actions class="p-6"`';
    }

    public function detect(): string
    {
        return 'An `<atom:form.actions>` whose static `class` (or a string in `:class`) holds a padding utility (`p-*`, `px-*`, `py-*`, `pt-*`, `pb-*`, `ps-*`, `pe-*`, `pl-*`, `pr-*`, behind any variant). A zero (`p-0`, `p-0!`, `p-[0px]`) is not flagged, and a class that holds an echo is not read.';
    }

    /**
     * It reads one tag's classes
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('form.actions') as $actions) {
            foreach ($outline->classes($actions) as $class) {
                if (preg_match(static::PADDING, $class)) {
                    yield [$actions['offset'], '`'.$class.'`: use `sticky` (a modal or slide-over) or `bar` (a page)'];

                    break;
                }
            }
        }
    }
}
