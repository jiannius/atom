<div class="grid gap-8 p-4">
    <atom:button size="sm" wire:click="bump" data-testid="bump">Re-render ({{ $renders }})</atom:button>

    <atom:timeline data-testid="events">
        <atom:timeline.item title="Reminder emailed" body="to accounts@example.test" actor="Ana" time="14 Aug, 3:05 pm" tone="danger" wire:key="e1">
            <x-slot:detail>Opened twice, last on 15 Aug.</x-slot:detail>
        </atom:timeline.item>
        <atom:timeline.item title="Payment received" body="RM 2,000.00" time="14 Jul" tone="success" status="Cleared" wire:key="e2" />
        <atom:timeline.item title="Created" actor="Ana" time="29 Jun" wire:key="e3" />
    </atom:timeline>

    <atom:timeline kind="step" data-testid="steps">
        <atom:timeline.item title="Queued" state="done" time="2s" wire:key="s1">Waiting for a worker.</atom:timeline.item>
        <atom:timeline.item title="Build" state="current" status="Running" time="1m 12s" wire:key="s2">Compiling the assets.</atom:timeline.item>
        <atom:timeline.item title="Deploy" state="pending" wire:key="s3">Not started.</atom:timeline.item>
        <atom:timeline.item title="Check" state="failed" status="Timed out" wire:key="s4">The site did not answer in time.</atom:timeline.item>
    </atom:timeline>

    <atom:timeline kind="message" data-testid="messages">
        @foreach ($messages as $i => $message)
            <atom:timeline.item :side="$message['side']" :speaker="$message['speaker']" :body="$message['body']" time="10:0{{ $i }}" wire:key="m{{ $i }}" />
        @endforeach

        <x-slot:composer>
            <form wire:submit="send" class="flex items-start gap-2">
                <atom:input wire:model="draft" aria-label="Message" data-testid="draft" />
                <atom:button type="submit" size="sm">Send</atom:button>
            </form>
        </x-slot:composer>
    </atom:timeline>
</div>
