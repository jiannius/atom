<?php

use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Finder\Finder;

/**
 * Every labelled control has to end up with an accessible name, and there is more than
 * one way to get one: a real form control takes <label for>, a composed widget is named
 * the other way round with aria-labelledby, and a control with no visible label needs
 * aria-label. What must never happen is a visible label that names nothing — the field
 * is then announced as an unnamed "edit text", which is what smgdms reported (#65).
 *
 * These are the server-rendered halves. tiptap's attributes are applied by ProseMirror at
 * runtime, so that one is asserted in tests/e2e/tiptap.spec.js.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

/** Pull the id off the field's <label>, if it carries one. */
function labelId(string $html): ?string
{
    preg_match('/<label[^<]*\bid="([^"]+)"/', $html, $m);

    return $m[1] ?? null;
}

/** Pull the `for` off the field's <label>, if it carries one. */
function labelFor(string $html): ?string
{
    preg_match('/<label[^<]*\bfor="([^"]+)"/', $html, $m);

    return $m[1] ?? null;
}

describe('accessible names', function () {
    it('names a real form control with label[for] pointing at the control itself', function (string $template, string $tag) {
        $html = renderBlade($template);
        $for = labelFor($html);

        expect($for)->not->toBeNull('the label has no for attribute');

        // a wrapper <div> carrying the id names nothing, while still looking correct to
        // any check that only asks whether `for` resolves to some element
        expect($html)->toMatch('/<'.$tag.'[^<]*\bid="'.preg_quote($for, '/').'"/');
    })->with([
        'select native' => ['<atom:select label="Country" wire:model="c" :options="[]" />', 'select'],
        'date-picker' => ['<atom:date-picker label="Due date" wire:model="d" />', 'input'],
    ]);

    it('names a composed widget with aria-labelledby back at the label', function (string $template, string $role) {
        $html = renderBlade($template);
        $id = labelId($html);

        expect($id)->not->toBeNull('the label carries no id for the widget to point at');

        // exactly one element claims the name: two would be a duplicate announcement
        expect(preg_match_all('/aria-labelledby="'.preg_quote($id, '/').'"/', $html))
            ->toBe(1, 'expected exactly one element to point at the label');

        // and it is the element carrying the role, not an outer wrapper
        expect($html)->toMatch('/role="'.$role.'"[\s\S]{0,400}?aria-labelledby="'.preg_quote($id, '/').'"/');
    })->with([
        'select listbox' => ['<atom:select variant="listbox" label="Country" wire:model="c" :options="[]" />', 'combobox'],
        'select listbox searchable' => ['<atom:select variant="listbox" searchable label="Country" wire:model="c" :options="[]" />', 'combobox'],
        'radio.group' => ['<atom:radio.group label="Plan" wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>', 'radiogroup'],
        'time-picker' => ['<atom:time-picker label="Start time" wire:model="t" />', 'group'],
    ]);

    // A native <select> is named by `for`. A second anchor on the label would be an id
    // nothing points at, and an id is a Livewire morph key, so it is not free.
    it('does not mint a label id for a control that label[for] already names', function () {
        $html = renderBlade('<atom:select label="Country" wire:model="c" :options="[]" />');

        expect(labelFor($html))->not->toBeNull()
            ->and(labelId($html))->toBeNull('the label carries an id nothing references');
    });

    // The uploader's own <input type="file"> is class="hidden", so it is out of the
    // accessibility tree: the trigger button is the only thing a screen reader reaches,
    // and on its own it says "Upload" — identical for every file field on a form. It
    // points at its own id first, then the field's label, so it reads "Upload Attachment"
    // without composing two translated strings by hand.
    it('names a file field through the uploader trigger, its real control being hidden', function () {
        $html = renderBlade('<atom:input type="file" label="Attachment" wire:model="doc" />');
        $id = labelId($html);

        expect($id)->not->toBeNull('the label carries no id for the trigger to point at')
            ->and(labelFor($html))->toBeNull('for would dangle at a control that cannot carry the name');

        preg_match('/<button[^<]*\bid="([^"]+)"[^<]*aria-labelledby="([^"]+)"/', $html, $button);

        expect($button[2] ?? null)->toBe(($button[1] ?? '').' '.$id, 'the trigger does not read as its own text then the field label');

        // the hidden input must not also claim the name
        expect($html)->not->toMatch('/<input[^<]*type="file"[^<]*aria-labelledby/');
    });

    it('names the chat editor, which shares the contenteditable mechanism', function () {
        $html = renderBlade('<atom:tiptap.chat label="Message" wire:model="m" />');
        $id = labelId($html);

        expect($id)->not->toBeNull()
            ->and(html_entity_decode($html))->toMatch('/labelledby:\s*[\'"]'.preg_quote($id, '/').'[\'"]/');
    });

    // These two build their label by hand in input.field's default slot rather than through
    // the `label` prop, so none of the automatic wiring reaches them.
    it('names the confirm dialog fields, whose labels are built by hand', function (string $id, string $tag) {
        $html = renderBlade('<atom:confirm />');

        expect($html)->toMatch('/<label[^<]*for="'.$id.'"/')
            ->and($html)->toMatch('/<'.$tag.'[^<]*\bid="'.$id.'"/');
    })->with([
        'passphrase' => ['atom-confirm-passphrase', 'input'],
        'reason' => ['atom-confirm-reason', 'textarea'],
    ]);

    // The audit that catches the NEXT one of these. <atom:input.field> renders a real
    // <label>, so any component using it owes its control a name — either `for` at a real
    // form control, or `labelId` for a composed widget to point back at. A new caller that
    // does neither renders a label that names nothing, which is the whole bug class.
    it('leaves no input.field caller rendering a label that names nothing', function () {
        $offenders = [];

        foreach (Finder::create()->files()->in(__DIR__.'/../../components')->name('*.blade.php') as $file) {
            $contents = $file->getContents();

            if (!str_contains($contents, 'atom:input.field')) {
                continue;
            }

            // `for` covers a real control and the hand-wired confirm dialog; `label-id`
            // covers a composed widget named the other way round with aria-labelledby.
            // The lookbehind matters: a plain `for="` substring also matches Alpine's
            // `x-for="`, which made this audit pass for every file using a loop.
            $named = str_contains($contents, ':for=')
                || str_contains($contents, ':label-id=')
                || preg_match('/(?<![\w:-])for="/', $contents);

            if (!$named) {
                $offenders[] = 'components/'.$file->getRelativePathname();
            }
        }

        expect($offenders)->toBe([]);
    });

    it('names the three time-picker inputs separately, since one for cannot reach them', function () {
        $html = renderBlade('<atom:time-picker label="Start time" wire:model="t" />');

        expect($html)
            ->toContain('aria-label="Hour"')
            ->toContain('aria-label="Minute"')
            ->toContain('aria-label="AM or PM"');
    });

    // A filter select takes no `label` prop from <atom:table.filters> — the chip is its
    // own affordance — so there is no field label to anchor to. Its trigger does show the
    // filter's name, but role="combobox" takes no accessible name from its own content
    // the way a button does, so it was announced as an unnamed combobox. Reported on
    // three smgdms listings (jiannius/atom#38).
    it('names a filter select, whose role takes no name from its own content', function (string $template, string $tag) {
        $html = renderBlade($template);

        preg_match('/<(button|input)[^<]*role="combobox"[^<]*>/', $html, $combobox);

        expect($combobox[1] ?? null)->toBe($tag)
            ->and($combobox[0])->toContain('aria-label="Type"');
    })->with([
        // searchable moves the combobox role off the trigger and onto the search input
        'trigger' => ['<atom:select variant="filter" label="Type" wire:model="t" :options="[]" />', 'button'],
        'searchable' => ['<atom:select variant="filter" searchable label="Type" wire:model="t" :options="[]" />', 'input'],
    ]);

    it('leaves a filter select with no label unnamed rather than emitting an empty name', function () {
        $html = renderBlade('<atom:select variant="filter" wire:model="t" :options="[]" />');

        expect($html)->not->toContain('aria-label=""');
    });

    it('names the table search box from its placeholder, having no visible label', function () {
        $html = renderBlade('<atom:table.search />');

        expect($html)->toContain('aria-label="Search"');
    });

    // An aria-label WINS over a <label for>, so naming the search box unconditionally made
    // a caller-supplied label unreachable: the field showed "Filter clients" and announced
    // "Search". v3.26.0 had just made that label work, so this regressed it.
    it('lets a caller-supplied label name the search box instead of the placeholder', function () {
        $html = renderBlade('<atom:table.search label="Filter clients" />');

        expect($html)->not->toContain('aria-label=')
            ->and(labelFor($html))->not->toBeNull('the visible label names nothing');
    });

    it('still names the search box when the label is present but empty', function () {
        $html = renderBlade('<atom:table.search label="" />');

        expect($html)->toContain('aria-label="Search"');
    });

    // A role="group" with no name is a boundary a screen reader announces carrying
    // nothing, and the three inputs name themselves regardless.
    it('groups the time-picker only when the group has a name', function () {
        expect(renderBlade('<atom:time-picker label="Start time" wire:model="t" />'))->toContain('role="group"')
            ->and(renderBlade('<atom:time-picker wire:model="t" />'))->not->toContain('role="group"');
    });

    // readonly makes the surface non-editable, so a bare textbox role would announce an
    // editable field that cannot be edited.
    it('marks a readonly tiptap surface readonly rather than plainly editable', function () {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../dist/manifest.json'), true);
        $bundle = file_get_contents(__DIR__.'/../../dist/'.$manifest['resources/js/atom.js']['file']);

        expect($bundle)->toContain('aria-readonly');
    });

    // Deliberate trade-off, pinned so it is a known property rather than a surprise: the
    // anchors derive from name|label|type, so the SAME field rendered twice on one page
    // collides. The alternative — minting per render — is what broke Livewire's morph, and
    // a per-request counter churns the moment one component re-renders on its own. Two
    // genuinely different fields never collide, which is the case that matters.
    it('collides only for a field that is literally duplicated on the page', function () {
        $same = renderBlade('<div><atom:input label="Name" wire:model="name" /><atom:input label="Name" wire:model="name" /></div>');
        $different = renderBlade('<div><atom:input label="Name" wire:model="name" /><atom:input label="Email" wire:model="email" /></div>');

        $ids = function (string $html) {
            preg_match_all('/<input[^<]*\bid="([^"]+)"/', $html, $m);

            return $m[1];
        };

        expect($ids($same))->toHaveCount(2)
            ->and($ids($same)[0])->toBe($ids($same)[1], 'a duplicated field is expected to share its anchor')
            ->and($ids($different)[0])->not->toBe($ids($different)[1], 'two different fields must never collide');
    });

    it('hands tiptap the anchor through its editor config, since ProseMirror owns the contenteditable', function () {
        $html = renderBlade('<atom:tiptap label="Body" wire:model="b" />');
        $id = labelId($html);

        // @js() picks its own quoting, so match the value rather than a literal string
        expect($id)->not->toBeNull()
            ->and(html_entity_decode($html))->toMatch('/labelledby:\s*[\'"]'.preg_quote($id, '/').'[\'"]/');
    });

    // Same reason as <atom:input>: the id doubles as Livewire's morph key, so one that
    // churns per render makes the morph replace the control instead of patching it.
    it('mints the same anchors on every render', function (string $template) {
        $ids = fn ($html) => preg_match_all('/(?:id|aria-labelledby)="(atom-[^"]+)"/', $html, $m) ? $m[1] : [];

        expect($ids(renderBlade($template)))
            ->not->toBeEmpty('no anchors were minted at all')
            ->toBe($ids(renderBlade($template)), 'the anchors churned between two renders');
    })->with([
        'select native' => ['<atom:select label="Country" wire:model="c" :options="[]" />'],
        'select listbox' => ['<atom:select variant="listbox" label="Country" wire:model="c" :options="[]" />'],
        'date-picker' => ['<atom:date-picker label="Due date" wire:model="d" />'],
        'time-picker' => ['<atom:time-picker label="Start time" wire:model="t" />'],
        'radio.group' => ['<atom:radio.group label="Plan" wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>'],
        'tiptap' => ['<atom:tiptap label="Body" wire:model="b" />'],
        'input file' => ['<atom:input type="file" label="Attachment" wire:model="doc" />'],
    ]);

    // Nothing points at them, and an id is what drags an element into the morph swap.
    it('mints no anchors for a control with no label', function (string $template) {
        expect(renderBlade($template))->not->toMatch('/(?:id|aria-labelledby)="atom-(?:select|date-picker|time-picker|radio-group|tiptap)-/');
    })->with([
        'select native' => ['<atom:select wire:model="c" :options="[]" />'],
        'select listbox' => ['<atom:select variant="listbox" wire:model="c" :options="[]" />'],
        'date-picker' => ['<atom:date-picker wire:model="d" />'],
        'time-picker' => ['<atom:time-picker wire:model="t" />'],
        'radio.group' => ['<atom:radio.group wire:model="p"><atom:radio value="a">A</atom:radio></atom:radio.group>'],
        'tiptap' => ['<atom:tiptap wire:model="b" />'],
    ]);
});
