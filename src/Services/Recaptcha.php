<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Recaptcha
{
    /**
     * Verify a reCAPTCHA v3 token.
     *
     * Fails CLOSED (throws ValidationException) when the token resolves to a
     * bot: a missing, empty or non-string token (a client can post any value,
     * or none, so a secret being configured means a token is required), an
     * explicit token failure, a score below the threshold, an action that is
     * not the one asked for, or a token Google minted for another hostname.
     *
     * Fails OPEN (allows the submit) when verification cannot complete:
     * recaptcha not configured (no secret), a misconfigured secret, or Google
     * being unreachable — so an outage never locks users out.
     */
    public function verify(mixed $token, ?string $action = null, ?float $minScore = null) : void
    {
        $secret = config('services.recaptcha.secret_key');

        if (!$secret) {
            return;
        }

        if (!is_string($token) || trim($token) === '') {
            $this->reject();
        }

        $endpoint = config('services.recaptcha.api_endpoint') ?: 'https://www.google.com/recaptcha/api/siteverify';
        $minScore ??= (float) (config('services.recaptcha.min_score') ?? 0.5);

        try {
            $result = Http::asForm()->post($endpoint, [
                'secret' => $secret,
                'response' => $token,
            ])->throw()->json();
        }
        catch (\Throwable $e) {
            Log::warning('[recaptcha] verification request failed, allowing submit', ['error' => $e->getMessage()]);
            return;
        }

        if (data_get($result, 'success') === false) {
            $errors = (array) data_get($result, 'error-codes', []);
            $configErrors = ['missing-input-secret', 'invalid-input-secret', 'bad-request'];

            // A secret/request problem is our fault, not the visitor's — fail open.
            if (array_intersect($errors, $configErrors)) {
                Log::warning('[recaptcha] misconfigured, allowing submit', ['error-codes' => $errors]);
                return;
            }

            // Invalid / expired / duplicate token — treat as a bot.
            $this->reject();
        }

        $score = data_get($result, 'score');

        if ($score !== null && $score < $minScore) {
            $this->reject();
        }

        // A token minted on another site is not this form's, whatever its score. Google
        // returns the hostname the widget ran on; skipped when it does not send one.
        $hostname = data_get($result, 'hostname');

        if (is_string($hostname) && $hostname !== '' && strcasecmp($hostname, request()->getHost()) !== 0) {
            $this->reject();
        }

        // When an action is given, bind the token to it (defends against token reuse).
        if ($action && ($returned = data_get($result, 'action')) && $returned !== $action) {
            $this->reject();
        }
    }

    /**
     * Throw the recaptcha validation failure.
     */
    protected function reject() : void
    {
        throw ValidationException::withMessages([
            '_recaptcha' => t('atom::messages.recaptcha-failed'),
        ]);
    }
}
