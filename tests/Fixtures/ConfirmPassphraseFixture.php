<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

class ConfirmPassphraseFixture extends Component
{
    public ?string $received = null;

    /**
     * Open the confirm from PHP, with a Livewire method as onAccepted.
     */
    public function ask(): void
    {
        $this->confirm(heading: 'Delete the domain?', passphrase: 'example.com', onAccepted: 'deleteDomain');
    }

    /**
     * Record what the dialog handed on, for the spec to read.
     */
    public function deleteDomain(array $params = []): void
    {
        $this->received = json_encode($params);
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::confirm-passphrase');
    }
}
