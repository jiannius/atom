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

    /**
     * Move the current item: the nav is morphed, nothing navigates.
     */
    public function select(string $section): void
    {
        $this->section = $section;
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
