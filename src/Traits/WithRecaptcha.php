<?php

namespace Jiannius\Atom\Traits;

/**
 * Receives the reCAPTCHA token that <atom:form recaptcha> attaches and verifies it.
 */
trait WithRecaptcha
{
    public $_recaptcha = null;

    /**
     * Verify the reCAPTCHA token carried by <atom:form recaptcha>.
     * Throws a validation error when the token resolves to a bot; fails open
     * (does nothing) when recaptcha is not configured or cannot be reached.
     */
    public function verifyRecaptcha(?string $action = null, ?float $minScore = null) : void
    {
        app('atom')->recaptcha()->verify($this->_recaptcha, $action, $minScore);
    }
}
