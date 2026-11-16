<?php

namespace App;

/**
 * A PHP file read for its markers: the seed checks read Blade only, so a PHP marker for them is not judged
 */
class Tags
{
    // atom:design-check ignore success-button: this check does not read PHP, so the marker cannot be judged unused
    public function one(): string
    {
        return 'one';
    }

    public function two(): string
    {
        return 'two'; # atom:design-check ignore no-such-check: a trailing marker in PHP
    }

    /* atom:design-check ignore bogus-id, success-button: a block comment in PHP */
    public function three(): string
    {
        return 'three';
    }
}
