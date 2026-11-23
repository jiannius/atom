<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A settings screen: a nav slot on page.columns (two groups, the second expandable and collapsed, eleven items), a form beside it with
 * the save bar, and a method that moves the current item, so a Livewire round trip morphs the nav.
 */
class SettingsPageFixture extends Component
{
    public string $section = 'domains';

    public ?string $name = 'Alex Doe';

    public int $saves = 0;

    /** @var bool whether the navlist is printed yet (`?nav=late` starts without it) */
    public bool $navReady = true;

    /** @var int bumps the navlist's `wire:key`, so a morph replaces the element */
    public int $navRevision = 0;

    /** @var bool whether the nav items are links that `wire:navigate` to this page again (`?links=1`), not buttons that call select() */
    public bool $links = false;

    /** @var string what is wrapped in `@persist` (`?persist=nav`: the navlist, `?persist=columns`: the whole page.columns), so a wire:navigate keeps the element; '' for nothing */
    public string $persist = '';

    /** @var array<string, string> the query the links carry from page to page (the consumer build, the direction) */
    public array $carry = [];

    /**
     * Move the current item: the nav is morphed, nothing navigates.
     */
    public function select(string $section): void
    {
        $this->section = $section;
    }

    /**
     * Print the navlist: a slot whose navlist was not there when the page was first drawn.
     */
    public function showNav(): void
    {
        $this->navReady = true;
    }

    /**
     * Give the navlist a new `wire:key`, so the next morph swaps the element for a new one.
     */
    public function replaceNav(): void
    {
        $this->navRevision++;
    }

    /**
     * "Save" the form.
     */
    public function save(): void
    {
        $this->saves++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::settings-page');
    }
}
