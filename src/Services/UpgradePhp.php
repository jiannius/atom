<?php

namespace Jiannius\Atom\Services;

use Throwable;

/**
 * What the codemod needs to know about a PHP file (or the PHP of a single-file
 * Blade component), read from tokens: which classes it declares and where their
 * bodies are, the trait `use` statements of each class body, the methods
 * declared directly in it, and the file's own imports.
 *
 * It answers "I cannot tell" (`ok` false) for what it does not model, and the
 * codemod then declines the file: an import it cannot parse, a braced namespace.
 */
class UpgradePhp
{
    /**
     * @return array{ok: bool, reason: string|null, braced: bool, namespace: string, imports: array<string, string>, statements: array<int, array{start: int, end: int, simple: bool, fqns: array<int, string>}>, classes: array<int, array<string, mixed>>}
     */
    public static function structure(string $source): array
    {
        $result = ['ok' => true, 'reason' => null, 'braced' => false, 'namespace' => '', 'imports' => [], 'statements' => [], 'classes' => []];

        try {
            $tokens = token_get_all($source);
        } catch (Throwable) {
            return [...$result, 'ok' => false, 'reason' => 'it could not be tokenised'];
        }

        $count = count($tokens);
        $offsets = [];
        $at = 0;

        foreach ($tokens as $i => $token) {
            $offsets[$i] = $at;
            $at += strlen(is_array($token) ? $token[1] : $token);
        }

        $significant = fn (int $from, int $step = 1) => static::next($tokens, $from, $step);
        $stack = [];
        $paren = 0;
        $pending = null;
        $namespacePending = false;
        $namespaces = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if ($id === T_NAMESPACE) {
                $next = $significant($i + 1);

                if ($next !== null && ($tokens[$next] === '{' || (is_array($tokens[$next]) && in_array($tokens[$next][0], [T_STRING, T_NAME_QUALIFIED], true)))) {
                    $namespacePending = true;

                    if (is_array($tokens[$next])) {
                        $namespaces++;
                        $result['namespace'] = $tokens[$next][1];

                        if ($namespaces > 1) {
                            $result['ok'] = false;
                            $result['reason'] ??= 'several namespace statements';
                        }
                    }
                }

                continue;
            }

            if ($id === T_CLASS || $id === T_TRAIT || $id === T_INTERFACE || $id === T_ENUM) {
                $previous = $significant($i - 1, -1);

                if ($previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOUBLE_COLON) {
                    continue;
                }

                // what comes before the keyword: modifiers and attribute groups, then `new` for an anonymous class
                $abstract = false;
                $anonymous = false;
                $back = $previous;

                while ($back !== null) {
                    $before = $tokens[$back];

                    if (is_array($before) && in_array($before[0], [T_FINAL, T_READONLY, T_ABSTRACT], true)) {
                        $abstract = $abstract || $before[0] === T_ABSTRACT;
                    } elseif ($before === ']') {
                        $depth = 0;

                        for (; $back >= 0; $back--) {
                            $depth += $tokens[$back] === ']' ? 1 : (is_array($tokens[$back]) && $tokens[$back][0] === T_ATTRIBUTE ? -1 : ($tokens[$back] === '[' ? -1 : 0));

                            if ($depth <= 0) {
                                break;
                            }
                        }
                    } elseif (is_array($before) && $before[0] === T_NEW) {
                        $anonymous = true;

                        break;
                    } else {
                        break;
                    }

                    $back = $significant($back - 1, -1);
                }

                $nameAt = $significant($i + 1);
                $name = ! $anonymous && $nameAt !== null && is_array($tokens[$nameAt]) && $tokens[$nameAt][0] === T_STRING ? $tokens[$nameAt][1] : null;

                if (! $anonymous && $name === null) {
                    continue;
                }

                $result['classes'][] = [
                    'name' => $name,
                    'kind' => $anonymous ? 'anonymous' : match ($id) {
                        T_TRAIT => 'trait',
                        T_INTERFACE => 'interface',
                        T_ENUM => 'enum',
                        default => 'class',
                    },
                    'abstract' => $abstract,
                    'extends' => null,
                    'open' => null,
                    'close' => null,
                    'uses' => [],
                    'methods' => [],
                ];
                $pending = ['index' => count($result['classes']) - 1, 'paren' => $paren];

                continue;
            }

            if ($id === T_EXTENDS && $pending !== null && $result['classes'][$pending['index']]['extends'] === null) {
                $nameAt = $significant($i + 1);

                if ($nameAt !== null && is_array($tokens[$nameAt])) {
                    $result['classes'][$pending['index']]['extends'] = ltrim($tokens[$nameAt][1], '\\');
                }

                continue;
            }

            if ($token === '(') {
                $paren++;

                continue;
            }

            if ($token === ')') {
                $paren--;

                continue;
            }

            if ($token === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                if ($token === '{' && $pending !== null && $paren === $pending['paren']) {
                    $stack[] = ['type' => 'class', 'class' => $pending['index']];
                    $result['classes'][$pending['index']]['open'] = $offsets[$i];
                    $pending = null;
                } elseif ($token === '{' && $namespacePending) {
                    $stack[] = ['type' => 'namespace', 'class' => null];
                    $result['braced'] = true;
                    $namespacePending = false;
                } else {
                    $stack[] = ['type' => 'block', 'class' => null];
                }

                continue;
            }

            if ($token === '}') {
                $top = array_pop($stack);

                if ($top !== null && $top['type'] === 'class') {
                    $result['classes'][$top['class']]['close'] = $offsets[$i];
                }

                continue;
            }

            if ($token === ';') {
                $namespacePending = false;

                continue;
            }

            $top = $stack ? end($stack) : null;

            if ($id === T_USE) {
                $next = $significant($i + 1);

                // a closure's `use (`
                if ($next !== null && $tokens[$next] === '(') {
                    continue;
                }

                if ($top !== null && $top['type'] === 'class' && $paren === 0) {
                    [$statement, $last] = static::traitUse($tokens, $offsets, $i);
                    $result['classes'][$top['class']]['uses'][] = $statement;
                    $i = $last;

                    continue;
                }

                if ($top === null || $top['type'] === 'namespace') {
                    $i = static::importStatement($tokens, $offsets, $i, $result);
                }

                continue;
            }

            if ($id === T_FUNCTION && $top !== null && $top['type'] === 'class' && $paren === 0) {
                $nameAt = $significant($i + 1);

                if ($nameAt !== null && $tokens[$nameAt] === '&') {
                    $nameAt = $significant($nameAt + 1);
                }

                if ($nameAt !== null && is_array($tokens[$nameAt])) {
                    $body = null;
                    $depth = 0;

                    for ($j = $nameAt + 1; $j < $count; $j++) {
                        if ($tokens[$j] === '(') {
                            $depth++;
                        } elseif ($tokens[$j] === ')') {
                            $depth--;
                        } elseif ($depth === 0 && $tokens[$j] === '{') {
                            $body = $offsets[$j];

                            break;
                        } elseif ($depth === 0 && $tokens[$j] === ';') {
                            break;
                        }
                    }

                    $result['classes'][$top['class']]['methods'][strtolower($tokens[$nameAt][1])] = ['offset' => $offsets[$i], 'body' => $body];
                }
            }
        }

        return $result;
    }

    /**
     * The first segment of every name written in code (not in a comment or a string), lowercased, outside the
     * given ranges (the import statements)
     *
     * @param  array<int, array{start: int, end: int}>  $skip
     * @return array<string, true>
     */
    public static function codeNames(string $source, array $skip = []): array
    {
        $names = [];
        $at = 0;

        try {
            $tokens = token_get_all($source);
        } catch (Throwable) {
            return [];
        }

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $inside = false;

            foreach ($skip as $range) {
                if ($at >= $range['start'] && $at < $range['end']) {
                    $inside = true;
                }
            }

            if (! $inside && is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                $names[strtolower(explode('\\', ltrim($text, '\\'))[0])] = true;
            }

            $at += strlen($text);
        }

        return $names;
    }

    /**
     * The index of the next token that is not whitespace or a comment (null when there is none)
     *
     * @param  array<int, mixed>  $tokens
     */
    protected static function next(array $tokens, int $from, int $step): ?int
    {
        for ($i = $from; $i >= 0 && $i < count($tokens); $i += $step) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * A trait `use` statement in a class body: its elements, whether it carries
     * `{ ... }` conflict rules, and whether a comment sits in it
     *
     * @param  array<int, mixed>  $tokens
     * @param  array<int, int>  $offsets
     * @return array{0: array<string, mixed>, 1: int}
     */
    protected static function traitUse(array $tokens, array $offsets, int $i): array
    {
        $count = count($tokens);
        $elements = [''];
        $comments = false;
        $adapted = false;
        $j = $i + 1;

        for (; $j < $count; $j++) {
            $token = $tokens[$j];

            if ($token === ';') {
                break;
            }

            if ($token === '{') {
                $adapted = true;
                $depth = 0;

                for (; $j < $count; $j++) {
                    $depth += $tokens[$j] === '{' ? 1 : ($tokens[$j] === '}' ? -1 : 0);

                    if ($depth === 0) {
                        break;
                    }
                }

                break;
            }

            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $comments = true;
            } elseif (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            } elseif ($token === ',') {
                $elements[] = '';
            } else {
                $elements[count($elements) - 1] .= is_array($token) ? $token[1] : $token;
            }
        }

        $end = $offsets[min($j, $count - 1)] + strlen(is_array($tokens[min($j, $count - 1)]) ? $tokens[min($j, $count - 1)][1] : $tokens[min($j, $count - 1)]);

        return [[
            'offset' => $offsets[$i],
            'end' => $end,
            'elements' => $elements,
            'adapted' => $adapted,
            'comments' => $comments,
        ], $j];
    }

    /**
     * A top-level `use` import: recorded in the result, or the result is marked not ok
     *
     * @param  array<int, mixed>  $tokens
     * @param  array<int, int>  $offsets
     * @param  array<string, mixed>  $result
     */
    protected static function importStatement(array $tokens, array $offsets, int $i, array &$result): int
    {
        $count = count($tokens);
        $text = '';
        $j = $i + 1;

        for (; $j < $count && $tokens[$j] !== ';'; $j++) {
            $token = $tokens[$j];

            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text .= is_array($token) ? ($token[0] === T_WHITESPACE ? ' ' : $token[1]) : $token;
        }

        $last = $tokens[min($j, $count - 1)];
        $end = $offsets[min($j, $count - 1)] + strlen(is_array($last) ? $last[1] : $last);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $fail = function () use (&$result, $text) {
            $result['reason'] ??= 'an import it cannot read (`use '.mb_substr($text, 0, 60).'`)';
            $result['ok'] = false;
        };

        if (preg_match('/^(function|const)\s/', $text)) {
            return $j;
        }

        $fqns = [];
        $simple = false;

        if (preg_match('/^\\\\?([\w\\\\]+)\\\\\s*\{(.*)\}$/', $text, $group)) {
            foreach (explode(',', $group[2]) as $item) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                if (! preg_match('/^(?:(function|const)\s+)?([\w\\\\]+)(?:\s+as\s+(\w+))?$/i', $item, $m)) {
                    $fail();

                    return $j;
                }

                // a function or a constant in a mixed group is not a class name
                if ($m[1] !== '') {
                    continue;
                }

                $fqns[] = [$group[1].'\\'.$m[2], $m[3] ?? null];
            }
        } else {
            $items = explode(',', $text);
            $simple = count($items) === 1;

            foreach ($items as $item) {
                if (! preg_match('/^\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?$/i', trim($item), $m)) {
                    $fail();

                    return $j;
                }

                $fqns[] = [$m[1], $m[2] ?? null];
            }
        }

        if (! $fqns) {
            if (empty($group)) {
                $fail();
            }

            return $j;
        }

        foreach ($fqns as [$fqn, $alias]) {
            $short = $alias ?? substr(strrchr('\\'.$fqn, '\\'), 1);
            $result['imports'][strtolower($short)] = $fqn;
        }

        $result['statements'][] = ['start' => $offsets[$i], 'end' => $end, 'simple' => $simple, 'fqns' => array_column($fqns, 0)];

        return $j;
    }

    /**
     * Resolve a name as written in the file to a class name: through its imports, else into the file's namespace
     * (no leading backslash)
     *
     * @param  array<string, string>  $imports
     */
    public static function resolve(string $name, array $imports, string $namespace = ''): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        // `namespace\Foo` is Foo in the file's own namespace
        if (stripos($name, 'namespace\\') === 0) {
            return ($namespace !== '' ? $namespace.'\\' : '').substr($name, 10);
        }

        $head = explode('\\', $name)[0];

        if (isset($imports[strtolower($head)])) {
            return $imports[strtolower($head)].substr($name, strlen($head));
        }

        return ($namespace !== '' ? $namespace.'\\' : '').$name;
    }
}
