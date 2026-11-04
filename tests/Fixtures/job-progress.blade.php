<div data-testid="job-progress-page">
    @php $export = $this->jobProgress('export:'.$doctype) @endphp

    @if ($export->exists())
        <atom:progress :job="$export" download="download" :poll="1000" data-testid="export">
            <x-slot:end>
                <button type="button" wire:click="dismiss" data-testid="dismiss">New export</button>
            </x-slot:end>
        </atom:progress>
    @else
        <button type="button" wire:click="again" data-testid="start">Start</button>
    @endif

    @if ($second)
        @php $other = $this->jobProgress('export:other') @endphp

        <atom:progress :job="$other" :poll="1000" data-testid="other" />
    @endif

    @if ($modal)
        <atom:button x-on:click="$dispatch('atom-modal-show', { name: 'job-progress-modal' })" data-testid="open-modal">Open</atom:button>

        <atom:modal name="job-progress-modal">
            @php $inside = $this->jobProgress('export:modal') @endphp

            <atom:progress :job="$inside" :poll="1000" data-testid="inside" />
        </atom:modal>
    @endif

    <button type="button" wire:click="again" data-testid="again">Again</button>

    <span data-testid="downloaded">{{ $downloaded }}</span>
</div>
