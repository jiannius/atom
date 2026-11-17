<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Str;

/**
 * A structural reader for one Blade view: which tag closes which, which `x-slot` a
 * tag sits in and who owns that slot, which `@if` branch it is under, and what text
 * a button or an item shows.
 *
 * It reads TEXT, never compiles: it must not call Blade, so a scan writes nothing.
 * It takes the comment-free text (comments and `@verbatim` blanked, offsets and
 * newlines kept) and every offset it hands back is a byte offset into that text, so
 * a line number is the same as in the file.
 *
 * It never throws on a malformed view, and its cost is bounded by the size of the
 * text, however it is malformed: a closer that is not there is remembered, a tag or
 * an argument list that never ends is read as text once too many of them have been
 * tried (`budget`), a view with more than MAX_NODES tags, tags nested more than
 * MAX_DEPTH deep or more than MAX_BLOCKS directive blocks open is read only as far as
 * that (the rest is text, and `balanced()` is false or `branchesDeclined()` true).
 * An unclosed tag, a stray closer, a tag that runs to the end of the file or a
 * directive with no closer degrade into a node with no `close`, an ignored closer or a
 * text run, and `balanced()` says whether an atom tag or an `x-slot` was left open,
 * closed twice or cut off. A check that needs structure declines on an unbalanced view.
 *
 * This is the reader of attributes the design checks use; `HostScanner::parseTags()`
 * and `UpgradeCheck` have their own and are not to be mixed with it. Where the two
 * differ this one is the more exact: a `>` inside an `@class([... => ...])` argument,
 * an unquoted `{{ $v }}` value, spans that are absolute offsets (theirs start at the
 * end of the tag name), and a tag written inside a string, a script or an echo, which
 * is not a tag.
 *
 * Nodes are arrays (this folder has no room for a node class), keyed by an id that is
 * the tag's position in document order. Every query takes a node or an id.
 *
 * A node:
 *   id, tag (`atom:page.header`, `x-slot`, `x-foo`, `livewire:x`, `div`, lowercased;
 *   `x-atom::x` reads as `atom:x`), kind (`atom`, `slot`, `component`, `html`),
 *   component (the part after `atom:`, or null), slot (a slot's name, or null when
 *   it is bound; as Blade reads it, `<x-slot:foo-bar>` is `fooBar` while
 *   `<x-slot name="foo-bar">` stays `foo-bar`: two names), offset, openEnd (after the `>`), close (the closer's offset, or
 *   null), end (after the closer; where it was cut off when there is none),
 *   selfClosing, attributes, spans (name => absolute [start, end]), directives
 *   (`@class([...])` and the like inside the tag), parent (id or null), depth.
 *
 * @phpstan-type Node array{id: int, tag: string, kind: string, component: ?string, slot: ?string, offset: int, openEnd: int, close: ?int, end: int, selfClosing: bool, attributes: array<string, string|true>, spans: array<string, array{0: int, 1: int}>, directives: array<int, array{name: string, args: ?string}>, parent: ?int, depth: int}
 */
class BladeOutline
{
    /**
     * HTML elements that never have a closer
     *
     * @var array<int, string>
     */
    public const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /**
     * Elements whose closer HTML lets a page leave out: an opener of the same name
     * straight after closes the one on top
     *
     * @var array<int, string>
     */
    public const IMPLIED_CLOSE = ['li', 'p', 'td', 'th', 'tr', 'option', 'dt', 'dd'];

    /**
     * A tag that is not closed within this many bytes is read as text
     */
    protected const MAX_TAG_BYTES = 20000;

    /**
     * Tags read from one view; the rest is text and `balanced()` is false
     */
    protected const MAX_NODES = 50000;

    /**
     * Tags open at once; a deeper one is a leaf and `balanced()` is false
     */
    protected const MAX_DEPTH = 256;

    /**
     * Directive blocks open at once; a deeper one stops the branch paths
     */
    protected const MAX_BLOCKS = 256;

    /**
     * Cuts (tag markup) a label reads at most; past that it is null
     */
    protected const MAX_LABEL_CUTS = 10000;

    /**
     * HTML element names, to tell a bare name that is one from a bare atom component
     *
     * @var array<int, string>
     */
    protected const HTML_ELEMENTS = [
        'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio', 'b', 'base', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'cite', 'code', 'col', 'colgroup', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'menu', 'meta', 'meter', 'nav', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script', 'section', 'select', 'slot', 'small', 'source', 'span', 'strong', 'style', 'sub', 'summary', 'sup', 'svg', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'u', 'ul', 'var', 'video', 'wbr',
    ];

    /**
     * Components whose own text is their label: a `label` or `heading` attribute is only
     * read when there is none, and `title` is a tooltip there, never the label
     *
     * @var array<int, string>
     */
    protected const TEXT_LABELLED = ['action', 'button', 'link', 'menu.item', 'navlist.item', 'tabs.item'];

    /**
     * Directives that open a block with branches (`@else` and its kin switch branch)
     *
     * @var array<int, string>
     */
    protected const BRANCHING = ['if', 'unless', 'isset', 'empty', 'auth', 'guest', 'can', 'cannot', 'canany', 'env', 'production', 'error', 'session', 'hassection', 'sectionmissing'];

    /**
     * Directives that open a branching block closed by another directive's closer
     *
     * @var array<string, string>
     */
    protected const CLOSED_BY = ['hassection' => 'if', 'sectionmissing' => 'if'];

    /**
     * Directives that open a loop
     *
     * @var array<int, string>
     */
    protected const LOOPS = ['foreach', 'for', 'while'];

    /**
     * @var array<int, array>
     */
    protected array $nodes = [];

    /**
     * Ids of the tags still open
     *
     * @var array<int, int>
     */
    protected array $stack = [];

    protected bool $balanced = true;

    /**
     * How many open tags answer to a name: the tag, and `x-slot:<name>` for a named slot
     *
     * @var array<string, int>
     */
    protected array $open = [];

    /**
     * Open directive blocks: kind, chain, branch, loop (a forelse's loop marker), base
     * (the path node outside it) and tail (its own last path node)
     *
     * @var array<int, array{kind: string, chain: int, branch: int, loop: ?int, base: int, tail: int}>
     */
    protected array $blocks = [];

    /**
     * How many open blocks there are of each kind
     *
     * @var array<string, int>
     */
    protected array $blockKinds = [];

    /**
     * Offsets at which the branch path changed, and the path node from there on
     *
     * @var array<int, int>
     */
    protected array $pathAt = [0];

    /**
     * @var array<int, int>
     */
    protected array $pathTail = [0];

    /**
     * The branch paths as a tree of `[chain, branch]` pairs, one node for each pair
     * (node 0 is the empty path): a path is a node, and a directive costs one node
     * however deep it sits, where a copy of the whole path would be quadratic
     *
     * @var array<int, int>
     */
    protected array $pathParent = [-1];

    /**
     * @var array<int, int>
     */
    protected array $pathChain = [0];

    /**
     * @var array<int, int>
     */
    protected array $pathBranch = [0];

    protected bool $declined = false;

    /**
     * Where each closer was last looked for: needle => [offset from which none was found,
     * offset of the last search, the match it found]
     *
     * @var array<string, array{0: int, 1: int, 2: int|false}>
     */
    protected array $found = [];

    /**
     * Bytes of failed tag and argument scans still allowed (see `fail()`)
     */
    protected int $budget;

    /**
     * The child ids of each node, built on the first `children()`
     *
     * @var array<int, array<int, int>>|null
     */
    protected ?array $childIds = null;

    /**
     * Where each tag's markup starts and ends, for `label()`, built on the first one
     *
     * @var array<int, int>|null
     */
    protected ?array $cutFrom = null;

    /**
     * @var array<int, int>
     */
    protected array $cutTo = [];

    protected int $chains = 0;

    /**
     * @var array<int, true>
     */
    protected array $loopChains = [];

    /**
     * The offset each line starts at, built on the first `line()`
     *
     * @var array<int, int>|null
     */
    protected ?array $lineStarts = null;

    public function __construct(protected string $text)
    {
        $this->budget = max(200000, 4 * strlen($text));
        $this->scan();
    }

    /**
     * The text the outline reads
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Every tag, in document order
     *
     * @return array<int, array>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * One node by id
     */
    public function node(int $id): ?array
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * False when an atom tag or an `x-slot` was left open, was cut off by a closer of
     * something around it, had no `>`, or was closed with nothing open. HTML elements
     * and Blade directives do not count: HTML leaves closers out and a conditional
     * wrapper opens in one `@if` and closes in another. It is also false when the view
     * was only read part of the way: more than MAX_NODES tags, or tags nested more than
     * MAX_DEPTH deep.
     */
    public function balanced(): bool
    {
        return $this->balanced;
    }

    /**
     * True when more than MAX_BLOCKS directive blocks were open at once, and the branch
     * paths stopped there: `together()` is false for every pair, `inLoop()` false, and a
     * check that reads branches declines
     */
    public function branchesDeclined(): bool
    {
        return $this->declined;
    }

    /**
     * The 1-based line an offset is on
     */
    public function line(int $offset): int
    {
        if ($this->lineStarts === null) {
            $this->lineStarts = [0];
            $at = 0;

            while (($position = strpos($this->text, "\n", $at)) !== false) {
                $this->lineStarts[] = $position + 1;
                $at = $position + 1;
            }
        }

        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($this->lineStarts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low + 1;
    }

    /**
     * Atom tags, by component name (`page.header`, or `atom:page.header`); every atom
     * tag when none is given
     *
     * @param  string|array<int, string>|null  $names
     * @return array<int, array>
     */
    public function atom(string|array|null $names = null): array
    {
        $wanted = $names === null ? null : array_map(fn ($name) => $this->bareName($name), (array) $names);

        return array_values(array_filter($this->nodes, fn ($node) => $node['kind'] === 'atom' && ($wanted === null || in_array($node['component'], $wanted, true))));
    }

    /**
     * The tag that contains a node
     */
    public function parent(array|int $node): ?array
    {
        $parent = $this->resolve($node)['parent'] ?? null;

        return $parent === null ? null : $this->nodes[$parent];
    }

    /**
     * The tags around a node, the nearest first
     *
     * @return array<int, array>
     */
    public function ancestors(array|int $node): array
    {
        $ancestors = [];
        $parent = $this->resolve($node)['parent'] ?? null;

        while ($parent !== null) {
            $ancestors[] = $this->nodes[$parent];
            $parent = $this->nodes[$parent]['parent'];
        }

        return $ancestors;
    }

    /**
     * The tags directly inside a node. Text and Blade directives are skipped, so
     * `<atom:page>@if($x)<atom:tabs>` has `atom:tabs` as a child of `atom:page`.
     *
     * @return array<int, array>
     */
    public function children(array|int $node): array
    {
        $id = $this->resolve($node)['id'] ?? null;

        if ($id === null) {
            return [];
        }

        if ($this->childIds === null) {
            $this->childIds = [];

            foreach ($this->nodes as $each) {
                if ($each['parent'] !== null) {
                    $this->childIds[$each['parent']][] = $each['id'];
                }
            }
        }

        return array_map(fn ($child) => $this->nodes[$child], $this->childIds[$id] ?? []);
    }

    /**
     * The first tag directly inside a node
     */
    public function firstChild(array|int $node): ?array
    {
        return $this->children($node)[0] ?? null;
    }

    /**
     * Every tag inside a node, at any depth, in document order
     *
     * @return array<int, array>
     */
    public function descendants(array|int $node): array
    {
        $node = $this->resolve($node);

        if ($node === null) {
            return [];
        }

        $found = [];

        for ($i = $node['id'] + 1; $i < count($this->nodes) && $this->nodes[$i]['offset'] < $node['end']; $i++) {
            $found[] = $this->nodes[$i];
        }

        return $found;
    }

    /**
     * The nearest tag around a node that is one of these. A name is:
     *  - an atom component: `atom:page.header`, `x-atom::page.header`, or `page.header`
     *    (a bare name with a dot is one unless it starts with `x-`: no HTML element has a
     *    dot), or a bare `card` or `page` that is an atom component and no HTML element;
     *  - `x-slot` (any slot) or `x-slot:actions` (that slot);
     *  - any other tag as written: `div`, `x-foo`, `livewire:foo`;
     *  - `html:button` for an HTML element that an atom component shares a name with.
     * A bare name that is both an atom component and an HTML element (`button`, `table`,
     * `form`, `label`, `link`...) is ambiguous and throws, so that a check cannot ask for the
     * wrong one by accident: write `atom:button`, or `html:button`.
     *
     * @param  string|array<int, string>  $names
     *
     * @throws \InvalidArgumentException
     */
    public function inside(array|int $node, string|array $names): ?array
    {
        $wanted = array_map(fn ($name) => $this->wantedTag($name), (array) $names);

        foreach ($this->ancestors($node) as $ancestor) {
            if (in_array($ancestor['tag'], $wanted, true) || ($ancestor['kind'] === 'slot' && $ancestor['slot'] !== null && in_array('x-slot:'.$ancestor['slot'], $wanted, true))) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * The `x-slot` a node sits in, and who owns it: the nearest enclosing slot, its
     * name, the component tag around that slot, and whether the node is the slot's
     * own content (`direct`) or inside another component within it. So the shell's
     * `actions` slot belongs to the shell, never to a `page.header` inside it.
     *
     * @return array{slot: array, name: ?string, owner: ?array, direct: bool}|null
     */
    public function slotOf(array|int $node): ?array
    {
        $node = $this->resolve($node);
        $between = false;

        foreach ($this->ancestors($node) as $ancestor) {
            if ($ancestor['kind'] === 'slot') {
                return ['slot' => $ancestor, 'name' => $ancestor['slot'], 'owner' => $this->owner($ancestor), 'direct' => ! $between];
            }

            if ($ancestor['kind'] !== 'html') {
                $between = true;
            }
        }

        return null;
    }

    /**
     * The component tag (atom, `x-…` or `livewire:…`) around a slot
     */
    public function owner(array|int $slot): ?array
    {
        foreach ($this->ancestors($slot) as $ancestor) {
            if ($ancestor['kind'] === 'atom' || $ancestor['kind'] === 'component') {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * The tag an offset is inside (the innermost)
     */
    public function at(int $offset): ?array
    {
        $low = 0;
        $high = count($this->nodes) - 1;
        $found = null;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->nodes[$middle]['offset'] <= $offset) {
                $found = $middle;
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        while ($found !== null && $this->nodes[$found]['end'] <= $offset) {
            $found = $this->nodes[$found]['parent'];
        }

        return $found === null ? null : $this->nodes[$found];
    }

    /**
     * The directive branches a node is under, outermost first, as
     * `[chain, branch]` pairs: one chain for each `@if` / `@elseif` / `@else` block and
     * the like, the branch counting up through it. A `@switch` is a chain with a branch
     * for each `@case` / `@default`, and a `@forelse` has branch 0 for the body and 1
     * for its bare `@empty`. A loop (`@foreach`, `@for`, `@while`, a forelse body) adds
     * a pair that `inLoop()` recognises.
     *
     * Covered: `@if`, `@unless`, `@isset`, `@empty(…)`, `@auth`, `@guest`, `@can`,
     * `@cannot`, `@canany` (and their `@else…`), `@env`, `@production`, `@error`,
     * `@session`, `@switch`, `@forelse`.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function branch(array $node): array
    {
        return $this->branchAt($this->resolve($node)['offset'] ?? 0);
    }

    /**
     * The branch path at an offset (see `branch()`)
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function branchAt(int $offset): array
    {
        $low = 0;
        $high = count($this->pathAt) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($this->pathAt[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        $path = [];

        for ($at = $this->pathTail[$low]; $at > 0; $at = $this->pathParent[$at]) {
            $path[] = [$this->pathChain[$at], $this->pathBranch[$at]];
        }

        return array_reverse($path);
    }

    /**
     * Whether a node is inside a loop body
     */
    public function inLoop(array|int $node): bool
    {
        $node = $this->resolve($node);

        if ($node === null || $this->declined) {
            return false;
        }

        foreach ($this->branch($node) as [$chain]) {
            if (isset($this->loopChains[$chain])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether two tags can show on the same render: one's branch path is a prefix of
     * the other's. The branches of one chain never do. Two separate `@if` blocks do not
     * count either, because they are often exclusive by status; that is a miss, never
     * a false alarm.
     */
    public function together(array|int $a, array|int $b): bool
    {
        $a = $this->resolve($a);
        $b = $this->resolve($b);

        if ($a === null || $b === null || $this->declined) {
            return false;
        }

        $pathA = $this->branch($a);
        $pathB = $this->branch($b);

        for ($i = 0, $n = min(count($pathA), count($pathB)); $i < $n; $i++) {
            if ($pathA[$i] !== $pathB[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * What a tag shows, as plain text, or null when it cannot be known.
     *
     * On a button, link, menu item, navlist item or tab item it is the text inside: a
     * `label` or `heading` attribute is read only when there is none, and `title` is a
     * tooltip there, never the label. On any other atom tag, component or slot, a
     * `label`, `heading` or `title` attribute (in that order) is the label, bound or
     * not, and then the text inside. Literal arguments of `t('…')`, `__('…')`,
     * `trans('…')` and `@lang('…')` are kept. Any other echo, a directive or an
     * unterminated tag makes it null, and a check skips a null label. A self-closing
     * tag with no label attribute shows nothing (an empty string). An HTML element whose
     * closer HTML lets a page leave out (`li`, `p`, `td`...) reads up to where it ended.
     */
    public function label(array|int $node): ?string
    {
        $node = $this->resolve($node);

        if ($node === null) {
            return null;
        }

        $ownText = $node['kind'] === 'atom' && in_array($node['component'], self::TEXT_LABELLED, true);

        if ($node['kind'] !== 'html' && ! $ownText) {
            $attribute = $this->attributeLabel($node, ['label', 'heading', 'title']);

            if ($attribute !== false) {
                return $attribute;
            }
        }

        $text = $this->contentText($node);

        if ($ownText && $text === '') {
            $attribute = $this->attributeLabel($node, ['label', 'heading']);

            if ($attribute !== false) {
                return $attribute;
            }
        }

        return $text;
    }

    /**
     * The first of these attributes a node has, read as a label: false when it has none,
     * null when it is not a literal
     *
     * @param  array<int, string>  $names
     */
    protected function attributeLabel(array $node, array $names): string|false|null
    {
        foreach ($names as $name) {
            foreach ([$name, ':'.$name, 'x-bind:'.$name] as $key) {
                if (! array_key_exists($key, $node['attributes'])) {
                    continue;
                }

                $value = $node['attributes'][$key];

                if (! is_string($value)) {
                    return null;
                }

                if ($key === $name) {
                    return $this->plainText($value);
                }

                $literal = $this->literal($value);

                return $literal === null ? null : $this->squash($literal);
            }
        }

        return false;
    }

    /**
     * Every tag's opener and closer as a stretch of text to cut out of a label, in order
     */
    protected function buildCuts(): void
    {
        $cuts = [];

        foreach ($this->nodes as $node) {
            $cuts[$node['offset']] = $node['openEnd'];

            if ($node['close'] !== null) {
                $cuts[$node['close']] = $node['end'];
            }
        }

        ksort($cuts);

        $this->cutFrom = array_keys($cuts);
        $this->cutTo = array_values($cuts);
    }

    /**
     * The text inside a node, tags removed (see `label()`)
     */
    protected function contentText(array $node): ?string
    {
        if ($node['selfClosing']) {
            return '';
        }

        $close = $node['close'];

        if ($close === null) {
            if ($node['kind'] !== 'html' || ! in_array($node['tag'], self::IMPLIED_CLOSE, true)) {
                return null;
            }

            $close = $node['end'];
        }

        if ($this->cutFrom === null) {
            $this->buildCuts();
        }

        $low = 0;
        $high = count($this->cutFrom);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->cutFrom[$middle] < $node['openEnd']) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $content = '';
        $at = $node['openEnd'];

        for ($i = $low, $n = count($this->cutFrom); $i < $n && $this->cutFrom[$i] < $close; $i++) {
            if ($i - $low >= self::MAX_LABEL_CUTS) {
                return null;
            }

            if ($this->cutFrom[$i] >= $at) {
                $content .= substr($this->text, $at, $this->cutFrom[$i] - $at);
                $at = $this->cutTo[$i];
            }
        }

        $content .= substr($this->text, $at, $close - $at);

        return $this->plainText($content);
    }

    /**
     * The class tokens a tag carries: its static `class`, string literals in `:class`
     * and `x-bind:class`, and in an `@class([...])` inside the tag. A token that
     * touches an echo (`px-{{ $n }}`) is left out.
     *
     * @return array<int, string>
     */
    public function classes(array|int $node): array
    {
        $node = $this->resolve($node);

        if ($node === null) {
            return [];
        }

        $tokens = [];
        $static = $node['attributes']['class'] ?? null;

        if (is_string($static)) {
            $marked = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', "\0", $static) ?? $static;

            foreach (preg_split('/\s+/', $marked, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (! str_contains($token, "\0")) {
                    $tokens[] = $token;
                }
            }
        }

        $expressions = [];

        foreach ([':class', 'x-bind:class'] as $key) {
            if (is_string($node['attributes'][$key] ?? null)) {
                $expressions[] = $node['attributes'][$key];
            }
        }

        foreach ($node['directives'] as $directive) {
            if ($directive['name'] === 'class' && $directive['args'] !== null) {
                $expressions[] = $directive['args'];
            }
        }

        foreach ($expressions as $expression) {
            $expression = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', "\0", $expression) ?? $expression;

            preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"/s', $expression, $found, PREG_SET_ORDER);

            foreach ($found as $match) {
                $literal = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');

                foreach (preg_split('/\s+/', $literal, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                    if (! str_contains($token, "\0") && ! str_contains($token, '$')) {
                        $tokens[] = $token;
                    }
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Whether a tag has an attribute, static or bound
     */
    public function has(array|int $node, string $name): bool
    {
        $attributes = $this->resolve($node)['attributes'] ?? [];

        return isset($attributes[$name]) || isset($attributes[':'.$name]) || isset($attributes['x-bind:'.$name]);
    }

    /**
     * The value of a static attribute, trimmed and lowercased: null when it is absent,
     * bound, bare or has Blade in it (as `UpgradeCheck::value()`)
     */
    public function value(array|int $node, string $name): ?string
    {
        $value = $this->resolve($node)['attributes'][$name] ?? null;

        return is_string($value) && ! str_contains($value, '{{') && ! str_contains($value, '{!!') ? strtolower(trim($value)) : null;
    }

    /**
     * A node from a node or an id
     */
    protected function resolve(array|int $node): ?array
    {
        return is_array($node) ? ($this->nodes[$node['id'] ?? -1] ?? null) : ($this->nodes[$node] ?? null);
    }

    /**
     * An atom component name without its `atom:` prefix
     */
    protected function bareName(string $name): string
    {
        $name = strtolower(trim($name));

        return str_starts_with($name, 'x-atom::') ? substr($name, 8) : (str_starts_with($name, 'atom:') ? substr($name, 5) : $name);
    }

    /**
     * A tag name asked for in `inside()`, as a node carries it
     *
     * @throws \InvalidArgumentException
     */
    protected function wantedTag(string $name): string
    {
        $name = trim($name);
        $lower = strtolower($name);

        if (str_starts_with($lower, 'x-slot:')) {
            return 'x-slot:'.$this->slotName(substr($name, 7), true);
        }

        if (str_starts_with($lower, 'html:')) {
            return substr($lower, 5);
        }

        if (str_starts_with($lower, 'atom:') || str_starts_with($lower, 'x-atom::')) {
            return 'atom:'.$this->bareName($lower);
        }

        if (str_contains($lower, '.') && ! str_contains($lower, ':') && ! str_starts_with($lower, 'x-')) {
            return 'atom:'.$lower;
        }

        if (preg_match('/^[a-z][a-z0-9]*$/', $lower) && $this->isAtomComponent($lower)) {
            if (in_array($lower, self::HTML_ELEMENTS, true)) {
                throw new \InvalidArgumentException("`{$name}` is ambiguous, an atom component and an HTML element: write `atom:{$lower}` or `html:{$lower}`.");
            }

            return 'atom:'.$lower;
        }

        return $lower;
    }

    /**
     * Whether `components/` has a component of this name
     */
    protected function isAtomComponent(string $name): bool
    {
        $root = dirname(__DIR__, 2).'/components/'.$name;

        return is_file($root.'.blade.php') || is_file($root.'/index.blade.php');
    }

    /**
     * A slot name the way Blade reads it: `<x-slot:header-actions>` is `headerActions`,
     * `<x-slot name="headerActions">` and `<x-slot:headerActions>` are as written
     */
    protected function slotName(string $name, bool $inline): string
    {
        $name = trim($name);

        return $inline && str_contains($name, '-') ? Str::camel($name) : $name;
    }

    /**
     * Read the whole text once, left to right
     */
    protected function scan(): void
    {
        $text = $this->text;
        $n = strlen($text);
        $i = 0;

        while ($i < $n) {
            $i += strcspn($text, '<{@', $i);

            if ($i >= $n) {
                break;
            }

            $i = match ($text[$i]) {
                '{' => $this->skipEcho($i),
                '@' => $this->readDirective($i),
                default => $this->readMarkup($i),
            };
        }

        foreach ($this->stack as $id) {
            $this->nodes[$id]['end'] = $n;

            if ($this->nodes[$id]['kind'] === 'atom' || $this->nodes[$id]['kind'] === 'slot') {
                $this->balanced = false;
            }
        }

        $this->stack = [];
        $this->open = [];
    }

    /**
     * The first place a string occurs at or after an offset, or false. The answers are
     * remembered (where there was none, and the last match), so that a closer that is
     * missing, or far away, is looked for once however many openers wait for it.
     */
    protected function find(string $needle, int $from, bool $insensitive = false): int|false
    {
        $key = ($insensitive ? 'i' : 's').$needle;
        [$none, $searched, $hit] = $this->found[$key] ?? [PHP_INT_MAX, PHP_INT_MAX, false];

        if ($from >= $none || $from > strlen($this->text)) {
            return false;
        }

        if ($hit !== false && $from >= $searched && $from <= $hit) {
            return $hit;
        }

        $at = $insensitive ? stripos($this->text, $needle, $from) : strpos($this->text, $needle, $from);

        $this->found[$key] = $at === false ? [$from, $searched, $hit] : [$none, $from, $at];

        return $at;
    }

    /**
     * Charge a scan that read bytes and found nothing against the budget of failed
     * scans, which is what keeps a view of unfinished tags and argument lists from being
     * read over and over. Once it is spent a tag or an argument list is read as text and
     * `balanced()` is false. Only the scan spends it: `label()` has an allowance of its own.
     */
    protected function fail(int $bytes): void
    {
        $this->budget -= $bytes + 1;
    }

    /**
     * Step over `{{ }}`, `{{{ }}}`, `{!! !!}` and `{{-- --}}`; the offset after it
     */
    protected function skipEcho(int $i): int
    {
        $text = $this->text;

        [$opener, $closer] = match (true) {
            str_starts_with(substr($text, $i, 4), '{{--') => [4, '--}}'],
            str_starts_with(substr($text, $i, 3), '{{{') => [3, '}}}'],
            str_starts_with(substr($text, $i, 2), '{{') => [2, '}}'],
            str_starts_with(substr($text, $i, 3), '{!!') => [3, '!!}'],
            default => [0, null],
        };

        if ($closer === null) {
            return $i + 1;
        }

        $end = $this->find($closer, $i + $opener);

        return $end === false ? $i + 2 : $end + strlen($closer);
    }

    /**
     * Step over a directive, recording its effect on the branch path; the offset after it
     */
    protected function readDirective(int $i): int
    {
        $text = $this->text;
        $next = $text[$i + 1] ?? '';

        if ($next === '@') {
            return $i + 2;
        }

        if ($next === '{') {
            return $this->skipEcho($i + 1);
        }

        $before = $i > 0 ? $text[$i - 1] : '';

        if ($before !== '' && (ctype_alnum($before) || $before === '_')) {
            return $i + 1;
        }

        if (! preg_match('/\G@([A-Za-z_]\w*)([ \t]*)/', $text, $found, 0, $i)) {
            return $i + 1;
        }

        $name = strtolower($found[1]);
        $at = $i + strlen($found[0]);
        $after = $at;
        $args = null;

        if (($text[$at] ?? '') === '(' && ($end = $this->parensEnd($text, $at)) !== null) {
            $args = substr($text, $at + 1, $end - $at - 2);
            $after = $end;
        } else {
            $after = $i + strlen($found[1]) + 1;
        }

        if ($name === 'php') {
            if ($args !== null) {
                return $after;
            }

            $end = $this->find('@endphp', $after, true);

            return $end === false ? $after : $end + 7;
        }

        if ($name === 'verbatim') {
            $end = $this->find('@endverbatim', $after, true);

            return $end === false ? strlen($text) : $end + 12;
        }

        $this->direct($name, $args !== null, $i);

        return $after;
    }

    /**
     * Apply a directive to the open blocks and note the new branch path
     */
    protected function direct(string $name, bool $hasArgs, int $offset): void
    {
        if ($this->declined) {
            return;
        }

        $changed = false;

        if ($name === 'forelse') {
            $changed = $this->open('forelse');
        } elseif (in_array($name, self::LOOPS, true)) {
            $changed = $this->open($name, loop: true);
        } elseif ($name === 'switch') {
            $changed = $this->open('switch');
        } elseif ($name === 'case' || $name === 'default') {
            $changed = $this->advance(['switch']);
        } elseif ($name === 'empty' && ! $hasArgs) {
            $changed = $this->advance(['forelse']);
        } elseif (in_array($name, self::BRANCHING, true)) {
            $changed = $this->open(self::CLOSED_BY[$name] ?? $name);
        } elseif ($name === 'else' || preg_match('/^else\w+$/', $name) === 1) {
            $changed = $this->advance(self::BRANCHING);
        } elseif (str_starts_with($name, 'end')) {
            $changed = $this->close(substr($name, 3));
        }

        if ($changed) {
            $this->pathAt[] = $offset;
            $this->pathTail[] = $this->blocks === [] ? 0 : $this->blocks[count($this->blocks) - 1]['tail'];
        }
    }

    /**
     * Open a block of this kind; false (and the paths stop for good) when too many are open
     */
    protected function open(string $kind, bool $loop = false): bool
    {
        if (count($this->blocks) >= self::MAX_BLOCKS) {
            $this->declined = true;

            return false;
        }

        $chain = ++$this->chains;
        $block = ['kind' => $kind, 'chain' => $chain, 'branch' => 0, 'loop' => null, 'base' => $this->blocks === [] ? 0 : $this->blocks[count($this->blocks) - 1]['tail'], 'tail' => 0];

        if ($kind === 'forelse') {
            $block['loop'] = ++$this->chains;
            $this->loopChains[$block['loop']] = true;
        } elseif ($loop) {
            $this->loopChains[$chain] = true;
        }

        $block['tail'] = $this->tail($block);
        $this->blocks[] = $block;
        $this->blockKinds[$kind] = ($this->blockKinds[$kind] ?? 0) + 1;

        return true;
    }

    /**
     * The path node of a block as it stands: its pair, and a forelse body's loop pair
     */
    protected function tail(array $block): int
    {
        $node = $this->pathNode($block['base'], $block['chain'], in_array($block['kind'], self::LOOPS, true) ? 0 : $block['branch']);

        if ($block['kind'] === 'forelse' && $block['branch'] === 0) {
            $node = $this->pathNode($node, $block['loop'], 0);
        }

        return $node;
    }

    /**
     * A new node of the path tree
     */
    protected function pathNode(int $parent, int $chain, int $branch): int
    {
        $this->pathParent[] = $parent;
        $this->pathChain[] = $chain;
        $this->pathBranch[] = $branch;

        return count($this->pathParent) - 1;
    }

    /**
     * Move the nearest open block of one of these kinds to its next branch, dropping
     * what was left open inside it
     *
     * @param  array<int, string>  $kinds
     */
    protected function advance(array $kinds): bool
    {
        $present = false;

        foreach ($kinds as $kind) {
            $present = $present || ($this->blockKinds[$kind] ?? 0) > 0;
        }

        if (! $present) {
            return false;
        }

        for ($k = count($this->blocks) - 1; $k >= 0; $k--) {
            if (in_array($this->blocks[$k]['kind'], $kinds, true)) {
                $this->drop($k + 1);
                $this->blocks[$k]['branch']++;
                $this->blocks[$k]['tail'] = $this->tail($this->blocks[$k]);

                return true;
            }
        }

        return false;
    }

    /**
     * Close the nearest open block of this kind, dropping what was left open inside it
     */
    protected function close(string $kind): bool
    {
        if (($this->blockKinds[$kind] ?? 0) === 0) {
            return false;
        }

        for ($k = count($this->blocks) - 1; $k >= 0; $k--) {
            if ($this->blocks[$k]['kind'] === $kind) {
                $this->drop($k);

                return true;
            }
        }

        return false;
    }

    /**
     * Drop the open blocks from an index up
     */
    protected function drop(int $from): void
    {
        while (count($this->blocks) > $from) {
            $block = array_pop($this->blocks);
            $this->blockKinds[$block['kind']]--;
        }
    }

    /**
     * The offset after the `)` that closes the `(` at $open, strings respected; null
     * when it never closes
     */
    protected function parensEnd(string $text, int $open, ?int &$allowance = null): ?int
    {
        $spent = $allowance ?? $this->budget;

        if ($spent <= 0) {
            if ($allowance === null) {
                $this->balanced = false;
            }

            return null;
        }

        $limit = min(strlen($text), $open + self::MAX_TAG_BYTES);
        $depth = 0;

        for ($i = $open; $i < $limit; $i++) {
            $c = $text[$i];

            if ($c === "'" || $c === '"') {
                for ($i++; $i < $limit && $text[$i] !== $c; $i++) {
                    if ($text[$i] === '\\') {
                        $i++;
                    }
                }

                continue;
            }

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return $i + 1;
            }
        }

        if ($allowance === null) {
            $this->fail($i - $open);
        } else {
            $allowance -= $i - $open + 1;
        }

        return null;
    }

    /**
     * Read a tag, a closer, a comment, a doctype or a PHP block at a `<`; the offset after it
     */
    protected function readMarkup(int $i): int
    {
        $text = $this->text;
        $next = $text[$i + 1] ?? '';

        if ($next === '!') {
            if (str_starts_with(substr($text, $i, 4), '<!--')) {
                $end = $this->find('-->', $i + 4);

                return $end === false ? strlen($text) : $end + 3;
            }

            $end = $this->find('>', $i);

            return $end === false ? $i + 2 : $end + 1;
        }

        if ($next === '?') {
            $end = $this->find('?>', $i + 2);

            return $end === false ? strlen($text) : $end + 2;
        }

        if ($next === '/' && preg_match('/\G<\/([A-Za-z][A-Za-z0-9_\-:.]*)/', $text, $found, 0, $i)) {
            return $this->readCloser($i, $found[1], strlen($found[0]));
        }

        if (preg_match('/\G<([A-Za-z][A-Za-z0-9_\-:.]*)/', $text, $found, 0, $i)) {
            return $this->readOpener($i, $found[1], strlen($found[0]));
        }

        return $i + 1;
    }

    /**
     * Whether a tag name is one that `balanced()` cares about
     */
    protected function isStructural(string $tag): bool
    {
        return str_starts_with($tag, 'atom:') || $tag === 'x-slot' || str_starts_with($tag, 'x-slot:');
    }

    /**
     * The tag, kind, component and slot of a tag name as written
     *
     * @return array{tag: string, kind: string, component: ?string, slot: ?string}
     */
    protected function describe(string $name): array
    {
        $tag = strtolower($name);

        if (str_starts_with($tag, 'x-atom::')) {
            $tag = 'atom:'.substr($tag, 8);
        }

        if (str_starts_with($tag, 'atom:')) {
            return ['tag' => $tag, 'kind' => 'atom', 'component' => substr($tag, 5), 'slot' => null];
        }

        if ($tag === 'x-slot' || str_starts_with($tag, 'x-slot:')) {
            return ['tag' => 'x-slot', 'kind' => 'slot', 'component' => null, 'slot' => $tag === 'x-slot' ? null : $this->slotName(substr($name, 7), true)];
        }

        if (str_starts_with($tag, 'x-') || str_starts_with($tag, 'livewire:')) {
            return ['tag' => $tag, 'kind' => 'component', 'component' => null, 'slot' => null];
        }

        return ['tag' => $tag, 'kind' => 'html', 'component' => null, 'slot' => null];
    }

    /**
     * The names a node is open under, for `$open`
     *
     * @return array<int, string>
     */
    protected function openKeys(array $node): array
    {
        return $node['slot'] === null ? [$node['tag']] : [$node['tag'], 'x-slot:'.$node['slot']];
    }

    /**
     * Put a node on the stack of open tags
     */
    protected function pushOpen(int $id): void
    {
        $this->stack[] = $id;

        foreach ($this->openKeys($this->nodes[$id]) as $key) {
            $this->open[$key] = ($this->open[$key] ?? 0) + 1;
        }
    }

    /**
     * Take the top node off the stack of open tags
     */
    protected function popOpen(): int
    {
        $id = array_pop($this->stack);

        foreach ($this->openKeys($this->nodes[$id]) as $key) {
            $this->open[$key]--;
        }

        return $id;
    }

    /**
     * Read an opening tag and push it; the offset after it
     */
    protected function readOpener(int $i, string $name, int $length): int
    {
        if (count($this->nodes) >= self::MAX_NODES) {
            $this->balanced = false;

            return $i + 1;
        }

        $read = $this->readTag($i + $length);
        $info = $this->describe($name);

        if ($read === null) {
            if ($this->isStructural($info['tag'])) {
                $this->balanced = false;
            }

            return $i + 1;
        }

        if ($info['kind'] === 'slot' && $info['slot'] === null && is_string($read['attributes']['name'] ?? null) && ! str_contains($read['attributes']['name'], '{{')) {
            $info['slot'] = $this->slotName($read['attributes']['name'], false);
        }

        if ($info['kind'] === 'html' && in_array($info['tag'], self::IMPLIED_CLOSE, true) && $this->stack !== []) {
            $top = $this->stack[count($this->stack) - 1];

            if ($this->nodes[$top]['tag'] === $info['tag']) {
                $this->nodes[$top]['end'] = $i;
                $this->popOpen();
            }
        }

        $parent = $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
        $selfClosing = $read['selfClosing'] || ($info['kind'] === 'html' && in_array($info['tag'], self::VOID_ELEMENTS, true));
        $id = count($this->nodes);

        $this->nodes[] = $info + [
            'id' => $id,
            'offset' => $i,
            'openEnd' => $read['end'],
            'close' => null,
            'end' => $read['end'],
            'selfClosing' => $selfClosing,
            'attributes' => $read['attributes'],
            'spans' => $read['spans'],
            'directives' => $read['directives'],
            'parent' => $parent,
            'depth' => count($this->stack),
        ];

        if ($selfClosing) {
            return $read['end'];
        }

        if (count($this->stack) >= self::MAX_DEPTH) {
            $this->balanced = false;

            return $read['end'];
        }

        $this->pushOpen($id);

        if ($info['kind'] === 'html' && in_array($info['tag'], ['script', 'style'], true)) {
            $end = $this->find('</'.$info['tag'], $read['end'], true);

            return $end === false ? strlen($this->text) : $end;
        }

        return $read['end'];
    }

    /**
     * Read a closing tag and pair it with the nearest open tag of its name; the offset after it
     */
    protected function readCloser(int $i, string $name, int $length): int
    {
        $end = $this->find('>', $i + $length);
        $info = $this->describe($name);

        if ($end === false || $end - $i > self::MAX_TAG_BYTES) {
            if ($this->isStructural($info['tag'])) {
                $this->balanced = false;
            }

            return $i + 2;
        }

        $key = $info['kind'] === 'slot' && $info['slot'] !== null ? 'x-slot:'.$info['slot'] : $info['tag'];

        if (($this->open[$key] ?? 0) === 0) {
            if ($this->isStructural($info['tag'])) {
                $this->balanced = false;
            }

            return $end + 1;
        }

        for ($k = count($this->stack) - 1; $k >= 0; $k--) {
            $node = $this->nodes[$this->stack[$k]];

            if (in_array($key, $this->openKeys($node), true)) {
                break;
            }
        }

        while (count($this->stack) - 1 > $k) {
            $cut = $this->popOpen();
            $this->nodes[$cut]['end'] = $i;

            if ($this->isStructural($this->nodes[$cut]['tag'])) {
                $this->balanced = false;
            }
        }

        $id = $this->popOpen();
        $this->nodes[$id]['close'] = $i;
        $this->nodes[$id]['end'] = $end + 1;

        return $end + 1;
    }

    /**
     * Read the attributes of a tag up to its `>`, quotes, echoes and directives respected
     *
     * @return array{end: int, selfClosing: bool, attributes: array<string, string|true>, spans: array<string, array{0: int, 1: int}>, directives: array<int, array{name: string, args: ?string}>}|null
     */
    protected function readTag(int $from): ?array
    {
        if ($this->budget <= 0) {
            $this->balanced = false;

            return null;
        }

        $gt = $this->find('>', $from);

        if ($gt === false || $gt - $from >= self::MAX_TAG_BYTES) {
            return null;
        }

        $reached = $from;
        $read = $this->scanTag($from, $reached);

        if ($read === null) {
            $this->fail($reached - $from);
        }

        return $read;
    }

    /**
     * What `readTag()` does, with where it got to when it found no end
     *
     * @return array{end: int, selfClosing: bool, attributes: array<string, string|true>, spans: array<string, array{0: int, 1: int}>, directives: array<int, array{name: string, args: ?string}>}|null
     */
    protected function scanTag(int $from, int &$reached): ?array
    {
        $text = $this->text;
        $limit = min(strlen($text), $from + self::MAX_TAG_BYTES);
        $attributes = [];
        $spans = [];
        $directives = [];
        $i = $from;

        while ($i < $limit) {
            $reached = $i;
            $c = $text[$i];

            if (ctype_space($c)) {
                $i++;

                continue;
            }

            if ($c === '>') {
                return ['end' => $i + 1, 'selfClosing' => false] + compact('attributes', 'spans', 'directives');
            }

            if ($c === '/') {
                if (($text[$i + 1] ?? '') === '>') {
                    return ['end' => $i + 2, 'selfClosing' => true] + compact('attributes', 'spans', 'directives');
                }

                $i++;

                continue;
            }

            if ($c === '{' && in_array(substr($text, $i, 2), ['{{', '{!'], true)) {
                $after = $this->skipEcho($i);
                $i = $after > $i ? $after : $i + 1;

                continue;
            }

            if ($c === '@' && preg_match('/\G@([A-Za-z_]\w*)[ \t]*(?=\()/', $text, $found, 0, $i)) {
                $open = $i + strlen($found[0]);
                $end = $this->parensEnd($text, $open);

                if ($end !== null) {
                    $directives[] = ['name' => strtolower($found[1]), 'args' => substr($text, $open + 1, $end - $open - 2)];
                    $i = $end;

                    continue;
                }
            }

            if ($c === '@' && preg_match('/\G@(?:else\w*|end\w+)(?![\w:.\-])/i', $text, $found, 0, $i)) {
                $i += strlen($found[0]);

                continue;
            }

            if ($c === '"' || $c === "'" || $c === '=') {
                $i++;

                continue;
            }

            $start = $i;

            while ($i < $limit && ! ctype_space($text[$i]) && ! in_array($text[$i], ['=', '>', '/', '"', "'"], true)) {
                $i++;
            }

            $name = substr($text, $start, $i - $start);

            if ($name === '') {
                $i++;

                continue;
            }

            $j = $i;

            while ($j < $limit && ctype_space($text[$j])) {
                $j++;
            }

            if (($text[$j] ?? '') !== '=') {
                $attributes[$name] = true;
                $spans[$name] = [$start, $i];

                continue;
            }

            $i = $j + 1;

            while ($i < $limit && ctype_space($text[$i])) {
                $i++;
            }

            $quote = $text[$i] ?? '';

            if ($quote === '"' || $quote === "'") {
                $i++;
                $valueStart = $i;

                while ($i < $limit && $text[$i] !== $quote) {
                    if ($text[$i] === '{' && in_array(substr($text, $i, 2), ['{{', '{!'], true)) {
                        $after = $this->skipEcho($i);
                        $i = $after > $i ? $after : $i + 1;

                        continue;
                    }

                    $i++;
                }

                if ($i >= $limit) {
                    $reached = $i;

                    return null;
                }

                $attributes[$name] = substr($text, $valueStart, $i - $valueStart);
                $i++;
                $spans[$name] = [$start, $i];

                continue;
            }

            $valueStart = $i;

            while ($i < $limit && ! ctype_space($text[$i]) && $text[$i] !== '>') {
                if ($text[$i] === '{' && in_array(substr($text, $i, 2), ['{{', '{!'], true)) {
                    $after = $this->skipEcho($i);
                    $i = $after > $i ? $after : $i + 1;

                    continue;
                }

                $i++;
            }

            $value = substr($text, $valueStart, $i - $valueStart);
            $selfClosing = ($text[$i] ?? '') === '>' && str_ends_with($value, '/') && strlen($value) > 1;

            if ($selfClosing) {
                $value = substr($value, 0, -1);
            }

            $attributes[$name] = $value;
            $spans[$name] = [$start, $selfClosing ? $i - 1 : $i];

            if ($selfClosing) {
                return ['end' => $i + 1, 'selfClosing' => true] + compact('attributes', 'spans', 'directives');
            }
        }

        $reached = $i;

        return null;
    }

    /**
     * The plain text of a stretch of Blade: echoes of `t()`, `__()` and `trans()`
     * literals, and `@lang('…')`, are kept; anything else dynamic makes it null
     */
    protected function plainText(string $source): ?string
    {
        $n = strlen($source);
        $out = '';
        $i = 0;
        $allowance = max(20000, 4 * $n);

        while ($i < $n) {
            $j = $i + strcspn($source, '{@', $i);
            $out .= substr($source, $i, $j - $i);

            if ($j >= $n) {
                break;
            }

            if (str_starts_with(substr($source, $j, 4), '{{--')) {
                $end = strpos($source, '--}}', $j + 4);
                $i = $end === false ? $n : $end + 4;
            } elseif (preg_match('/\G\{\{\{?(.*?)\}?\}\}|\G\{!!(.*?)!!\}/s', $source, $echo, 0, $j)) {
                $literal = $this->literal($echo[1] !== '' ? $echo[1] : ($echo[2] ?? ''));

                if ($literal === null) {
                    return null;
                }

                $out .= $literal;
                $i = $j + strlen($echo[0]);
            } elseif ($source[$j] === '{') {
                if (in_array(substr($source, $j, 2), ['{{', '{!'], true)) {
                    return null;
                }

                $out .= '{';
                $i = $j + 1;
            } else {
                $before = $j > 0 ? $source[$j - 1] : '';

                if (($source[$j + 1] ?? '') === '@') {
                    $out .= '@';
                    $i = $j + 2;
                } elseif ($before !== '' && (ctype_alnum($before) || $before === '_')) {
                    $out .= '@';
                    $i = $j + 1;
                } elseif (preg_match('/\G@lang[ \t]*(?=\()/i', $source, $lang, 0, $j) && ($end = $this->parensEnd($source, $j + strlen($lang[0]), $allowance)) !== null) {
                    $literal = $this->literal('t'.substr($source, $j + strlen($lang[0]), $end - $j - strlen($lang[0])));

                    if ($literal === null) {
                        return null;
                    }

                    $out .= $literal;
                    $i = $end;
                } elseif (preg_match('/\G@[A-Za-z_]/', $source, $directive, 0, $j)) {
                    return null;
                } else {
                    $out .= '@';
                    $i = $j + 1;
                }
            }
        }

        return $this->squash($out);
    }

    /**
     * Entities decoded and whitespace collapsed
     */
    protected function squash(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? $text);
    }

    /**
     * The string an expression is when it is one literal, or `t('…')`, `__('…')` or
     * `trans('…')` with a literal first argument; null for anything else
     */
    protected function literal(string $expression): ?string
    {
        $expression = trim($expression);

        if (preg_match('/^(?:t|__|trans)\s*\(\s*/i', $expression, $call)) {
            $open = strpos($expression, '(');

            $allowance = self::MAX_TAG_BYTES;

            if ($this->parensEnd($expression, $open, $allowance) !== strlen($expression)) {
                return null;
            }

            $read = $this->readString($expression, strlen($call[0]));

            if ($read === null) {
                return null;
            }

            return preg_match('/^\s*[,)]/', substr($expression, $read[1])) ? $read[0] : null;
        }

        $read = $this->readString($expression, 0);

        return $read !== null && $read[1] === strlen($expression) ? $read[0] : null;
    }

    /**
     * A PHP string literal at an offset: its value and the offset after it; null when
     * it is not one, or a double-quoted one interpolates
     *
     * @return array{0: string, 1: int}|null
     */
    protected function readString(string $text, int $i): ?array
    {
        $quote = $text[$i] ?? '';

        if ($quote !== "'" && $quote !== '"') {
            return null;
        }

        for ($j = $i + 1; $j < strlen($text) && $text[$j] !== $quote; $j++) {
            if ($text[$j] === '\\') {
                $j++;
            }
        }

        if ($j >= strlen($text)) {
            return null;
        }

        $body = substr($text, $i + 1, $j - $i - 1);

        if ($quote === '"') {
            return str_contains($body, '$') ? null : [stripcslashes($body), $j + 1];
        }

        return [str_replace(['\\\\', "\\'"], ['\\', "'"], $body), $j + 1];
    }
}
