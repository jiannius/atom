<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\Enum;

/**
 * An invoice state on the trait's own colour() and label(): paid is green, due is red, new is yellow, anything else gray.
 */
enum StatusTraitState: string
{
    use Enum;

    case PAID = 'paid';
    case DUE = 'due';
    case NEW = 'new';
    case DRAFT = 'draft';
}

/**
 * A state that picks its own tone and label, as a host does to fix the trait's mapping (pending reads yellow there).
 */
enum StatusToneState: string
{
    use Enum;

    case PROCESSING = 'processing';
    case ODD = 'paid';

    public function tone(): string
    {
        return match ($this) {
            self::PROCESSING => 'info',
            self::ODD => 'not-a-tone',
        };
    }

    public function label(): string
    {
        return 'In progress <b>';
    }
}

/**
 * A plain backed enum with neither colour() nor label().
 */
enum StatusBareState: string
{
    case OPEN = 'open';
}

/**
 * A state whose colour() is black or a hex: status draws no hex and no inverse pill, so both read neutral.
 */
enum StatusOddColorState: string
{
    case BLACK = 'black';
    case HEX = 'hex';

    public function color(): string
    {
        return match ($this) {
            self::BLACK => 'black',
            self::HEX => '#ff0000',
        };
    }
}
