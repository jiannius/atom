<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * A Livewire component that sits inside FormDirtyFixture's form and owns a wire:model field of its own: its value is not the parent's
 * `$wire` state, so the form's "Unsaved changes" label can only learn of it from the field's own events.
 */
class FormDirtyChildFixture extends Component
{
    public ?string $remark = 'child preset';

    /**
     * Render the child.
     */
    public function render(): string
    {
        return <<<'HTML'
        <div data-testid="child">
            <atom:input label="Remark" wire:model="remark" data-probe="remark" />
        </div>
        HTML;
    }
}
