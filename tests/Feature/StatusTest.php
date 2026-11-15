<?php

use Jiannius\Atom\Tests\Fixtures\StatusBareState;
use Jiannius\Atom\Tests\Fixtures\StatusOddColorState;
use Jiannius\Atom\Tests\Fixtures\StatusToneState;
use Jiannius\Atom\Tests\Fixtures\StatusTraitState;

// four enums in one file, which the PSR-4 autoloader would not find by name
require_once __DIR__.'/../Fixtures/StatusFixtureEnums.php';

/**
 * The one status a template renders.
 */
function statusElement(string $template, array $data = []): DOMElement
{
    $found = domQuery(renderBlade($template, $data), '//*[@data-atom-status]');

    expect($found)->toHaveCount(1);

    return $found[0];
}

describe('status', function () {
    it('prints the word for every tone, so the colour is never the only signal', function (string $tone) {
        $html = renderBlade('<atom:status tone="'.$tone.'" label="Word '.$tone.'" />');
        $root = domQuery($html, '//*[@data-atom-status]')[0];

        expect($root->getAttribute('data-tone'))->toBe($tone)
            ->and(trim($root->textContent))->toBe('Word '.$tone)
            // the dot is decoration: the word carries the meaning
            ->and(domQuery($html, '//*[@data-atom-status-dot][@aria-hidden="true"]'))->toHaveCount(1);
    })->with(['neutral', 'info', 'success', 'warning', 'danger', 'accent']);

    it('prints the tone\'s own name when it has no label and no slot, so it is never colour only', function (string $tone) {
        $root = domQuery(renderBlade('<atom:status tone="'.$tone.'" />'), '//*[@data-atom-status]')[0];

        expect(trim($root->textContent))->toBe(ucfirst($tone));
    })->with(['neutral', 'info', 'success', 'warning', 'danger', 'accent']);

    it('names an unknown tone neutral when it has no label', function () {
        expect(trim(domQuery(renderBlade('<atom:status tone="pink" />'), '//*[@data-atom-status]')[0]->textContent))->toBe('Neutral');
    });

    it('reads each tone from its role token pair, whole-token', function (string $tone, string $bg, string $fg) {
        $root = domQuery(renderBlade('<atom:status tone="'.$tone.'" label="x" />'), '//*[@data-atom-status]')[0];

        expect(domClasses($root))->toContain($bg, $fg, 'rounded-pill');
    })->with([
        ['neutral', 'bg-neutral-bg', 'text-neutral-fg'],
        ['info', 'bg-info-bg', 'text-info-fg'],
        ['success', 'bg-success-bg', 'text-success-fg'],
        ['warning', 'bg-warning-bg', 'text-warning-fg'],
        ['danger', 'bg-danger-bg', 'text-danger-fg'],
        ['accent', 'bg-accent-soft', 'text-accent-ink'],
    ]);

    it('falls back to neutral for a tone it does not know', function () {
        $root = domQuery(renderBlade('<atom:status tone="bright pink" label="x" />'), '//*[@data-atom-status]')[0];

        expect($root->getAttribute('data-tone'))->toBe('neutral')
            ->and(domClasses($root))->toContain('bg-neutral-bg');
    });

    it('marks a running status for the pulse, and only then', function () {
        expect(domQuery(renderBlade('<atom:status running label="Building" />'), '//*[@data-atom-status][@data-running]'))->toHaveCount(1)
            ->and(domQuery(renderBlade('<atom:status label="Live" />'), '//*[@data-atom-status][@data-running]'))->toHaveCount(0);
    });

    it('lets a slot replace the label', function () {
        $root = domQuery(renderBlade('<atom:status label="ignored"><b>Slot word</b></atom:status>'), '//*[@data-atom-status]')[0];

        expect(trim($root->textContent))->toBe('Slot word');
    });

    it('prints the label as text, not markup', function () {
        $html = renderBlade('<atom:status :label="$label" />', ['label' => '<img src=x onerror=alert(1)>']);

        expect(domQuery($html, '//*[@data-atom-status]//img'))->toBeEmpty()
            ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('prints the bag', function () {
        $root = domQuery(renderBlade('<atom:status id="s" class="ms-2" label="x" x-show="ready" />'), '//*[@data-atom-status]')[0];

        expect($root->getAttribute('id'))->toBe('s')
            ->and($root->getAttribute('x-show'))->toBe('ready')
            ->and(domClasses($root))->toContain('ms-2');
    });

    it('pulses the dot only under no-preference motion, in atom.css', function () {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/atom.css');

        expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*\[data-atom-status\]\[data-running\] > \[data-atom-status-dot\]\s*\{\s*animation: atom-status-pulse/');
    });
});

describe('status from a value', function () {
    it('reads the tone and the label from an enum that uses the trait', function (StatusTraitState $case, string $tone, string $label) {
        $root = statusElement('<atom:status :value="$value" />', ['value' => $case]);

        expect($root->getAttribute('data-tone'))->toBe($tone)
            ->and(trim($root->textContent))->toBe($label);
    })->with([
        'green is success' => [StatusTraitState::PAID, 'success', 'Paid'],
        'red is danger' => [StatusTraitState::DUE, 'danger', 'Due'],
        'yellow is warning' => [StatusTraitState::NEW, 'warning', 'New'],
        'gray is neutral' => [StatusTraitState::DRAFT, 'neutral', 'Draft'],
    ]);

    it('reads the enum\'s own tone() before its colour()', function () {
        // the trait's colour() would say warning for `processing`; the host's tone() says info
        $root = statusElement('<atom:status :value="$value" />', ['value' => StatusToneState::PROCESSING]);

        expect($root->getAttribute('data-tone'))->toBe('info')
            ->and(domClasses($root))->toContain('bg-info-bg', 'text-info-fg');
    });

    it('skips a tone() that is not a tone and reads the colour() instead', function () {
        $root = statusElement('<atom:status :value="$value" />', ['value' => StatusToneState::ODD]);

        // the trait's colour() of `paid` is green
        expect($root->getAttribute('data-tone'))->toBe('success');
    });

    it('lets the tone prop win over the enum', function () {
        $root = statusElement('<atom:status :value="$value" tone="accent" />', ['value' => StatusToneState::PROCESSING]);

        expect($root->getAttribute('data-tone'))->toBe('accent');
    });

    it('lets the label prop win over the enum, and the slot win over both', function () {
        $prop = statusElement('<atom:status :value="$value" label="Settled" />', ['value' => StatusTraitState::PAID]);
        $slot = statusElement('<atom:status :value="$value" label="Settled"><b>Slot word</b></atom:status>', ['value' => StatusTraitState::PAID]);

        expect(trim($prop->textContent))->toBe('Settled')
            // the value still gives the tone
            ->and($prop->getAttribute('data-tone'))->toBe('success')
            ->and(trim($slot->textContent))->toBe('Slot word');
    });

    it('takes the colour and label of an array', function () {
        $root = statusElement('<atom:status :value="$value" />', ['value' => ['label' => 'Overdue', 'color' => 'red']]);

        expect($root->getAttribute('data-tone'))->toBe('danger')
            ->and(trim($root->textContent))->toBe('Overdue');
    });

    it('takes a tone from an array before its colour', function () {
        $root = statusElement('<atom:status :value="$value" />', ['value' => ['label' => 'Blocked', 'tone' => 'warning', 'color' => 'red']]);

        expect($root->getAttribute('data-tone'))->toBe('warning');
    });

    it('takes the colour and label of an object', function () {
        $root = statusElement('<atom:status :value="$value" />', ['value' => (object) ['label' => 'Done', 'color' => 'green']]);

        expect($root->getAttribute('data-tone'))->toBe('success')
            ->and(trim($root->textContent))->toBe('Done');
    });

    it('draws neutral for an unknown colour, a hex and black', function (mixed $value) {
        $root = statusElement('<atom:status :value="$value" />', ['value' => $value]);

        expect($root->getAttribute('data-tone'))->toBe('neutral')
            ->and(domClasses($root))->toContain('bg-neutral-bg');
    })->with([
        'unknown name' => [['label' => 'X', 'color' => 'chartreuse']],
        'hex' => [['label' => 'X', 'color' => '#ff0000']],
        'black' => [['label' => 'X', 'color' => 'black']],
        'enum black' => [StatusOddColorState::BLACK],
        'enum hex' => [StatusOddColorState::HEX],
    ]);

    it('names the tone when the value has no label', function () {
        $bare = statusElement('<atom:status :value="$value" />', ['value' => StatusBareState::OPEN]);
        $array = statusElement('<atom:status :value="$value" />', ['value' => ['color' => 'green']]);

        expect(trim($bare->textContent))->toBe('Neutral')
            ->and(trim($array->textContent))->toBe('Success');
    });

    it('prints a label from the value as text, not markup', function () {
        $html = renderBlade('<atom:status :value="$value" />', ['value' => StatusToneState::PROCESSING]);
        $array = renderBlade('<atom:status :value="$value" />', ['value' => ['label' => '<img src=x onerror=alert(1)>', 'color' => 'red']]);

        expect(domQuery($html, '//*[@data-atom-status]//b'))->toBeEmpty()
            ->and($html)->toContain('In progress &lt;b&gt;')
            ->and(domQuery($array, '//*[@data-atom-status]//img'))->toBeEmpty()
            ->and($array)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('does not run an enum label through the translator, as the badge does', function () {
        app('translator')->setLoaded(['*' => ['*' => [app()->getLocale() => ['Paid' => 'Dibayar']]]]);

        $fromValue = statusElement('<atom:status :value="$value" />', ['value' => StatusTraitState::PAID]);
        $fromProp = statusElement('<atom:status label="Paid" />');

        expect(trim($fromValue->textContent))->toBe('Paid')
            ->and(trim($fromProp->textContent))->toBe('Dibayar');
    });

    it('keeps running and the bag working beside a value', function () {
        $root = statusElement('<atom:status :value="$value" running id="s" />', ['value' => StatusTraitState::NEW]);

        expect($root->hasAttribute('data-running'))->toBeTrue()
            ->and($root->getAttribute('id'))->toBe('s')
            ->and($root->getAttribute('data-tone'))->toBe('warning');
    });

    it('reads a null value as it always did', function () {
        $root = statusElement('<atom:status :value="null" />');

        expect($root->getAttribute('data-tone'))->toBe('neutral')
            ->and(trim($root->textContent))->toBe('Neutral');
    });

    it('treats a value that is a plain string as no state', function () {
        $root = statusElement('<atom:status value="paid" label="Paid" />');

        expect($root->getAttribute('data-tone'))->toBe('neutral')
            ->and(trim($root->textContent))->toBe('Paid');
    });
});
