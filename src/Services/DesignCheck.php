<?php

namespace Jiannius\Atom\Services;

use InvalidArgumentException;
use Jiannius\Atom\Services\DesignChecks\Context;
use Jiannius\Atom\Services\DesignChecks\File;
use Jiannius\Atom\Services\DesignChecks\Rule;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * Reads a host's Blade views and PHP and lists where a screen drifts from atom's design rules,
 * with `file:line`, a severity, the rule and the atom component to use. Read only: it never
 * writes and never compiles a view.
 *
 * The checks are classes in `DesignChecks/`, found by a glob (`rules()`), each reading a file
 * through `DesignChecks\File` and `BladeOutline`. A check is a candidate from a text scan, not
 * a proof: when unsure, it declines. A deliberate exception is a marker in the host's own file:
 *
 *   {{-- atom:design-check ignore <ids>: <reason> --}}   the line it trails, else the next line with code
 *   {{-- atom:design-check off <ids|all>: <reason> --}} ... {{-- atom:design-check on --}}
 *   // atom:design-check ignore <ids>: <reason>          the same in a PHP file (`//`, `#` or a block comment)
 *
 * An unknown id, a marker with no reason and an `ignore` that suppressed nothing are reported.
 */
class DesignCheck extends HostScanner
{
    /**
     * Something works wrong, not just looks off
     */
    public const BREAK = 'break';

    /**
     * Drift from a rule that a person decides
     */
    public const REVIEW = 'review';

    /**
     * A nit or a style mark
     */
    public const INFO = 'info';

    /**
     * In the order the report prints them
     *
     * @var array<int, string>
     */
    public const SEVERITIES = [self::BREAK, self::REVIEW, self::INFO];

    /**
     * A directory of this name under a views folder holds a mail, a PDF, a print or an error page:
     * not a screen, and not read
     *
     * @var array<int, string>
     */
    public const NON_SCREEN_SEGMENTS = ['mail', 'mails', 'email', 'emails', 'pdf', 'pdfs', 'print', 'errors'];

    /**
     * What is read, under the root (the app root: the directory that holds `app/` and `resources/`)
     *
     * @var array<int, string>
     */
    public const READ_DIRECTORIES = ['app', 'resources', 'modules', 'Modules'];

    /**
     * Top-level directories that hold no screens, listed under NOT READ when the host has them
     *
     * @var array<int, string>
     */
    public const NOT_SCREENS = ['tests', 'config', 'routes', 'database'];

    /**
     * What no scan can judge, left to a person
     *
     * @var array<int, string>
     */
    public const NOT_CHECKED = [
        'Whether the primary is the next step for the status, and whether Edit is right as the primary.',
        'Whether an "archive" really deletes: the consequence decides the tone, a verb list only suggests it.',
        'The same action in the same place on every record page of the app.',
        'Page or modal for a form, by module; toast, callout or alert, by meaning.',
        'Status tones per state: they come from the enum at runtime.',
        'One callout under the header, in the status\'s tone.',
        'A button that starts with a verb (nouns are fine in navigation and tabs).',
        'Creating several kinds of record as one primary with a menu of kinds.',
        'That nothing important appears only on hover.',
        'Labels and classes built in PHP at runtime, `<x-dynamic-component>` and a variable `@include`.',
        'A slot split across partials: each file is read on its own.',
        'Hand totals, hand stat tiles, hand selection tiles, "Successfully" in a toast, a bare `grid-cols-2` in a form and footer button order.',
    ];

    /**
     * The checks, once found
     *
     * @var array<string, Rule>|null
     */
    protected ?array $found = null;

    /**
     * @param  array<int, Rule>|null  $rules  Run these instead of the ones found in DesignChecks/ (a test's own)
     */
    public function __construct(protected ?array $rules = null) {}

    /**
     * Screens live in views and components, so these are read; `tests/`, `config/`, `routes/` and `database/` are not
     */
    protected function directories(): array
    {
        return static::READ_DIRECTORIES;
    }

    /**
     * Every check, by id, in report order (the group, then the id). Found by a glob of `DesignChecks/`:
     * every class there that extends Rule and is not abstract.
     *
     * @return array<string, Rule>
     */
    public function rules(): array
    {
        if ($this->found !== null) {
            return $this->found;
        }

        $rules = $this->rules;

        if ($rules === null) {
            $rules = [];

            foreach (glob(__DIR__.'/DesignChecks/*.php') ?: [] as $path) {
                $class = __NAMESPACE__.'\\DesignChecks\\'.basename($path, '.php');

                if (! class_exists($class) || ! is_subclass_of($class, Rule::class) || (new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $rules[] = new $class;
            }
        }

        $byId = [];

        foreach ($rules as $rule) {
            $id = $rule::ID;

            if ($id === '' || isset($byId[$id])) {
                throw new LogicException(get_class($rule).($id === '' ? ' has no ID' : ' repeats the id `'.$id.'`'));
            }

            $byId[$id] = $rule;
        }

        $order = fn (Rule $rule): int => ($position = array_search($rule::GROUP, Rule::GROUPS, true)) === false ? count(Rule::GROUPS) : $position;

        uasort($byId, fn (Rule $a, Rule $b) => [$order($a), $a::ID] <=> [$order($b), $b::ID]);

        return $this->found = $byId;
    }

    /**
     * @return array<int, string>
     */
    public function ruleIds(): array
    {
        return array_keys($this->rules());
    }

    /**
     * Scan a host. `$only` runs just those checks (an unknown id throws).
     *
     * @param  array<int, string>|null  $only
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function scan(string $root, ?array $only = null): array
    {
        $root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
        $this->root = $root;
        $this->overlay = [];
        $this->skipped = [];

        $all = $this->rules();

        if ($only !== null) {
            $unknown = array_values(array_diff($only, array_keys($all)));

            if ($unknown) {
                throw new InvalidArgumentException('Unknown check: '.implode(', ', $unknown));
            }
        }

        $active = $only === null ? $all : array_intersect_key($all, array_flip($only));
        $context = new Context($root);
        $findings = array_fill_keys(array_keys($active), []);
        $scanned = ['blade' => 0, 'php' => 0];
        $textOnly = [];
        $errors = [];
        $markers = [];
        $problems = [];
        $ran = [];
        $reported = [];

        foreach ($this->discover($root) as [$path, $relative, $scope, $kind]) {
            if (! in_array($kind, ['blade', 'php'], true) || $scope !== 'app') {
                continue;
            }

            if ($reason = $this->notRead($relative)) {
                if (! isset($reported[$reason[0]])) {
                    $reported[$reason[0]] = true;
                    $this->skipped[] = ['path' => $reason[0], 'reason' => $reason[1]];
                }

                continue;
            }

            $source = $this->read($path);

            if ($source === false || $source === '') {
                continue;
            }

            if ($kind === 'blade' && preg_match('/<x-mail::|@component\(\s*[\'"]mail::/', $source)) {
                $this->skipped[] = ['path' => $relative, 'reason' => 'a mail view (x-mail components): not a screen'];

                continue;
            }

            $scanned[$kind]++;
            $prepared = $this->prepare($kind, $source);
            $text = $prepared['full'];
            $markup = $prepared['markup'];

            if ($kind === 'blade') {
                $text = $this->blank($text, '/(?<!@)@verbatim.*?@endverbatim/s');
                $markup = $this->blank($markup, '/(?<!@)@verbatim.*?@endverbatim/s');
            }

            $file = new File($relative, $kind, $source, $text, $markup);

            if ($kind === 'blade' && ! $file->structural()) {
                $textOnly[] = $relative;
            }

            foreach ($active as $id => $rule) {
                if (! in_array($kind, $rule->kinds(), true) || ($rule->structural() && ! $file->structural())) {
                    continue;
                }

                $ran[$relative][$id] = true;

                try {
                    foreach ($rule->find($file, $context) as $hit) {
                        $offset = $hit[0] ?? null;

                        if (! is_int($offset) || $offset < 0 || $offset > strlen($text)) {
                            continue;
                        }

                        $line = $file->line($offset);
                        $finding = [
                            'file' => $relative,
                            'line' => $line,
                            'text' => $file->lineText($line),
                            'note' => $hit[1] ?? null,
                        ];

                        if (($hit[2] ?? null) !== null) {
                            $finding['data'] = $hit[2];
                        }

                        $findings[$id][] = $finding;
                    }
                } catch (Throwable $e) {
                    $errors[] = ['check' => $id, 'file' => $relative, 'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 160)];
                }
            }

            if (stripos($source, 'atom:design-check') !== false) {
                [$read, $bad] = $this->markers($relative, $kind, $source, $text, array_keys($all));

                if ($read) {
                    $markers[$relative] = $read;
                }

                array_push($problems, ...$bad);
            }
        }

        foreach ($active as $id => $rule) {
            try {
                $findings[$id] = array_values($rule->finish($findings[$id], $context));
            } catch (Throwable $e) {
                $errors[] = ['check' => $id, 'file' => '(finish)', 'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 160)];
            }
        }

        [$findings, $suppressed, $unused] = $this->suppress($findings, $markers, $ran);

        array_push($problems, ...$unused);
        usort($problems, fn ($a, $b) => [$a['file'], $a['line'], $a['problem']] <=> [$b['file'], $b['line'], $b['problem']]);

        foreach (static::NOT_SCREENS as $directory) {
            if (is_dir($root.DIRECTORY_SEPARATOR.$directory) && ! is_link($root.DIRECTORY_SEPARATOR.$directory)) {
                $this->skipped[] = ['path' => $directory, 'reason' => 'not screens: the design rules read views and components'];
            }
        }

        $checks = [];
        $count = array_fill_keys(static::SEVERITIES, 0);
        $suppressedBy = array_count_values(array_column($suppressed, 'id'));

        foreach ($active as $id => $rule) {
            $list = $findings[$id];
            usort($list, fn ($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

            $checks[$id] = [
                'id' => $id,
                'title' => $rule->title(),
                'group' => $rule::GROUP,
                'severity' => $rule::SEVERITY,
                'fix' => $rule->fix(),
                'rule' => $rule->rule(),
                'detect' => $rule->detect(),
                'count' => count($list),
                'suppressed' => $suppressedBy[$id] ?? 0,
                'findings' => array_map(function ($finding) {
                    unset($finding['data']);

                    return $finding;
                }, $list),
            ];
            $count[$rule::SEVERITY] = ($count[$rule::SEVERITY] ?? 0) + count($list);
        }

        return [
            'root' => $root,
            'atom_version' => $this->installedVersion(),
            'only' => $only,
            'scanned' => $scanned,
            'checks' => $checks,
            'suppressed' => $suppressed,
            'markers' => $problems,
            'skipped' => $this->relevantSkips(),
            'text_only' => $textOnly,
            'errors' => $errors,
            'warnings' => $scanned['blade'] + $scanned['php'] === 0
                ? ['No Blade or PHP file was read: --path must be the app root, the directory that holds app/ and resources/ (or modules/).']
                : [],
            'not_checked' => static::NOT_CHECKED,
            'summary' => [
                'checks' => count($checks),
                'findings' => array_sum($count),
                'checks_with_findings' => count(array_filter($checks, fn ($check) => $check['count'] > 0)),
                'by_severity' => $count,
                'suppressed' => count($suppressed),
                'marker_problems' => count($problems),
            ],
        ];
    }

    /**
     * Why a file is not read, as [the path to list, the reason], or null when it is: atom's published
     * views and the views of a mail, a PDF, a print page or an error page are not the host's screens
     *
     * @return array{0: string, 1: string}|null
     */
    protected function notRead(string $relative): ?array
    {
        if (preg_match('#^((?:modules|Modules)/[^/]+/tests)/#', $relative, $m)) {
            return [$m[1], 'not screens: the design rules read views and components'];
        }

        if (preg_match('#^(.*?(?:^|/)resources/views/vendor)/#', $relative, $m)) {
            return [$m[1], 'published views (atom\'s own markup, not the host\'s): not read'];
        }

        if (preg_match('#^(.*?(?:^|/)resources/views)/(.+)/[^/]+$#', $relative, $m)) {
            $segments = explode('/', $m[2]);

            foreach ($segments as $i => $segment) {
                if (in_array(strtolower($segment), static::NON_SCREEN_SEGMENTS, true)) {
                    return [$m[1].'/'.implode('/', array_slice($segments, 0, $i + 1)), 'a mail, PDF, print or error view: not a screen'];
                }
            }
        }

        return null;
    }

    /**
     * What the walk skipped that matters to a design check: third-party scripts and oversize
     * scripts or styles are not its business
     *
     * @return array<int, array{path: string, reason: string}>
     */
    protected function relevantSkips(): array
    {
        return array_values(array_filter($this->skipped, function (array $skip) {
            if (str_starts_with($skip['reason'], 'third-party scripts or styles')) {
                return false;
            }

            if ((str_starts_with($skip['reason'], 'over ') || $skip['reason'] === 'looks minified') && ! in_array($this->kindOf($skip['path']), ['blade', 'php'], true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * The markers in a file, read from its raw source (comments are blanked before a check runs)
     * and the problems with them. A marker is a Blade comment (`{{-- --}}`) in a Blade file, a PHP
     * comment in a PHP file; text in an HTML comment is not one.
     *
     * @param  array<int, string>  $known
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    protected function markers(string $relative, string $kind, string $source, string $text, array $known): array
    {
        $starts = $this->lineStarts($source);
        $codeLines = explode("\n", $text);
        $comments = [];

        if ($kind === 'blade') {
            // a Blade comment is a marker; one inside an HTML comment or a @verbatim block is text, not a marker
            $readable = preg_replace_callback('/\{\{--.*?--\}\}|<!--.*?-->|(?<!@)@verbatim.*?@endverbatim/s', fn (array $m) => str_starts_with($m[0], '{{--') ? $m[0] : preg_replace('/[^\n]/', ' ', $m[0]), $source) ?? $source;

            preg_match_all('/\{\{--(.*?)--\}\}/s', $readable, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($found as $match) {
                $comments[] = [$match[1][0], $match[0][1], strlen($match[0][0])];
            }
        } else {
            $offset = 0;

            try {
                foreach (token_get_all($source) as $token) {
                    $piece = is_array($token) ? $token[1] : $token;

                    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        $body = preg_replace('/^(?:\/\/|#|\/\*+)|\*+\/$/', '', rtrim($piece));
                        $comments[] = [preg_replace('/^\s*\*+/m', '', $body) ?? $body, $offset, strlen(rtrim($piece))];
                    }

                    $offset += strlen($piece);
                }
            } catch (Throwable) {
                // a file PHP cannot tokenise has no markers to read
            }
        }

        $markers = [];
        $problems = [];

        foreach ($comments as [$body, $start, $length]) {
            if (! preg_match('/^\s*atom:design-check\b\s*(.*)$/s', $body, $m)) {
                continue;
            }

            $line = $this->lineAt($starts, $start);
            $endLine = $this->lineAt($starts, max($start, $start + $length - 1));
            $problem = function (string $what, ?string $id = null) use (&$problems, $relative, $line): void {
                $problems[] = ['file' => $relative, 'line' => $line, 'id' => $id, 'problem' => $what];
            };

            if (! preg_match('/^(ignore|off|on)\b\s*(.*)$/s', trim($m[1]), $parts)) {
                $problem('not a marker: say `ignore <ids>: <reason>`, `off <ids|all>: <reason>` or `on`');

                continue;
            }

            [, $type, $rest] = $parts;
            $reason = '';
            $idText = $rest;

            if ($type !== 'on' && ($colon = strpos($rest, ':')) !== false) {
                $idText = substr($rest, 0, $colon);
                $reason = trim(preg_replace('/\s+/', ' ', substr($rest, $colon + 1)) ?? '');
            } elseif ($type === 'on') {
                $idText = trim(explode(':', $rest, 2)[0]);
            }

            $ids = array_values(array_unique(array_filter(preg_split('/[\s,]+/', strtolower(trim($idText))) ?: [])));

            if ($type !== 'on' && ! $ids) {
                $problem('no check id: say `'.$type.' <ids>: <reason>`');

                continue;
            }

            if ($type === 'ignore' && in_array('all', $ids, true)) {
                $problem('`all` is for `off` only: name the checks to ignore');

                continue;
            }

            foreach ($ids as $id) {
                if ($id !== 'all' && ! in_array($id, $known, true)) {
                    $problem('unknown check id', $id);
                }
            }

            if ($type !== 'on' && $reason === '') {
                $problem('no reason given');
            }

            $trailing = trim(substr($source, $starts[$line - 1], $start - $starts[$line - 1])) !== '';
            $target = null;

            if ($type === 'ignore') {
                if ($trailing) {
                    $target = $line;
                } else {
                    for ($next = $endLine + 1; $next <= count($codeLines); $next++) {
                        if (trim($codeLines[$next - 1]) !== '') {
                            $target = $next;

                            break;
                        }
                    }
                }
            }

            $valid = array_values(array_filter($ids, fn ($id) => $id === 'all' || in_array($id, $known, true)));
            $markers[] = ['type' => $type, 'ids' => $valid, 'all_ids' => $ids, 'reason' => $reason, 'line' => $line, 'end_line' => $endLine, 'target' => $target];
        }

        return [$markers, $problems];
    }

    /**
     * Take the findings a marker covers out of the checks. Returns the findings left, what was
     * suppressed, and the markers that suppressed nothing: only for a check that ran on that file (a
     * check not in this run, or one that does not read this kind of file, cannot be judged).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $findings
     * @param  array<string, array<int, array<string, mixed>>>  $markers  by file
     * @param  array<string, array<string, true>>  $ran  the checks that ran on each file
     * @return array{0: array<string, array<int, array<string, mixed>>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    protected function suppress(array $findings, array $markers, array $ran): array
    {
        $suppressed = [];
        $used = [];
        $regions = [];
        $ignores = [];

        foreach ($markers as $file => $list) {
            $open = [];

            foreach ($list as $index => $marker) {
                if ($marker['type'] === 'ignore' && $marker['target'] !== null) {
                    $ignores[$file][$marker['target']][] = $index;
                } elseif ($marker['type'] === 'off') {
                    // each id of an `off` runs until an `on` names it (or a bare `on`, or `on all`, closes them all)
                    $regions[$file][$index] = ['from' => $marker['line'], 'to' => array_fill_keys($marker['ids'], PHP_INT_MAX)];

                    foreach ($marker['ids'] as $id) {
                        $open[$index][$id] = true;
                    }
                } elseif ($marker['type'] === 'on') {
                    $closed = false;
                    $everything = ! $marker['all_ids'] || in_array('all', $marker['all_ids'], true);

                    foreach ($open as $index2 => $ids) {
                        foreach (array_keys($ids) as $id) {
                            if ($everything || in_array($id, $marker['all_ids'], true)) {
                                $regions[$file][$index2]['to'][$id] = $marker['line'];
                                unset($open[$index2][$id]);
                                $closed = true;
                            }
                        }
                    }

                    $used[$file][$index]['on'] = $closed;
                }
            }
        }

        foreach ($findings as $id => $list) {
            $kept = [];

            foreach ($list as $finding) {
                $file = $finding['file'];
                $reason = false;

                foreach ($ignores[$file][$finding['line']] ?? [] as $index) {
                    if (in_array($id, $markers[$file][$index]['ids'], true)) {
                        $used[$file][$index][$id] = true;
                        $reason = $reason === false ? $markers[$file][$index]['reason'] : $reason;
                    }
                }

                foreach ($regions[$file] ?? [] as $index => $region) {
                    $key = isset($region['to'][$id]) ? $id : (isset($region['to']['all']) ? 'all' : null);

                    if ($key !== null && $finding['line'] >= $region['from'] && $finding['line'] <= $region['to'][$key]) {
                        $used[$file][$index][$key] = true;
                        $reason = $reason === false ? $markers[$file][$index]['reason'] : $reason;
                    }
                }

                if ($reason === false) {
                    $kept[] = $finding;
                } else {
                    $suppressed[] = ['id' => $id, 'file' => $file, 'line' => $finding['line'], 'text' => $finding['text'], 'reason' => $reason];
                }
            }

            $findings[$id] = $kept;
        }

        $unused = [];

        foreach ($markers as $file => $list) {
            foreach ($list as $index => $marker) {
                if ($marker['type'] === 'on') {
                    if (! ($used[$file][$index]['on'] ?? false)) {
                        $unused[] = ['file' => $file, 'line' => $marker['line'], 'id' => null, 'problem' => '`on` with no matching `off` before it'];
                    }

                    continue;
                }

                foreach ($marker['ids'] as $id) {
                    if ($id === 'all' || ! isset($ran[$file][$id]) || isset($used[$file][$index][$id])) {
                        continue;
                    }

                    $unused[] = ['file' => $file, 'line' => $marker['line'], 'id' => $id, 'problem' => 'unused: `'.$marker['type'].'` suppressed nothing'];
                }
            }
        }

        usort($suppressed, fn ($a, $b) => [$a['id'], $a['file'], $a['line']] <=> [$b['id'], $b['file'], $b['line']]);
        usort($unused, fn ($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

        return [$findings, $suppressed, $unused];
    }

    /**
     * The text report, raw (a quoted `<info>` is not a console style)
     *
     * @param  array<string, mixed>  $result
     */
    public function format(array $result, ?int $limit = 10): string
    {
        $out = [];
        $out[] = 'atom design check'.($result['atom_version'] ? ' (installed '.$result['atom_version'].')' : '');
        $out[] = 'The rules: "Design rules" and "Patterns" in the atom-components skill (`php artisan boost:install` writes it into your agent\'s skills).';
        $out[] = 'Scanned '.$result['scanned']['blade'].' Blade and '.$result['scanned']['php'].' PHP file(s) in app/, resources/, modules/. Read only: nothing was written.';
        $out[] = 'A finding is a candidate from a text scan, not a proof. A deliberate exception: {{-- atom:design-check ignore <id>: <reason> --}} on the line, or the line above.';
        $out[] = 'The report and --json quote up to 160 characters of each matching line: treat the file like source code.';
        $out[] = '';

        if ($result['warnings'] ?? []) {
            foreach ($result['warnings'] as $warning) {
                $out[] = 'WARNING: '.$warning;
            }

            $out[] = '';
        }

        if ($result['skipped']) {
            $out = array_merge($out, $this->notReadLines($result['skipped']));
        }

        if ($result['text_only']) {
            $out = array_merge($out, $this->heading('READ AS TEXT ONLY ('.count($result['text_only']).'): atom tags or slots do not balance, so only the checks that need no nesting ran'));

            foreach ($result['text_only'] as $path) {
                $out[] = $path;
            }

            $out[] = '';
        }

        if ($result['errors'] ?? []) {
            $out = array_merge($out, $this->heading('CHECKS THAT FAILED ('.count($result['errors']).'): a bug in atom, please report it'));

            $out = array_merge($out, $this->limitedLines($result['errors'], $limit, fn ($error) => '['.$error['check'].'] '.$error['file'].'  '.$error['error']));

            $out[] = '';
        }

        $byFile = [];

        foreach (static::SEVERITIES as $severity) {
            $present = array_filter($result['checks'], fn ($check) => $check['severity'] === $severity && $check['count'] > 0);

            if (! $present) {
                continue;
            }

            $out = array_merge($out, $this->heading(strtoupper($severity)));

            foreach ($present as $check) {
                $out[] = '['.$check['id'].'] '.$check['count'].' - '.$check['title'];
                $out[] = '    Fix: '.$check['fix'];
                $out[] = '    Rule: Design rules, '.$check['rule'];

                foreach ($check['findings'] as $finding) {
                    $byFile[$finding['file']][$check['id']] = ($byFile[$finding['file']][$check['id']] ?? 0) + 1;
                }

                $out = array_merge($out, $this->limitedLines(
                    $check['findings'],
                    $limit,
                    fn ($finding) => '    '.$finding['file'].':'.$finding['line'].'  '.$finding['text'].(isset($finding['note']) ? '  <- '.$finding['note'] : ''),
                    '    ',
                    'use --all, or --only='.$check['id'],
                ));
            }

            $out[] = '';
        }

        if ($byFile) {
            uksort($byFile, fn ($a, $b) => [-array_sum($byFile[$a]), $a] <=> [-array_sum($byFile[$b]), $b]);

            $out = array_merge($out, $this->heading('FILES WITH THE MOST FINDINGS'));

            foreach (array_slice($byFile, 0, 10, true) as $file => $counts) {
                $out[] = $file.'  '.array_sum($counts).' ('.implode(', ', array_map(fn ($id, $n) => $id.' '.$n, array_keys($counts), $counts)).')';
            }

            $out[] = '';
        }

        if ($result['suppressed'] || $result['markers']) {
            $out = array_merge($out, $this->heading('SUPPRESSED ('.count($result['suppressed']).')'));

            $out = array_merge($out, $this->limitedLines($result['suppressed'], $limit, fn ($item) => '['.$item['id'].'] '.$item['file'].':'.$item['line'].'  '.($item['reason'] !== '' ? $item['reason'] : '(no reason given)')));

            if ($result['markers']) {
                $out[] = 'Marker problems ('.count($result['markers']).'):';

                foreach ($result['markers'] as $marker) {
                    $out[] = '    '.$marker['file'].':'.$marker['line'].'  '.(isset($marker['id']) ? '['.$marker['id'].'] ' : '').$marker['problem'];
                }
            }

            $out[] = '';
        }

        $out = array_merge($out, $this->heading('NOT CHECKED (left to a person)'));

        foreach ($result['not_checked'] as $line) {
            $out[] = '- '.$line;
        }

        $summary = $result['summary'];
        $out[] = '';
        $out = array_merge($out, $this->heading('SUMMARY'));
        $out[] = $summary['findings'].' finding(s) in '.$summary['checks_with_findings'].' of '.$summary['checks'].' checks: '
            .implode(', ', array_map(fn ($severity) => $summary['by_severity'][$severity].' '.$severity, static::SEVERITIES))
            .'; '.$summary['suppressed'].' suppressed.';

        return implode("\n", $out)."\n";
    }
}
