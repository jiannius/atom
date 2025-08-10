<?php

namespace Jiannius\Atom\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

trait Enum
{
    /**
     * Get enum from name or value
     */
    public static function get($name)
    {
        if (!is_string($name)) return $name;

        if ($value = static::tryFrom($name)) return $value;

        $name = str($name)->upper()->replace('-', '_')->replace(' ', '_')->toString();

        return static::all()->first(fn ($case) => $case->is($name));
    }

    /**
     * Get all enum cases
     */
    public static function all($filtered = true) : Collection
    {
        $cases = collect(static::cases());

        return $filtered
            ? $cases->filter(fn($case) => $case->isNot('TRASHED'))->values()
            : $cases;
    }

    /**
     * Get color for enum value
     */
    public function color() : string
    {
        return match($this->value) {
            'active',
            'published',
            'approved',
            'completed',
            'success',
            'onboarded',
            'paid',
            'verified' => 'green',

            'new',
            'pending',
            'processing' => 'yellow',

            'partially-paid' => 'blue',

            'cancelled',
            'rejected',
            'failed',
            'error',
            'blocked',
            'due' => 'red',

            default => 'gray',
        };
    }

    /**
     * Get enum value as option
     */
    public function option() : array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
    
    /**
     * Convert enum to array with value, label, and color
     */
    public function toArray() : array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->color(),
        ];
    }

    /**
     * Get formatted label from enum value
     */
    public function label() : string
    {
        return str()->headline($this->value);
    }

    /**
     * Check if enum matches given value(s)
     */
    public function is() : bool
    {
        $val = func_num_args() > 1 ? func_get_args() : (array) func_get_arg(0);

        return in_array($this->value, (array) $val) || in_array($this->name, (array) $val);
    }

    /**
     * Check if enum does not match given value(s)
     */
    public function isNot(...$val) : bool
    {
        return !$this->is(...$val);
    }

    /**
     * Get enum value as Stringable
     */
    public function str($type = 'name') : Stringable
    {
        return new Stringable($this->{$type});
    }

    /**
     * Convert enum name to snake case
     */
    public function snake($type = 'name') : string
    {
        return (string) str($this->{$type})->lower()->snake();
    }

    /**
     * Convert enum name to slug
     */
    public function slug($type = 'name') : string
    {
        return (string) str($this->{$type})->lower()->slug();
    }
}