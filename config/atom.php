<?php

/*
 * atom's own defaults, merged under the host's config/atom.php (the host's keys win).
 *
 * `timezone` is the viewer's zone for a signed-in user with none of their own (Carbon::getLocalTimezone()). It is read here, at config load,
 * because `env()` is null at runtime once `php artisan config:cache` has run; `config:cache` keeps this value.
 */
return [
    'timezone' => env('TIMEZONE'),
];
