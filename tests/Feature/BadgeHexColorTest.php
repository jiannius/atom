<?php

use Jiannius\Atom\Services\Color;

/**
 * A badge colour that starts with `#` is written into a style attribute, so it
 * has to BE a hex colour. `{{ }}` keeps a value inside the attribute but would
 * still let `#000; position: fixed` add declarations of its own.
 *
 * The assertions read the parsed style attribute: a declaration that was
 * injected is a declaration the element has, wherever the value ended up.
 */

enum BadgeHexColorStatus: string
{
    case VALID = 'valid';
    case SHORT = 'short';
    case INJECTED = 'injected';
    case URL = 'url';
    case BRACE = 'brace';

    public function color(): string
    {
        return match ($this) {
            self::VALID => '#aabbcc',
            self::SHORT => '#abc',
            self::INJECTED => '#000; position: fixed; inset: 0; z-index: 9999',
            self::URL => '#000;background:url(https://attacker.test/x)',
            self::BRACE => '#000}',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

/**
 * The single badge element a template renders.
 */
function badgeHexColorElement(string $template, array $data = []): DOMElement
{
    $badges = domQuery(renderBlade($template, $data), '//*[@data-atom-badge]');

    expect($badges)->toHaveCount(1);

    return $badges[0];
}

/**
 * The CSS property names a style attribute declares.
 *
 * @return list<string>
 */
function badgeHexColorProperties(DOMElement $badge): array
{
    $declarations = array_filter(array_map('trim', explode(';', $badge->getAttribute('style'))));

    return array_values(array_map(fn ($declaration) => strtolower(trim(strstr($declaration, ':', true))), $declarations));
}

dataset('badge hex colors', [
    '3 digits' => ['#abc'],
    '4 digits' => ['#abcd'],
    '6 digits' => ['#aabbcc'],
    '8 digits' => ['#aabbccdd'],
    'upper case' => ['#AABBCC'],
    'surrounding whitespace' => ["  #aabbcc \n"],
]);

dataset('badge injected colors', [
    'declarations' => ['#000; position: fixed; inset: 0; z-index: 9999'],
    'url without a space' => ['#000;background:url(https://attacker.test/x)'],
    'url after the hex' => ['#000 url(https://attacker.test/x)'],
    'colon' => ['#000:red'],
    'whitespace inside' => ['#00 0'],
    'closing brace' => ['#000}'],
    'newline inside' => ["#000\nposition: fixed"],
    'over-long hex' => ['#'.str_repeat('a', 500)],
    'nine digits' => ['#aabbccdde'],
    'five digits' => ['#abcde'],
    'seven digits' => ['#aabbccd'],
    'two digits' => ['#ab'],
    'not hex digits' => ['#ggg'],
    'just a hash' => ['#'],
    'semicolon only' => ['#;'],
]);

describe('badge hex colour', function () {
    it('renders the hex style for a valid hex colour', function (string $color) {
        $badge = badgeHexColorElement('<atom:badge :color="$color" label="Custom" />', compact('color'));

        expect(badgeHexColorProperties($badge))->toBe(['color', 'background-color', 'border-color'])
            ->and($badge->getAttribute('style'))->toStartWith('color: '.trim($color).';')
            ->and($badge->getAttribute('style'))->toContain('background-color: rgba(')
            ->and($badge->getAttribute('style'))->toContain('border-color: rgba(');
    })->with('badge hex colors');

    it('shades a 4-digit hex colour as its 3-digit form', function () {
        $short = badgeHexColorElement('<atom:badge color="#f00" label="x" />');
        $withAlpha = badgeHexColorElement('<atom:badge color="#f00a" label="x" />');

        expect(preg_replace('/^color: [^;]+;/', '', $withAlpha->getAttribute('style')))
            ->toBe(preg_replace('/^color: [^;]+;/', '', $short->getAttribute('style')));
    });

    it('adds no declaration and falls back to the default style for a value that is not hex', function (string $color) {
        $badge = badgeHexColorElement('<atom:badge :color="$color" label="Custom" />', compact('color'));

        expect($badge->hasAttribute('style'))->toBeFalse()
            ->and(domClasses($badge))->toContain('bg-zinc-100');
    })->with('badge injected colors');

    it('keeps a caller style last and still refuses an injected colour beside it', function () {
        $badge = badgeHexColorElement(
            '<atom:badge :color="$color" label="x" style="margin-left: 4px" />',
            ['color' => '#000; position: fixed'],
        );

        expect($badge->getAttribute('style'))->toBe('margin-left: 4px');

        $badge = badgeHexColorElement('<atom:badge color="#aabbcc" label="x" style="margin-left: 4px" />');

        expect($badge->getAttribute('style'))->toEndWith('margin-left: 4px;');
    });

    it('applies the same rule to a colour that comes from a status', function (BadgeHexColorStatus $status, bool $isHex) {
        $badge = badgeHexColorElement('<atom:badge :status="$status" />', compact('status'));

        if ($isHex) {
            expect(badgeHexColorProperties($badge))->toBe(['color', 'background-color', 'border-color']);
        } else {
            expect($badge->hasAttribute('style'))->toBeFalse()
                ->and(domClasses($badge))->toContain('bg-zinc-100');
        }
    })->with([
        'valid' => [BadgeHexColorStatus::VALID, true],
        'short' => [BadgeHexColorStatus::SHORT, true],
        'declarations' => [BadgeHexColorStatus::INJECTED, false],
        'url' => [BadgeHexColorStatus::URL, false],
        'brace' => [BadgeHexColorStatus::BRACE, false],
    ]);

    it('applies the same rule to a colour from a status array or object', function (mixed $status, bool $isHex) {
        $badge = badgeHexColorElement('<atom:badge :status="$status" />', compact('status'));

        expect($badge->hasAttribute('style'))->toBe($isHex)
            ->and(domClasses($badge))->when(! $isHex, fn ($classes) => $classes->toContain('bg-zinc-100'));
    })->with([
        'array hex' => [['color' => '#aabbcc', 'label' => 'x'], true],
        'array injected' => [['color' => '#000;background:url(https://attacker.test/x)', 'label' => 'x'], false],
        'object injected' => [(object) ['color' => '#000; position: fixed', 'label' => 'x'], false],
    ]);

    it('does not shade a value that is not a hex colour', function (string $color) {
        expect(Color::isHex($color))->toBeFalse()
            ->and(Color::shade($color, 70, 0.4))->toBeNull();
    })->with('badge injected colors');

    it('recognises exactly the hex shapes', function (string $color) {
        expect(Color::isHex($color))->toBeTrue()
            ->and(Color::shade($color, 70, 0.4))->toStartWith('rgba(');
    })->with('badge hex colors');
});
