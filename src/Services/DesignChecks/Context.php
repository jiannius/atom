<?php

namespace Jiannius\Atom\Services\DesignChecks;

/**
 * What a scan knows beyond one file, for a check that has to wait for the rest (`Rule::finish()`).
 * A rule's `find()` leaves facts here while it reads each file; its `finish()` reads them once all
 * are read. Key a fact with the rule's id.
 */
class Context
{
    /**
     * @var array<string, array<int, mixed>>
     */
    protected array $facts = [];

    public function __construct(public readonly string $root = '') {}

    /**
     * Remember a fact under a key
     */
    public function add(string $key, mixed $value): void
    {
        $this->facts[$key][] = $value;
    }

    /**
     * The facts left under a key, in the order files were read
     *
     * @return array<int, mixed>
     */
    public function get(string $key): array
    {
        return $this->facts[$key] ?? [];
    }
}
