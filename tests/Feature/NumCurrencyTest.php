<?php

/**
 * num($v)->currency() shows 2dp and rounds half-up (away from zero) by default;
 * `$roundingMode` picks 'half-even' or 'half-down' instead. `$maxPrecision`
 * opts into at least 2 and at most N decimals, trailing zeros past the 2nd trimmed.
 */

it('rounds half-up at 2dp by default', function (mixed $in, string $expected) {
    expect(num($in)->currency())->toBe($expected);
})->with([
    '10.185' => [10.185, '10.19'],
    '0.125' => [0.125, '0.13'],
    'unit price 0.065' => [0.065, '0.07'],
    'negative tie goes away from zero' => [-0.125, '-0.13'],
    'not a tie' => [10.184, '10.18'],
]);

it('leaves values that are not a rounding tie as they were', function () {
    expect(num(1234.5)->currency())->toBe('1,234.50');
    expect(num(1234567.891)->currency())->toBe('1,234,567.89');
    expect(num(0)->currency())->toBe('0.00');
    expect(num(-5.5)->currency())->toBe('-5.50');
    expect(num('1234.5')->currency())->toBe('1,234.50');
    expect(num(1234.5)->currency('USD'))->toBe('USD 1,234.50');
    expect(num(-5.5)->currency(null, false, true))->toBe('(5.50)');
    expect(num(-5.5)->currency('MYR', false, true))->toBe('(MYR 5.50)');
    expect(num(10.12)->currency(null, true))->toBe('10.10');
    expect(num(10.12)->currency('MYR', true))->toBe('MYR 10.10');
    expect(num(1500000)->currency('USD', abbreviate: true))->toBe('USD 2M');
    expect(num('abc')->currency('USD'))->toBe('abc');
});

it('keeps the locale of the Number helper', function () {
    Illuminate\Support\Number::withLocale('de', function () {
        expect(num(1234.5)->currency())->toBe('1.234,50');
        expect(num(0.065)->currency(maxPrecision: 6))->toBe('0,065');
    });
});

it('rounds half-even when asked', function (mixed $in, string $expected) {
    expect(num($in)->currency(roundingMode: 'half-even'))->toBe($expected);
})->with([
    '10.185' => [10.185, '10.18'],
    '0.125' => [0.125, '0.12'],
    '0.135 goes up to the even digit' => [0.135, '0.14'],
    'negative' => [-0.125, '-0.12'],
]);

it('rounds half-down when asked', function () {
    expect(num(0.125)->currency(roundingMode: 'half-down'))->toBe('0.12');
    expect(num(0.126)->currency(roundingMode: 'half-down'))->toBe('0.13');
    expect(num(-0.125)->currency(roundingMode: 'half-down'))->toBe('-0.12');
});

it('refuses an unknown rounding mode and names it as the caller wrote it', function () {
    expect(fn () => num(1)->currency(roundingMode: 'BANKER'))->toThrow(InvalidArgumentException::class, 'Unknown rounding mode [BANKER]');
    expect(fn () => num('abc')->currency(roundingMode: 'nope'))->toThrow(InvalidArgumentException::class, 'Unknown rounding mode [nope]');
});

// The "intl missing" guard in currency() is untested: intl can't be unloaded in-process.

it('formats with at least 2 and at most maxPrecision decimals, half-up', function (mixed $in, string $expected) {
    expect(num($in)->currency(maxPrecision: 6))->toBe($expected);
})->with([
    'unit price 0.065' => [0.065, '0.065'],
    'half-up kept 10.185' => [10.185, '10.185'],
    'integer pads to 2dp' => [10, '10.00'],
    'one decimal pads to 2dp' => [10.5, '10.50'],
    'small' => [0.00125, '0.00125'],
    'thousands separator' => [1234.5678, '1,234.5678'],
    'half-up at the 6th place' => [0.1234567, '0.123457'],
    'half-up 5 at the 7th place' => [1.0000005, '1.000001'],
    'zero' => [0, '0.00'],
    'numeric string' => ['0.065', '0.065'],
]);

it('rounds half-up at maxPrecision by default, half-even when asked', function () {
    expect(num(0.125)->currency(maxPrecision: 2))->toBe('0.13');
    expect(num(10.185)->currency(maxPrecision: 2))->toBe('10.19');
    expect(num(0.1234565)->currency(maxPrecision: 6))->toBe('0.123457');
    expect(num(0.1234565)->currency(maxPrecision: 6, roundingMode: 'half-even'))->toBe('0.123456');
    expect(num(10.185)->currency(maxPrecision: 2, roundingMode: 'half-even'))->toBe('10.18');
    expect(num(0.065)->currency(maxPrecision: 6, roundingMode: 'half-even'))->toBe('0.065');
});

it('never goes below 2 decimals when maxPrecision is lower', function () {
    expect(num(10.5)->currency(maxPrecision: 0))->toBe('10.50');
    expect(num(0.065)->currency(maxPrecision: 1))->toBe('0.07');
});

it('applies maxPrecision to negatives', function () {
    expect(num(-0.065)->currency(maxPrecision: 6))->toBe('-0.065');
    expect(num(-10)->currency(maxPrecision: 6))->toBe('-10.00');
    expect(num(-0.0000005)->currency(maxPrecision: 6))->toBe('-0.000001');
});

it('applies maxPrecision with a symbol and brackets', function () {
    expect(num(0.065)->currency('MYR', maxPrecision: 6))->toBe('MYR 0.065');
    expect(num(-0.065)->currency('MYR', false, true, false, 6))->toBe('(MYR 0.065)');
    expect(num(-0.065)->currency(null, bracket: true, maxPrecision: 6))->toBe('(0.065)');
});

it('lets abbreviate win over maxPrecision', function () {
    expect(num(1500000)->currency('USD', abbreviate: true, maxPrecision: 6))->toBe('USD 2M');
});

it('applies the 0.05 rounding first, then maxPrecision', function () {
    expect(num(10.12)->currency('MYR', true, maxPrecision: 6))->toBe('MYR 10.10');
    expect(num(10.13)->currency(null, true, maxPrecision: 6))->toBe('10.15');
});

it('returns a non-numeric value untouched with maxPrecision', function () {
    expect(num('abc')->currency('USD', maxPrecision: 6))->toBe('abc');
});

it('makes the invoice line from humblebear 401 add up', function () {
    $unit = num(0.065)->currency(maxPrecision: 6);
    $line = num(51 * 0.065)->currency();

    expect("51 x {$unit} = {$line}")->toBe('51 x 0.065 = 3.32');
});

it('takes the rounding mode in any case', function () {
    expect(num(0.125)->currency(roundingMode: 'Half-Even'))->toBe('0.12');
    expect(num(0.125)->currency(roundingMode: 'HALF-UP'))->toBe('0.13');
    expect(num(0.125)->currency(roundingMode: 'Half-Down'))->toBe('0.12');
});

it('does not pre-round the float, so a product that lands just under a tie rounds down (known limit)', function () {
    // 175 * 9.825 is 1719.3749999999998 in floating point, so the tie never reaches the formatter.
    // Callers must pass an exact value (e.g. a rounded or decimal-computed total) when a sum lands on a tie.
    expect(175 * 9.825)->toBe(1719.3749999999998);
    expect(num(175 * 9.825)->currency())->toBe('1,719.37');
    expect(num(round(175 * 9.825, 3))->currency())->toBe('1,719.38');
});

it('keeps the sign of a negative zero', function () {
    expect(num(-0.0)->currency())->toBe('-0.00');
    expect(num(0.0)->currency())->toBe('0.00');
});

it('applies the 0.05 rounding first, whatever the rounding mode', function () {
    expect(num(10.12)->currency(null, true, roundingMode: 'half-even'))->toBe('10.10');
    expect(num(10.125)->currency(null, true, roundingMode: 'half-even'))->toBe('10.15');
    expect(num(10.125)->currency(null, true, roundingMode: 'half-down'))->toBe('10.15');
});
