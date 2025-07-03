<?php

namespace Jiannius\Atom\Macros;

use Illuminate\Support\Arr;

class ComponentAttributeBag
{
    public function hasLike()
    {
        return function (...$value) {
            $keys = collect($this->getAttributes())->keys();

            return !empty(
                $keys->first(fn($key) => str($key)->is($value))
            );
        };
    }

    public function modifier()
    {
        return function($name = null) {
            $attribute = collect($this->whereStartsWith('wire:model')->getAttributes())->keys()->first()
                ?? collect($this->whereStartsWith('x-model')->getAttributes())->keys()->first();

            $modifier = (string) str($attribute)->replace('x-model', '')->replace('wire:model', '');

            return $name ? str($modifier)->is('*'.$name.'*') : $modifier;
        };
    }

    public function size()
    {
        return function($default = null) {
            return $this->get('size') ?? Arr::pick([
                '2xs' => $this->has('2xs'),
                'xs' => $this->has('xs'),
                'sm' => $this->has('sm'),
                'md' => $this->has('md'),
                'lg' => $this->has('lg'),
                'xl' => $this->has('xl'),
                '2xl' => $this->has('2xl'),
                '3xl' => $this->has('3xl'),
                '4xl' => $this->has('4xl'),
            ]) ?? $default;
        };
    }

    public function field()
    {
        return function() {
            return $this->get('field') ?? $this->get('for') ?? $this->wire('model')->value();
        };
    }

    // TODO: deprecate this
    public function submitAction()
    {
        return function() {
            if ($this->hasLike('wire:submit*', 'x-on:submit*', 'x-recaptcha:submit*')) return true;
            if (is_string($this->get('submit'))) return $this->get('submit');
            if (is_string($this->get('form'))) return $this->get('form');
            if ($this->has('submit') || $this->has('form')) return 'submit';

            return false;
        };
    }

    public function submit()
    {
        return function() {
            $attrs = collect(
                $this->filter(fn ($value, $key) => 
                    str($key)->is('wire:submit*')
                    || str($key)->is('x-on:submit*')
                    || str($key)->is('x-recaptcha:submit*')
                )->getAttributes()
            );

            $attr = $attrs->keys()->first();
            $value = $attrs->values()->first();

            return $attr ? (object) compact('attr', 'value') : false;
        };
    }

    public function getAny()
    {
        return function(...$args) {
            return collect($args)->map(fn($arg) => $this->get($arg))->filter()->first();
        };
    }

    public function getLike()
    {
        return function ($value) {
            $keys = collect($this->getAttributes())->keys();
            $key = $keys->first(fn($key) => str($key)->is($value));
            return $this->get($key);
        };
    }

    public function classes()
    {
        return function () {
            return new class
            {
                public $pending = [];

                public function add($classes)
                {
                    $this->pending[] = $classes;
                    return $this;
                }

                public function __toString()
                {
                    return collect($this->pending)->filter()->join(' ');
                }
            };
        };
    }

    public function styles()
    {
        return function () {
            return new class
            {
                public $pending = [];

                public function add($prop, $value)
                {
                    $this->pending[$prop] = $value;
                    return $this;
                }

                public function __toString()
                {
                    return collect($this->pending)->map(fn($value, $prop) => "$prop: $value")->filter()->join('; ');
                }
            };
        };
    }
}