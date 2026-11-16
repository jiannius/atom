<?php

namespace Jiannius\Atom\Services\DesignChecks;

/**
 * One design check: a class in this folder, found by `DesignCheck::rules()`. It reads
 * a file through `File` (its comment-free text and, for Blade, its outline) and yields
 * where a screen drifts from the design rules.
 *
 * Rules for writing one:
 *  - Read structure through the outline; never a regex across tag boundaries.
 *  - When unsure, decline: an unknown label (`label()` is null), an unbalanced view or a
 *    class that is an expression all mean no finding.
 *  - At most one finding per element per check.
 *  - The note and `fix()` name the atom component to use.
 *  - No palette utility or `text-muted` token written out in `src/` (PaletteTest reads it
 *    as markup): build such a pattern from parts.
 *  - Never call `Blade::render()` or `compileString()`: a scan writes nothing.
 *  - `ID` is a name hosts write into their views (`atom:design-check ignore <id>`): never
 *    rename one once a beta ships it.
 */
abstract class Rule
{
    /**
     * Report order of the groups (the folder's PR groups)
     *
     * @var array<int, string>
     */
    public const GROUPS = ['Page and header', 'Actions', 'Tone and confirm', 'Lists', 'Forms and modals', 'Boxes and colour', 'Copy and state'];

    /**
     * A kebab-case id, unique, stable
     */
    public const ID = '';

    /**
     * `break` (something works wrong, not just looks off), `review` (drift a person decides) or `info` (a nit)
     */
    public const SEVERITY = '';

    /**
     * One of GROUPS
     */
    public const GROUP = '';

    /**
     * The rule, in one line
     */
    abstract public function title(): string;

    /**
     * What to use instead, naming the atom component (`<atom:...>`) or prop
     */
    abstract public function fix(): string;

    /**
     * A phrase that appears in the skill's Design rules or Patterns (or the guideline): a test holds it there
     */
    abstract public function rule(): string;

    /**
     * How it is found, and what it cannot see
     */
    abstract public function detect(): string;

    /**
     * The file kinds it reads: `blade`, `php`
     *
     * @return array<int, string>
     */
    public function kinds(): array
    {
        return ['blade'];
    }

    /**
     * Whether it reads nesting (a tag's parent, slot or branch). A structural rule is skipped on a
     * view whose atom tags do not balance; a text rule runs on it.
     */
    public function structural(): bool
    {
        return true;
    }

    /**
     * Where the file drifts: an offset into `$file->text()` and an optional note (the finding's line is
     * the offset's, so give the tag's start). A third element is kept for `finish()` only.
     *
     * @return iterable<int, array{0: int, 1?: ?string, 2?: mixed}>
     */
    abstract public function find(File $file, Context $context): iterable;

    /**
     * Drop or annotate this check's findings once every file is read (a fact from another file). Each
     * finding is `['file', 'line', 'text', 'note'?, 'data'?]`.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    public function finish(array $findings, Context $context): array
    {
        return $findings;
    }
}
