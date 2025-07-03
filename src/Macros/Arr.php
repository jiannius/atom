<?php

namespace Jiannius\Atom\Macros;

class Arr
{
    public function pick()
    {
        return function ($array) {
            return collect($array)->filter()->keys()->first();
        };
    }
}