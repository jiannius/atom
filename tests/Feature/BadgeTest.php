<?php

use Illuminate\Support\HtmlString;
use Jiannius\Atom\Services\Color;

/*
 * The v4 badge: a borderless pill on the tone tokens. A colour name a host passes maps to a tone (Color::tone()), so
 * `amber` and `indigo` no longer fall back to grey. <atom:status> stays the dot plus the word.
 */

/**
 * The single badge a template renders.
 */
function badgeElement(string $template, array $data = []): DOMElement
{
    $badges = domQuery(renderBlade($template, $data), '//*[@data-atom-badge]');

    expect($badges)->toHaveCount(1);

    return $badges[0];
}

enum BadgeTestStatus: string
{
    case PAID = 'paid';
    case LATE = 'late';

    public function color(): string
    {
        return match ($this) {
            self::PAID => 'green',
            self::LATE => 'amber',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

describe('badge colours', function () {
    // the mapping table of UPGRADE.md: every name, the tone it reads, and the pair of classes that tone draws
    $tones = [
        'neutral' => 'bg-neutral-bg text-neutral-fg',
        'info' => 'bg-info-bg text-info-fg',
        'success' => 'bg-success-bg text-success-fg',
        'warning' => 'bg-warning-bg text-warning-fg',
        'danger' => 'bg-danger-bg text-danger-fg',
        'accent' => 'bg-accent-soft text-accent-ink',
        'inverse' => 'bg-fg text-surface',
    ];

    $names = [
        'gray' => 'neutral', 'grey' => 'neutral', 'zinc' => 'neutral', 'slate' => 'neutral', 'stone' => 'neutral', 'neutral' => 'neutral', 'white' => 'neutral',
        'blue' => 'info', 'sky' => 'info', 'cyan' => 'info', 'indigo' => 'info', 'info' => 'info',
        'green' => 'success', 'emerald' => 'success', 'teal' => 'success', 'lime' => 'success', 'success' => 'success',
        'yellow' => 'warning', 'amber' => 'warning', 'orange' => 'warning', 'warning' => 'warning',
        'red' => 'danger', 'rose' => 'danger', 'danger' => 'danger', 'error' => 'danger',
        'purple' => 'accent', 'violet' => 'accent', 'fuchsia' => 'accent', 'pink' => 'accent', 'accent' => 'accent', 'primary' => 'accent',
        'black' => 'inverse',
    ];

    it('maps every colour name a host plausibly passes to a tone, and draws that tone\'s pair', function (string $name, string $tone) use ($tones) {
        $badge = badgeElement('<atom:badge :color="$name" label="x" />', compact('name'));

        expect(Color::tone($name))->toBe($tone)
            ->and($badge->getAttribute('data-tone'))->toBe($tone)
            ->and(domClasses($badge))->toContain(...explode(' ', $tones[$tone]));
    })->with(array_map(null, array_keys($names), array_values($names)));

    it('reads amber and indigo as tones, not as the grey fallback', function () {
        expect(domClasses(badgeElement('<atom:badge color="amber" label="x" />')))->toContain('bg-warning-bg', 'text-warning-fg')->not->toContain('bg-neutral-bg')
            ->and(domClasses(badgeElement('<atom:badge color="indigo" label="x" />')))->toContain('bg-info-bg', 'text-info-fg')->not->toContain('bg-neutral-bg');
    });

    it('is neutral for a colour it does not know, for no colour, and for a value that is not a string', function (mixed $color) {
        $badge = badgeElement('<atom:badge :color="$color" label="x" />', compact('color'));

        expect($badge->getAttribute('data-tone'))->toBe('neutral')
            ->and(domClasses($badge))->toContain('bg-neutral-bg', 'text-neutral-fg');
    })->with([null, '', 'chartreuse', 'bright pink', [['red']], 42, false]);

    it('ignores the case and the spaces round a colour name', function () {
        expect(Color::tone('  Amber '))->toBe('warning')
            ->and(Color::tone('GREEN'))->toBe('success');
    });

    it('reads the colour from a status enum and from an array', function () {
        $paid = badgeElement('<atom:badge :status="$status" />', ['status' => BadgeTestStatus::PAID]);
        $late = badgeElement('<atom:badge :status="$status" />', ['status' => BadgeTestStatus::LATE]);
        $array = badgeElement('<atom:badge :status="$status" />', ['status' => ['color' => 'indigo', 'label' => 'Queued']]);

        expect(domClasses($paid))->toContain('bg-success-bg')
            ->and(trim($paid->textContent))->toBe('Paid')
            ->and(domClasses($late))->toContain('bg-warning-bg')
            ->and(domClasses($array))->toContain('bg-info-bg')
            ->and(trim($array->textContent))->toBe('Queued');
    });
});

describe('badge shape', function () {
    it('is a borderless pill: no border, no shadow, a 999px radius', function (string $color) {
        $classes = domClasses(badgeElement('<atom:badge :color="$color" label="x" />', compact('color')));

        expect($classes)->toContain('rounded-pill')
            ->and(array_filter($classes, fn ($c) => preg_match('/^(?:border|shadow|ring)(?:-|$)/', $c)))->toBe([]);
    })->with(['neutral', 'red', 'black', 'indigo', '#aabbcc']);

    it('has three sizes, 18, 22 and 26px, on the 12 and 14px text', function () {
        expect(domClasses(badgeElement('<atom:badge size="xs" label="x" />')))->toContain('h-[18px]', 'text-xs')
            ->and(domClasses(badgeElement('<atom:badge label="x" />')))->toContain('h-[22px]', 'text-xs')
            ->and(domClasses(badgeElement('<atom:badge size="sm" label="x" />')))->toContain('h-[22px]')
            ->and(domClasses(badgeElement('<atom:badge size="lg" label="x" />')))->toContain('h-[26px]', 'text-sm');
    });

    it('names no raw palette colour, whatever colour it is given', function (string $color) {
        $html = renderBlade('<atom:badge :color="$color" label="x" />', compact('color'));

        expect($html)->not->toMatch('/\b(?:bg|text|border)-(?:zinc|gray|red|orange|amber|yellow|green|sky|blue|indigo|purple|white|black)\b/');
    })->with(['red', 'amber', 'indigo', 'black', 'gray', 'purple', 'zzz']);

    it('keeps a caller class in place of the max width, and the caller\'s attributes', function () {
        $badge = badgeElement('<atom:badge label="x" class="max-w-24" data-testid="b" />');

        expect(domClasses($badge))->toContain('max-w-24')->not->toContain('max-w-xs')
            ->and($badge->getAttribute('data-testid'))->toBe('b');

        expect(domClasses(badgeElement('<atom:badge label="x" />')))->toContain('max-w-xs');
    });
});

describe('badge label', function () {
    it('prints a label as text, never as markup', function () {
        $html = renderBlade('<atom:badge :label="$label" />', ['label' => '<img src=x onerror=alert(1)>']);

        expect(domQuery($html, '//*[@data-atom-badge]//img'))->toBeEmpty()
            ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('prints a status label as text too', function () {
        $html = renderBlade('<atom:badge :status="$status" />', ['status' => ['color' => 'red', 'label' => '<b onmouseover=x>Hi</b>']]);

        expect(domQuery($html, '//*[@data-atom-badge]//b'))->toBeEmpty()
            ->and($html)->toContain('&lt;b onmouseover=x&gt;Hi&lt;/b&gt;');
    });

    it('prints markup only from an HtmlString the caller built on purpose', function () {
        $html = renderBlade('<atom:badge :label="$label" />', ['label' => new HtmlString('<b>Bold</b>')]);

        expect(domQuery($html, '//*[@data-atom-badge]//b'))->toHaveCount(1);
    });

    it('lets the slot replace the label', function () {
        $badge = badgeElement('<atom:badge label="ignored">Slot</atom:badge>');

        expect(trim($badge->textContent))->toBe('Slot');
    });
});

describe('badge and status', function () {
    it('is not the status: a badge has no dot, a status has one', function () {
        $badge = renderBlade('<atom:badge color="green" label="Paid" />');
        $status = renderBlade('<atom:status tone="success" label="Paid" />');

        expect(domQuery($badge, '//*[@data-atom-status-dot]'))->toBeEmpty()
            ->and(domQuery($badge, '//*[@data-atom-status]'))->toBeEmpty()
            ->and(domQuery($status, '//*[@data-atom-status-dot]'))->toHaveCount(1)
            ->and(domQuery($status, '//*[@data-atom-badge]'))->toBeEmpty();
    });

    it('draws a badge and a status of one tone with the same pair', function (string $name, string $tone) {
        $badge = domClasses(badgeElement('<atom:badge :color="$name" label="x" />', compact('name')));
        $status = domClasses(domQuery(renderBlade('<atom:status :tone="$tone" label="x" />', compact('tone')), '//*[@data-atom-status]')[0]);

        expect(array_values(array_intersect($badge, ['bg-neutral-bg', 'bg-info-bg', 'bg-success-bg', 'bg-warning-bg', 'bg-danger-bg', 'bg-accent-soft'])))
            ->toBe(array_values(array_intersect($status, ['bg-neutral-bg', 'bg-info-bg', 'bg-success-bg', 'bg-warning-bg', 'bg-danger-bg', 'bg-accent-soft'])));
    })->with([['gray', 'neutral'], ['indigo', 'info'], ['green', 'success'], ['amber', 'warning'], ['red', 'danger'], ['purple', 'accent']]);
});

describe('navlist badge', function () {
    it('maps the same names, so a count and a badge agree', function (string $name, string $tone) {
        $html = renderBlade('<atom:navlist.badge :color="$name">3</atom:navlist.badge>', compact('name'));

        $expected = match ($tone) {
            'accent' => ['bg-accent-soft', 'text-accent-ink'],
            'inverse' => ['bg-fg', 'text-surface'],
            default => ["bg-{$tone}-bg", "text-{$tone}-fg"],
        };

        $span = domQuery($html, '//span')[0];

        expect(domClasses($span))->toContain(...[...$expected, 'rounded-pill', 'tabular-nums']);
    })->with([['amber', 'warning'], ['indigo', 'info'], ['red', 'danger'], ['green', 'success'], ['purple', 'accent'], ['gray', 'neutral'], ['black', 'inverse'], ['nonsense', 'neutral']]);
});

describe('badge group', function () {
    it('draws the overflow count in the muted role token', function () {
        $html = renderBlade('<atom:badge.group max="1"><atom:badge label="a" /><atom:badge label="b" /></atom:badge.group>');

        expect($html)->toContain('text-fg-muted')->not->toContain('text-muted');
    });
});
