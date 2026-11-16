<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class ModalTitle extends CopyFormsRule
{
    public const ID = 'modal-title';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Forms and modals';

    /**
     * The headings a modal is titled with by hand
     *
     * @var array<int, string>
     */
    protected const HEADINGS = ['atom:heading', 'h1', 'h2', 'h3', 'h4'];

    public function title(): string
    {
        return 'A modal form titled by a hand heading';
    }

    public function fix(): string
    {
        return '`<atom:page.header level="2" size="sm">` as the form\'s first child, or let `<atom:form.modal>` title it.';
    }

    public function rule(): string
    {
        return 'A modal or slide-over form is titled by `page.header level="2" size="sm"`';
    }

    public function detect(): string
    {
        return 'An `<atom:modal>` that holds an `<atom:form>` whose first heading-like tag (`<atom:page.header>`, `<atom:heading>`, `<h1>` to `<h4>`) is an `<atom:heading>` or an `<h1>` to `<h4>`. A modal with no heading, or whose first is a `page.header`, is not flagged, and a heading inside another modal nested in it is left to that one.';
    }

    public function find(File $file, Context $context): iterable
    {
        $outline = $file->outline();

        foreach ($outline->atom('modal') as $modal) {
            $inside = $outline->descendants($modal);

            if (! array_filter($inside, fn ($node) => $node['tag'] === 'atom:form' && $this->ownedBy($file, $node, $modal))) {
                continue;
            }

            foreach ($inside as $node) {
                if (! in_array($node['tag'], [...static::HEADINGS, 'atom:page.header'], true) || ! $this->ownedBy($file, $node, $modal)) {
                    continue;
                }

                if ($node['tag'] !== 'atom:page.header') {
                    yield [$node['offset'], 'the modal\'s first heading is `'.($node['kind'] === 'atom' ? '<atom:heading>' : '<'.$node['tag'].'>').'`: use `<atom:page.header level="2" size="sm">`'];
                }

                break;
            }
        }
    }

    /**
     * Whether a modal is the nearest one around a node (a modal nested in it has its own title)
     */
    protected function ownedBy(File $file, array $node, array $modal): bool
    {
        return ($file->outline()->inside($node, 'atom:modal')['id'] ?? null) === $modal['id'];
    }
}
