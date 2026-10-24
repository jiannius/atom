{{-- The real bar is an Alpine template that opens when `$wire._table.checkboxes` has an id (the `WithTable` trait), so a static page
     cannot open it: these are its two looks, drawn with the classes the table prints. In a Livewire page you write
     `<x-slot:checked tone="accent"> ...bulk actions... </x-slot:checked>` and the table does the rest. --}}
<div class="max-w-2xl space-y-8">
    {{-- framed: the bar is its own strip above the panel --}}
    <div class="space-y-4">
        <div class="flex min-h-10 items-center gap-3 rounded-panel bg-accent-soft px-3.5 py-2" data-tone="accent">
            <div class="flex shrink-0 items-center gap-2 text-sm font-medium text-accent-ink">
                <atom:icon.double-check class="size-4"/>
                <div>2 Selected</div>
            </div>
            <div class="flex grow items-center gap-3"><atom:button size="sm">Send reminders</atom:button></div>
        </div>
    </div>

    {{-- frameless: the bar is the top strip of the panel your page draws --}}
    <div class="overflow-hidden rounded-panel border bg-surface">
        <div class="flex min-h-10 items-center gap-3 border-b bg-accent-soft px-3 py-2.5" data-tone="accent">
            <div class="flex shrink-0 items-center gap-2 text-sm font-medium text-accent-ink">
                <atom:icon.double-check class="size-4"/>
                <div>2 Selected</div>
            </div>
            <div class="flex grow items-center gap-3"><atom:button size="sm">Send reminders</atom:button></div>
        </div>
        <div class="px-3 py-2.5 text-sm text-fg-muted">Rows go here.</div>
    </div>
</div>
