<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Support\Facades\Blade;
use Livewire\Component;

/**
 * A component with no atom trait that renders table markup outside its own view:
 * in mount() and in an action (an export, a PDF, a mail body).
 */
class TraitGuardActionFixture extends Component
{
    public string $html = '';

    /**
     * Render table markup while mounting
     */
    public function mount(): void
    {
        $this->html = Blade::render('<atom:table><x-slot:rows></x-slot:rows></atom:table>');
    }

    /**
     * Render table markup in an action
     */
    public function export(): void
    {
        $this->html = Blade::render('<atom:table><x-slot:rows></x-slot:rows></atom:table>');
    }

    /**
     * Render the fixture
     */
    public function render()
    {
        return '<div>ok</div>';
    }
}
