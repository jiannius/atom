<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Decides whether a log line is the first of its kind, so a failure a visitor can repeat on every request is logged once and not on every one.
 *
 * The caller builds the id, the message and a context free of the exception message and of any value the visitor chose; this only answers "log it now?".
 * The seen-set stays in the caller's own static (so it can be emptied for a test), and is capped at 100 ids and emptied when full.
 */
class LogOnce
{
    /**
     * Whether the caller should log now. With a `$cacheKey`, once per `$ttl` seconds across processes through the cache; if the cache throws, or cannot
     * remember (the null store: add() is false and has() is false too), the per-process seen-set decides. Without one, only the seen-set does.
     *
     * @param  array<string, true>  $seen
     */
    public static function first(string $id, array &$seen, ?string $cacheKey = null, int $ttl = 3600): bool
    {
        if ($cacheKey !== null) {
            try {
                $first = Cache::add($cacheKey, 1, $ttl);
                $remembers = $first || Cache::has($cacheKey);
            }
            catch (Throwable) {
                $first = $remembers = false;
            }

            if ($remembers) {
                return $first;
            }
        }

        if (isset($seen[$id])) {
            return false;
        }

        if (count($seen) >= 100) {
            $seen = [];
        }

        $seen[$id] = true;

        return true;
    }
}
