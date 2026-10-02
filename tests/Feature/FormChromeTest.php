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

/**
 * The attributes of the first <form> in a render, by parsing it rather than grepping.
 *
 * @return array<string, string>
 */
function formErrorToastAttributes(string $html): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $form = $document->getElementsByTagName('form')->item(0);
    $attributes = [];

    foreach ($form->attributes as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
    }

    return $attributes;
}

describe('form error toast', function () {
    it('opts in by default, with the heading translated server-side', function () {
        $attributes = formErrorToastAttributes(renderBlade('<atom:form wire:submit="save"><input name="x"/></atom:form>'));

        expect($attributes)
            ->toHaveKey('data-atom-error-toast')
            ->toHaveKey('data-atom-error-heading', 'Please check the form')
            ->toHaveKey('data-atom-error-more', 'and :count more');
    });

    it('translates the heading', function () {
        app('translator')->setLoaded(['*' => ['*' => ['en' => ['Please check the form' => 'Sila semak borang']]]]);

        $attributes = formErrorToastAttributes(renderBlade('<atom:form><input name="x"/></atom:form>'));

        expect($attributes['data-atom-error-heading'])->toBe('Sila semak borang');
    });

    it('translates the line that counts the messages the toast leaves out, keeping :count', function () {
        app('translator')->setLoaded(['*' => ['*' => ['en' => ['and :count more' => 'dan :count lagi']]]]);

        $attributes = formErrorToastAttributes(renderBlade('<atom:form><input name="x"/></atom:form>'));

        expect($attributes['data-atom-error-more'])->toBe('dan :count lagi');
    });

    it('opts out with :error-toast="false"', function () {
        $attributes = formErrorToastAttributes(renderBlade('<atom:form :error-toast="false"><input name="x"/></atom:form>'));

        expect($attributes)
            ->toHaveKey('data-atom-form')
            ->not->toHaveKey('data-atom-error-toast')
            ->not->toHaveKey('data-atom-error-heading')
            ->not->toHaveKey('data-atom-error-more');
    });

    it('stays off for a disabled form, which submits nothing', function () {
        $attributes = formErrorToastAttributes(renderBlade('<atom:form disabled><input name="x"/></atom:form>'));

        expect($attributes)
            ->toHaveKey('data-atom-form')
            ->not->toHaveKey('data-atom-error-toast')
            ->not->toHaveKey('data-atom-error-heading')
            ->not->toHaveKey('data-atom-error-more');
    });

    it('stays on for a reCAPTCHA form, targeting the same method', function () {
        $attributes = formErrorToastAttributes(renderBlade('<atom:form wire:submit="create" recaptcha><input name="x"/></atom:form>'));

        expect($attributes)
            ->toHaveKey('data-atom-error-toast')
            ->toHaveKey('wire:target', 'create');
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

    // -bottom-6 (not bottom-0) parks the bar at the panel edge rather than a
    // padding's height above it, and the negative margins let it bleed into the
    // modal's p-6 on the three sides it touches — see the note in the component.
    it('pins to the bottom when sticky', function () {
        $html = renderBlade('<atom:form.actions sticky><button>x</button></atom:form.actions>');

        expect($html)
            ->toContain('sticky -bottom-6')
            ->toContain('-mx-6 px-6')
            ->toContain('pb-6 -mb-6');
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
