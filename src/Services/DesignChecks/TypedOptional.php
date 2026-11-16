<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class TypedOptional extends CopyFormsRule
{
    public const ID = 'typed-optional';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Forms and modals';

    /**
     * Components whose text is their label (a `label` attribute there is not a field's)
     *
     * @var array<int, string>
     */
    protected const TEXT_LABELLED = ['button', 'link', 'menu.item', 'navlist.item', 'tabs.item'];

    public function title(): string
    {
        return 'A field label that types "(optional)"';
    }

    public function fix(): string
    {
        return '`<atom:form marker="optional">`, with `required` on the required fields: atom draws the marker itself.';
    }

    public function rule(): string
    {
        return 'never a typed "(optional)"';
    }

    public function detect(): string
    {
        return 'An atom tag whose `label` (static, or a `t()`, `__()` literal) ends in "(optional)", in any case. A label that is bound to a variable is not read.';
    }

    /**
     * It reads one tag's label
     */
    public function structural(): bool
    {
        return false;
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom() as $node) {
            if (in_array($node['component'], static::TEXT_LABELLED, true) || ! $outline->has($node, 'label')) {
                continue;
            }

            $label = $outline->label($node);

            if ($label !== null && preg_match('/\(\s*optional\s*\)\s*$/iu', $label)) {
                yield [$node['offset'], '"'.$label.'": use `<atom:form marker="optional">` and `required` on the others'];
            }
        }
    }
}
