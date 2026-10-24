<?php

namespace Jiannius\Atom\Services;

use Illuminate\Support\Facades\Blade;
use Throwable;

/**
 * Reads a host application's source (never writes) and lists what the move to
 * atom 4 will break or change, with file:line and counts. One check per entry
 * of UPGRADE.md; `severity` says how it shows ("breaks at runtime", "visual
 * change", "review") and `codemod` whether the atom:upgrade codemod will fix it
 * ("auto") or only report it ("report").
 *
 * The scan is text based on purpose: a regex or token scan over the file
 * kinds a check names, after comments are blanked (line numbers kept) and,
 * for the JS checks in a Blade file, after every PHP region is masked. A match
 * is a candidate, not a proof: a `->local(` on something that is not a date is
 * counted, and the report says so.
 *
 * Works from a root directory only, so it runs on a throwaway tree in a test
 * and on a host from the artisan command.
 */
class UpgradeCheck
{
    public const BREAKS = 'breaks at runtime';

    public const VISUAL = 'visual change';

    public const REVIEW = 'review';

    /**
     * Where the list of breaks lives (UPGRADE.md is on the v4 branch, not in 3.x)
     */
    public const GUIDE = 'https://github.com/jiannius/atom/blob/v4/UPGRADE.md';

    /**
     * The checks whose finding the Date::use line fixes
     */
    public const COVERED_BY_DATE_USE = ['date-pretty', 'date-local', 'date-recent', 'date-range-string', 'carbon-typed', 'date-printed-bare'];

    /**
     * Files whose average line is longer than this many bytes look minified and are skipped
     */
    public const MINIFIED_LINE = 20000;

    /**
     * Directory names skipped at any depth
     */
    public const SKIP_DIRECTORIES = ['node_modules', '.git'];

    /**
     * Composer's directory: skipped at the root and wherever it holds an install
     * (an autoload.php or a composer/ directory). Any other directory with this
     * name is the host's own code, such as a "vendor" (supplier) screen.
     */
    public const VENDOR_DIRECTORY = 'vendor';

    /**
     * Paths, from the root, skipped
     */
    public const SKIP_PATHS = ['storage', 'public/build', 'bootstrap/cache'];

    /**
     * What is scanned: these directories (under the root) and the root's own build config
     */
    public const DIRECTORIES = ['app', 'resources', 'routes', 'modules', 'Modules', 'config', 'database', 'tests'];

    /**
     * Files beyond this are skipped
     */
    public const MAX_BYTES = 2097152;

    /**
     * The traits AtomComponent became, in the order they are printed
     */
    public const TRAITS = ['WithTable', 'WithBreadcrumbs', 'WithEditor', 'WithRecaptcha', 'WithPagination', 'WithFileUploads'];

    /**
     * Where each section of components is described in UPGRADE.md, by first tag segment
     */
    public const RESTYLED = [
        'Controls' => ['button', 'input', 'select', 'date-picker', 'time-picker', 'textarea', 'checkbox', 'radio', 'toggle', 'slider', 'rating', 'uploader'],
        'Data display' => ['table', 'badge', 'card', 'callout', 'dd', 'list', 'avatar', 'pagination', 'progress', 'skeleton', 'kbd', 'profile', 'placeholder-bar', 'empty'],
        'Overlays and navigation' => ['modal', 'alert', 'confirm', 'dropdown', 'menu', 'tooltip', 'toast', 'command', 'context-menu', 'lightbox', 'tabs', 'breadcrumbs', 'accordion'],
        'Text, editor, calendar, chart and the docs' => ['heading', 'subheading', 'caption', 'label', 'error', 'link', 'separator', 'sharer', 'copy', 'embed', 'whatsapp', 'tiptap', 'editor', 'calendar', 'chart'],
        'Shells' => ['layouts', 'navlist', 'html', 'darkmode-toggle'],
        'Lists, forms and the timeline' => ['form'],
    ];

    /**
     * The tone a badge colour name reads as (UPGRADE.md, "Badge colours")
     */
    public const TONES = [
        'neutral' => ['neutral', 'gray', 'grey', 'zinc', 'slate', 'stone', 'white'],
        'info' => ['info', 'blue', 'sky', 'cyan', 'indigo'],
        'success' => ['success', 'green', 'emerald', 'teal', 'lime'],
        'warning' => ['warning', 'yellow', 'amber', 'orange'],
        'danger' => ['danger', 'error', 'red', 'rose'],
        'accent' => ['accent', 'primary', 'purple', 'violet', 'fuchsia', 'pink'],
    ];

    /**
     * Classes a test may match, which the restyle changed (UPGRADE.md, "Breaks a test").
     * One is built from two strings so PaletteTest does not read this list as markup.
     */
    public const TEST_CLASSES = [
        'bg-primary', 'bg-white', 'shadow-xs', 'h-10', 'rounded-lg', 'size-4.5', 'bg-green-100', 'bg-sky-100', 'bg-zinc-100', 'p-6',
        'size-8', 'size-10', 'bg-green-500', 'animate-pulse', 'shadow-lg', 'rounded-xl', 'bg-zinc-800', 'bg-zinc-900', 'dark:bg-zinc-700',
        'text-zinc-400', 'text-gray-100', 'text-red-500', 'text-green-500', 'text-yellow-500', 'px-4', 'text-sky-600', 'decoration-dotted',
        'text-zinc-500', 'dark:text-zinc-400', 'bg-zinc-200', 'text-black', 'text-blue-500', 'text-'.'muted', 'border-zinc-200', 'uppercase',
        'py-3', 'hover:bg-red-100', 'hover:bg-yellow-100', 'focus:bg-zinc-800/5', 'size-5', 'data-[active]:shadow-sm', 'font-medium',
        'bg-red-100', 'border-red-600', 'focus:ring', 'focus:outline-none', 'focus:outline-hidden', 'dialog button',
    ];

    /**
     * Names the old AtomComponent helpers have, now macros on Livewire\Component
     */
    public const HELPERS = ['toast', 'alert', 'confirm', 'modal', 'command', 'wirekey', 'action'];

    /**
     * The attribute-bag macros v4 removes
     */
    public const BAG_MACROS = ['size', 'field', 'getAny', 'getLike', 'classes', 'styles', 'fieldId', 'modifier', 'hasLike'];

    /**
     * What the last scan did not read, as [path, reason]
     *
     * @var array<int, array{path: string, reason: string}>
     */
    protected array $skipped = [];

    /**
     * @param  string|null  $guidelinePath  the guideline to compare a host's copy with (default: this package's)
     */
    public function __construct(protected ?string $guidelinePath = null) {}

    /**
     * Every check, by id: what it is, where it is described, how it is found, how
     * bad it is and whether atom:upgrade will fix it.
     *
     * Keys: title, section (the UPGRADE.md heading), severity, codemod ('auto' or
     * 'report'), detect (how it is found, in words), kinds (php, blade, js, css,
     * config), scope ('tests' for a check that only reads tests/), and one of
     * pattern (a regex, over `variant`: 'full' or 'markup'), tag (a Blade tag
     * check) or custom.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function checks(): array
    {
        $helpers = implode('|', static::HELPERS);
        $bag = implode('|', static::BAG_MACROS);
        $dateNative = ['toDateString', 'toTimeString'];

        return [
            // tokens and CSS
            'css-atom-source' => [
                'title' => 'Delete the atom @source lines (atom.tailwind.css scans atom itself)',
                'section' => 'Tokens', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '@source line naming jiannius/atom in a CSS file',
                'kinds' => ['css'], 'pattern' => '/@source\s+[\'"][^\'"\n]*jiannius\/atom[^\'"\n]*[\'"]/',
            ],
            'css-border-color-rule' => [
                'title' => 'Delete the copied border-color base rule (atom sets its own)',
                'section' => 'Tokens', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => 'a CSS rule whose selector is `*` (or `*, ::after, ::before`, at the start of a rule) that sets border-color',
                'kinds' => ['css'], 'pattern' => '/(?:^|[;{}])\s*\K\*\s*(?:,[^{}]*::?(?:after|before)[^{}]*)?\{[^{}]*border-color\s*:/',
            ],
            'css-root-font-size' => [
                'title' => 'Remove the html font-size override (the 14px root is gone)',
                'section' => 'Tokens', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => 'html or :root rule with font-size in a CSS file',
                'kinds' => ['css'], 'pattern' => '/(?<![\w\-.#])(?:html|:root)\s*\{[^{}]*font-size\s*:/',
            ],
            'css-old-font' => [
                'title' => 'Remove the font loaded for the old design (atom serves Geist)',
                'section' => 'Tokens', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '@font-face or --font-sans in CSS; a font plugin in vite.config',
                'kinds' => ['css', 'config'], 'pattern' => '/@font-face|--font-sans\s*:|fonts\.bunny\.net|fonts\.googleapis\.com|bunny[-.]?fonts?|google-fonts|vite-plugin-webfont|unplugin-fonts|@fontsource/i',
            ],

            // shells
            'navlist-group-class' => [
                'title' => 'A class on a headed navlist.group now lands on the group root',
                'section' => 'Shells', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:navlist.group> with both heading and class',
                'kinds' => ['blade'],
                'tag' => ['navlist.group', fn (array $a) => static::has($a, 'heading') && static::has($a, 'class') ? 'class moves from the item list to the group root' : null],
            ],
            'shell-dropdown-menu-wrap' => [
                'title' => 'The shell dropdown slot takes menu items only (it wraps them in a menu itself)',
                'section' => 'Shells', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<x-slot:dropdown> whose first child is <atom:menu>',
                'kinds' => ['blade'], 'pattern' => '/<x-slot:dropdown\s*>\s*(?:<atom:menu\b|<x-atom::menu\b)/',
            ],
            'html-editor-prop' => [
                'title' => 'Delete the dead `editor` prop of <atom:html> and the shells',
                'section' => 'PHP globals: dates and macros', 'severity' => static::REVIEW, 'codemod' => 'auto',
                'detect' => 'editor attribute on <atom:html>, layouts.sidebar or layouts.header',
                'kinds' => ['blade'],
                'tag' => [['html', 'layouts.sidebar', 'layouts.header'], fn (array $a) => static::has($a, 'editor') ? 'lands as an attribute on <body>' : null],
            ],

            // controls
            'button-implicit-icon' => [
                'title' => 'type="submit" and type="delete" no longer add an icon by themselves',
                'section' => 'Controls', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:button type="submit|delete"> with no icon',
                'kinds' => ['blade'],
                'tag' => ['button', fn (array $a) => in_array(static::value($a, 'type'), ['submit', 'delete'], true) && ! static::has($a, 'icon') && ! static::has($a, 'icon-only') ? 'pass icon="'.(static::value($a, 'type') === 'delete' ? 'delete' : 'check').'" for the old look' : null],
            ],
            'button-variant-undefined' => [
                'title' => 'variant="outline" and "secondary" were never defined: write the default',
                'section' => 'Controls', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:button variant="outline|secondary">',
                'kinds' => ['blade'],
                'tag' => ['button', fn (array $a) => in_array(static::value($a, 'variant'), ['outline', 'secondary'], true) ? 'draws the default (bordered) tier' : null],
            ],
            'button-variant-brand' => [
                'title' => 'facebook, google, linkedin, whatsapp and telegram buttons no longer paint the brand colour',
                'section' => 'Controls', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:button variant="facebook|google|linkedin|whatsapp|telegram">',
                'kinds' => ['blade'],
                'tag' => ['button', fn (array $a) => in_array(static::value($a, 'variant'), ['facebook', 'google', 'linkedin', 'whatsapp', 'telegram'], true) ? 'default tier with the brand icon' : null],
            ],
            'button-variant-primary' => [
                'title' => 'variant="primary" is the accent now (it was a near-black button)',
                'section' => 'Controls', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:button variant="primary">',
                'kinds' => ['blade'],
                'tag' => ['button', fn (array $a) => static::value($a, 'variant') === 'primary' ? 'follows --color-accent' : null],
            ],
            'button-size-prop' => [
                'title' => 'Button sizes come from the control token (the default is the field height)',
                'section' => 'Controls', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:button size="xs|sm|md|lg"> (or a bare sm, xs, lg)',
                'kinds' => ['blade'],
                'tag' => ['button', fn (array $a) => in_array(static::value($a, 'size'), ['xs', 'sm', 'md', 'lg'], true) || static::has($a, 'sm') || static::has($a, 'xs') || static::has($a, 'lg') ? 'sm/xs sit 4/8px under the control height, md/lg over' : null],
            ],

            // data display, text, chart
            'badge-color-name' => [
                'title' => 'Badge colour names map to six tones (amber, indigo... no longer have their own hue)',
                'section' => 'Data display', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:badge color="..."> with a name that is not already a tone',
                'kinds' => ['blade'],
                'tag' => ['badge', function (array $a) {
                    $color = static::value($a, 'color');

                    if ($color === null || str_starts_with(trim($color), '#') || in_array(strtolower(trim($color)), ['neutral', 'info', 'success', 'warning', 'danger', 'accent', 'black'], true)) {
                        return null;
                    }

                    return 'reads as '.static::tone($color);
                }],
            ],
            'chart-color-unknown' => [
                'title' => 'A chart colour that is not a tone, a mapped name or a hex now draws neutral',
                'section' => 'Text, editor, calendar, chart and the docs', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:chart color="..."> with rgb(), hsl() or a name outside the tone set',
                'kinds' => ['blade'],
                'tag' => ['chart', function (array $a) {
                    $color = static::value($a, 'color');

                    if ($color === null || str_starts_with(trim($color), '#')) {
                        return null;
                    }

                    return in_array(strtolower(trim($color)), ['success', 'danger', 'warning', 'info', 'accent', 'neutral', 'green', 'red', 'orange', 'gray', 'grey'], true) ? null : 'resolves to neutral';
                }],
            ],
            'link-colour-override' => [
                'title' => 'A link styled text-sky-* to match the old colour needs the override removed',
                'section' => 'Text, editor, calendar, chart and the docs', 'severity' => static::VISUAL, 'codemod' => 'report',
                'detect' => '<atom:link class="... text-sky-..."> ',
                'kinds' => ['blade'],
                'tag' => ['link', fn (array $a) => preg_match('/(?<![\w-])text-sky-/', (string) static::value($a, 'class')) ? 'the link is the accent ink now' : null],
            ],

            // livewire traits
            'use-atom-component' => [
                'title' => 'AtomComponent is deleted: use only the traits the component needs',
                'section' => 'Livewire traits', 'severity' => static::BREAKS, 'codemod' => 'auto',
                'detect' => 'a class body `use AtomComponent` (token scan of PHP and single-file Blade components); the traits are inferred from the view',
                'kinds' => ['php', 'blade'], 'custom' => 'atomComponentSites',
            ],
            'wire-helper-browser' => [
                'title' => '$wire.toast(), modal() and the other helpers can no longer be called from the browser',
                'section' => 'Livewire traits', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '$wire.<helper>( or wire:*="<helper>(" in JS and Blade markup',
                'kinds' => ['js', 'blade'], 'variant' => 'markup',
                'pattern' => '/\$wire\s*\.\s*(?:'.$helpers.')\s*\(|\bwire:[\w.\-]+\s*=\s*["\']\s*(?:'.$helpers.')\s*\(/',
            ],
            'wire-helper-listener' => [
                'title' => 'An event mapped to toast(), modal()... fails with EventHandlerDoesNotExist',
                'section' => 'Livewire traits', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'a handler named like a helper in a $listeners property, a getListeners() return array or a #[On] method (no other array)',
                'kinds' => ['php', 'blade'], 'custom' => 'wireHelperListeners',
            ],
            'trait-hook-name' => [
                'title' => 'mountAtomComponent / updatingAtomComponent / updatedAtomComponent are gone',
                'section' => 'Livewire traits', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'the old hook names in PHP',
                'kinds' => ['php', 'blade'], 'pattern' => '/\b(?:mount|updating|updated)AtomComponent\b/',
            ],
            'table-state-in-url' => [
                'title' => '`_table` in queryString or #[Url] throws at mount',
                'section' => 'Livewire traits', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '#[Url] on $_table, or _table in a queryString definition',
                'kinds' => ['php', 'blade'],
                'pattern' => '/#\[Url[^\]]*\]\s*(?:public|protected)\s+(?:[\w?|\\\\]+\s+)?\$_table\b|queryString[^;{]*[\'"]_table[\'"]/s',
            ],

            // dates
            'date-use-line' => [
                'title' => 'Add Date::use(Services\Carbon::class) to AppServiceProvider::boot(), or dates change class and string form',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'auto',
                'detect' => 'no Date::use(<atom Carbon>::class) call in a *ServiceProvider.php, a file of app/Providers or bootstrap/*.php',
                'kinds' => [], 'custom' => 'dateUseLine',
            ],
            'date-pretty' => [
                'title' => '->pretty() needs the Date::use line (or carbon($date)->pretty())',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->pretty( in PHP and Blade (a candidate: the receiver is not typed)',
                'kinds' => ['php', 'blade'], 'pattern' => '/->\s*pretty\s*\(/',
            ],
            'date-local' => [
                'title' => '->local() needs the Date::use line',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->local( in PHP and Blade, not on $this (a candidate: the receiver is not typed)',
                'kinds' => ['php', 'blade'], 'pattern' => '/(?<!\$this)->\s*local\s*\(/',
            ],
            'date-recent' => [
                'title' => '->recent() needs the Date::use line',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->recent( in PHP and Blade, not on $this (a candidate: a recent() query scope matches too)',
                'kinds' => ['php', 'blade'], 'pattern' => '/(?<!\$this)->\s*recent\s*\(/',
            ],
            'date-range-string' => [
                'title' => '->toDateRangeString() needs the Date::use line',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->toDateRangeString( in PHP and Blade',
                'kinds' => ['php', 'blade'], 'pattern' => '/->\s*toDateRangeString\s*\(/',
            ],
            'date-printed-bare' => [
                'title' => 'A date printed without ->format() changes string form (ISO with a Z) when Date::use is not added',
                'section' => 'PHP globals: dates and macros', 'severity' => static::REVIEW, 'codemod' => 'report',
                'detect' => '{{ $x->some_at }} / {{ $x->some_date }} with no method call (a heuristic)',
                'kinds' => ['blade'], 'pattern' => '/\{\{\s*\$[\w>\-?]+?->\s*\w+_(?:at|date)\s*\}\}/',
            ],
            'carbon-import' => [
                'title' => 'Jiannius\Atom\Services\Carbon is unchanged, but it is no longer what a model date is (an import is fine; look at its types)',
                'section' => 'PHP globals: dates and macros', 'severity' => static::REVIEW, 'codemod' => 'report',
                'detect' => 'use Jiannius\Atom\Services\Carbon in PHP and Blade',
                'kinds' => ['php', 'blade'], 'pattern' => '/^[ \t]*use\s+\\\\?Jiannius\\\\Atom\\\\Services\\\\Carbon\b[^;]*;/m',
            ],
            'carbon-typed' => [
                'title' => 'A parameter, return type or instanceof on Services\Carbon no longer matches a model date (type to CarbonInterface)',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'Carbon as a type or instanceof in a file that imports Services\Carbon (or names it in full)',
                'kinds' => ['php', 'blade'], 'custom' => 'carbonTyped',
            ],
            'macro-request' => [
                'title' => 'Request::subdomain(), hostWithoutSubdomain() and isLivewireRequest() are removed',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->subdomain(, ->hostWithoutSubdomain(, ->isLivewireRequest( (not on app(\'livewire\'))',
                'kinds' => ['php', 'blade'], 'pattern' => '/->\s*(?:subdomain|hostWithoutSubdomain|isLivewireRequest)\s*\(/',
                'exclude' => fn (string $line) => str_contains($line, "app('livewire')") || str_contains($line, 'app("livewire")') || str_contains($line, 'Livewire::') || str_contains($line, 'app(Livewire'),
            ],
            'macro-str' => [
                'title' => 'Str::dotpath() is removed',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->dotpath( or ::dotpath(',
                'kinds' => ['php', 'blade'], 'pattern' => '/(?:->|::)\s*dotpath\s*\(/',
            ],
            'macro-builder' => [
                'title' => 'Builder::tableHasColumn() is removed (use Schema::hasColumn())',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '->tableHasColumn(',
                'kinds' => ['php', 'blade'], 'pattern' => '/->\s*tableHasColumn\s*\(/',
            ],
            'macro-attribute-bag' => [
                'title' => 'The ComponentAttributeBag macros (size, field, classes, modifier...) are removed',
                'section' => 'PHP globals: dates and macros', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '$attributes->'.implode('|', static::BAG_MACROS).'(',
                'kinds' => ['php', 'blade'], 'pattern' => '/\$attributes\s*->\s*(?:'.$bag.')\s*\(/',
            ],

            // js globals
            'js-currency' => [
                'title' => 'x.currency(...) becomes atom.currency(x, ...)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '.currency( in JS files and in Blade markup outside PHP regions',
                'kinds' => ['js', 'blade'], 'variant' => 'markup', 'pattern' => '/(?<!atom)\.currency\s*\(/',
            ],
            'js-array-helper' => [
                'title' => 'Array helpers (pluck, unique, sum, where, firstWhere, toggle) become atom.arr.*(list, ...)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '.pluck( .unique( .sum( .where( .firstWhere( .toggle( in JS (not this.x(, not classList.toggle)',
                'kinds' => ['js', 'blade'], 'variant' => 'markup',
                'pattern' => '/(?<!atom\.arr)(?<!\bthis)(?<!classList)\.\s*(?:pluck|unique|sum|where|firstWhere|toggle)\s*\(/',
            ],
            'js-string-helper' => [
                'title' => 'String helpers (headline, limit, slug, camel, nl2br, striptags) become atom.str.*(s, ...)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '.headline( .limit( .slug( .camel( .nl2br( .striptags( in JS',
                'kinds' => ['js', 'blade'], 'variant' => 'markup',
                'pattern' => '/(?<!atom\.str)(?<!\bthis)\.\s*(?:headline|limit|slug|camel|nl2br|striptags)\s*\(/',
            ],
            'js-date-helper' => [
                'title' => 'String date helpers (toDateString, toDatetimeString, toTimeString, toDateFormat) become atom.str.*(s)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '.toDateString( .toDatetimeString( .toTimeString( .toDateFormat( in JS (the first and third are also native Date methods)',
                'kinds' => ['js', 'blade'], 'variant' => 'markup',
                'pattern' => '/(?<!atom\.str)\.\s*(?:toDateString|toDatetimeString|toTimeString|toDateFormat)\s*\(/',
                'note' => fn (string $match) => preg_match('/\.\s*('.implode('|', $dateNative).')\s*\(/', $match) ? 'also a native Date method: check the receiver is a string' : null,
            ],
            'js-from-now' => [
                'title' => '.fromNow() is removed with no replacement',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '.fromNow( in JS',
                'kinds' => ['js', 'blade'], 'variant' => 'markup', 'pattern' => '/\.\s*fromNow\s*\(/',
            ],
            'js-empty' => [
                'title' => 'A bare empty(x) in JS becomes atom.empty(x)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'empty( not preceded by . $ > : - or a word, in JS and Blade markup outside PHP regions',
                'kinds' => ['js', 'blade'], 'variant' => 'markup', 'pattern' => '/(?<![\w$.>:\-])empty\s*\(/',
                'exclude' => fn (string $line) => (bool) preg_match('/\bfunction\s+empty\b/', $line),
            ],
            'js-dd' => [
                'title' => 'A bare dd(...) in JS becomes console.log(...)',
                'section' => 'JS globals', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'dd( not preceded by . $ > : - or a word, in JS and Blade markup outside PHP regions',
                'kinds' => ['js', 'blade'], 'variant' => 'markup', 'pattern' => '/(?<![\w$.>:\-])dd\s*\(/',
                'exclude' => fn (string $line) => (bool) preg_match('/\bfunction\s+dd\b/', $line),
            ],

            // the legacy editor
            'editor-cast' => [
                'title' => 'A column still cast as AsEditorContent: switch it and run atom:tiptap-migrate on 3.x, before v4',
                'section' => 'The legacy editor: AsEditorContent and <atom:editor*>', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => 'AsEditorContent on a line that is not a use statement',
                'kinds' => ['php', 'blade'], 'pattern' => '/^(?![ \t]*use\s)[^\n]*AsEditorContent[^\n]*/m',
            ],
            'editor-tag' => [
                'title' => '<atom:editor*> is deleted: rename to <atom:tiptap*>',
                'section' => 'The legacy editor: AsEditorContent and <atom:editor*>', 'severity' => static::BREAKS, 'codemod' => 'auto',
                'detect' => '<atom:editor, <atom:editor.chat, <atom:editor.content, <x-atom::editor...',
                'kinds' => ['blade'],
                'tag' => [['editor', 'editor.chat', 'editor.content'], fn (array $a, string $name) => 'rename to tiptap'.substr($name, 6)],
            ],
            'editor-content-static' => [
                'title' => 'Static prose in <atom:editor.content> is re-parsed and loses its markup: use <div class="text-base">',
                'section' => 'The legacy editor: AsEditorContent and <atom:editor*>', 'severity' => static::BREAKS, 'codemod' => 'report',
                'detect' => '<atom:editor.content> or <atom:tiptap.content> with no content attribute (text in the slot)',
                'kinds' => ['blade'],
                'tag' => [['editor.content', 'tiptap.content'], fn (array $a) => ! static::has($a, 'content') && ! static::has($a, 'value') && ! static::has($a, 'wire:model') ? 'no content attribute: the slot is static prose' : null],
            ],

            // tests
            'test-old-classes' => [
                'title' => 'A test that matches a class or selector the restyle changed needs updating',
                'section' => 'Breaks a test (every restyle section)', 'severity' => static::REVIEW, 'codemod' => 'report',
                'detect' => 'tests/ files naming a changed class (bg-white, rounded-lg, shadow-xs...) or `dialog button`',
                'kinds' => ['php', 'js'], 'scope' => 'tests',
                'pattern' => '/(?<![\w\-:.\/\[])(?:'.implode('|', array_map(fn ($class) => preg_quote($class, '/'), static::TEST_CLASSES)).')(?![\w\-\]])/',
            ],
            'test-heading-weight' => [
                'title' => 'A test that asserts a heading\'s weight needs updating: every heading is font-semibold in v4',
                'section' => 'Breaks a test (every restyle section)', 'severity' => static::REVIEW, 'codemod' => 'report',
                'detect' => 'tests/ lines naming font-semibold or font-medium and a heading (any word containing heading except subheading, <h1>..<h6> or h1..h6 as a selector); a finding a line. A line with the weight but no heading word (`expect($html)->not->toContain(\'font-semibold\')`) is not seen',
                'kinds' => ['php', 'js'], 'scope' => 'tests',
                'pattern' => '/^(?=[^\n]*(?<![\w\-])font-(?:semibold|medium)(?![\w\-]))(?=[^\n]*(?:(?<!sub)heading|(?<![\w\-])h[1-6](?![\w\-])))[^\n]*/mi',
            ],
        ];
    }

    /**
     * Scan a host directory. Reads only.
     *
     * @return array<string, mixed>
     */
    public function scan(string $root): array
    {
        $root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
        $checks = static::checks();
        $findings = array_fill_keys(array_keys($checks), []);
        $tags = [];
        $candidates = [];
        $localScopes = [];
        $scanned = ['app' => 0, 'tests' => 0];
        $published = [];
        $dateUse = null;
        $appProvider = null;
        $this->skipped = [];

        foreach ($this->discover($root) as [$path, $relative, $scope, $kind]) {
            $source = @file_get_contents($path);

            if ($source === false || $source === '') {
                continue;
            }

            // minified code has no lines: a regex over one is slow for nothing
            if (in_array($kind, ['js', 'css', 'config'], true) && strlen($source) > 100000 && strlen($source) / (substr_count($source, "\n") + 1) > static::MINIFIED_LINE) {
                $this->skipped[] = ['path' => $relative, 'reason' => 'looks minified'];

                continue;
            }

            $scanned[$scope]++;
            $prepared = $this->prepare($kind, $source);

            if (str_starts_with($relative, 'resources/views/vendor/atom/')) {
                $published[] = $relative;
            }

            if ($kind === 'php' && $scope === 'app' && str_ends_with($relative, 'ServiceProvider.php')) {
                if (basename($relative) === 'AppServiceProvider.php') {
                    $appProvider ??= $relative;
                }
            }

            // any provider, and any file in app/Providers, may make the call
            if ($kind === 'php' && $scope === 'app' && (str_ends_with($relative, 'ServiceProvider.php') || str_starts_with($relative, 'app/Providers/')) && $this->callsDateUse($prepared['full'])) {
                $dateUse ??= $relative;
            }

            $starts = $this->lineStarts($source);
            $lines = null;

            if ($kind === 'php' || $kind === 'blade') {
                foreach (['Recent', 'Local', 'Pretty'] as $scopeName) {
                    if (str_contains($prepared['full'], 'function scope'.$scopeName.'(')) {
                        $localScopes[strtolower($scopeName)] = true;
                    }
                }
            }

            $add = function (string $id, int $offset, ?string $note = null) use (&$findings, &$lines, $source, $starts, $relative, $scope) {
                $line = $this->lineAt($starts, $offset);
                $lines ??= explode("\n", str_replace("\r\n", "\n", $source));

                $findings[$id][] = array_filter([
                    'file' => $relative,
                    'line' => $line,
                    'text' => mb_substr(trim($lines[$line - 1] ?? ''), 0, 160),
                    'scope' => $scope,
                    'note' => $note,
                ], fn ($value) => $value !== null);
            };

            // Blade tags: counted, and the tag checks read them
            $bladeTags = $kind === 'blade' ? $this->parseTags($prepared['full']) : [];

            foreach ($bladeTags as $tag) {
                $tags[$tag['name']] ??= ['count' => 0, 'files' => [], 'tests' => 0];

                if ($scope === 'tests') {
                    $tags[$tag['name']]['tests']++;
                } else {
                    $tags[$tag['name']]['count']++;
                    $tags[$tag['name']]['files'][$relative] = true;
                }
            }

            foreach ($checks as $id => $check) {
                if (! in_array($kind, $check['kinds'], true) || (($check['scope'] ?? 'all') === 'tests' && $scope !== 'tests')) {
                    continue;
                }

                if (isset($check['tag'])) {
                    [$names, $callback] = $check['tag'];

                    foreach ($bladeTags as $tag) {
                        if (! in_array($tag['name'], (array) $names, true)) {
                            continue;
                        }

                        $note = $callback($tag['attributes'], $tag['name']);

                        if ($note !== null) {
                            $add($id, $tag['offset'], $note);
                        }
                    }

                    continue;
                }

                if (isset($check['custom'])) {
                    $sites = $this->{$check['custom']}($kind, $prepared, $source);

                    foreach ($sites as [$offset, $note]) {
                        $add($id, $offset, $note);
                    }

                    if ($sites && $id === 'use-atom-component') {
                        $candidates[] = compact('path', 'relative', 'scope', 'kind', 'prepared', 'source');
                    }

                    continue;
                }

                $text = $prepared[$check['variant'] ?? 'full'];

                if (! preg_match_all($check['pattern'], $text, $found, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($found[0] as [$match, $offset]) {
                    if (isset($check['exclude'])) {
                        $from = strrpos(substr($text, 0, $offset), "\n");
                        $to = strpos($text, "\n", $offset);
                        $line = substr($text, $from === false ? 0 : $from + 1, $to === false ? null : $to - ($from === false ? 0 : $from + 1));

                        if ($check['exclude']($line)) {
                            continue;
                        }
                    }

                    $add($id, $offset, isset($check['note']) ? $check['note']($match) : null);
                }
            }

            unset($source, $prepared, $lines);
        }

        foreach (['date-pretty' => 'pretty', 'date-local' => 'local', 'date-recent' => 'recent'] as $id => $name) {
            if (! empty($localScopes[$name])) {
                foreach ($findings[$id] as &$finding) {
                    $finding['note'] = 'this app also defines scope'.ucfirst($name).'(): may be the query scope';
                }

                unset($finding);
            }
        }

        // bootstrap/ is not scanned, but a provider can be registered there
        foreach (glob($root.'/bootstrap/*.php') ?: [] as $file) {
            if (! is_link($file) && $this->callsDateUse($this->blankPhpComments((string) @file_get_contents($file)))) {
                $dateUse ??= 'bootstrap/'.basename($file);
            }
        }

        $dateFeaturesUsed = $findings['date-pretty'] || $findings['date-local'] || $findings['date-recent'] || $findings['date-range-string'] || $findings['carbon-import'];

        if ($dateUse === null && is_dir($root.'/app')) {
            $findings['date-use-line'][] = [
                'file' => $appProvider ?? '',
                'line' => $appProvider ? 1 : 0,
                'text' => $appProvider ? 'no Date::use( call found' : 'no AppServiceProvider found',
                'scope' => 'app',
                'note' => ($dateFeaturesUsed ? 'the app uses date methods or the Carbon class' : 'dates change class, mutability and string form without it')
                    .($appProvider ? '' : '; add the call to the boot() of a service provider'),
            ];
        }

        // with the Date::use line the class, the mutability and the string form are the ones atom had
        if ($dateUse !== null) {
            foreach (static::COVERED_BY_DATE_USE as $id) {
                foreach ($findings[$id] as &$finding) {
                    $finding['note'] = trim(($finding['note'] ?? '').'; covered by the Date::use line ('.$dateUse.')', '; ');
                }

                unset($finding);
            }
        }

        $components = $this->components($root, $candidates);
        $result = [
            'root' => basename($root),
            'guide' => static::GUIDE,
            'atom_version' => $this->installedVersion(),
            'scanned' => $scanned,
            'checks' => [],
            'components' => $components,
            'tags' => $this->tagReport($tags),
            'published_views' => $published,
            'skipped' => $this->skipped,
            'date_use' => $dateUse,
            'guideline' => $this->guideline($root),
        ];

        foreach ($checks as $id => $check) {
            $list = $findings[$id];
            $result['checks'][$id] = [
                'id' => $id,
                'title' => $check['title'],
                'section' => $check['section'],
                'severity' => $dateUse !== null && in_array($id, static::COVERED_BY_DATE_USE, true) ? static::REVIEW : $check['severity'],
                'codemod' => $check['codemod'],
                'detect' => $check['detect'],
                'count' => count(array_filter($list, fn ($finding) => $finding['scope'] === 'app')),
                'tests_count' => count(array_filter($list, fn ($finding) => $finding['scope'] === 'tests')),
                'findings' => $list,
            ];
        }

        $result['summary'] = $this->summarise($result);

        return $result;
    }

    /**
     * The installed atom version, when Composer can say
     */
    protected function installedVersion(): ?string
    {
        try {
            return class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('jiannius/atom')
                ? \Composer\InstalledVersions::getPrettyVersion('jiannius/atom')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Totals by severity and by what the codemod will do
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function summarise(array $result): array
    {
        $summary = [
            'checks' => count($result['checks']),
            'checks_with_findings' => 0,
            'findings' => 0,
            'tests_findings' => 0,
            'by_severity' => [static::BREAKS => 0, static::VISUAL => 0, static::REVIEW => 0],
            'by_codemod' => ['auto' => 0, 'report' => 0],
            'components' => count($result['components']),
            'tags' => array_sum(array_column($result['tags'], 'count')),
            'editor_casts' => $result['checks']['editor-cast']['count'],
            'editor_tags' => $result['checks']['editor-tag']['count'],
        ];

        foreach ($result['checks'] as $check) {
            $summary['findings'] += $check['count'];
            $summary['tests_findings'] += $check['tests_count'];
            $summary['by_severity'][$check['severity']] += $check['count'];
            $summary['by_codemod'][$check['codemod']] += $check['count'];
            $summary['checks_with_findings'] += $check['count'] || $check['tests_count'] ? 1 : 0;
        }

        return $summary;
    }

    /**
     * The files to read: [absolute path, relative path, 'app'|'tests', kind]
     *
     * @return \Generator<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function discover(string $root): \Generator
    {
        $seen = [];
        $roots = [];

        foreach (static::DIRECTORIES as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.$directory;

            if (is_link($path)) {
                $this->skipped[] = ['path' => $directory, 'reason' => 'symlink'];

                continue;
            }

            $stat = is_dir($path) ? @stat($path) : false;

            // modules and Modules are one directory on a case-insensitive disk: tell them apart by inode
            if ($stat !== false && ! isset($seen[$stat['dev'].':'.$stat['ino']])) {
                $seen[$stat['dev'].':'.$stat['ino']] = true;
                $roots[] = $path;
            }
        }

        foreach ($roots as $path) {
            $files = $this->walk($root, $path);
            sort($files);

            foreach ($files as $file) {
                $kind = $this->kindOf($file);

                if ($kind === null) {
                    continue;
                }

                $relative = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR));

                if (filesize($file) > static::MAX_BYTES) {
                    $this->skipped[] = ['path' => $relative, 'reason' => 'over '.(static::MAX_BYTES / 1048576).' MB'];

                    continue;
                }

                yield [$file, $relative, str_starts_with($relative, 'tests/') ? 'tests' : 'app', $kind];
            }
        }

        foreach (glob($root.'/vite.config.*') ?: [] as $file) {
            if (is_link($file)) {
                $this->skipped[] = ['path' => basename($file), 'reason' => 'symlink'];

                continue;
            }

            yield [$file, basename($file), 'app', 'config'];
        }
    }

    /**
     * Every regular file under a directory. A symlink (file or directory) is not
     * followed, a directory that cannot be read is recorded, and Composer's
     * `vendor/` is skipped (see isComposerVendor()), as is `node_modules/` and
     * `.git/` at any depth. Only atom's own published views are read from
     * `resources/views/vendor/`.
     *
     * @return array<int, string>
     */
    protected function walk(string $root, string $directory): array
    {
        $relativeDirectory = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($directory, strlen($root)), DIRECTORY_SEPARATOR));
        $entries = @scandir($directory);

        if ($entries === false) {
            $this->skipped[] = ['path' => $relativeDirectory, 'reason' => 'unreadable'];

            return [];
        }

        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $relative = ($relativeDirectory === '' ? '' : $relativeDirectory.'/').$name;

            if (is_link($path)) {
                $this->skipped[] = ['path' => $relative, 'reason' => 'symlink'];

                continue;
            }

            if (is_dir($path)) {
                // only atom's own published views live under resources/views/vendor that are read
                $publishedViews = $relative === 'resources/views/vendor';

                if ((! $publishedViews && $this->isSkippedDirectory($relative, $name, $path)) || in_array($relative, static::SKIP_PATHS, true)) {
                    continue;
                }

                if ($relativeDirectory === 'resources/views/vendor' && $name !== 'atom') {
                    continue;
                }

                $files = array_merge($files, $this->walk($root, $path));
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Whether a directory is left unread: node_modules and .git at any depth,
     * and Composer's vendor
     */
    protected function isSkippedDirectory(string $relative, string $name, string $path): bool
    {
        if (in_array($name, static::SKIP_DIRECTORIES, true)) {
            return true;
        }

        // the root vendor/, or a nested one that holds a Composer install (modules/X/vendor); any other is the host's
        return $name === static::VENDOR_DIRECTORY
            && ($relative === static::VENDOR_DIRECTORY || is_file($path.DIRECTORY_SEPARATOR.'autoload.php') || is_dir($path.DIRECTORY_SEPARATOR.'composer'));
    }

    /**
     * What a file is: blade, php, js, css, or null for something not read
     */
    protected function kindOf(string $file): ?string
    {
        $name = strtolower(basename($file));

        return match (true) {
            str_ends_with($name, '.blade.php') => 'blade',
            str_ends_with($name, '.php') => 'php',
            str_ends_with($name, '.min.js'), str_ends_with($name, '.min.css') => null,
            (bool) preg_match('/\.(?:js|mjs|cjs|ts|jsx|tsx|vue)$/', $name) => 'js',
            str_ends_with($name, '.css') => 'css',
            default => null,
        };
    }

    /**
     * The text a check reads. `full` is the source with comments blanked (same
     * length, so offsets and line numbers hold); `markup` is a Blade file with
     * every PHP region (`{{ }}`, `{!! !!}`, `@php`, `<?php`, a directive's
     * arguments) blanked too, which is where its JavaScript lives.
     *
     * @return array{full: string, markup: string}
     */
    protected function prepare(string $kind, string $source): array
    {
        if ($kind === 'php') {
            $full = $this->blankPhpComments($source);

            return ['full' => $full, 'markup' => $full];
        }

        if ($kind === 'js' || $kind === 'config') {
            $full = $this->blankJsComments($source);

            return ['full' => $full, 'markup' => $full];
        }

        if ($kind === 'css') {
            $full = $this->blank($source, '/\/\*.*?\*\//s');

            return ['full' => $full, 'markup' => $full];
        }

        // blade: PHP comments inside <?php regions, then Blade and HTML comments, then PHP comments in @php and {{ }}
        $full = $this->blankPhpComments($source);
        $full = $this->blank($full, '/\{\{--.*?--\}\}|<!--.*?-->/s');
        $full = preg_replace_callback('/@php\b(?!\s*\()(.*?)@endphp|\{\{(?!--)(.*?)\}\}|\{!!(.*?)!!\}/s', function (array $m) {
            $inner = $m[1] ?? '';
            $inner = ($m[2] ?? '') !== '' ? $m[2] : $inner;
            $inner = ($m[3] ?? '') !== '' ? $m[3] : $inner;

            if ($inner === '' || (! str_contains($inner, '//') && ! str_contains($inner, '/*') && ! str_contains($inner, '#'))) {
                return $m[0];
            }

            $blanked = substr($this->blankPhpComments('<?php '.$inner), 6);

            return str_replace($inner, $blanked, $m[0]);
        }, $full) ?? $full;

        $markup = $this->maskBladePhp($full);

        // a <script> block is JavaScript: its comments are not code
        $markup = preg_replace_callback('/(<script\b[^>]*>)(.*?)(<\/script>)/is', fn (array $m) => $m[1].$this->blankJsComments($m[2]).$m[3], $markup) ?? $markup;

        return ['full' => $full, 'markup' => $markup];
    }

    /**
     * Replace every non-newline byte matching $pattern with a space
     */
    protected function blank(string $text, string $pattern): string
    {
        return preg_replace_callback($pattern, fn (array $m) => preg_replace('/[^\n]/', ' ', $m[0]), $text) ?? $text;
    }

    /**
     * PHP comments blanked, by tokens, so a `//` in a string or a URL is safe.
     * HTML outside the PHP tags is left as is.
     */
    protected function blankPhpComments(string $code): string
    {
        if (! str_contains($code, '//') && ! str_contains($code, '/*') && ! str_contains($code, '#')) {
            return $code;
        }

        try {
            $out = '';

            foreach (token_get_all($code) as $token) {
                if (is_array($token)) {
                    $out .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                        ? preg_replace('/[^\n]/', ' ', $token[1])
                        : $token[1];
                } else {
                    $out .= $token;
                }
            }

            return strlen($out) === strlen($code) ? $out : $code;
        } catch (Throwable) {
            return $code;
        }
    }

    /**
     * JavaScript comments blanked by a small scanner that knows strings and
     * template literals (a `//` after a `:` is a URL, not a comment)
     */
    protected function blankJsComments(string $js): string
    {
        $n = strlen($js);
        $out = $js;
        $i = 0;
        $last = '';

        while ($i < $n) {
            $c = $js[$i];
            $next = $js[$i + 1] ?? '';

            if ($c === '"' || $c === "'" || $c === '`') {
                $quote = $c;
                $i++;

                while ($i < $n && $js[$i] !== $quote) {
                    $i += $js[$i] === '\\' ? 2 : 1;

                    if ($quote !== '`' && ($js[$i] ?? '') === "\n") {
                        break;
                    }
                }

                $i++;
                $last = $quote;

                continue;
            }

            // a regex literal may hold a quote: skip it whole, so the quote does not open a string
            if ($c === '/' && $next !== '/' && $next !== '*' && ($last === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $last) || preg_match('/\b(?:return|typeof|case|in|of|void)\s*$/', substr($js, max(0, $i - 8), min(8, $i))))) {
                $j = $i + 1;
                $class = false;

                while ($j < $n && $js[$j] !== "\n") {
                    if ($js[$j] === '\\') {
                        $j += 2;

                        continue;
                    }

                    if ($js[$j] === '[') {
                        $class = true;
                    } elseif ($js[$j] === ']') {
                        $class = false;
                    } elseif ($js[$j] === '/' && ! $class) {
                        break;
                    }

                    $j++;
                }

                if (($js[$j] ?? '') === '/') {
                    $i = $j + 1;
                    $last = '/';

                    continue;
                }
            }

            if ($c === '/' && $next === '/' && ($js[$i - 1] ?? '') !== ':') {
                $end = strpos($js, "\n", $i);
                $end = $end === false ? $n : $end;

                for ($j = $i; $j < $end; $j++) {
                    $out[$j] = ' ';
                }

                $i = $end;

                continue;
            }

            if ($c === '/' && $next === '*') {
                $end = strpos($js, '*/', $i + 2);
                $end = $end === false ? $n : $end + 2;

                for ($j = $i; $j < $end; $j++) {
                    if ($js[$j] !== "\n") {
                        $out[$j] = ' ';
                    }
                }

                $i = $end;

                continue;
            }

            if (! ctype_space($c)) {
                $last = $c;
            }

            $i++;
        }

        return $out;
    }

    /**
     * Blank the PHP of a Blade file: `<?php ... ?>`, `@php ... @endphp`, `{{ }}`,
     * `{!! !!}` and a directive's parenthesised arguments (`@if(...)`)
     */
    protected function maskBladePhp(string $blade): string
    {
        $blade = $this->blank($blade, '/<\?(?:php|=).*?(?:\?>|\z)/s');
        $blade = $this->blank($blade, '/@php\b(?!\s*\().*?(?:@endphp|\z)|\{\{.*?\}\}|\{!!.*?!!\}/s');

        $out = $blade;
        $n = strlen($blade);
        $offset = 0;

        while (preg_match('/(?<![\w@])@\w+[ \t]*\(/', $blade, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]) - 1;
            $close = $this->matchingParenthesis($blade, $open);
            $end = $close === null ? min($n, strpos($blade, "\n", $open) ?: $n) : $close + 1;

            for ($j = $start; $j < $end; $j++) {
                if ($out[$j] !== "\n") {
                    $out[$j] = ' ';
                }
            }

            $offset = max($end, $start + 1);
        }

        return $this->maskBoundAttributes($out);
    }

    /**
     * A bound attribute on a component tag (`:items="$rows"`) is a PHP expression, not JavaScript
     */
    protected function maskBoundAttributes(string $text): string
    {
        if (! preg_match_all('/<(?:atom:|x-)[a-z][\w.\-:]*/i', $text, $tags, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        foreach ($tags[0] as [$tag, $at]) {
            $from = $at + strlen($tag);
            $attributes = $this->readAttributes($text, $from);

            if (! preg_match_all('/(?<=\s):[\w.\-]+\s*=\s*("[^"]*"|\'[^\']*\')/', $attributes, $bound, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($bound[1] as [$value, $valueAt]) {
                for ($j = $from + $valueAt, $end = $j + strlen($value); $j < $end; $j++) {
                    if ($text[$j] !== "\n") {
                        $text[$j] = ' ';
                    }
                }
            }
        }

        return $text;
    }

    /**
     * The index of the parenthesis that closes the one at $open, strings skipped
     */
    protected function matchingParenthesis(string $text, int $open): ?int
    {
        $depth = 0;
        $n = strlen($text);

        for ($i = $open; $i < $n; $i++) {
            $c = $text[$i];

            if ($c === '"' || $c === "'") {
                $i++;

                while ($i < $n && $text[$i] !== $c) {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }

                continue;
            }

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Byte offsets at which each line starts
     *
     * @return array<int, int>
     */
    protected function lineStarts(string $source): array
    {
        $starts = [0];
        $offset = 0;

        while (($position = strpos($source, "\n", $offset)) !== false) {
            $starts[] = $position + 1;
            $offset = $position + 1;
        }

        return $starts;
    }

    /**
     * The 1-based line an offset is on
     *
     * @param  array<int, int>  $starts
     */
    protected function lineAt(array $starts, int $offset): int
    {
        $low = 0;
        $high = count($starts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($starts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low + 1;
    }

    /**
     * Every `<atom:name ...>` and `<x-atom::name ...>` in a Blade file, with its attributes
     *
     * @return array<int, array{name: string, offset: int, attributes: array<string, string|true>}>
     */
    protected function parseTags(string $blade): array
    {
        if (! preg_match_all('/<(?:x-atom::|atom:)([a-z][a-z0-9\-]*(?:\.[a-z0-9\-]+)*)/i', $blade, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tags = [];

        foreach ($found[1] as $i => [$name, $at]) {
            $offset = $found[0][$i][1];

            $tags[] = [
                'name' => strtolower($name),
                'offset' => $offset,
                'attributes' => $this->parseAttributes($this->readAttributes($blade, $at + strlen($name))),
            ];
        }

        return $tags;
    }

    /**
     * The text between a tag name and the `>` that ends the tag, quotes and `{{ }}` respected
     */
    protected function readAttributes(string $text, int $from): string
    {
        $n = min(strlen($text), $from + 6000);
        $quote = null;
        $i = $from;

        while ($i < $n) {
            $c = $text[$i];

            if ($c === '{' && ($text[$i + 1] ?? '') === '{') {
                $end = strpos($text, '}}', $i);
                $i = $end === false ? $n : $end + 2;

                continue;
            }

            if ($quote !== null) {
                $quote = $c === $quote ? null : $quote;
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '>') {
                break;
            }

            $i++;
        }

        return substr($text, $from, $i - $from);
    }

    /**
     * Name to value (true for a bare attribute). A `{{ }}` anywhere in a value
     * leaves the value as is, so it is never mistaken for a static one.
     *
     * @return array<string, string|true>
     */
    protected function parseAttributes(string $text): array
    {
        $attributes = [];
        $n = strlen($text);
        $i = 0;

        while ($i < $n) {
            while ($i < $n && (ctype_space($text[$i]) || $text[$i] === '/')) {
                $i++;
            }

            if ($i >= $n) {
                break;
            }

            if ($text[$i] === '{' && ($text[$i + 1] ?? '') === '{') {
                $end = strpos($text, '}}', $i);
                $i = $end === false ? $n : $end + 2;

                continue;
            }

            $start = $i;

            while ($i < $n && ! ctype_space($text[$i]) && ! in_array($text[$i], ['=', '/', '"', "'"], true)) {
                $i++;
            }

            $name = substr($text, $start, $i - $start);

            if ($name === '') {
                $i++;

                continue;
            }

            while ($i < $n && ctype_space($text[$i])) {
                $i++;
            }

            if (($text[$i] ?? '') !== '=') {
                $attributes[$name] = true;

                continue;
            }

            $i++;

            while ($i < $n && ctype_space($text[$i])) {
                $i++;
            }

            $quote = $text[$i] ?? '';

            if ($quote === '"' || $quote === "'") {
                $i++;
                $from = $i;

                while ($i < $n && $text[$i] !== $quote) {
                    if ($text[$i] === '{' && ($text[$i + 1] ?? '') === '{') {
                        $end = strpos($text, '}}', $i);
                        $i = $end === false ? $n : $end + 2;

                        continue;
                    }

                    $i++;
                }

                $attributes[$name] = substr($text, $from, $i - $from);
                $i++;

                continue;
            }

            $from = $i;

            while ($i < $n && ! ctype_space($text[$i])) {
                $i++;
            }

            $attributes[$name] = substr($text, $from, $i - $from);
        }

        return $attributes;
    }

    /**
     * Whether a tag has an attribute, static or bound
     *
     * @param  array<string, string|true>  $attributes
     */
    public static function has(array $attributes, string $name): bool
    {
        return isset($attributes[$name]) || isset($attributes[':'.$name]) || isset($attributes['x-bind:'.$name]);
    }

    /**
     * The value of a static attribute (null when it is absent, bound or has Blade in it)
     *
     * @param  array<string, string|true>  $attributes
     */
    public static function value(array $attributes, string $name): ?string
    {
        $value = $attributes[$name] ?? null;

        return is_string($value) && ! str_contains($value, '{{') && ! str_contains($value, '{!!') ? strtolower(trim($value)) : null;
    }

    /**
     * The tone a badge colour name reads as
     */
    public static function tone(string $name): string
    {
        $name = strtolower(trim($name));

        foreach (static::TONES as $tone => $names) {
            if (in_array($name, $names, true)) {
                return $tone;
            }
        }

        return 'neutral';
    }

    /**
     * Tag name to count, files and the UPGRADE.md section that restyled it, most used first
     *
     * @param  array<string, array<string, mixed>>  $tags
     * @return array<string, array{count: int, files: int, tests: int, restyled: string|null}>
     */
    protected function tagReport(array $tags): array
    {
        $report = [];

        foreach ($tags as $name => $data) {
            $first = explode('.', $name)[0];
            $section = null;

            foreach (static::RESTYLED as $heading => $names) {
                if (in_array($first, $names, true)) {
                    $section = $heading;
                }
            }

            $report[$name] = [
                'count' => $data['count'],
                'files' => count($data['files']),
                'tests' => $data['tests'] ?? 0,
                'restyled' => $section,
            ];
        }

        uksort($report, fn ($a, $b) => [$report[$b]['count'], $a] <=> [$report[$a]['count'], $b]);

        return $report;
    }

    /**
     * Handlers named like a helper in the places Livewire reads event listeners
     * from: a `$listeners` property, the array a `getListeners()` returns, and
     * the method under an `#[On(...)]` attribute. Any other array is not one.
     *
     * @param  array{full: string, markup: string}  $prepared
     * @return array<int, array{0: int, 1: string|null}>
     */
    protected function wireHelperListeners(string $kind, array $prepared, string $source): array
    {
        $text = $prepared['full'];
        $found = [];
        $arrays = [];

        // $listeners = [...] and $listeners = array(...)
        if (preg_match_all('/\$listeners\s*=\s*(?:\[|array\s*\()/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$match, $at]) {
                $arrays[] = $at + strlen($match) - 1;
            }
        }

        // every array literal in a return of getListeners(): [...], array(...), array_merge(parent::getListeners(), [...]), $l + [...]
        if (preg_match_all('/function\s+getListeners\s*\([^)]*\)[^{;]*\{/', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$match, $at]) {
                $open = $at + strlen($match) - 1;
                $close = $this->matchingDelimiter($text, $open) ?? strlen($text);

                if (preg_match_all('/\breturn\b/', substr($text, $open, $close - $open), $returns, PREG_OFFSET_CAPTURE)) {
                    foreach ($returns[0] as [$return, $rat]) {
                        $from = $open + $rat + strlen($return);
                        $end = $this->statementEnd($text, $from, $close);

                        foreach ($this->arrayLiterals($text, $from, $end) as $literal) {
                            $arrays[] = $literal;
                        }
                    }
                }
            }
        }

        foreach (array_unique($arrays) as $open) {
            $close = $this->matchingDelimiter($text, $open) ?? strlen($text);
            $inner = substr($text, $open, $close - $open + 1);

            if (! preg_match_all('/([\'"])((?:\\\\.|(?!\1).)*)\1/s', $inner, $strings, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($strings as $string) {
                $start = $string[0][1];
                $end = $start + strlen($string[0][0]);
                $before = rtrim(substr($inner, 0, $start));
                $after = ltrim(substr($inner, $end));

                if (str_starts_with($after, '=>')) {
                    continue; // an event name
                }

                $isValue = str_ends_with($before, '=>');
                $isListItem = (str_ends_with($before, '[') || str_ends_with($before, ',') || str_ends_with($before, '(')) && ($after === '' || $after[0] === ',' || $after[0] === ']' || $after[0] === ')');

                if (($isValue || $isListItem) && in_array($string[2][0], static::HELPERS, true)) {
                    $found[] = $open + $start;
                }
            }
        }

        // #[On(...)] on a method named like a helper, alone or stacked with other attributes
        $position = 0;

        while (preg_match('/#\[/', $text, $m, PREG_OFFSET_CAPTURE, $position)) {
            $groups = [];
            $cursor = $m[0][1];

            while (substr($text, $cursor, 2) === '#[') {
                $close = $this->matchingDelimiter($text, $cursor + 1);

                if ($close === null) {
                    break;
                }

                $groups[] = [$cursor, substr($text, $cursor, $close - $cursor + 1)];
                $cursor = $close + 1;

                while ($cursor < strlen($text) && ctype_space($text[$cursor])) {
                    $cursor++;
                }
            }

            $position = max($cursor, $m[0][1] + 2);

            if (! preg_match('/\G(?:(?:public|protected|private|final|static|abstract)\s+)*function\s+&?\s*(?:'.implode('|', static::HELPERS).')\s*\(/', $text, $method, 0, $cursor)) {
                continue;
            }

            foreach ($groups as [$at, $group]) {
                if (preg_match('/(?<![\w])On\s*\(/', $group)) {
                    $found[] = $at;

                    break;
                }
            }
        }

        // one finding a line
        $lines = [];

        foreach ($found as $offset) {
            $lines[substr_count($text, "\n", 0, $offset)] ??= $offset;
        }

        ksort($lines);

        return array_map(fn ($offset) => [$offset, null], array_values($lines));
    }

    /**
     * Where the statement that starts at $from ends: the first `;` outside any bracket
     */
    protected function statementEnd(string $text, int $from, int $limit): int
    {
        $depth = 0;

        for ($i = $from; $i < $limit; $i++) {
            $c = $text[$i];

            if ($c === '"' || $c === "'") {
                $i++;

                while ($i < $limit && $text[$i] !== $c) {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }

                continue;
            }

            if (str_contains('([{', $c)) {
                $depth++;
            } elseif (str_contains(')]}', $c)) {
                $depth--;
            } elseif ($c === ';' && $depth <= 0) {
                return $i;
            }
        }

        return $limit;
    }

    /**
     * The offsets of the array literals (`[` or `array(`) in a stretch of code, not
     * the ones nested in another or an index (`$a[0]`)
     *
     * @return array<int, int>
     */
    protected function arrayLiterals(string $text, int $from, int $to): array
    {
        $literals = [];

        for ($i = $from; $i < $to; $i++) {
            $c = $text[$i];

            if ($c === '"' || $c === "'") {
                $i++;

                while ($i < $to && $text[$i] !== $c) {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }

                continue;
            }

            $isLiteral = false;

            if ($c === '[') {
                $before = rtrim(substr($text, $from, $i - $from));
                $isLiteral = $before === '' || ! preg_match('/[\w\]\)\}]$/', $before) || preg_match('/\breturn$/', $before);
            } elseif ($c === '(' && preg_match('/\barray\s*$/i', substr($text, max($from, $i - 12), $i - max($from, $i - 12)))) {
                $isLiteral = true;
            }

            if ($isLiteral) {
                $literals[] = $i;
                $i = $this->matchingDelimiter($text, $i) ?? $to;
            }
        }

        return $literals;
    }

    /**
     * The index of the bracket that closes the one at $open, strings skipped
     */
    protected function matchingDelimiter(string $text, int $open): ?int
    {
        $close = ['(' => ')', '[' => ']', '{' => '}'][$text[$open]] ?? null;

        if ($close === null) {
            return null;
        }

        $depth = 0;
        $n = strlen($text);

        for ($i = $open; $i < $n; $i++) {
            $c = $text[$i];

            if ($c === '"' || $c === "'") {
                $i++;

                while ($i < $n && $text[$i] !== $c) {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }

                continue;
            }

            if ($c === $text[$open]) {
                $depth++;
            } elseif ($c === $close && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The class-body `use AtomComponent` sites of a file, as [offset, note]. A
     * token scan, so a comment or a `use` import is not one; a `use` inside a
     * class (or an anonymous class, as a single-file Livewire component has)
     * that names a trait ending in AtomComponent is.
     *
     * @param  array{full: string, markup: string}  $prepared
     * @return array<int, array{0: int, 1: string|null}>
     */
    protected function atomComponentSites(string $kind, array $prepared, string $source): array
    {
        if (! str_contains($source, 'AtomComponent')) {
            return [];
        }

        try {
            $tokens = token_get_all($source);
        } catch (Throwable) {
            return [];
        }

        $sites = [];
        $aliases = ['AtomComponent'];
        $depth = 0;
        $offset = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if ($token === '{' || (is_array($token) && $token[0] === T_CURLY_OPEN) || (is_array($token) && $token[0] === T_DOLLAR_OPEN_CURLY_BRACES)) {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            } elseif (is_array($token) && $token[0] === T_USE) {
                $names = '';

                for ($j = $i + 1; $j < $count && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
                    $names .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }

                if ($depth === 0) {
                    // `use ...\AtomComponent as Alias;` makes Alias the trait's name in this file
                    if (preg_match('/AtomComponent\s+as\s+(\w+)/', $names, $alias)) {
                        $aliases[] = $alias[1];
                    }
                } elseif (preg_match('/(?:^|[\s,\\\\])(?:'.implode('|', array_map('preg_quote', $aliases)).')\s*(?:,|$)/', trim($names).',')) {
                    // a closure's `use (` follows a parenthesis and is not a trait
                    $sites[] = [$offset, null];
                }
            }

            $offset += strlen($text);
        }

        return $sites;
    }

    /**
     * Services\Carbon used as a type or in instanceof, in a file that names it
     *
     * @param  array{full: string, markup: string}  $prepared
     * @return array<int, array{0: int, 1: string|null}>
     */
    protected function carbonTyped(string $kind, array $prepared, string $source): array
    {
        $text = $prepared['full'];

        if (! str_contains($text, 'Jiannius\\Atom\\Services\\Carbon')) {
            return [];
        }

        $found = [];
        $pattern = '/(?:instanceof\s+|\?|:\s*|\|\s*|[(,]\s*|(?:public|protected|private|readonly|static)\s+)(?:\\\\?Jiannius\\\\Atom\\\\Services\\\\)?Carbon\b(?!::|\\\\)/';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$match, $offset]) {
                // `use Jiannius\Atom\Services\Carbon;` itself starts with `use`, which is not in the pattern
                $found[] = [$offset, null];
            }
        }

        return $found;
    }

    /**
     * Whether (comment-free) PHP calls Date::use() with atom's Carbon: its
     * `Jiannius\Atom\Services\Carbon::class` in full, or `Carbon::class` (or an
     * alias) imported from that namespace. `Date::use(CarbonImmutable::class)` is
     * a different date class and does not give back pretty(), local() and the rest.
     */
    protected function callsDateUse(string $php): bool
    {
        if (! preg_match_all('/\bDate\s*::\s*use\s*\(/', $php, $calls, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $names = [];

        if (preg_match_all('/^[ \t]*use\s+\\\\?Jiannius\\\\Atom\\\\Services\\\\Carbon(?:\s+as\s+(\w+))?\s*;/m', $php, $imports, PREG_SET_ORDER)) {
            foreach ($imports as $import) {
                $names[] = $import[1] ?? 'Carbon';
            }
        }

        foreach ($calls[0] as [$call, $at]) {
            $open = $at + strlen($call) - 1;
            $close = $this->matchingDelimiter($php, $open);
            $argument = preg_replace('/\s+/', '', substr($php, $open + 1, ($close ?? $open + 200) - $open - 1));

            if (preg_match('/^\\\\?Jiannius\\\\Atom\\\\Services\\\\Carbon::class$/', $argument)) {
                return true;
            }

            foreach ($names as $name) {
                if ($argument === $name.'::class') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Each component that uses AtomComponent, with the traits its markup and
     * methods need. A component is a class file or a single-file Blade component;
     * its markup is its own file, or the views its class renders, followed one
     * level into partials and anonymous components.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function components(string $root, array $candidates): array
    {
        $components = [];
        $viewRoots = $this->viewRoots($root);

        foreach ($candidates as $candidate) {
            $text = $candidate['prepared']['full'];
            $sites = $this->atomComponentSites($candidate['kind'], $candidate['prepared'], $candidate['source']);
            $starts = $this->lineStarts($candidate['source']);
            $views = [];
            $markup = $candidate['kind'] === 'blade' ? $text : '';
            $notes = [];

            if ($candidate['kind'] === 'php') {
                foreach ($this->renderedViews($text) as $name) {
                    $path = $this->resolveView($viewRoots, $name);

                    if ($path === null) {
                        $notes[] = 'view '.$name.' not found: traits inferred from the class only';

                        continue;
                    }

                    $views[] = $path;
                }

                if (! $views && ! $notes) {
                    $notes[] = 'no view() call found: traits inferred from the class only';
                }
            }

            $partials = [];

            foreach ($views as $view) {
                $markup .= "\n".$this->prepare('blade', (string) file_get_contents($view))['full'];
            }

            foreach ($this->partialsOf($viewRoots, $markup, 2) as $partial) {
                $partials[] = ltrim(substr($partial['path'], strlen($root)), '/');
                $markup .= "\n".$partial['text'];
            }

            $evidence = $this->traitEvidence($text, $markup, $candidate['kind']);
            $present = $this->traitsPresent($text);

            foreach ($sites as [$offset]) {
                $needs = array_keys($evidence);
                $components[] = [
                    'file' => $candidate['relative'],
                    'line' => $this->lineAt($starts, $offset),
                    'scope' => $candidate['scope'],
                    'traits' => $this->orderTraits($needs),
                    'also_present' => $present,
                    'evidence' => $evidence,
                    'partials' => $partials,
                    'notes' => $notes,
                ];
            }
        }

        usort($components, fn ($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

        return $components;
    }

    /**
     * The traits already in the class body besides AtomComponent
     *
     * @return array<int, string>
     */
    protected function traitsPresent(string $text): array
    {
        $present = [];

        foreach (['WithPagination', 'WithFileUploads', 'WithUrlFilters'] as $trait) {
            if (preg_match('/^\s*use\s+(?:[\w\\\\]+,\s*)*(?:\\\\?[\w\\\\]*\\\\)?'.$trait.'\b[^;]*;/m', $text) && preg_match('/\b'.$trait.'\b/', $text)) {
                $present[] = $trait;
            }
        }

        return $present;
    }

    /**
     * The traits a component needs, each with the reason (one example), from its
     * markup and its methods
     *
     * @return array<string, string>
     */
    protected function traitEvidence(string $php, string $markup, string $kind): array
    {
        $evidence = [];
        $tags = $this->parseTags($markup);

        $tagged = fn (array $names, ?callable $when = null) => (bool) array_filter($tags, fn ($tag) => in_array($tag['name'], $names, true) && (! $when || $when($tag['attributes'])));

        if ($tagged(['table', 'table.column', 'table.cell', 'table.actions', 'table.toolbar', 'table.views', 'table.views.item']) || preg_match('/->\s*toTable\s*\(|\$_table\b|\$this->_table\b/', $php)) {
            $evidence['WithTable'] = $tagged(['table']) ? '<atom:table> in the view' : 'toTable() or $_table';
        }

        if (preg_match('/function\s+breadcrumbs\s*\(\s*[^)\s]/', $php)) {
            $evidence['WithBreadcrumbs'] = 'defines breadcrumbs($b)';
        }

        if ($tagged(['tiptap', 'editor'])) {
            $evidence['WithEditor'] = '<atom:tiptap> in the view';
        }

        if ($tagged(['form'], fn ($a) => static::has($a, 'recaptcha')) || preg_match('/\bverifyRecaptcha\s*\(/', $php)) {
            $evidence['WithRecaptcha'] = $tagged(['form']) ? '<atom:form recaptcha>' : 'verifyRecaptcha()';
        }

        if (preg_match('/->\s*(?:paginate|simplePaginate|cursorPaginate|toPage|toTable)\s*\(|\b(?:resetPage|setPage|gotoPage|nextPage|previousPage)\s*\(/', $php) || $tagged(['table', 'pagination']) || str_contains($markup, '->links(')) {
            $evidence['WithPagination'] = 'paginates (paginate(), toTable(), <atom:table> or <atom:pagination>)';
        }

        if (! isset($evidence['WithEditor']) && ($tagged(['uploader', 'tiptap.chat', 'editor.chat']) || $tagged(['input'], fn ($a) => static::value($a, 'type') === 'file') || preg_match('/TemporaryUploadedFile|\bWithFileUploads\b/', $php) || preg_match('/<input\b[^>]*\btype\s*=\s*["\']file["\']/i', $markup))) {
            $evidence['WithFileUploads'] = 'an upload in the view';
        }

        return $evidence;
    }

    /**
     * @param  array<int, string>  $traits
     * @return array<int, string>
     */
    protected function orderTraits(array $traits): array
    {
        return array_values(array_filter(static::TRAITS, fn ($trait) => in_array($trait, $traits, true)));
    }

    /**
     * The view names a class renders: view('x'), ->view('x'), view: 'x'
     *
     * @return array<int, string>
     */
    protected function renderedViews(string $php): array
    {
        preg_match_all('/\bview\s*\(\s*[\'"]([^\'"]+)[\'"]/', $php, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Where views live: the host's and each module's
     *
     * @return array<int, string>
     */
    protected function viewRoots(string $root): array
    {
        $roots = [$root.'/resources/views'];

        foreach (['modules', 'Modules'] as $directory) {
            foreach (glob($root.'/'.$directory.'/*/resources/views') ?: [] as $path) {
                $roots[] = $path;
            }
        }

        return array_values(array_filter(array_unique($roots), 'is_dir'));
    }

    /**
     * The file of a view name, or null (a `namespace::` prefix is dropped)
     *
     * @param  array<int, string>  $viewRoots
     */
    protected function resolveView(array $viewRoots, string $name): ?string
    {
        $name = str_contains($name, '::') ? substr($name, strpos($name, '::') + 2) : $name;
        $relative = str_replace('.', '/', $name);

        foreach ($viewRoots as $directory) {
            foreach (['.blade.php', '/index.blade.php'] as $suffix) {
                $path = $directory.'/'.$relative.$suffix;

                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * The partials and anonymous components a markup includes, to a depth
     *
     * @param  array<int, string>  $viewRoots
     * @return array<int, array{path: string, text: string, relative?: string}>
     */
    protected function partialsOf(array $viewRoots, string $markup, int $depth, array &$seen = []): array
    {
        if ($depth <= 0) {
            return [];
        }

        $names = [];

        if (preg_match_all('/@(?:include|includeIf|includeWhen|includeUnless|each)\s*\([^\'"]*?[\'"]([\w.\-:]+)[\'"]/', $markup, $m)) {
            $names = array_merge($names, $m[1]);
        }

        if (preg_match_all('/<x-([a-z][a-z0-9\-]*(?:\.[a-z0-9\-]+)*)/', $markup, $m)) {
            foreach ($m[1] as $component) {
                $names[] = 'components.'.$component;
            }
        }

        $found = [];

        foreach (array_unique($names) as $name) {
            $path = $this->resolveView($viewRoots, $name);

            if ($path === null || isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $text = $this->prepare('blade', (string) file_get_contents($path))['full'];
            $found[] = ['path' => $path, 'text' => $text];

            foreach ($this->partialsOf($viewRoots, $text, $depth - 1, $seen) as $inner) {
                $found[] = $inner;
            }
        }

        return $found;
    }

    /**
     * Compare the host's copy of atom's Boost guideline with what the installed
     * version renders. The host's block is the part of CLAUDE.md or AGENTS.md
     * from its `=== jiannius/atom/core rules ===` line to the next `=== ... ===`
     * line (or the closing tag), the way `boost:install` writes it.
     *
     * @return array<string, mixed> state: in_sync, diverged or not_found
     */
    public function guideline(string $root): array
    {
        $files = [];
        $installed = $this->renderedGuideline();

        foreach (['CLAUDE.md', 'AGENTS.md'] as $name) {
            $path = $root.'/'.$name;

            if (! is_file($path)) {
                continue;
            }

            $block = $this->guidelineBlock((string) file_get_contents($path));

            if ($block === null) {
                $files[$name] = ['state' => 'not_found'];

                continue;
            }

            if ($installed === null) {
                $files[$name] = ['state' => 'unknown', 'reason' => 'the installed guideline could not be rendered'];

                continue;
            }

            $files[$name] = $this->compareGuideline($block, $installed);
        }

        $states = array_column($files, 'state');
        $state = match (true) {
            in_array('diverged', $states, true) => 'diverged',
            in_array('in_sync', $states, true) => 'in_sync',
            in_array('unknown', $states, true) => 'unknown',
            default => 'not_found',
        };

        return ['state' => $state, 'files' => $files];
    }

    /**
     * The atom block of a CLAUDE.md or AGENTS.md, or null when there is none
     */
    public function guidelineBlock(string $markdown): ?string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        if (! preg_match('/^=== jiannius\/atom(?:\/core)? rules ===[ \t]*\n/m', $markdown, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        $rest = substr($markdown, $start);

        if (preg_match('/^=== .+ rules ===[ \t]*$|^<\/laravel-boost-guidelines>/m', $rest, $end, PREG_OFFSET_CAPTURE)) {
            $rest = substr($rest, 0, $end[0][1]);
        }

        return $rest;
    }

    /**
     * What the installed guideline renders to, the way Boost renders a Blade
     * guideline: backticks, PHP tags and `x-` tags held back, Blade::render(),
     * entities decoded, placeholders put back. Null when it cannot be rendered.
     */
    public function renderedGuideline(): ?string
    {
        $path = $this->guidelinePath ?? dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php';

        if (! is_file($path)) {
            return null;
        }

        $placeholders = [
            '`' => '___SINGLE_BACKTICK___',
            '<?php' => '___OPEN_PHP_TAG___',
            '@volt' => '___VOLT_DIRECTIVE___',
            '@endvolt' => '___ENDVOLT_DIRECTIVE___',
            '@can' => '___CAN_DIRECTIVE___',
            '@include' => '___INCLUDE_DIRECTIVE___',
            '@props' => '___PROPS_DIRECTIVE___',
            '</x-' => '___BLADE_COMPONENT_CLOSE___',
            '<x-' => '___BLADE_COMPONENT_OPEN___',
        ];

        try {
            $content = (string) file_get_contents($path);
            $content = preg_replace_callback('/(?<fence>`{3,}|~{3,}).*?\k<fence>/s', fn (array $m) => str_replace('&', '___AMPERSAND___', $m[0]), $content) ?? $content;
            // compiled in memory: Blade::render() would write the compiled view into the host's storage/framework/views
            $compiled = Blade::compileString(str_replace(array_keys($placeholders), array_values($placeholders), $content));
            $rendered = (function (string $__php): string {
                $__env = app('view');
                $level = ob_get_level();
                ob_start();

                try {
                    eval('?>'.$__php);

                    return (string) ob_get_clean();
                } catch (Throwable $e) {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }

                    throw $e;
                }
            })($compiled);
            $rendered = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);
            $rendered = str_replace('___AMPERSAND___', '&', $rendered);

            return str_replace(array_values($placeholders), array_keys($placeholders), $rendered);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Compare two guideline texts line by line, ignoring blank lines and
     * trailing whitespace (Boost's formatter normalises both)
     *
     * @return array<string, mixed>
     */
    protected function compareGuideline(string $host, string $installed): array
    {
        $normalise = function (string $text): array {
            $text = str_replace(["\r\n", "\r"], "\n", $text);

            return array_values(array_filter(array_map('rtrim', explode("\n", $text)), fn ($line) => trim($line) !== ''));
        };

        $hostLines = $normalise($host);
        $installedLines = $normalise($installed);

        if ($hostLines === $installedLines) {
            return ['state' => 'in_sync'];
        }

        $hostCounts = array_count_values($hostLines);
        $installedCounts = array_count_values($installedLines);
        $onlyHost = [];
        $onlyInstalled = [];

        foreach ($hostCounts as $line => $count) {
            $extra = $count - ($installedCounts[$line] ?? 0);

            for ($i = 0; $i < $extra; $i++) {
                $onlyHost[] = $line;
            }
        }

        foreach ($installedCounts as $line => $count) {
            $extra = $count - ($hostCounts[$line] ?? 0);

            for ($i = 0; $i < $extra; $i++) {
                $onlyInstalled[] = $line;
            }
        }

        $shorten = fn (string $line) => mb_substr(trim($line), 0, 110);

        return [
            'state' => 'diverged',
            'host_lines' => count($hostLines),
            'installed_lines' => count($installedLines),
            'host_only' => count($onlyHost),
            'installed_only' => count($onlyInstalled),
            'host_only_examples' => array_map($shorten, array_slice($onlyHost, 0, 3)),
            'installed_only_examples' => array_map($shorten, array_slice($onlyInstalled, 0, 3)),
        ];
    }

    /**
     * The result as the human report: grouped by check, with counts and file:line
     *
     * @param  array<string, mixed>  $result
     * @param  int|null  $limit  findings printed per check (null: all)
     */
    public function format(array $result, ?int $limit = 10): string
    {
        $out = [];
        $out[] = 'atom upgrade check'.($result['atom_version'] ? ' (installed '.$result['atom_version'].')' : '');
        $out[] = 'What each check means: '.$result['guide'];
        $out[] = 'Scanned '.$result['scanned']['app'].' file(s) in app/, resources/, routes/, modules/, config/, database/'.($result['scanned']['tests'] ? ' and '.$result['scanned']['tests'].' in tests/' : '').'. Read only: nothing was written.';
        $out[] = 'A finding is a candidate from a text scan, not a proof: check the receiver of a date method, and the file.';
        $out[] = 'The report and --output quote up to 160 characters of each matching line: treat the file like source code.';
        $out[] = '';

        if ($result['skipped']) {
            $out[] = 'NOT READ ('.count($result['skipped']).')';
            $out[] = '-----------';

            foreach ($result['skipped'] as $skipped) {
                $out[] = $skipped['path'].'  ('.$skipped['reason'].')';
            }

            $out[] = '';
        }

        if ($result['published_views']) {
            $out[] = 'PUBLISHED ATOM VIEWS ('.count($result['published_views']).'): re-diff against v4';
            $out[] = '------------------------------------------------';

            foreach ($result['published_views'] as $view) {
                $out[] = $view;
            }

            $out[] = '';
        }

        $groups = [];

        foreach ($result['checks'] as $check) {
            $groups[$check['severity']][] = $check;
        }

        foreach ([static::BREAKS, static::VISUAL, static::REVIEW] as $severity) {
            $present = array_filter($groups[$severity] ?? [], fn ($check) => $check['count'] || $check['tests_count']);

            if (! $present) {
                continue;
            }

            $out[] = strtoupper($severity);
            $out[] = str_repeat('-', strlen($severity));

            foreach ($present as $check) {
                $out[] = '['.$check['id'].'] '.$check['count'].' in app'.($check['tests_count'] ? ', '.$check['tests_count'].' in tests' : '').' - '.$check['title'];
                $out[] = '    '.$check['section'].'; the codemod: '.($check['codemod'] === 'auto' ? 'fixes it' : 'reports it only');

                $shown = 0;

                foreach (['app', 'tests'] as $scope) {
                    foreach (array_filter($check['findings'], fn ($finding) => $finding['scope'] === $scope) as $finding) {
                        if ($limit !== null && $shown >= $limit) {
                            break 2;
                        }

                        $out[] = '    '.($scope === 'tests' ? '(tests) ' : '').($finding['line'] ? $finding['file'].':'.$finding['line'] : '(app)').'  '.$finding['text'].(isset($finding['note']) ? '  <- '.$finding['note'] : '');
                        $shown++;
                    }
                }

                $total = $check['count'] + $check['tests_count'];

                if ($limit !== null && $total > $shown) {
                    $out[] = '    ... and '.($total - $shown).' more (use --all)';
                }
            }

            $out[] = '';
        }

        $out[] = 'COMPONENTS THAT USE AtomComponent ('.count($result['components']).')';
        $out[] = '-----------------------------------';

        foreach ($result['components'] as $component) {
            $traits = $component['traits'] ? implode(', ', $component['traits']) : '(none: drop AtomComponent)';
            $out[] = $component['file'].':'.$component['line'].'  needs '.$traits
                .($component['also_present'] ? '  (already has '.implode(', ', $component['also_present']).')' : '')
                .($component['partials'] ? '  [via '.count($component['partials']).' partial(s)]' : '')
                .($component['notes'] ? '  <- '.implode('; ', $component['notes']) : '');
        }

        $out[] = '';
        $counts = [];

        foreach ($result['components'] as $component) {
            foreach ($component['traits'] as $trait) {
                $counts[$trait] = ($counts[$trait] ?? 0) + 1;
            }
        }

        $out[] = 'Traits needed: '.($counts ? implode(', ', array_map(fn ($trait, $n) => $trait.' '.$n, array_keys($counts), $counts)) : 'none');
        $out[] = '';
        $out[] = '<atom:...> TAGS IN USE ('.count($result['tags']).' distinct, '.array_sum(array_column($result['tags'], 'count')).' uses)';
        $out[] = '----------------------';

        foreach ($result['tags'] as $name => $tag) {
            $out[] = str_pad($name, 28).' '.str_pad((string) $tag['count'], 6, ' ', STR_PAD_LEFT).' in '.$tag['files'].' file(s)'.($tag['restyled'] ? '  restyled: '.$tag['restyled'] : '');
        }

        $out[] = '';
        $out[] = 'CLAUDE.md / AGENTS.md atom section';
        $out[] = '----------------------------------';
        $guideline = $result['guideline'];

        if (! $guideline['files']) {
            $out[] = 'not found: no CLAUDE.md or AGENTS.md here';
        }

        foreach ($guideline['files'] as $name => $file) {
            $out[] = $name.': '.match ($file['state']) {
                'in_sync' => 'in sync with the installed version',
                'diverged' => 'diverged: '.$file['host_only'].' line(s) only in yours, '.$file['installed_only'].' only in the installed one'
                    .($file['installed_only_examples'] ? '; installed has e.g. "'.$file['installed_only_examples'][0].'"' : '')
                    .($file['host_only_examples'] ? '; yours has e.g. "'.$file['host_only_examples'][0].'"' : '')
                    .'. Run `php artisan boost:install` to refresh it.',
                'unknown' => 'could not compare ('.($file['reason'] ?? '').')',
                default => 'not found: no atom block in this file',
            };
        }

        $summary = $result['summary'];
        $out[] = '';
        $out[] = 'SUMMARY';
        $out[] = '-------';
        $out[] = $summary['findings'].' finding(s) in '.$summary['checks_with_findings'].' of '.$summary['checks'].' checks'.($summary['tests_findings'] ? ' (+ '.$summary['tests_findings'].' in tests)' : '').': '
            .collect($summary['by_severity'])->map(fn ($n, $severity) => $n.' '.$severity)->implode(', ').'.';
        $out[] = 'The codemod will fix '.$summary['by_codemod']['auto'].' and only report '.$summary['by_codemod']['report'].'.';
        $out[] = $summary['components'].' component(s) use AtomComponent; '.$summary['editor_casts'].' AsEditorContent cast(s); '.$summary['editor_tags'].' <atom:editor*> tag(s).';

        return implode("\n", $out)."\n";
    }
}
