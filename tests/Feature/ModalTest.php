<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/**
 * Build a stand-in for a Livewire component on the stack — modal name
 * defaults only need getName().
 */
function fakeLivewireComponent(string $name = 'fixture-component'): object
{
    return new class ($name) {
        public function __construct(public string $name) {}

        public function getName(): string
        {
            return $this->name;
        }
    };
}

describe('modal', function () {
    it('renders a dialog wired to the modal alpine component', function () {
        $html = renderBlade('<atom:modal name="test-modal">Body</atom:modal>');

        expect($html)
            ->toContain('<dialog')
            ->toContain('x-data="modal({')
            ->toContain("name: 'test-modal'")
            ->toContain('x-on:atom-modal-show.window="showModal"')
            ->toContain('x-on:atom-modal-close.window="closeModal"')
            ->toContain('x-on:keydown.escape.stop.prevent="escapeClose"')
            ->toContain('wire:ignore.self')
            ->toContain('Body')
            ->not->toContain('scope:'); // targeting is by name only
    });

    it('defaults the name to the current Livewire component name', function () {
        $html = withLivewireContext(
            fakeLivewireComponent('my-page'),
            fn () => renderBlade('<atom:modal>Body</atom:modal>'),
        );

        expect($html)->toContain("name: 'my-page'");
    });

    it('renders without a name outside a Livewire context', function () {
        // Regression: app('livewire')->current() returns false outside a
        // component render — getName() on it was a fatal error.
        $html = renderBlade('<atom:modal>Body</atom:modal>');

        expect($html)->toContain('name: null');
    });

    it('is dismissible and escapable by default', function () {
        $html = renderBlade('<atom:modal name="m">Body</atom:modal>');

        expect($html)
            ->toContain('escapable: true')
            ->toContain('x-on:click="backdropClick"');
    });

    it('drops backdrop dismissal when dismissible is false', function () {
        $html = renderBlade('<atom:modal name="m" :dismissible="false">Body</atom:modal>');

        expect($html)
            ->not->toContain('backdropClick')
            // the other two switches are independent
            ->toContain('escapable: true')
            ->toContain('aria-label="Close"');
    });

    it('keeps ESC from closing when escapable is false', function () {
        $html = renderBlade('<atom:modal name="m" :escapable="false">Body</atom:modal>');

        expect($html)
            ->toContain('escapable: false')
            // the keydown stays bound (and prevented) so the native dialog
            // cancel can't bypass cleanup; the JS side gates the close
            ->toContain('x-on:keydown.escape.stop.prevent="escapeClose"')
            // the other two switches are independent
            ->toContain('x-on:click="backdropClick"')
            ->toContain('aria-label="Close"');
    });

    it('renders a labelled close button by default', function () {
        $html = renderBlade('<atom:modal name="m">Body</atom:modal>');

        expect($html)
            ->toContain('aria-label="Close"')
            ->toContain('x-on:click="closeModal"');
    });

    it('omits the close button when closeable is false', function () {
        $html = renderBlade('<atom:modal name="m" :closeable="false">Body</atom:modal>');

        expect($html)->not->toContain('aria-label="Close"');
    });

    it('caps width at the viewport with a zero-specificity default', function () {
        // Regression: the class was written as [:where(&):max-w-full], which
        // Tailwind silently drops — no max-width ever shipped in the CSS.
        $html = renderBlade('<atom:modal name="m">Body</atom:modal>');

        expect($html)->toContain(')]:max-w-full');
    });

    it('merges consumer classes onto the dialog', function () {
        $html = renderBlade('<atom:modal name="m" class="max-w-2xl">Body</atom:modal>');

        expect($html)
            ->toContain('max-w-2xl')
            ->toContain('group/modal');
    });

    // A dialog is content-sized, so a max-w-* capped a width nothing set: the
    // modal rendered at the min-w-sm floor (336px) however large the cap, with
    // no error to notice. Worst inside <atom:form.grid cols="auto">, a
    // container query that then never reached two columns.
    // `w-full` as its own class, not the tail of the max-w-full default — a
    // plain toContain('w-full') passes on the unfixed component.
    $standaloneWFull = '/(?<![-\w:])w-full/';

    it('gives a max-w-* modal a width to cap', function (string $class) use ($standaloneWFull) {
        $html = renderBlade('<atom:modal name="m" class="'.$class.'">Body</atom:modal>');

        expect($html)->toMatch($standaloneWFull);
    })->with(['max-w-2xl', 'max-w-screen-lg', 'max-w-[900px]']);

    it('leaves a content-sized modal alone when no max-w is given', function () use ($standaloneWFull) {
        $html = renderBlade('<atom:modal name="m" class="rounded-none">Body</atom:modal>');

        // the zero-specificity default is still there; the sizing opt-in is not
        expect($html)->toContain(')]:max-w-full')->not->toMatch($standaloneWFull);
    });

    it('sizes the form modal, whose width comes from cols', function () use ($standaloneWFull) {
        // form.modal sets max-w-2xl itself, so it needs the same treatment
        expect(renderBlade('<atom:form.modal name="m">F</atom:form.modal>'))->toMatch($standaloneWFull);
    });

    it('removes padding when inset', function () {
        expect(renderBlade('<atom:modal name="m" inset>Body</atom:modal>'))->toContain('p-0');
        expect(renderBlade('<atom:modal name="m">Body</atom:modal>'))->toContain('p-6');
    });
});

describe('modal.trigger', function () {
    it('shows the modal by name on click', function () {
        $html = renderBlade('<atom:modal.trigger name="m"><button>Open</button></atom:modal.trigger>');

        expect($html)
            ->toContain('data-atom-modal-trigger')
            ->toContain("atom.modal('m').show()")
            ->toContain('$el.querySelector(\'button[disabled]\')')
            ->toContain('<button>Open</button>');
    });

    it('slides the modal when the slide prop is set', function () {
        $html = renderBlade('<atom:modal.trigger name="m" slide="left"><button>Open</button></atom:modal.trigger>');

        expect($html)->toContain("atom.modal('m').slide('left')");
    });

    it('binds a document-level keyboard shortcut', function () {
        $html = renderBlade('<atom:modal.trigger name="m" shortcut="meta.k"><button>Open</button></atom:modal.trigger>');

        expect($html)
            ->toContain('x-on:keydown.meta.k.document')
            ->toContain("atom.modal('m').show()");
    });

    it('uses the slide variant for the shortcut too', function () {
        $html = renderBlade('<atom:modal.trigger name="m" slide="bottom" shortcut="meta.k"><button>Open</button></atom:modal.trigger>');

        expect($html)->toContain("x-on:keydown.meta.k.document=\"\$event.preventDefault(); atom.modal('m').slide('bottom')\"");
    });

    it('defaults the name to the current Livewire component name', function () {
        // Mirrors the modal's own default so a bare trigger pairs with a
        // bare modal in the same component.
        $html = withLivewireContext(
            fakeLivewireComponent('my-page'),
            fn () => renderBlade('<atom:modal.trigger><button>Open</button></atom:modal.trigger>'),
        );

        expect($html)->toContain("atom.modal('my-page').show()");
    });
});

describe('form.modal', function () {
    it('renders a form inside a modal with a submit button', function () {
        $html = renderBlade('<atom:form.modal name="m"><div>Fields</div></atom:form.modal>');

        expect($html)
            ->toContain('<dialog')
            ->toContain('<form')
            ->toContain('type="submit"')
            ->toContain('Save')
            ->toContain('Fields');
    });

    it('maps cols to a max width', function () {
        expect(renderBlade('<atom:form.modal name="m">F</atom:form.modal>'))->toContain('max-w-2xl');
        expect(renderBlade('<atom:form.modal name="m" cols="3">F</atom:form.modal>'))->toContain('max-w-4xl');
        expect(renderBlade('<atom:form.modal name="m" cols="1">F</atom:form.modal>'))->toContain('max-w-xl');
    });

    it('renders the delete slot next to the submit button', function () {
        $html = renderBlade(<<<'HTML'
            <atom:form.modal name="m">
                <div>Fields</div>
                <x-slot:delete><button type="button">Remove</button></x-slot:delete>
            </atom:form.modal>
        HTML);

        expect($html)->toContain('Remove');
    });

    it('forwards the close switches to the modal', function () {
        $html = renderBlade('<atom:form.modal name="m" :dismissible="false" :escapable="false" :closeable="false">F</atom:form.modal>');

        expect($html)
            ->toContain('escapable: false')
            ->not->toContain('backdropClick')
            ->not->toContain('aria-label="Close"');
    });
});
