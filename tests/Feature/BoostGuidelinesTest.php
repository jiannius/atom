<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Yaml\Yaml;

/**
 * Read one of the packaged Boost files.
 */
function boostFile(string $path): string
{
    return file_get_contents(__DIR__.'/../../resources/boost/'.$path);
}

/**
 * Render a Boost Blade file the way Boost does (RendersBladeGuidelines::renderContent()):
 * backticks, PHP tags and x- component tags are swapped for placeholders, the result goes
 * through Blade::render(), and the placeholders are put back. Boost itself is not a dev
 * dependency of atom, so this mirrors its render path rather than calling it.
 */
function boostRender(string $path): string
{
    $placeholders = [
        '`' => '___SINGLE_BACKTICK___',
        '<?php' => '___OPEN_PHP_TAG___',
        '@volt' => '___VOLT_DIRECTIVE___',
        '@endvolt' => '___ENDVOLT_DIRECTIVE___',
        '</x-' => '___BLADE_COMPONENT_CLOSE___',
        '<x-' => '___BLADE_COMPONENT_OPEN___',
    ];

    $rendered = renderBlade(str_replace(array_keys($placeholders), array_values($placeholders), boostFile($path)));
    $rendered = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);

    return str_replace(array_values($placeholders), array_keys($placeholders), $rendered);
}

/**
 * The guideline and the skill together: what a consuming app's agent can reach.
 */
function boostAll(): string
{
    return boostFile('guidelines/core.blade.php')."\n".boostFile('skills/atom-components/SKILL.blade.php');
}

dataset('boost files', [
    'guideline' => 'guidelines/core.blade.php',
    'skill' => 'skills/atom-components/SKILL.blade.php',
]);

// A literal <atom:...> tag outside @verbatim is compiled as an unclosed component and
// blows up rendering with a misleading "expecting elseif/else/endif" error.
it('does not compile documented atom tags in the boost files', function (string $path) {
    $compiled = Blade::compileString(boostFile($path));

    expect($compiled)->not->toContain('$__componentOriginal');
})->with('boost files');

it('renders the boost files through Blade without error and keeps the documented tags', function (string $path) {
    $rendered = boostRender($path);

    expect($rendered)->toContain('<atom:')
        ->and($rendered)->toContain('{{ }}')
        ->and($rendered)->not->toContain('___')
        ->and($rendered)->not->toContain('@verbatim')
        ->and($rendered)->not->toContain('@endverbatim');
})->with('boost files');

it('keeps @verbatim balanced in the boost files', function (string $path) {
    $contents = boostFile($path);

    expect(substr_count($contents, '@verbatim'))->toBe(substr_count($contents, '@endverbatim'));
})->with('boost files');

// Boost discovers a third-party skill under resources/boost/skills/<name>/ and reads its
// frontmatter from the RENDERED file (SkillComposer::parseSkill()), so a literal tag in the
// description would render to something else. Boost is not installed in this rig, so this
// repeats its parse: render, cut the frontmatter, YAML-decode, need a name and a description.
it('ships a skill Boost can discover', function () {
    $dir = __DIR__.'/../../resources/boost/skills';
    $skills = glob($dir.'/*', GLOB_ONLYDIR);

    expect($skills)->toHaveCount(1);

    $rendered = boostRender('skills/'.basename($skills[0]).'/SKILL.blade.php');

    expect(preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $rendered, $matches))->toBe(1);

    $frontmatter = Yaml::parse($matches[1]);

    expect($frontmatter['name'])->toBe('atom-components')
        ->and(basename($skills[0]))->toBe($frontmatter['name'])
        ->and($frontmatter['description'])->toBeString()->not->toBeEmpty();

    // The description is the only text that decides when the skill loads: it must survive
    // rendering byte for byte, so it names no tag.
    preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', boostFile('skills/atom-components/SKILL.blade.php'), $source);

    $raw = Yaml::parse($source[1]);

    expect($frontmatter['description'])->toBe($raw['description'])
        ->and($raw['description'])->not->toContain('<');
});

it('points the always-on guideline at the skill by its real name', function () {
    expect(boostFile('guidelines/core.blade.php'))->toContain('`atom-components`');
});

// The guideline is resident in every turn of every consuming app; the PR that split it kept
// only what applies to every view. Growing it back is a decision, not an accident.
it('keeps the always-on guideline lean', function () {
    $words = str_word_count(strip_tags(boostFile('guidelines/core.blade.php')));

    expect($words)->toBeLessThan(700);
});

// This content is copied into consuming apps' AI guidelines, so a wrong prop here
// teaches every agent in every app a component API that does not exist — and it
// reinstates itself whenever Boost regenerates, overwriting hand corrections.
it('reaches a remote option set through the options prop, not name', function () {
    $contents = boostAll();

    expect($contents)->toContain('<atom:select options="users"')
        ->and($contents)->not->toContain('<atom:select name=')
        ->and($contents)->not->toContain(':callback');
});

// Chat / editor HTML reaches the host from the browser. The always-on guideline is what tells
// every host app (and its agents) to clean it before storing and never to x-html it; the skill
// carries the detail.
it('tells hosts that editor and chat HTML is untrusted and to sanitise it', function () {
    $guideline = boostFile('guidelines/core.blade.php');
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($guideline)->toContain('Content::sanitize()')
        ->and($guideline)->toContain('untrusted')
        ->and($guideline)->toContain('x-html')
        ->and($guideline)->toContain('<atom:tiptap.content>')
        ->and($skill)->toContain('Content::sanitize(')
        ->and($skill)->toContain('untrusted')
        ->and($skill)->toContain('x-html')
        ->and($skill)->toContain('<atom:tiptap.content')
        ->and($skill)->toContain('Content::sanitizeRefuses(')
        ->and($skill)->toContain('atom.editor.render_max_bytes')
        ->and($skill)->toContain('render_max_tags');
});

// A URL a user typed is scheme-checked by atom's own components, and by the host's own markup
// only if the host knows to call the helper: the rule is always-on, the list of components and
// the colon gotcha live in the skill.
it('tells hosts to scheme-check a user-supplied URL', function () {
    $guideline = boostFile('guidelines/core.blade.php');
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($guideline)->toContain('safe_url($url)')
        ->and($guideline)->toContain('atom.safeUrl(url)')
        ->and($guideline)->toContain('javascript:')
        ->and($skill)->toContain('safe_url($url)')
        ->and($skill)->toContain('atom.safeUrl(url)')
        ->and($skill)->toContain('./foo:bar')
        ->and($skill)->toContain('`//evil.com`');
});

it('tells hosts that an option set html key is trusted and must be escaped', function () {
    $guideline = boostFile('guidelines/core.blade.php');
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($guideline)->toContain("'html'")
        ->and($guideline)->toContain('e()')
        ->and($skill)->toContain("'html' => '<b>'.e(\$doc->name).'</b>'")
        ->and($skill)->toContain('stored XSS');
});

it('documents the components that need a script, a flag or a colour the source does not show', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($skill)->toContain('sharer.js@0.5.4')
        ->and($skill)->toContain('`table-filter`')
        ->and($skill)->toContain('overflow="modal"')
        ->and($skill)->toContain('`updatingAtomComponent`')
        ->and($skill)->toContain('border-zinc-200 dark:border-zinc-700');
});

// These are the rules an agent breaks without ever opening the skill: a public action, a select
// that silently renders empty, a hand-rolled layout. They stay always-on; the skill has the detail.
it('keeps the action, option-set and layout rules in the always-on guideline', function () {
    $guideline = boostFile('guidelines/core.blade.php');

    expect($guideline)->toContain('POST /atom/action/{Name}')
        ->and($guideline)->toContain('implements `WebAction`')
        ->and($guideline)->toContain('`authorize()`')
        ->and($guideline)->toContain('return columns, not models')
        ->and($guideline)->toContain('must declare it `protected`')
        ->and($guideline)->toContain('app/Actions/GetOptions.php')
        ->and($guideline)->toContain('\Jiannius\Atom\Actions\GetOptions')
        ->and($guideline)->toContain('`$auth`')
        ->and($guideline)->toContain('`$guest`')
        ->and($guideline)->toContain('scoped to the current tenant')
        ->and($guideline)->toContain('<atom:form.grid>')
        ->and($guideline)->toContain('<atom:form.modal>')
        ->and($guideline)->toContain('<atom:form.actions>')
        ->and($guideline)->toContain('bare `grid-cols-2`')
        ->and($guideline)->toContain('<atom:callout>')
        ->and($guideline)->toContain('<atom:navlist>')
        ->and($guideline)->toContain('border-t border-zinc-200 dark:border-zinc-700');
});

it('says sanitize() is for chat HTML only and the JSON editor value is stored as JSON', function () {
    expect(boostFile('guidelines/core.blade.php'))
        ->toContain('`sanitize()` is for chat HTML only')
        ->toContain('stored as JSON');
});

it('tells hosts the skill needs Boost skills enabled and a skills-capable agent', function () {
    $guideline = boostFile('guidelines/core.blade.php');

    expect($guideline)->toContain('`boost.json`')
        ->and($guideline)->toContain('Claude Code, Codex, Cursor, Gemini, Amp')
        ->and($guideline)->toContain('php artisan boost:install');
});

// A host's agent only learns the form's error toast, and how to opt out, from the skill.
it('documents the form error toast and its opt-out in the skill', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($skill)->toContain('**Error toast.**')
        ->and($skill)->toContain(':error-toast="false"')
        ->and($skill)->toContain('`<atom:toast>` mounted');
});

// What was moved into the always-on guideline is still in the skill, with its detail.
it('keeps the moved rules in the skill as well', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($skill)->toContain('Default to NOT implementing `WebAction`')
        ->and($skill)->toContain('**Scope the query anyway.**')
        ->and($skill)->toContain('Never use bare `grid-cols-2`');
});

// A host's agent only learns how the calendar is wired, and the three ways it goes wrong silently (a public items method, a date that
// is not converted, a save that nothing holds), from the skill; the always-on guideline points at it.
it('documents the calendar wiring and its silent footguns in the skill', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($skill)->toContain('### Calendar')
        ->and($skill)->toContain('**The items method must be `protected`.**')
        ->and($skill)->toContain('`Jiannius\Atom\Services\Calendar::fromLocal($string)`')
        ->and($skill)->toContain('**Load by overlap, not by start.**')
        ->and($skill)->toContain('->whereNull(\'ends_at\')')
        ->and($skill)->toContain('**Pass the promise of your save to `waitUntil()`**')
        ->and($skill)->toContain('**Do not put `#[Renderless]` on the handler**')
        ->and($skill)->toContain('`modes` and `periods`')
        ->and(boostFile('guidelines/core.blade.php'))->toContain('the calendar');
});

// Validation does not reject the promise of an action (Livewire answers 200, so it resolves null): the skill must teach the refusal that works.
it('teaches the calendar refusal pattern and never says validation rejects the promise', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');

    expect($skill)->toContain('saved || Promise.reject()')
        ->and($skill)->toContain('does not reject the promise')
        ->and($skill)->toContain('error modal')
        ->and($skill)->toContain('return `false` to refuse and `true` once saved')
        ->and($skill)->toContain('A renderless method must still `return true`')
        ->and($skill)->not->toMatch('/(?:Livewire rejects|rejects the promise)[^.]*validat/i')
        ->and($skill)->not->toContain('refuse by throwing')
        ->and($skill)->not->toContain('a failed validation, an abort');
});

// The skill's calendar section is the wiring the README shows: its events and props are the ones the component has.
it('names only calendar events and props the component has', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');
    $component = file_get_contents(__DIR__.'/../../components/calendar/index.blade.php');

    preg_match_all('/(calendar-(?:select|item-[a-z]+))/', $skill, $events);

    expect(array_values(array_unique($events[1])))->toEqualCanonicalizing(['calendar-select', 'calendar-item-move', 'calendar-item-resize', 'calendar-item-click']);

    expect($component)->toContain("'views' =>");
});

// The skill's event line gives each event's detail keys, and says which are cancelable: both are what the dispatch sites in the browser code say.
it('gives each calendar event the detail keys and cancelability the code has', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');
    $facts = calendarEventFacts();

    preg_match_all('/`(calendar-(?:select|item-[a-z]+))` `\{ ([^}]*) \}`/', $skill, $found, PREG_SET_ORDER);

    $named = [];

    foreach ($found as [, $event, $keys]) {
        $keys = array_map('trim', explode(',', $keys));
        sort($keys);

        $named[$event] = $keys;
    }

    expect(array_keys($named))->toEqualCanonicalizing(array_keys($facts));

    foreach ($facts as $event => $fact) {
        expect($named[$event])->toBe($fact['keys'], 'the detail keys of '.$event);
    }

    // `calendar-select` ... not cancelable; `calendar-item-move` and `calendar-item-resize` ... are cancelable
    expect(preg_match('/`calendar-select` `\{[^}]*\}`, (not cancelable)?/', $skill, $select))->toBe(1)
        ->and(isset($select[1]))->toBe(! $facts['calendar-select']['cancelable'])
        ->and(preg_match('/`calendar-item-move` `\{[^}]*\}` and `calendar-item-resize` `\{[^}]*\}` (are cancelable)/', $skill, $change))->toBe(1)
        ->and($facts['calendar-item-move']['cancelable'])->toBeTrue()
        ->and($facts['calendar-item-resize']['cancelable'])->toBeTrue();
});

// The props the skill names are read out of its own "Props:" sentence, not from a list written here, so one it adds that the component
// does not have fails; a default it states in brackets is the component's.
it('names only calendar props the component has, with the defaults it has', function () {
    $skill = boostFile('skills/atom-components/SKILL.blade.php');
    $defaults = calendarPropDefaults();

    $section = substr($skill, strpos($skill, '### Calendar'), strpos($skill, '### Actions') - strpos($skill, '### Calendar'));

    expect(preg_match('/Props: (.+)/', $section, $sentence))->toBe(1);

    // the names are the backticked words outside the brackets (a default sits inside one); `draggable` and `resizable` share a bracket
    $outside = preg_replace('/\([^)]*\)/', '', $sentence[1]);

    preg_match_all('/`([a-z]+(?:-[a-z]+)*)`/', $outside, $names);

    $named = array_map(fn ($name) => (string) str($name)->camel(), $names[1]);

    preg_match_all('/`([a-z]+(?:-[a-z]+)*)`(?: and `([a-z]+(?:-[a-z]+)*)`)? \(([^)]*)\)/', $sentence[1], $bracketed, PREG_SET_ORDER);

    expect($named)->toContain('views', 'weekStart', 'nowIndicator', 'name')->and($named)->toHaveCount(14);

    foreach ($named as $prop) {
        expect(array_key_exists($prop, $defaults))->toBeTrue('the skill names `'.$prop.'`, which the component has no prop for');
    }

    foreach ($bracketed as $match) {
        foreach (array_filter([$match[1], $match[2]]) as $kebab) {
            $default = $defaults[(string) str($kebab)->camel()];

            if (preg_match_all('/`([^`]+)`/', $match[3], $values)) {
                expect(in_array((string) $default, $values[1], true))->toBeTrue('the default the skill gives `'.$kebab.'`');
            } else {
                expect($match[3])->toBe($default ? 'on' : 'off');
            }
        }
    }
});
