<?php

use Illuminate\Support\ViewErrorBag;

// The sidebar shell mounts <atom:confirm>, whose reason input reads the shared $errors bag.
beforeEach(fn () => view()->share('errors', new ViewErrorBag));

/** A shell with an optional account menu, rendered with the given props. */
function shellHtml(string $layout, string $props = '', bool $dropdown = true, string $slots = ''): string
{
    $menu = $dropdown ? "<x-slot:dropdown>\n<atom:menu.item>OUT</atom:menu.item>\n</x-slot>\n" : '';

    return renderBlade('<atom:'.$layout.' :vite="false" '.$props.">\n".$menu.$slots."\nx</atom:".$layout.'>');
}

describe('the theme row in the account menu', function () {
    it('is a labelled group of three pressable buttons inside the account menu', function (string $layout) {
        $html = shellHtml($layout, 'dark');
        $row = '//*[@data-atom-menu]//*[@data-atom-theme-row]';
        // the sidebar has two account menus (the brand row's, and the phone one in the bar); the header one
        $menus = $layout === 'layouts.sidebar' ? 2 : 1;

        expect(domQuery($html, $row))->toHaveCount($menus)
            ->and(domQuery($html, $row.'//*[@role="group"][@aria-label="Theme"]'))->toHaveCount($menus)
            ->and(domQuery($html, $row.'//button[@type="button"][@role="menuitemradio"][@aria-checked][@aria-label]'))->toHaveCount(3 * $menus)
            ->and(domQuery($html, $row.'//button[@aria-label="Light"][@data-atom-theme-option="light"]'))->toHaveCount($menus)
            ->and(domQuery($html, $row.'//button[@aria-label="Dark"][@data-atom-theme-option="dark"]'))->toHaveCount($menus)
            ->and(domQuery($html, $row.'//button[@aria-label="System"][@data-atom-theme-option="system"]'))->toHaveCount($menus);
    })->with(['layouts.sidebar', 'layouts.header']);

    it('is not a menu item and does not let a click reach the dropdown, so picking a theme keeps the menu open', function (string $layout) {
        $html = shellHtml($layout, 'dark');

        expect(domQuery($html, '//*[@data-atom-theme-row]//*[@role="menuitem"]'))->toHaveCount(0)
            ->and(domQuery($html, '//*[@data-atom-theme-row][@*[name()="x-on:click.stop"]]'))->toHaveCount(count(domQuery($html, '//*[@data-atom-theme-row]')));
    })->with(['layouts.sidebar', 'layouts.header']);

    it('is absent with dark mode off', function (string $layout) {
        expect(domQuery(shellHtml($layout), '//*[@data-atom-theme-row]'))->toHaveCount(0);
    })->with(['layouts.sidebar', 'layouts.header']);

    it('is in the sidebar foot menu too with account="end", and in the header\'s avatar menu', function (string $layout) {
        $html = shellHtml($layout, 'dark account="end"');

        expect(domQuery($html, '//*[@data-atom-menu]//*[@data-atom-theme-row]'))->not->toBeEmpty();
    })->with(['layouts.sidebar', 'layouts.header']);
});

describe('the bar toggle is the fallback', function () {
    it('is drawn only with dark mode on and no account menu', function (string $layout) {
        $toggle = '//*[@data-atom-darkmode-toggle]';

        expect(domQuery(shellHtml($layout, 'dark', false), $toggle))->toHaveCount(1)
            ->and(domQuery(shellHtml($layout, 'dark', true), $toggle))->toHaveCount(0)
            ->and(domQuery(shellHtml($layout, '', false), $toggle))->toHaveCount(0)
            ->and(domQuery(shellHtml($layout, 'dark', false), '//*[@data-atom-theme-row]'))->toHaveCount(0);
    })->with(['layouts.sidebar', 'layouts.header']);

    it('counts as bar content in the sidebar shell only when it is drawn', function () {
        $empty = fn (string $props, bool $dropdown) => str_contains(shellHtml('layouts.sidebar', $props, $dropdown), 'data-atom-header-empty');

        // dark with no account menu: the toggle is the bar's content
        expect($empty('dark', false))->toBeFalse()
            // dark with an account menu: the toggle moved into the menu, so the bar holds nothing
            ->and($empty('dark', true))->toBeTrue()
            ->and($empty('', false))->toBeTrue();
    });

    it('keeps a bar that holds only crumbs marked for breadcrumbs.js when dark is on behind an account menu', function () {
        $html = shellHtml('layouts.sidebar', 'dark', true, "<x-slot:crumbs>\nC\n</x-slot>");

        expect($html)->toContain('data-atom-header-crumbs-only');
    });
});

describe('theme-switcher="false"', function () {
    it('draws neither the row nor the bar toggle, and still emits the darkmode bootstrap', function (string $layout, bool $dropdown) {
        $html = shellHtml($layout, 'dark :theme-switcher="false"', $dropdown);

        expect(domQuery($html, '//*[@data-atom-theme-row]'))->toHaveCount(0)
            ->and(domQuery($html, '//*[@data-atom-darkmode-toggle]'))->toHaveCount(0)
            ->and($html)->toContain('window.darkmode')->toContain('prefers-color-scheme');
    })->with(['layouts.sidebar', 'layouts.header'])->with([true, false]);

    it('is read in kebab case, and on by default', function (string $layout) {
        expect(domQuery(shellHtml($layout, 'dark :theme-switcher="false"'), '//*[@data-atom-theme-row]'))->toHaveCount(0)
            ->and(domQuery(shellHtml($layout, 'dark :theme-switcher="true"'), '//*[@data-atom-theme-row]'))->not->toBeEmpty()
            ->and(domQuery(shellHtml($layout, 'dark'), '//*[@data-atom-theme-row]'))->not->toBeEmpty();
    })->with(['layouts.sidebar', 'layouts.header']);

    it('empties the sidebar\'s bar again, since the toggle that held it is not drawn', function () {
        $empty = fn (string $props, bool $dropdown) => str_contains(shellHtml('layouts.sidebar', $props, $dropdown), 'data-atom-header-empty');

        expect($empty('dark :theme-switcher="false"', false))->toBeTrue();
    });

    it('keeps the two shells\' @props blocks in step', function () {
        $props = fn (string $layout): string => preg_match('/@props\(\[.*?\]\)/s', file_get_contents(__DIR__.'/../../components/layouts/'.$layout.'.blade.php'), $m) ? $m[0] : '';

        expect($props('header'))->toBe($props('sidebar'))->toContain("'themeSwitcher' => true");
    });
});

describe('the sidebar collapse button', function () {
    it('is a button in the top bar, from lg up, that names and controls the sidebar', function () {
        $html = shellHtml('layouts.sidebar');
        $button = '//header[@data-atom-header]//button[@data-atom-sidebar-collapse]';

        expect(domQuery($html, $button))->toHaveCount(1)
            ->and(domQuery($html, $button.'[@type="button"][@aria-expanded="true"][@aria-label="Sidebar"][@aria-controls="atom-sidebar"][contains(@title, "Toggle sidebar")]'))->toHaveCount(1)
            ->and(domQuery($html, $button.'[contains(@class, "max-lg:hidden")]'))->toHaveCount(1)
            // the sidebar it controls, and the menu button for small screens beside it
            ->and(domQuery($html, '//*[@id="atom-sidebar"][@data-atom-sidebar]'))->toHaveCount(1)
            ->and(domQuery($html, '//header//button[@data-atom-sidebar-toggle][contains(@class, "lg:hidden")]'))->toHaveCount(1)
            // both glyphs, one shown by atom.css from the attribute on <html>
            ->and(domQuery($html, $button.'//*[@data-atom-sidebar-glyph="collapse"]//*[contains(@class, "lucide-panel-left-close")]'))->toHaveCount(1)
            ->and(domQuery($html, $button.'//*[@data-atom-sidebar-glyph="expand"]//*[contains(@class, "lucide-panel-left-open")]'))->toHaveCount(1);
    });

    it('is the first thing in the bar, before the crumbs', function () {
        $html = shellHtml('layouts.sidebar', '', true, "<x-slot:crumbs>\nC\n</x-slot>");

        expect(domQuery($html, '//*[@data-atom-header]/div/button[@data-atom-sidebar-collapse]/following-sibling::*[@data-atom-shell-crumbs]'))->toHaveCount(1);
    });

    it('has one name through t(), the state in aria-expanded, and keeps the collapsed state in localStorage', function () {
        $html = shellHtml('layouts.sidebar');

        expect($html)
            ->not->toContain("collapsed ? 'Show sidebar' : 'Hide sidebar'")
            ->toContain("collapsed ? 'false' : 'true'")
            ->toContain("window.localStorage.getItem('atom-sidebar-collapsed') === '1'")
            ->toContain("window.localStorage.setItem('atom-sidebar-collapsed', '1')");
    });

    it('writes the remembered state on <html> from a script in the body, before the sidebar', function () {
        $html = shellHtml('layouts.sidebar');

        $script = strpos($html, "toggleAttribute('data-atom-sidebar-collapsed', window.localStorage");
        $sidebar = strpos($html, 'data-atom-sidebar>');

        expect($script)->not->toBeFalse()->and($script)->toBeLessThan($sidebar);
        // in the body, so wire:navigate runs it again; not in the head
        expect(domQuery($html, '//head//script[contains(., "atom-sidebar-collapsed")]'))->toHaveCount(0);
    });

    it('leaves a copy of the button at the sidebar\'s foot, for atom.css to show only when the bar is not drawn', function () {
        $html = shellHtml('layouts.sidebar');

        expect(domQuery($html, '//*[@data-atom-sidebar]//button[@data-atom-sidebar-fallback][@aria-controls="atom-sidebar"][contains(., "Hide sidebar")]'))->toHaveCount(1);
    });

    it('listens for Ctrl or Cmd + B, and leaves a field or an editor alone', function () {
        $html = shellHtml('layouts.sidebar');

        expect($html)
            ->toContain('x-on:keydown.window')
            ->toContain('e.ctrlKey || e.metaKey')
            ->toContain('e.repeat')
            ->toContain("e.code === 'KeyB'")
            ->toContain("document.querySelector('dialog[open]')")
            ->toContain('e.target?.isContentEditable')
            ->toContain("closest?.('input, textarea, select')");
    });

    it('is not in the header shell', function () {
        expect(shellHtml('layouts.header'))->not->toContain('data-atom-sidebar-collapse')->not->toContain('atom-sidebar-collapsed');
    });

    it('keeps the bar-empty marks as they were: atom.css, not the server, draws the bar while collapsed', function () {
        $css = file_get_contents(__DIR__.'/../../resources/css/atom.css');

        expect(str_contains(shellHtml('layouts.sidebar'), 'data-atom-header-empty'))->toBeTrue()
            ->and($css)->toContain('html:not([data-atom-sidebar-collapsed]) [data-atom-sidebar] + [data-atom-header][data-atom-header-empty]')
            ->and($css)->toContain('html[data-atom-sidebar-collapsed] [data-atom-sidebar]');
    });
});
