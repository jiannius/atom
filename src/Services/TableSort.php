<?php

namespace Jiannius\Atom\Services;

/**
 * Signs and verifies the "raw:" values a table column's declared sort can put
 * into "_table.sort.column" (see components/table/column.blade.php), so
 * toTable() can tell a host's own raw SQL expression apart from one a client
 * tampered with over the wire (issue #54).
 */
class TableSort
{
    /**
     * Sign a raw sort expression for the client-facing "_table.sort.column"
     * value. Keeps the "raw:" prefix toTable() dispatches on, followed by a
     * fixed 64-char sha256 hmac and a colon, then the expression itself — the
     * fixed length lets the token be parsed back out even if the expression
     * carries colons of its own.
     */
    public static function sign(string $expression) : string
    {
        return 'raw:'.static::mac($expression).':'.$expression;
    }

    /**
     * Whether a "_table.sort.column" value must be refused when it comes from
     * the client: any "raw:" value with no signature, or one whose signature
     * does not match. A plain column name is never refused here — orderBy()
     * already quotes it safely, which is why only the "raw:" branch needs
     * this gate.
     */
    public static function isUnsafeFromClient(string $column) : bool
    {
        return str_starts_with($column, 'raw:') && !static::isSignedAndValid($column);
    }

    /**
     * The trusted SQL expression behind a "raw:" column value, for toTable()
     * to hand to orderByRaw(). Returns null when a signed value's signature
     * does not match. An unsigned "raw:<expr>" value is trusted as-is here —
     * it can only have been set from PHP, since isUnsafeFromClient() above
     * refuses it from the browser.
     */
    public static function expression(string $column) : ?string
    {
        $parsed = static::parse($column);

        if (!$parsed) {
            return substr($column, 4);
        }

        return hash_equals(static::mac($parsed[1]), $parsed[0]) ? $parsed[1] : null;
    }

    /**
     * Whether a "raw:" column value carries a signature and it verifies.
     */
    protected static function isSignedAndValid(string $column) : bool
    {
        $parsed = static::parse($column);

        return $parsed !== null && hash_equals(static::mac($parsed[1]), $parsed[0]);
    }

    /**
     * Split a "raw:<64-hex-hmac>:<expression>" value into [hmac, expression],
     * or null when it isn't shaped like a signed token.
     */
    protected static function parse(string $column) : ?array
    {
        return preg_match('/^raw:([0-9a-f]{64}):(.*)$/s', $column, $matches)
            ? [$matches[1], $matches[2]]
            : null;
    }

    /**
     * HMAC a raw sort expression against the app key.
     */
    protected static function mac(string $expression) : string
    {
        return hash_hmac('sha256', $expression, (string) config('app.key'));
    }
}
