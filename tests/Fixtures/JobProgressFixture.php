<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Jiannius\Atom\Traits\WithJobProgress;
use Livewire\Component;

/**
 * A page that shows a job's progress with <atom:progress :job>. boot() signs a user in, so the user is there on the update
 * requests too, as a host's session would give it. $doctype is a client-writable property that feeds the name (a client can
 * only switch between its own entries); $second adds a second job on the page, $modal puts one inside a closed modal.
 */
class JobProgressFixture extends Component
{
    use WithJobProgress;

    public string $doctype = 'invoice';

    public bool $second = false;

    public bool $modal = false;

    public string $downloaded = '';

    public int $renders = 0;

    /**
     * Sign the fixture user in: on the first request and on every update.
     */
    public function boot(): void
    {
        Auth::setUser(new GenericUser(['id' => 7]));
    }

    /**
     * The host's own download action: reads the path on the server, as a host re-checking access would.
     */
    public function download(): void
    {
        $export = $this->jobProgress('export:'.$this->doctype);

        abort_unless($export->isFinished() && filled($export->file), 404);

        $this->downloaded = $export->fileName;
    }

    /**
     * Forget the entry: the dismiss button.
     */
    public function dismiss(): void
    {
        $this->forgetJobProgress('export:'.$this->doctype);
    }

    /**
     * Re-render, nothing else (the host's "run it again" button after an entry was replaced).
     */
    public function again(): void
    {
        $this->renders++;
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::job-progress');
    }
}
