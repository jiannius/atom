<?php

use Illuminate\Support\ViewErrorBag;

/**
 * The optional marker (#212): `<atom:form marker="optional">` (or `config('atom.form.marker')`) draws no asterisk and adds "(optional)" to
 * the label of a field that is not `required`. Every labelled control goes through `input.field`, which reads the mode with `@aware`.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/** The asterisks and the "(optional)" notes inside the rendered fields. */
function markerCounts(string $html): array
{
    return [
        'asterisk' => count(domQuery($html, '//*[@data-atom-input-field]//label//*[@data-atom-icon][contains(@class, "text-danger-fg")]')),
        'optional' => count(domQuery($html, '//*[@data-atom-input-field]//label//*[@data-atom-label-optional]')),
    ];
}

/** Every control that draws a field label, as a tag with `%s` for the extra attributes. */
function markerControls(): array
{
    return [
        'input' => '<atom:input name="x" label="Name" %s />',
        'input password' => '<atom:input type="password" name="x" label="Name" %s />',
        'input number' => '<atom:input type="number" name="x" label="Name" %s />',
        'input with prefix' => '<atom:input name="x" label="Name" prefix="RM" %s />',
        'input email' => '<atom:input type="email" name="x" label="Name" %s />',
        'input email multiple' => '<atom:input type="email" name="x" label="Name" multiple %s />',
        'input tel' => '<atom:input type="tel" name="x" label="Name" %s />',
        'input color' => '<atom:input type="color" name="x" label="Name" %s />',
        'input file' => '<atom:input type="file" name="x" label="Name" wire:model="doc" %s />',
        'textarea' => '<atom:textarea name="x" label="Name" %s />',
        'select native' => '<atom:select name="x" label="Name" :options="[]" %s />',
        'select listbox' => '<atom:select variant="listbox" name="x" label="Name" :options="[]" %s />',
        'select listbox searchable' => '<atom:select variant="listbox" searchable name="x" label="Name" :options="[]" %s />',
        'date-picker' => '<atom:date-picker name="x" label="Name" %s />',
        'date-picker range' => '<atom:date-picker range name="x" label="Name" %s />',
        'time-picker' => '<atom:time-picker name="x" label="Name" %s />',
        'radio.group' => '<atom:radio.group name="x" label="Name" %s><atom:radio value="a">A</atom:radio></atom:radio.group>',
        'tiptap' => '<atom:tiptap name="x" label="Name" wire:model="body" %s />',
    ];
}

describe('the default marker', function () {
    it('draws the asterisk on a required field and nothing on the others', function (string $template) {
        $html = renderBlade('<atom:form>'.sprintf($template, 'required').sprintf($template, '').'</atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    })->with(markerControls());
});

describe('marker="optional"', function () {
    it('draws no asterisk and "(optional)" on a field that is not required, for every labelled control', function (string $template) {
        $required = renderBlade('<atom:form marker="optional">'.sprintf($template, 'required').'</atom:form>');
        $other = renderBlade('<atom:form marker="optional">'.sprintf($template, '').'</atom:form>');

        expect(markerCounts($required))->toBe(['asterisk' => 0, 'optional' => 0])
            ->and(markerCounts($other))->toBe(['asterisk' => 0, 'optional' => 1])
            // the note is inside the label, so the accessible name carries it
            ->and(preg_replace('/\s+/', ' ', trim(domQuery($other, '//*[@data-atom-input-field]//label')[0]->textContent)))->toBe('Name (optional)');
    })->with(markerControls());

    it('prints the note muted at the regular weight, like one typed in the label', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="Name" /></atom:form>');
        $note = domQuery($html, '//*[@data-atom-label-optional]');

        expect($note)->toHaveCount(1)
            ->and($note[0]->textContent)->toBe('(optional)')
            ->and(domClasses($note[0]))->toContain('text-fg-muted', 'font-normal');
    });

    it('translates the word', function () {
        $dir = sys_get_temp_dir().'/atom-marker-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/ms.json', json_encode(['optional' => 'pilihan']));
        app('translator')->addJsonPath($dir);
        app()->setLocale('ms');

        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="Name" /></atom:form>');

        unlink($dir.'/ms.json');
        rmdir($dir);

        expect(domQuery($html, '//*[@data-atom-label-optional]')[0]->textContent)->toBe('(pilihan)');
    });

    it('does not add it again when the label already ends with it', function (string $label) {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="'.$label.'" /></atom:form>');

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toHaveCount(1)
            ->and(preg_replace('/\s+/', ' ', trim(domQuery($html, '//label')[0]->textContent)))->toBe($label);
    })->with(['Reference (optional)', 'Reference (Optional)']);

    it('adds it to a label that only has the word in the middle', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="Optional extras" /></atom:form>');

        expect(domQuery($html, '//label')[0]->textContent)->toContain('Optional extras')
            ->and(domQuery($html, '//*[@data-atom-label-optional]'))->toHaveCount(1);
    });

    it('marks a label that is HTML the host chose, with the note after it', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" :label="new \Illuminate\Support\HtmlString(\'<em>Ref</em>\')" /></atom:form>');

        expect(domQuery($html, '//label//em'))->toHaveCount(1)
            ->and(domQuery($html, '//label//*[@data-atom-label-optional]'))->toHaveCount(1);
    });

    it('draws nothing for a field with no label', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" /><atom:input name="y" caption="Help" /></atom:form>');

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toBeEmpty()
            ->and(domQuery($html, '//label'))->toBeEmpty();
    });

    it('leaves a lone checkbox, a toggle and a label slot as they are', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:form marker="optional">
                <atom:checkbox name="a" label="Agree" />
                <atom:toggle name="b" label="On" />
                <atom:input.field><x-slot:label>Custom</x-slot:label><input></atom:input.field>
            </atom:form>
        BLADE);

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toBeEmpty();
    });

    it('leaves a table filter chip alone: it is not a field with a label', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:select variant="filter" name="x" label="Status" :options="[]" /></atom:form>');

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toBeEmpty();
    });

    it('leaves a chat composer unmarked: it has no required', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:tiptap.chat name="x" label="Message" wire:model="body" /></atom:form>');

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toBeEmpty();
    });

    it('reaches a field through form.section and form.grid', function () {
        $html = renderBlade(<<<'BLADE'
            <atom:form marker="optional">
                <atom:form.section heading="Customer">
                    <atom:form.grid cols="2">
                        <atom:input name="a" label="Name" required />
                        <atom:input name="b" label="Phone" />
                    </atom:form.grid>
                </atom:form.section>
            </atom:form>
        BLADE);

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('reaches a field of form.modal', function () {
        $html = renderBlade('<atom:form.modal name="m" marker="optional"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form.modal>');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('does not print marker on the form tag', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="a" label="Name" /></atom:form>');

        expect(domQuery($html, '//form')[0]->hasAttribute('marker'))->toBeFalse()
            ->and(domQuery($html, '//*[@data-atom-input-field]')[0]->hasAttribute('marker'))->toBeFalse();
    });

    it('ends at the form: a field after it is back to the default', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="a" label="Name" /></atom:form><atom:input name="b" label="Phone" required />');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 1]);
    });

    it('is overridden by an inner form', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:form marker="required"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    });

    it('reads a value it does not know as the default', function () {
        $html = renderBlade('<atom:form marker="nonsense"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    });
});

describe('config("atom.form.marker")', function () {
    it('is "required" out of the box', function () {
        expect(config('atom.form.marker'))->toBe('required');
    });

    it('marks the optional fields of a form with no marker of its own, and of a field outside a form', function () {
        config(['atom.form.marker' => 'optional']);

        $inForm = renderBlade('<atom:form><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');
        $alone = renderBlade('<atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" />');

        expect(markerCounts($inForm))->toBe(['asterisk' => 0, 'optional' => 1])
            ->and(markerCounts($alone))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('loses to the form: marker="required" brings the asterisk back', function () {
        config(['atom.form.marker' => 'optional']);

        $html = renderBlade('<atom:form marker="required"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    });

    it('falls back to the asterisk for a value it does not know, or none', function (mixed $value) {
        config(['atom.form.marker' => $value]);

        $html = renderBlade('<atom:form><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    })->with([null, 'nonsense', false]);
});
