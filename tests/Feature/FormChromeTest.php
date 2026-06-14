<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

describe('label', function () {
    it('renders a label element with an optional icon', function () {
        $html = Blade::render('<atom:label icon="close">Name</atom:label>');

        expect($html)
            ->toContain('data-atom-label')
            ->toContain('<label')
            ->toContain('Name')
            ->toContain('data-atom-icon');
    });

    it('lays out an actions slot alongside the label', function () {
        $html = Blade::render(<<<'BLADE'
            <atom:label>
                Name
                <x-slot:actions><span>edit</span></x-slot:actions>
            </atom:label>
        BLADE);

        expect($html)
            ->toContain('Name')
            ->toContain('edit');
    });
});

describe('error', function () {
    it('renders a bullet list from the errors attribute', function () {
        $html = Blade::render('<atom:error :errors="[\'Required\', \'Too short\']" />');

        expect($html)
            ->toContain('data-atom-error')
            ->toContain('<li>Required</li>')
            ->toContain('<li>Too short</li>');
    });

    it('renders slot content when no errors array is given', function () {
        $html = Blade::render('<atom:error>Bad value</atom:error>');

        expect($html)
            ->toContain('data-atom-error')
            ->toContain('Bad value');
    });

    it('renders nothing when empty', function () {
        $html = Blade::render('<atom:error />');

        expect(trim($html))->not->toContain('data-atom-error');
    });
});

describe('caption', function () {
    it('renders caption content', function () {
        $html = Blade::render('<atom:caption>Helper text</atom:caption>');

        expect($html)
            ->toContain('data-atom-caption')
            ->toContain('Helper text');
    });
});

describe('form', function () {
    it('renders a form wired to submit with a loading overlay', function () {
        $html = renderBlade('<atom:form><input name="x"/></atom:form>');

        expect($html)
            ->toContain('data-atom-form')
            ->toContain('wire:submit="submit"')
            ->toContain('flex flex-col gap-6')
            ->toContain('wire:target="submit"')
            ->toContain('role="status"')
            ->toContain('Saving');
    });

    it('anchors the loading overlay to the form, not a display:contents wrapper', function () {
        // display:contents nukes position:relative, so the absolute overlay must
        // sit on a real positioned box — the <form> itself carries `relative`.
        $html = renderBlade('<atom:form><input name="x"/></atom:form>');

        expect($html)
            ->toContain('group/form relative')
            ->not->toContain('class="contents relative"');
    });

    it('points the loading overlay at a custom submit handler', function () {
        $html = renderBlade('<atom:form wire:submit="save"><input name="x"/></atom:form>');

        expect($html)
            ->toContain('wire:submit="save"')
            ->toContain('wire:target="save"');
    });

    it('lays the slot out in a grid when cols is set', function () {
        $html = renderBlade('<atom:form cols="2"><input name="x"/></atom:form>');

        expect($html)->toContain('md:grid-cols-2');
    });

    it('drops the stacking gap when inset', function () {
        $html = renderBlade('<atom:form inset><input name="x"/></atom:form>');

        expect($html)->not->toContain('flex flex-col gap-6');
    });
});

describe('form.grid', function () {
    it('uses a container query for cols=auto', function () {
        $html = renderBlade('<atom:form.grid><span>a</span></atom:form.grid>');

        expect($html)
            ->toContain('@container')
            ->toContain('@2xl:grid-cols-2');
    });

    it('forces a viewport grid for cols=2 and cols=3', function () {
        expect(renderBlade('<atom:form.grid cols="2"><span>a</span></atom:form.grid>'))
            ->toContain('md:grid-cols-2');

        expect(renderBlade('<atom:form.grid cols="3"><span>a</span></atom:form.grid>'))
            ->toContain('md:grid-cols-3');
    });
});

describe('form.actions', function () {
    it('renders a default Save submit when empty', function () {
        $html = renderBlade('<atom:form.actions/>');

        expect($html)
            ->toContain('data-atom-form-actions')
            ->toContain('Save')
            ->toContain('type="submit"');
    });

    it('renders slot content instead of the default button', function () {
        $html = renderBlade('<atom:form.actions><button>Custom</button></atom:form.actions>');

        expect($html)
            ->toContain('Custom')
            ->not->toContain('Save');
    });

    it('pins to the bottom when sticky', function () {
        $html = renderBlade('<atom:form.actions sticky><button>x</button></atom:form.actions>');

        expect($html)->toContain('sticky bottom-0');
    });
});

describe('form.modal', function () {
    beforeEach(fn () => view()->share('errors', new ViewErrorBag));

    it('composes modal, form and a Save footer', function () {
        $html = renderBlade('<atom:form.modal name="edit"><input name="x"/></atom:form.modal>');

        expect($html)
            ->toContain('<dialog')
            ->toContain("name: 'edit'")
            ->toContain('data-atom-form')
            ->toContain('data-atom-form-actions')
            ->toContain('Save')
            ->toContain('max-w-2xl'); // cols=auto default
    });

    it('derives width from cols', function () {
        expect(renderBlade('<atom:form.modal name="a" cols="3"><span>a</span></atom:form.modal>'))
            ->toContain('max-w-4xl');

        expect(renderBlade('<atom:form.modal name="b" cols="1"><span>a</span></atom:form.modal>'))
            ->toContain('max-w-xl');
    });

    it('renders a delete slot in the footer', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:form.modal name="c">
                <span>field</span>
                <x-slot:delete><button>Remove</button></x-slot:delete>
            </atom:form.modal>
        BLADE);

        expect($html)->toContain('Remove');
    });
});
