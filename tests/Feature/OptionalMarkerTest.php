<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Jiannius\Atom\Tests\Fixtures\MarkerChildFixture;
use Jiannius\Atom\Tests\Fixtures\MarkerIslandFixture;
use Jiannius\Atom\Tests\Fixtures\MarkerParentFixture;
use Livewire\Livewire;

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
        file_put_contents($dir.'/ms.json', json_encode(['(optional)' => '(pilihan)']));
        app('translator')->addJsonPath($dir);
        app()->setLocale('ms');

        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="Name" /></atom:form>');

        unlink($dir.'/ms.json');
        rmdir($dir);

        expect(domQuery($html, '//*[@data-atom-label-optional]')[0]->textContent)->toBe('(pilihan)');
    });

    it('does not add it again when the label already ends with it, whatever surrounds it', function (string $label) {
        $html = renderBlade('<atom:form marker="optional"><atom:input name="x" label="'.$label.'" /></atom:form>');

        expect(preg_replace('/\s+/', ' ', trim(domQuery($html, '//label')[0]->textContent)))->toBe(preg_replace('/\s+/', ' ', trim($label)))
            ->and(substr_count(strtolower($html), '(optional)'))->toBe(1);
    })->with(['Reference (optional)', 'Reference (Optional)', 'Reference (optional) ', '  Reference  (optional)  ', '(optional)']);

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

    it('prints neither marker nor the key atom publishes it under, on a form, a modal form or a filter bar', function (string $template) {
        $html = renderBlade($template);

        expect($html)->not->toMatch('/\bmarker=/i')->and($html)->not->toContain('atomformmarker')->and($html)->not->toContain('atomFormMarker');
    })->with([
        'form' => '<atom:form marker="optional"><atom:input name="a" label="Name" /></atom:form>',
        'form.modal' => '<atom:form.modal name="m" marker="optional"><atom:input name="a" label="Name" /></atom:form.modal>',
        'form with a bag' => '<atom:form marker="optional" class="x"><atom:input name="a" label="Name" /></atom:form>',
        'table.filters' => '<atom:table.filters><atom:input name="a" label="Name" /></atom:table.filters>',
    ]);

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

    it('is what a form with a value it does not know falls back to', function () {
        config(['atom.form.marker' => 'optional']);

        $html = renderBlade('<atom:form marker="nonsense"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('falls back to the asterisk for a value it does not know, or none', function (mixed $value) {
        config(['atom.form.marker' => $value]);

        $html = renderBlade('<atom:form><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    })->with([null, 'nonsense', false]);
});

describe('what reaches a field', function () {
    beforeEach(fn () => Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents'));

    // `marker` is a name any host component may use (a map pin, a highlight): the form publishes its mode under a key of atom's own.
    it('is not changed by a component of the host with a marker of its own between the form and the field', function (string $value) {
        $html = renderBlade('<atom:form marker="optional"><x-marker-probe marker="'.$value.'"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></x-marker-probe></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    })->with(['pin', 'required', 'optional']);

    it('is not changed by it in a form that keeps the asterisk, either', function (string $value) {
        $html = renderBlade('<atom:form><x-marker-probe marker="'.$value.'"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></x-marker-probe></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    })->with(['pin', 'required', 'optional']);

    it('is not changed by a marker on a component with no form around it', function () {
        $html = renderBlade('<x-marker-probe marker="optional"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></x-marker-probe>');

        expect(markerCounts($html))->toBe(['asterisk' => 1, 'optional' => 0]);
    });

    // A table filter is a chip in the bar, not a field of the form: the bar publishes `required`, and a select that is a table filter says so itself.
    it('never marks a table filter, in the filter bar or on its own', function (string $template) {
        config(['atom.form.marker' => 'optional']);

        $html = renderBlade('<atom:form marker="optional">'.$template.'</atom:form>');

        expect(domQuery($html, '//*[@data-atom-label-optional]'))->toBeEmpty()
            ->and(domQuery($html, '//*[@data-atom-input-field]'))->not->toBeEmpty();
    })->with([
        'a select table filter' => '<atom:select multiple table-filter name="tags" label="Tags" :options="[]" />',
        'a listbox table filter' => '<atom:select variant="listbox" multiple table-filter name="tags" label="Tags" :options="[]" />',
        'a field in the filter bar' => '<atom:table.filters><x-slot:more><atom:select name="tags" label="Tags" :options="[]" /><atom:date-picker range name="due" label="Due" /></x-slot:more></atom:table.filters>',
    ]);

    it('marks a field beside the filter bar as the form says', function () {
        $html = renderBlade('<atom:form marker="optional"><atom:table.filters><atom:input name="q" label="Search" /></atom:table.filters><atom:input name="b" label="Phone" /></atom:form>');

        expect(array_map(fn ($note) => $note->parentNode->parentNode->textContent, domQuery($html, '//*[@data-atom-label-optional]')))->toHaveCount(1)
            ->and(preg_replace('/\s+/', ' ', domQuery($html, '//*[@data-atom-label-optional]')[0]->parentNode->textContent))->toContain('Phone');
    });
});

describe('a Livewire child inside the form', function () {
    beforeEach(function () {
        Livewire::component('atom-test-marker-child', MarkerChildFixture::class);
    });

    /** The child's two labels, as the text a screen reader reads, and how many asterisks it draws. */
    function markerChild(string $html): array
    {
        $labels = array_map(fn ($label) => preg_replace('/\s+/', ' ', trim($label->textContent)), domQuery($html, '//*[@data-child]//label'));

        return ['labels' => $labels, 'asterisks' => markerCounts($html)['asterisk']];
    }

    it('draws the same on its first render and after its own update, whatever the form says', function (string $config, array $expected) {
        config(['atom.form.marker' => $config]);

        $parent = Livewire::test(MarkerParentFixture::class);
        $first = markerChild($parent->html());

        // the child's own update renders it alone, with no form around it
        $alone = Livewire::test(MarkerChildFixture::class)->call('bump');
        $afterUpdate = markerChild($alone->html());

        expect($first['labels'])->toBe($afterUpdate['labels'])->toBe($expected['labels'])
            ->and($first['asterisks'])->toBe($expected['asterisks']);
    })->with([
        'config required' => ['required', ['labels' => ['Child required', 'Child other'], 'asterisks' => 1]],
        'config optional' => ['optional', ['labels' => ['Child required', 'Child other (optional)'], 'asterisks' => 0]],
    ]);

    it('still marks the parent\'s own fields as its form says', function () {
        $labels = array_map(fn ($label) => preg_replace('/\s+/', ' ', trim($label->textContent)), domQuery(Livewire::test(MarkerParentFixture::class)->html(), '//label'));

        expect($labels)->toContain('Parent required', 'Parent other (optional)');
    });
});

describe('the mode', function () {
    it('reads a config value in any case', function (string $value) {
        config(['atom.form.marker' => $value]);

        $html = renderBlade('<atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" />');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    })->with(['Optional', 'OPTIONAL', ' optional ']);

    it('reads the form\'s marker in any case', function () {
        $html = renderBlade('<atom:form marker="Optional"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('reads a bound marker', function () {
        $html = renderBlade('<atom:form :marker="$mode"><atom:input name="a" label="Name" required /><atom:input name="b" label="Phone" /></atom:form>', ['mode' => 'optional']);

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });

    it('reads a marker that arrives in the attribute bag of a host wrapper', function () {
        Blade::anonymousComponentPath(__DIR__.'/../Fixtures/AnonComponents');

        $html = renderBlade('<x-marker-form marker="optional" />');

        expect(markerCounts($html))->toBe(['asterisk' => 0, 'optional' => 1]);
    });
});

describe('an island', function () {
    /** The island's label as its own render prints it, apart from the page. */
    function markerIslandAlone(\Livewire\Features\SupportTesting\Testable $test): string
    {
        $component = $test->instance();
        $component->renderIsland('main');

        $fragments = (new ReflectionProperty($component, 'renderedIslandFragments'))->getValue($component);

        return preg_replace('/\s+/', ' ', trim(strip_tags(implode(' ', $fragments))));
    }

    it('draws a field of an island the same on its first render and when it is rendered on its own, a form outside it or not', function (string $where, string $first, string $alone) {
        $test = Livewire::test(MarkerIslandFixture::class, ['where' => $where]);

        expect(preg_replace('/\s+/', ' ', trim(strip_tags($test->html()))))->toBe($first)
            ->and(markerIslandAlone($test))->toBe($alone);
    })->with([
        // the form is around the island's first render only: the island reads the config on both
        'form outside' => ['outside', 'Beside (optional) Island', 'Island'],
        // the form is inside the island: it is there on both
        'form inside' => ['inside', 'Island (optional)', 'Island (optional)'],
    ]);
});

describe('a note typed in the older translation key', function () {
    /** Locale `ms` with the key the first version of the marker used: "optional" alone. */
    function markerTranslateOldKey(): void
    {
        $dir = sys_get_temp_dir().'/atom-marker-old-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/ms.json', json_encode(['optional' => 'pilihan']));
        app('translator')->addJsonPath($dir);
        app()->setLocale('ms');
        register_shutdown_function(function () use ($dir) {
            @unlink($dir.'/ms.json');
            @rmdir($dir);
        });
    }

    it('is still muted as a note, and not marked again', function (string $form) {
        markerTranslateOldKey();

        $html = renderBlade($form.'<atom:input name="a" label="Nama (pilihan)" />'.($form ? '</atom:form>' : ''));
        $note = domQuery($html, '//*[@data-atom-label-optional]');

        expect($note)->toHaveCount(1)
            ->and($note[0]->textContent)->toBe('(pilihan)')
            ->and(preg_replace('/\s+/', ' ', trim(domQuery($html, '//label')[0]->textContent)))->toBe('Nama (pilihan)');
    })->with(['outside a form' => '', 'in an optional form' => '<atom:form marker="optional">']);
});

describe('a bound marker', function () {
    it('is evaluated no more often than any other bound attribute of the tag', function () {
        $count = function (string $attribute): int {
            $calls = 0;
            $tick = function () use (&$calls) {
                $calls++;

                return 'optional';
            };

            renderBlade('<atom:form '.$attribute.'="$tick()"><atom:input name="a" label="Name" /></atom:form>', ['tick' => $tick]);

            return $calls;
        };

        expect($count(':marker'))->toBe($count(':data-probe'))->toBeGreaterThan(0);
    });
});
