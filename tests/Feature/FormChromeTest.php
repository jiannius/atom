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
    it('wires submit and drives loading off the submit method', function () {
        $html = renderBlade('<atom:form><input name="x"/></atom:form>');

        expect($html)
            ->toContain('data-atom-form')
            ->toContain('group/form relative')
            ->toContain('flex flex-col gap-6')
            ->toContain('wire:submit="submit"')
            ->toContain('wire:target="submit"')
            ->toContain('wire:loading.class="is-loading"')
            // the dead display:contents wrapper + standalone overlay are gone
            ->not->toContain('class="contents relative"')
            ->not->toContain('wire:loading.flex');
    });

    it('follows a custom submit method for both wiring and loading', function () {
        $html = renderBlade('<atom:form wire:submit="create"><input name="x"/></atom:form>');

        expect($html)
            ->toContain('wire:submit="create"')
            ->toContain('wire:target="create"');
    });

    it('intercepts submit for recaptcha and drops the native wire:submit', function () {
        $html = renderBlade('<atom:form wire:submit="create" recaptcha><input name="x"/></atom:form>');

        expect($html)
            ->toContain('window.atom.recaptcha(')
            ->toContain('() =&gt; $wire.create()')   // blade-escaped arrow; browser decodes it
            ->toContain('wire:target="create"')       // button still spins on create()
            ->not->toContain('wire:submit');           // native submit suppressed
    });

    it('uses the recaptcha action label when given a string', function () {
        $html = renderBlade('<atom:form wire:submit="register" recaptcha="signup"><input name="x"/></atom:form>');

        // single quotes are blade-escaped in the attribute; the browser decodes them
        expect($html)->toContain('action: &#039;signup&#039;');
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

describe('button submit-loading', function () {
    it('mirrors the parent form loading state for type=submit', function () {
        $html = renderBlade('<atom:button type="submit">Save</atom:button>');

        expect($html)
            ->toContain('group-[.is-loading]/form:flex')
            ->toContain('group-[.is-loading]/form:opacity-0')
            ->toContain('group-[.is-loading]/form:opacity-50')
            ->toContain('group-[.is-loading]/form:pointer-events-none');
    });

    it('does not react to form loading for non-submit buttons', function () {
        $html = renderBlade('<atom:button>Cancel</atom:button>');

        expect($html)->not->toContain('group-[.is-loading]/form');
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
