{{-- extra-primary: the branches of one `@if` chain never show together, and neither do separate blocks that we cannot pair. --}}
<atom:page>
    <atom:page.header title="Job">
        <x-slot:primary>
            @if ($job->isDraft())
                <atom:button variant="primary">Schedule</atom:button>
            @elseif ($job->isScheduled())
                <atom:button variant="primary">Start</atom:button>
            @else
                <atom:button variant="primary">Close</atom:button>
            @endif
        </x-slot:primary>
    </atom:page.header>

    @if ($job->isOverdue())
        <atom:callout variant="warning" heading="Overdue" />
    @endif

    @switch($job->status)
        @case('draft')
            <atom:button variant="primary" wire:click="a">First</atom:button>
            @break
        @case('open')
            <atom:button variant="primary" wire:click="b">Second</atom:button>
            @break
    @endswitch

    <atom:button variant="primary" wire:click="c">Always there</atom:button> {{-- expect: extra-primary --}}
</atom:page>
