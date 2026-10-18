{{-- Pattern: list page. A listing people filter and open records from (invoices): the page header, a totals strip, then ONE panel
     holding saved views, a toolbar and a frameless table. Built from atom's components with invented data; copy the
     structure, not the data.

     The rules this page follows:
     - The panel is the host's: `rounded-panel border bg-surface overflow-hidden`. Everything in it (views, toolbar, rows, footer)
       is separated by hairlines, so the table is `frameless`; a table on its own, with no panel around it, stays framed.
     - ONE primary action: the header's "New invoice". The toolbar's buttons are plain.
     - Saved views are tabs with counts, bound with wire:model in a real page (`wire:model.live="filters.view"`, which
       `WithUrlFilters` can carry in the URL, with `:current` for the first paint). This page has no Livewire component, so
       each view is a link: `?view=unpaid`. The host supplies the counts; atom never runs a count query.
     - Money: `numeric` on the column and the cell (right-aligned, tabular figures); the currency is named once, in the header
       ("Amount (RM)"), never in every cell. Identifiers (an invoice number) are monospace: `font-id`.
     - A two-line cell (a name and a 12px line under it) keeps its padding: rows have a minimum height, never a fixed one.
     - A status is a tone plus a word. Overdue is danger, a partial payment is info, waiting is neutral, done is success.
     - On a phone the table scrolls sideways INSIDE the panel; the page never does.

     Open it as ?view=all|unpaid|overdue|paid|draft to see the views filter the rows. --}}
@php
$styles = $styles ?? [];
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

// number, customer, what it is for, issued, due, total, still owed, state, label
$invoices = [
    ['INV-0412', 'Customer A', 'Monthly support, October', '2 Oct', '1 Nov', 1300.00, 1300.00, 'sent', 'Due in 13 days'],
    ['INV-0411', 'Customer B', 'Website care plan and domain', '21 Sep', '21 Oct', 2000.00, 2000.00, 'sent', 'Due in 2 days'],
    ['INV-0410', 'Customer C', 'Booking site, milestone 2', '26 Aug', '25 Sep', 750.00, 750.00, 'overdue', '23 days overdue'],
    ['INV-0409', 'Customer D', 'Yearly support', '24 Aug', '23 Sep', 650.00, 150.00, 'overdue', '25 days overdue'],
    ['INV-0408', 'Customer B', 'Monthly support, September', '12 Aug', '11 Sep', 2000.00, 0.00, 'paid', 'Paid'],
    ['INV-0407', 'Customer E', 'Domain and hosting, one year', '9 Jul', '8 Aug', 500.00, 0.00, 'paid', 'Paid'],
    ['INV-0406', 'Customer F', 'Booking site, milestone 1', '20 May', '19 Jun', 7500.00, 0.00, 'paid', 'Paid'],
    ['INV-0405', 'Customer G', 'Website care plan', '', '', 1500.00, 1500.00, 'draft', 'Draft'],
];

$views = [
    'all' => fn ($row) => true,
    'unpaid' => fn ($row) => $row[6] > 0 && $row[7] !== 'draft',
    'overdue' => fn ($row) => $row[7] === 'overdue',
    'paid' => fn ($row) => $row[7] === 'paid',
    'draft' => fn ($row) => $row[7] === 'draft',
];

$view = request()->query('view', 'all');
$view = is_string($view) && array_key_exists($view, $views) ? $view : 'all';

// the host's counts: here from the rows; in an app, one grouped query kept in a computed property
$counts = array_map(fn ($filter) => count(array_filter($invoices, $filter)), $views);

$rows = array_values(array_filter($invoices, $views[$view]));
$total = array_sum(array_column($rows, 5));

$tones = ['paid' => 'success', 'overdue' => 'danger', 'sent' => 'neutral', 'draft' => 'neutral'];
$money = fn (float $amount) => number_format($amount, 2);
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="List page" :vite="[]" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" @class([
            'flex items-center gap-[9px] px-2 pb-3 pt-1.5' => $shell === 'sidebar',
            'flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight' => $shell === 'header',
        ])>
            <div class="flex size-[26px] items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="truncate text-sm font-semibold">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:nav>
        @include('atom::docs.patterns.nav')
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page data-testid="list-page">
        <atom:page.header title="Invoices" :facts="['Acme Studio', 'Last 7 months']">
            <x-slot:meta>
                <span><b class="font-medium text-fg">{{ $counts['all'] }}</b> invoices</span>
            </x-slot:meta>

            <x-slot:primary>
                <atom:button size="sm" variant="primary" icon="add" data-testid="list-primary">New invoice</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <atom:stat.group>
            <atom:stat label="Outstanding" prefix="RM" value="12,480.00" breakdown="7 unpaid invoices" href="?view=unpaid"/>
            <atom:stat label="Overdue" prefix="RM" value="3,200.00" tone="danger" breakdown="2 invoices, oldest 78 days" href="?view=overdue"/>
            <atom:stat label="Due in the next 30 days" prefix="RM" value="5,400.00" breakdown="3 invoices" href="?view=unpaid"/>
            <atom:stat label="Drafts" value="1" breakdown="not sent yet" href="?view=draft"/>
        </atom:stat.group>

        <div class="min-w-0 overflow-hidden rounded-panel border bg-surface" data-testid="list-panel">
            <atom:table.views label="Invoice views" :counts="$counts" :current="$view">
                <atom:table.views.item value="all" label="All" href="?view=all"/>
                <atom:table.views.item value="unpaid" label="Unpaid" href="?view=unpaid"/>
                <atom:table.views.item value="overdue" label="Overdue" href="?view=overdue"/>
                <atom:table.views.item value="paid" label="Paid" href="?view=paid"/>
                <atom:table.views.item value="draft" label="Drafts" href="?view=draft"/>
            </atom:table.views>

            {{-- The toolbar. In a Livewire page it goes in the table's `header` slot (`<x-slot:header>`, no `strip`): the table draws
                 the hairline around it and swaps it for the bulk-action bar while rows are ticked. That slot needs a component, which
                 this page has not, so the toolbar sits above the table here and draws its own strip. --}}
            <atom:table.toolbar strip>
                <atom:table.search placeholder="Search number or customer"/>

                <x-slot:end>
                    <atom:button size="sm" variant="ghost" icon="download">Export</atom:button>
                </x-slot:end>
            </atom:table.toolbar>

            {{-- No Livewire component here, so no rows, sorting or paging come from one. In a real page the rows come from a component
                 (`:paginate="$this->invoices"` draws the pager); `:empty` is derived from the paginator then. --}}
            <atom:table frameless :empty="! $rows">
                <x-slot:columns>
                    <atom:table.column>Number</atom:table.column>
                    <atom:table.column>Customer</atom:table.column>
                    <atom:table.column>Issued</atom:table.column>
                    <atom:table.column>Due</atom:table.column>
                    <atom:table.column numeric>Amount (RM)</atom:table.column>
                    <atom:table.column>Status</atom:table.column>
                </x-slot:columns>

                <x-slot:rows>
                    @foreach ($rows as [$number, $customer, $what, $issued, $due, $amount, $owed, $state, $label])
                        <atom:table.row href="document-page">
                            <atom:table.cell><span class="font-id">{{ $number }}</span></atom:table.cell>
                            <atom:table.cell class="max-w-64">
                                <div class="truncate font-medium">{{ $customer }}</div>
                                <div class="mt-0.5 truncate text-xs text-fg-subtle">{{ $what }}</div>
                            </atom:table.cell>
                            <atom:table.cell>{{ $issued ?: '—' }}</atom:table.cell>
                            <atom:table.cell :muted="$state !== 'overdue'" :class="$state === 'overdue' ? 'text-danger-fg!' : ''">{{ $due ?: '—' }}</atom:table.cell>
                            <atom:table.cell numeric>
                                {{ $money($amount) }}
                                @if ($owed > 0 && $owed < $amount)
                                    <div class="mt-0.5 text-xs text-fg-subtle">{{ $money($owed) }} due</div>
                                @endif
                            </atom:table.cell>
                            <atom:table.cell><atom:status :tone="$tones[$state]" :label="$label"/></atom:table.cell>
                        </atom:table.row>
                    @endforeach
                </x-slot:rows>
            </atom:table>

            @if ($rows)
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t px-3.5 py-2.5 text-xs tabular-nums text-fg-subtle">
                    <span data-testid="list-sum">{{ count($rows) }} of {{ $counts['all'] }} invoices · RM {{ $money($total) }}</span>
                    <span>Sorted by issue date, newest first</span>
                </div>
            @endif
        </div>
    </atom:page>

    @livewireScripts
</x-dynamic-component>
