<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\BladeOutline;
use Jiannius\Atom\Services\LineIndex;

/**
 * One file a check reads: its path from the root, its kind and its texts, shared by every check.
 * `text()` is the source with comments blanked (and, in Blade, `@verbatim` blocks too) at the same
 * length, so an offset and its line are the file's. A Blade file also has an outline, built on the
 * first use.
 */
class File
{
    protected ?BladeOutline $outline = null;

    /**
     * @var array<int, int>|null
     */
    protected ?array $lineStarts = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $lines = null;

    public function __construct(
        public readonly string $path,
        public readonly string $kind,
        public readonly string $source,
        protected string $text,
        protected string $markup,
    ) {}

    /**
     * The source with comments blanked
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Blade with every PHP region blanked too (where its markup and JavaScript are); the text itself for PHP
     */
    public function markup(): string
    {
        return $this->markup;
    }

    public function isBlade(): bool
    {
        return $this->kind === 'blade';
    }

    /**
     * The outline of a Blade file; null for any other kind. It exists for an unbalanced view too (its
     * attributes read as in a balanced one), but a structural check does not run on that: see `structural()`.
     */
    public function outline(): ?BladeOutline
    {
        if (! $this->isBlade()) {
            return null;
        }

        return $this->outline ??= new BladeOutline($this->text);
    }

    /**
     * Whether nesting can be trusted: false only for a Blade file whose atom tags or slots do not balance (a
     * PHP file has no tags to nest)
     */
    public function structural(): bool
    {
        return ! $this->isBlade() || $this->outline()->balanced();
    }

    /**
     * The 1-based line an offset is on
     */
    public function line(int $offset): int
    {
        return LineIndex::lineAt($this->lineStarts(), $offset);
    }

    /**
     * A line of the source as written (comments included), trimmed and cut at 160 characters
     */
    public function lineText(int $line): string
    {
        $this->lines ??= explode("\n", str_replace("\r\n", "\n", $this->source));

        return mb_substr(trim($this->lines[$line - 1] ?? ''), 0, 160);
    }

    /**
     * @return array<int, int>
     */
    protected function lineStarts(): array
    {
        $this->lineStarts ??= LineIndex::starts($this->text);

        return $this->lineStarts;
    }
}
