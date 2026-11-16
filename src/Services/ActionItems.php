<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\ComponentSlot;
use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

/**
 * The request-scoped registry behind `<atom:action>`.
 *
 * Blade renders a slot before the view of the component that owns it, and an anonymous component cannot open a scope first (`FormMarker` says so
 * too), so an action cannot find its surface by looking up. It keeps what it is (its props, its attribute bag, its label) here under a random id and
 * prints only the marker `<!--atom:action:<id>-->`. The surface reads the HTML of its slots for markers, in order, takes the actions out of the
 * registry, strips every marker and places the actions (`ActionPlacement`).
 *
 * - **Ownership.** An action records `FormMarker::owner()` (the Livewire component being rendered and how deep the render stack is), and a surface takes only
 *   actions of its own owner: an action in a Livewire child or an island is never placed by the parent's surface, on the first render or after the child's
 *   own update.
 * - **A marker never reaches the browser from a surface.** Every well-formed marker in a slot a surface reads is stripped, placed or not. An id that is
 *   not in the registry (forged or stale) is stripped and ignored.
 * - **An action nobody placed draws nothing.** The registry logs it once when the request ends (`reportUnplaced()`, called from the one `terminating()` callback
 *   `AtomServiceProvider` registers for the container, so a worker that never terminates does not pile callbacks up), without its label, which can hold record data.
 * - **Scoped** (`AtomServiceProvider`): Octane and queue workers get a fresh registry per request and per job.
 */
class ActionItems
{
    /**
     * A marker: 32 lowercase hex characters between the fixed comment text.
     */
    public const PATTERN = '/<!--atom:action:([0-9a-f]{32})-->/';

    /**
     * The registered actions that no surface took yet, by id.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];

    protected int $counter = 0;

    /**
     * Once per process, when neither the cache nor its fallback can remember: the ids already logged, capped at 100.
     *
     * @var array<string, true>
     */
    protected static array $reported = [];

    /**
     * Keep an action and give back the marker that stands for it in the slot.
     *
     * @param  array<string, mixed>  $item  `label` (plain text), `html` (the label as markup), `icon`, `priority`, `tone`, `confirm`, `href`, `newtab`, `passphrase`, `attributes` (the host's bag)
     */
    public function add(array $item): string
    {
        $id = bin2hex(random_bytes(16));

        $this->items[$id] = [
            ...$item,
            'id' => $id,
            'owner' => FormMarker::owner(),
            'where' => static::where(),
            'order' => $this->counter++,
            'slug' => Str::slug((string) ($item['label'] ?? '')) ?: 'action',
        ];

        return '<!--atom:action:'.$id.'-->';
    }

    /**
     * Take the actions out of a slot a surface reads.
     *
     * With no marker in it the slot comes back as it was (so a surface with no action prints the bytes it always did). With markers the slot comes back without
     * them, and the actions of this owner are taken out of the registry, in the order they were written, each tagged with the slot's name and its position in it.
     *
     * @return array{0: ?ComponentSlot, 1: list<array<string, mixed>>}
     */
    public function collect(mixed $slot, string $slotName): array
    {
        if ($slot === null) {
            return [null, []];
        }

        $html = (string) $slot;

        if (! str_contains($html, '<!--atom:action:') || ! preg_match_all(static::PATTERN, $html, $found)) {
            return [$slot instanceof ComponentSlot ? $slot : new ComponentSlot($html), []];
        }

        $owner = FormMarker::owner();
        $actions = [];

        foreach ($found[1] as $id) {
            $item = $this->items[$id] ?? null;

            if ($item === null || $item['owner'] !== $owner) {
                continue;
            }

            unset($this->items[$id]);

            $actions[] = [...$item, 'slot' => $slotName, 'position' => count($actions)];
        }

        $attributes = $slot instanceof ComponentSlot ? $slot->attributes->getAttributes() : [];

        return [new ComponentSlot(preg_replace(static::PATTERN, '', $html), $attributes), $actions];
    }

    /**
     * Give each action the `wire:key` it is drawn with: the host's own when it wrote one, else `atom-action-<slug of the label>` (`-2`, `-3` for a repeat).
     * Derived, so it is the same on every render and a morph moves the right node when an `@if` adds an action.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    public function keyed(array $actions): array
    {
        $seen = [];

        return array_map(function (array $action) use (&$seen) {
            $host = $action['attributes']?->get('wire:key');

            if (filled($host)) {
                return [...$action, 'key' => (string) $host];
            }

            $seen[$action['slug']] = ($seen[$action['slug']] ?? 0) + 1;
            $count = $seen[$action['slug']];

            return [...$action, 'key' => 'atom-action-'.$action['slug'].($count > 1 ? '-'.$count : '')];
        }, array_values($actions));
    }

    /**
     * How many actions are waiting for a surface.
     */
    public function pending(): int
    {
        return count($this->items);
    }

    /**
     * Log the actions no surface took, once for the request and once an hour per place. Position, tone and priority are logged; the label never is.
     * Dedupe goes through the cache (across processes); if the cache throws or cannot remember (the null store), a per-process set capped at 100.
     */
    public function reportUnplaced(): void
    {
        if ($this->items === []) {
            return;
        }

        $left = array_map(fn (array $item) => [
            'where' => $item['where'],
            'position' => $item['order'],
            'tone' => $item['tone'] ?? null,
            'priority' => $item['priority'] ?? null,
        ], array_values($this->items));

        $this->items = [];

        $id = md5(json_encode($left));
        $cacheKey = 'atom:unplaced-action:'.$id;

        try {
            $first = Cache::add($cacheKey, 1, 3600);
            $remembers = $first || Cache::has($cacheKey);
        }
        catch (\Throwable) {
            $first = $remembers = false;
        }

        if (! $remembers) {
            $first = ! isset(static::$reported[$id]);

            if (count(static::$reported) >= 100) {
                static::$reported = [];
            }

            static::$reported[$id] = true;
        }

        if (! $first) {
            return;
        }

        Log::warning('An <atom:action> was not placed by any surface and was not drawn.', [
            'actions' => $left,
            'hint' => 'Put it in a slot a surface reads: page.header, table.toolbar, the table\'s checked and checked-menu, card and panel, table.actions. `atom:design-check` reports it as action-misplaced.',
        ]);
    }

    /**
     * Where the action was written: the Livewire component being rendered, else the route's URI pattern.
     */
    protected static function where(): string
    {
        if (class_exists(ExtendBlade::class) && method_exists(ExtendBlade::class, 'currentRendering')) {
            $component = ExtendBlade::currentRendering();

            if (is_object($component)) {
                return $component::class;
            }
        }

        return optional(request()->route())->uri() ?? 'unknown';
    }

    /**
     * What `confirm` is for an action of this tone: a warning or danger action confirms in its tone by default (A4), `false` opts out, and a delete confirms itself.
     * Returned as the value `<atom:button>` and `<atom:menu.item>` take.
     */
    public static function confirmFor(?string $tone, mixed $confirm): mixed
    {
        if ($confirm === false) {
            return false;
        }

        if (! in_array($tone, ['warning', 'danger'], true)) {
            return $confirm === true && $tone === 'delete' ? null : $confirm;
        }

        $given = match (true) {
            is_array($confirm) => $confirm,
            is_string($confirm) && $confirm !== '' => ['heading' => $confirm],
            default => [],
        };

        return [...['variant' => $tone], ...$given];
    }
}
