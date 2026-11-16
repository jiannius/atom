<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class DangerSection extends CopyFormsRule
{
    public const ID = 'danger-section';

    public const SEVERITY = DesignCheck::REVIEW;

    public const GROUP = 'Forms and modals';

    /**
     * Past this many danger sections a view is not a form someone built by hand: it is declined (comparing them is quadratic)
     */
    protected const MAX_DANGERS = 200;

    public function title(): string
    {
        return 'A danger section that is not the only one, or not last';
    }

    public function fix(): string
    {
        return 'One `<atom:form.section variant="danger">`, last in its form. On a record, the same actions live in `<atom:page.header>`\'s `menu` slot, behind a confirm.';
    }

    public function rule(): string
    {
        return 'may end with ONE `<atom:form.section variant="danger">`';
    }

    public function detect(): string
    {
        return 'An `<atom:form.section variant="danger">` that is a second one in the same form, or that is followed, in the same form, by another `<atom:form.section>`. A view with over 200 danger sections is not read. Two sections that cannot show together (the branches of one `@if`, or two separate `@if` blocks) are not compared, and a bound `variant` is not read.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();
        $sections = $outline->atom('form.section');
        $dangers = array_values(array_filter($sections, fn ($section) => $outline->value($section, 'variant') === 'danger'));

        if (count($dangers) > static::MAX_DANGERS) {
            return;
        }

        foreach ($dangers as $at => $danger) {
            $earlier = array_filter(array_slice($dangers, 0, $at), fn ($other) => $this->sameForm($file, $other, $danger) && $outline->together($other, $danger));

            if ($earlier !== []) {
                yield [$danger['offset'], 'a second danger section in the form: keep one, last'];

                continue;
            }

            foreach ($sections as $after) {
                if ($after['offset'] >= ($danger['end'] ?? $danger['offset']) && $this->sameForm($file, $danger, $after) && $outline->together($danger, $after)) {
                    yield [$danger['offset'], 'followed by another section: the danger section goes last'];

                    break;
                }
            }
        }
    }

    /**
     * Whether two sections are in the same form (or both outside any)
     */
    protected function sameForm(File $file, array $a, array $b): bool
    {
        $outline = $file->outline();

        return ($outline->inside($a, ['atom:form', 'atom:form.modal'])['id'] ?? null) === ($outline->inside($b, ['atom:form', 'atom:form.modal'])['id'] ?? null);
    }
}
