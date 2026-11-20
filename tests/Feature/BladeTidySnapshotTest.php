<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Pins the rendered markup of the components a Blade tidy touched (the shells' user prelude, badge, the choice controls and
 * the Tiptap toolbar menus) against files in tests/Fixtures/tidy-snapshots, so moving markup into a partial shows up as a diff
 * wherever it changes the output. Set UPDATE_TIDY_SNAPSHOTS=1 to rewrite them.
 */
beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/** A user model: with or without its own initials(), an avatar and an email. */
function tidyUser(string $kind): ?Illuminate\Contracts\Auth\Authenticatable
{
    return match ($kind) {
        'guest' => null,
        'name only' => new class(['name' => 'tan joon long']) extends Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        },
        'own initials' => new class(['name' => 'Alex Doe', 'email' => 'alex@example.com']) extends Illuminate\Foundation\Auth\User {
            protected $guarded = [];

            public function initials(): string
            {
                return ' XQ ';
            }
        },
        'avatar' => new class(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'avatar' => '/img/ada.png']) extends Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        },
        'blank initials' => new class(['name' => 'éva östen']) extends Illuminate\Foundation\Auth\User {
            protected $guarded = [];

            public function initials(): string
            {
                return '  ';
            }
        },
        'strict' => new class(['id' => 1]) extends Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        },
    };
}

/** The shells, once per kind of user, with the account menu in the brand row and at the end, with and without a brand, logo and profile. */
function tidyLayoutCases(): array
{
    $cases = [];

    foreach (['layouts.sidebar', 'layouts.header'] as $layout) {
        foreach (['guest', 'name only', 'own initials', 'avatar', 'blank initials', 'strict'] as $user) {
            foreach (['brand', 'end'] as $account) {
                $cases[$layout.' / '.$user.' / '.$account] = [$user, '<atom:'.$layout." :vite=\"false\" dark account=\"{$account}\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>', $user === 'strict'];
            }
        }

        $cases[$layout.' / avatar / no dropdown'] = ['avatar', '<atom:'.$layout.' :vite="false">x</atom:'.$layout.'>', false];
        $cases[$layout.' / avatar / profile only'] = ['avatar', '<atom:'.$layout." :vite=\"false\">\n<x-slot:profile>\nPROFILE\n</x-slot>\nx</atom:".$layout.'>', false];
        $cases[$layout.' / avatar / brand slot'] = ['avatar', '<atom:'.$layout." :vite=\"false\" dark>\n<x-slot:brand>\n<a href=\"/x\">BRAND</a>\n</x-slot>\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>', false];
        $cases[$layout.' / avatar / logo and caption'] = ['avatar', '<atom:'.$layout." :vite=\"false\" dark>\n<x-slot:logo>\nLOGO\n</x-slot>\n<x-slot:caption>\nCAP\n</x-slot>\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:".$layout.'>', false];
        $cases[$layout.' / guest / logo, no dropdown'] = ['guest', '<atom:'.$layout." :vite=\"false\">\n<x-slot:logo>\nLOGO\n</x-slot>\nx</atom:".$layout.'>', false];
    }

    return array_map(fn (array $case) => fn () => tidyRender($case), $cases);
}

/** Render a layout case for its user, with strict attribute access when the case asks for it. */
function tidyRender(array $case): string
{
    [$user, $template, $strict] = $case;

    Auth::forgetUser();

    if ($user !== 'guest') {
        $model = tidyUser($user);
        $model->exists = $strict;
        Auth::setUser($model);
    }

    Model::preventAccessingMissingAttributes($strict);

    try {
        return renderBlade($template);
    } finally {
        Model::preventAccessingMissingAttributes(false);
        Auth::forgetUser();
    }
}

/** Every badge a tidy of its two branches must not change. */
function tidyBadgeCases(): array
{
    $templates = [
        'plain' => '<atom:badge>New</atom:badge>',
        'tones' => '<atom:badge color="info">I</atom:badge><atom:badge color="success">S</atom:badge><atom:badge color="warning">W</atom:badge><atom:badge color="danger">D</atom:badge><atom:badge color="accent">A</atom:badge><atom:badge color="black">B</atom:badge><atom:badge color="red">R</atom:badge><atom:badge color="nope">N</atom:badge>',
        'sizes' => '<atom:badge size="lg">L</atom:badge><atom:badge size="xs">X</atom:badge>',
        'icon' => '<atom:badge icon="add">New</atom:badge><atom:badge icon="add" size="lg">New</atom:badge>',
        'label' => '<atom:badge label="Paid"/>',
        'status array' => '<atom:badge :status="[\'label\' => \'Open\', \'color\' => \'green\']"/>',
        'status enum' => '<atom:badge :status="$s"/>',
        'attributes' => '<atom:badge class="max-w-sm extra" id="b" data-x="1" style="margin: 1px">New</atom:badge>',
        'hex' => '<atom:badge color="#ff0000">New</atom:badge>',
        'hex icon' => '<atom:badge color="#00ff88" icon="add" size="lg">New</atom:badge>',
        'hex label' => '<atom:badge color="#336699" label="Paid" class="extra" id="h" data-x="1"/>',
        'hex style' => '<atom:badge color="#336699" style="margin: 1px">New</atom:badge>',
        'hex enum' => '<atom:badge :status="$h"/>',
        'inline' => 'Use <atom:badge>New</atom:badge>. And <atom:badge color="#ff0000">New</atom:badge>.',
    ];

    return array_map(fn (string $template) => fn () => renderBlade($template, ['s' => tidyEnum('info'), 'h' => tidyEnum('#123456')]), $templates);
}

enum TidyState: string
{
    case Info = 'info';
    case Hex = '#123456';

    public function label(): string
    {
        return 'State '.$this->value;
    }

    public function color(): string
    {
        return $this->value;
    }
}

function tidyEnum(string $value): TidyState
{
    return TidyState::from($value);
}

/** The choice controls: every variant, plain and tile. */
function tidyChoiceCases(): array
{
    $cases = [];
    $states = [
        'default' => '',
        'disabled' => ' disabled',
        'error' => ' error="Pick one"',
        'required' => ' required',
        'caption' => ' caption="Helper text"',
        'align start' => ' align="start" caption="Helper"',
        'align end' => ' align="end"',
        'checked' => ' checked class="extra" id="c1"',
        'wire model' => ' wire:model="agree"',
        'slot' => '',
    ];

    foreach (['checkbox', 'radio', 'toggle'] as $kind) {
        foreach ($states as $state => $attributes) {
            $body = $state === 'slot' ? '<b>Rich</b> label' : '';
            $label = $state === 'slot' ? '' : ' label="Label"';
            $value = $kind === 'radio' ? ' value="a"' : '';

            $cases[$kind.' / plain / '.$state] = renderBlade("<atom:{$kind}{$label}{$value}{$attributes}>{$body}</atom:{$kind}>");

            if ($kind !== 'toggle') {
                $group = "<atom:{$kind}.group variant=\"tile\" name=\"g\"><atom:{$kind}{$label}{$value}{$attributes}>{$body}</atom:{$kind}></atom:{$kind}.group>";
                $cases[$kind.' / tile / '.$state] = renderBlade($group);
            }
        }

        $cases[$kind.' / disabled form'] = renderBlade("<atom:form disabled><atom:{$kind} label=\"Label\" value=\"a\"/></atom:form>");
        $cases[$kind.' / no label'] = renderBlade("<atom:{$kind}/>");
    }

    return array_map(fn (string $html) => fn () => $html, $cases);
}

/** The Tiptap toolbar menus, and the chat composer's formatting button. */
function tidyToolbarCases(): array
{
    $names = ['text', 'bullet', 'text-align', 'heading', 'font-size', 'table', 'text-color', 'link', 'image', 'youtube', 'undo', 'redo', 'horizontal-rule', 'remove-formatting', 'text-highlight', 'separator'];
    $cases = [];

    foreach ($names as $name) {
        $cases['toolbar.'.$name] = "<atom:tiptap.toolbar.{$name}/>";
    }

    $cases['chat.formatting'] = '<atom:tiptap.chat.formatting/>';
    $cases['toolbar.button'] = '<atom:tiptap.toolbar.button label="Bold" active="isActive(\'bold\')">B</atom:tiptap.toolbar.button>';
    $cases['toolbar.button plain'] = '<atom:tiptap.toolbar.button>B</atom:tiptap.toolbar.button>';

    return array_map(fn (string $template) => fn () => renderBlade($template), $cases);
}

/** Every group a tidy touches, by name. */
function tidyGroups(): array
{
    return [
        'layouts' => tidyLayoutCases(),
        'badge' => tidyBadgeCases(),
        'choice' => tidyChoiceCases(),
        'toolbar' => tidyToolbarCases(),
    ];
}

/**
 * Collapse whitespace so indentation changes in a blade do not count, one tag per line so a diff reads.
 */
function normaliseTidy(string $html): string
{
    // the page's head and its inline scripts and styles are atom:html's, not the part a tidy touches
    $html = preg_replace(['#<head>.*?</head>#s', '#<script\b.*?</script>#s', '#<style\b.*?</style>#s'], '', $html);

    // icons and the logo come from their own components, so their paths are not what a tidy touches
    $html = preg_replace(['#(<span\b[^>]*data-atom-icon>)\s*<svg\b.*?</svg>#s', '#(<figure\b[^>]*>)\s*<svg\b.*?</svg>#s'], '$1<svg/>', $html);

    // the alert, toast and confirm the shells mount after <main> are the same in every case
    $html = preg_replace('#(</main>).*#s', '$1', $html);
    $html = preg_replace('/\s+/', ' ', trim($html));

    return preg_replace('/>\s*</', ">\n<", $html);
}

/**
 * The snapshot file of a group: every case under a `=== name ===` line.
 */
function tidySnapshotOf(array $cases): string
{
    $out = '';

    foreach ($cases as $name => $render) {
        $out .= "=== {$name} ===\n".normaliseTidy($render())."\n";
    }

    return $out;
}

it('keeps the markup of the tidied components', function (string $group) {
    $path = __DIR__.'/../Fixtures/tidy-snapshots/'.$group.'.html';
    $actual = tidySnapshotOf(tidyGroups()[$group]);

    if (getenv('UPDATE_TIDY_SNAPSHOTS')) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $actual);
    }

    expect(file_exists($path))->toBeTrue("no snapshot at {$path}: run with UPDATE_TIDY_SNAPSHOTS=1 to write it");

    // sections apart, so a failure names the case
    $sections = fn (string $text): array => collect(preg_split('/^=== (.*) ===$/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY))->chunk(2)->mapWithKeys(fn ($pair) => [$pair->first() => $pair->last()])->all();
    $expected = $sections(file_get_contents($path));
    $got = $sections($actual);

    expect(array_keys($got))->toBe(array_keys($expected));

    foreach ($got as $name => $html) {
        expect($html)->toBe($expected[$name], $name);
    }
})->with(['layouts', 'badge', 'choice', 'toolbar']);

it('renders the same twice (no random id in a snapshot)', function (string $group) {
    expect(tidySnapshotOf(tidyGroups()[$group]))->toBe(tidySnapshotOf(tidyGroups()[$group]));
})->with(['layouts', 'badge', 'choice', 'toolbar']);

/** The morph markers Livewire prints for a template rendered inside a component. */
function tidyMarkers(string $blade): array
{
    $component = new class extends Component {
        public static string $blade = '';

        public function render()
        {
            return '<div>'.static::$blade.'</div>';
        }
    };

    $component::$blade = $blade;
    $html = Livewire::test($component::class)->html();

    return [
        'block' => substr_count($html, '<!--[if BLOCK]><![endif]-->'),
        'end' => substr_count($html, '<!--[if ENDBLOCK]><![endif]-->'),
    ];
}

// An apostrophe in a `//` comment inside a `{{ }}` echo once dropped the marker of the next `@if` (see PagerMarkersTest), so every
// component a tidy touches must print as many end markers as start markers inside a Livewire component, whatever branch it takes.
it('prints as many end markers as start markers inside a Livewire component', function (string $blade, ?string $user = null) {
    Auth::forgetUser();

    if ($user) {
        Auth::setUser(tidyUser($user));
    }

    $markers = tidyMarkers($blade);

    Auth::forgetUser();

    expect($markers['block'])->toBeGreaterThan(0)
        ->and($markers['end'])->toBe($markers['block']);
})->with([
    'badge' => ['<atom:badge>New</atom:badge>'],
    'badge icon' => ['<atom:badge icon="add" label="Paid"/>'],
    'badge hex' => ['<atom:badge color="#ff0000">New</atom:badge>'],
    'badge hex icon label' => ['<atom:badge color="#ff0000" icon="add" label="Paid"/>'],
    'checkbox' => ['<atom:checkbox label="L" caption="C" error="E"/>'],
    'checkbox slot' => ['<atom:checkbox>x</atom:checkbox>'],
    'checkbox tile' => ['<atom:checkbox.group variant="tile"><atom:checkbox label="L" caption="C"/></atom:checkbox.group>'],
    'radio' => ['<atom:radio label="L" caption="C"/>'],
    'radio tile' => ['<atom:radio.group variant="tile"><atom:radio label="L" caption="C"/></atom:radio.group>'],
    'toggle' => ['<atom:toggle label="L" caption="C" error="E"/>'],
    'toolbar text' => ['<atom:tiptap.toolbar.text/>'],
    'toolbar bullet' => ['<atom:tiptap.toolbar.bullet/>'],
    'toolbar text-align' => ['<atom:tiptap.toolbar.text-align/>'],
    'toolbar heading' => ['<atom:tiptap.toolbar.heading/>'],
    'chat formatting' => ['<atom:tiptap.chat.formatting/>'],
    'sidebar guest' => ["<atom:layouts.sidebar :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.sidebar>"],
    'sidebar user' => ["<atom:layouts.sidebar :vite=\"false\" dark>\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.sidebar>", 'avatar'],
    'sidebar account end' => ["<atom:layouts.sidebar :vite=\"false\" dark account=\"end\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.sidebar>", 'name only'],
    'header guest' => ["<atom:layouts.header :vite=\"false\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.header>"],
    'header user' => ["<atom:layouts.header :vite=\"false\" dark>\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.header>", 'avatar'],
    'header account end' => ["<atom:layouts.header :vite=\"false\" dark account=\"end\">\n<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\nx</atom:layouts.header>", 'name only'],
]);
