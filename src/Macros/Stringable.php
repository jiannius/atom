<?php

namespace Jiannius\Atom\Macros;

// register the same macro in \Jiannius\Atom\Macros\Str here
// so all the methods can be use in the str() helper
class Stringable
{
    public function namespace()
    {
        return function () {
            return new \Illuminate\Support\Stringable (
                \Illuminate\Support\Str::namespace($this->value)
            );
        };
    }

    public function dotpath()
    {
        return function () {
            return new \Illuminate\Support\Stringable (
                \Illuminate\Support\Str::dotpath($this->value)
            );
        };
    }

    public function interval()
    {
        return function (string $delimiter = '') {
            return new \Illuminate\Support\Stringable (
                \Illuminate\Support\Str::interval($this->value)
            );
        };
    }
}