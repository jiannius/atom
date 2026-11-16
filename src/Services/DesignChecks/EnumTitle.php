<?php

namespace Jiannius\Atom\Services\DesignChecks;

use Jiannius\Atom\Services\DesignCheck;

class EnumTitle extends CopyFormsRule
{
    public const ID = 'enum-title';

    public const SEVERITY = DesignCheck::INFO;

    public const GROUP = 'Copy and state';

    /**
     * An enum read for its word: `->status->name`, `->stage->value`, `$status->value`. A `type`, `kind` or `state` is
     * only read by `->value`: `->type->name` is as often a lookup model's name (a category, a place) as a case.
     */
    protected const ENUM_WORD = '/(?:->|\?->)\s*(?:status|stage)\s*(?:\(\s*\))?\s*(?:->|\?->)\s*(?:name|value)\b|(?:->|\?->)\s*(?:type|kind|state)\s*(?:\(\s*\))?\s*(?:->|\?->)\s*value\b|\$(?:status|stage)\s*(?:->|\?->)\s*(?:name|value)\b/';

    /**
     * A function that turns a word into a title: it opens a call (`Str::title(`, `ucfirst(`)
     */
    protected const FUNCTION_CALL = '/(?<!\w|\$|:|->)(?:Str::(?:title|headline|ucfirst|ucwords)|ucfirst|ucwords)\s*\(/';

    /**
     * A call whose arguments are a log line or a cache key, not a screen: `Log::info(`, `logger(`, `cache(`, `Cache::remember(`
     */
    protected const NOT_SCREEN_CALL = '/(?<![\w$>])(?:Log::\w+|logger|cache|Cache::\w+)\s*\(/';

    /**
     * `str(` or `Str::of(`, whose chain may end in `->title()`
     */
    protected const STRING_CALL = '/(?<!\w|\$|:|->)(?:str|Str::of)\s*\(/';

    /**
     * What a chain after `str(…)` has to start with to be a title: `->title(`, `->headline(`, `->ucfirst(`, `->ucwords(`
     */
    protected const TITLE_METHOD = '/(?:->|\?->)\s*(?:title|headline|ucfirst|ucwords)\s*\(/';

    public function title(): string
    {
        return 'An enum\'s name or value title-cased by hand';
    }

    public function fix(): string
    {
        return 'The enum\'s `label()`, or `<atom:status :value="$order->status" />`, which reads it (and `tone()` or `color()`).';
    }

    public function rule(): string
    {
        return 'so never write `str($case->name)->title()`';
    }

    public function detect(): string
    {
        return 'In Blade and PHP: `str(…)`, `Str::of(…)` (with `->title()`, `->headline()`, `->ucfirst()` or `->ucwords()` after it, possibly after other calls), `Str::title(`, `Str::headline(`, `ucfirst(` or `ucwords(` around a chain that ends `->status->name`, `->stage->value` (or `->type->value`, `->kind->value`, `->state->value`, or a `$status->name`). One level (`str($user->name)->title()`) is a person\'s name, `->type->name` is as often a lookup model, and neither is read; nor is a call inside `Log::`, `logger(` or `cache(`, or a PHP file under a mail, PDF, print, export or AI (`Ai`, `Mcp`) directory.';
    }

    public function structural(): bool
    {
        return false;
    }

    public function kinds(): array
    {
        return ['blade', 'php'];
    }

    public function find(File $file, Context $context): iterable
    {
        if ($this->printsNoScreen($file)) {
            return;
        }

        $text = $file->text();
        $outline = $file->outline();
        $found = [];

        preg_match_all(static::FUNCTION_CALL, $text, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$call, $offset]) {
            $arguments = $this->arguments($text, $offset + strlen($call) - 1);

            if ($arguments !== null && preg_match(static::ENUM_WORD, $arguments[0], $word)) {
                $found[$offset] = trim($call, ' (').'(…'.trim($word[0]).')';
            }
        }

        preg_match_all(static::STRING_CALL, $text, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$call, $offset]) {
            $arguments = $this->arguments($text, $offset + strlen($call) - 1);

            if ($arguments === null || ! preg_match(static::ENUM_WORD, $arguments[0], $word) || ! $this->titled($text, $arguments[1])) {
                continue;
            }

            $found[$offset] = 'str(…'.trim($word[0]).')->title()';
        }

        ksort($found);

        foreach ($found as $offset => $note) {
            if (($outline === null || ! $this->inCode($outline, $offset)) && ! $this->inLogOrCache($text, $offset)) {
                yield [$offset, $note.': use the enum\'s `label()` or `<atom:status>`'];
            }
        }
    }

    /**
     * Whether an offset is inside the arguments of a log or cache call of the same statement: a `;`, a `{` or a `}` (a block
     * or an echo boundary) ends the statement
     */
    protected function inLogOrCache(string $text, int $offset): bool
    {
        $before = substr($text, max(0, $offset - 400), min(400, $offset));
        $cut = max((int) strrpos($before, ';'), (int) strrpos($before, '{'), (int) strrpos($before, '}'));

        return preg_match(static::NOT_SCREEN_CALL, substr($before, $cut)) === 1;
    }

    /**
     * Whether the calls that follow a `str(…)` are, through to the end of the statement, a title
     */
    protected function titled(string $text, int $from): bool
    {
        $tail = substr($text, $from, 300);

        if (preg_match('/\{\{|\{!!|!!\}|\}\}|;/', $tail, $stop, PREG_OFFSET_CAPTURE)) {
            $tail = substr($tail, 0, $stop[0][1]);
        }

        return preg_match('/^\s*(?:->|\?->)/', $tail) === 1 && preg_match(static::TITLE_METHOD, $tail) === 1;
    }
}
