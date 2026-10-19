<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Livewire\Component;

/**
 * Hosts the three timeline kinds in a Livewire component, so the E2E can fold a step or open a detail and then re-render the
 * component: the open state is Alpine's, and a morph must leave it alone.
 */
class TimelineFixture extends Component
{
    public int $renders = 0;

    public string $draft = '';

    /** @var array<int, array{side: string, speaker: string, body: string}> */
    public array $messages = [
        ['side' => 'start', 'speaker' => 'Customer', 'body' => 'Can you move the visit to Friday?'],
        ['side' => 'end', 'speaker' => 'You', 'body' => 'Friday at 10 works.'],
    ];

    /**
     * Re-render the component, so Livewire morphs the timelines.
     */
    public function bump(): void
    {
        $this->renders++;
    }

    /**
     * Add the composer's text as a message of yours.
     */
    public function send(): void
    {
        if (trim($this->draft) !== '') {
            $this->messages[] = ['side' => 'end', 'speaker' => 'You', 'body' => $this->draft];
            $this->draft = '';
        }
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::timeline-fixture');
    }
}
