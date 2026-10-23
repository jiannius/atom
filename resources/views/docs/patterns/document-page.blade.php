{{-- Pattern: document page. One printable record (an invoice) with the lines, the totals and the history around it: the
     header, the document as paper, and a rail with the balance, the details and a timeline of what happened. Built from
     atom's components with invented data; copy the structure, not the data.

     The rules this page follows:
     - The header carries the record's state (status pills beside the title) and ONE primary action, the one for the CURRENT state:
       Overdue: Record payment; Draft: Send invoice; Paid: Download receipt. Open it as ?status=overdue|draft|paid. An overdue
       invoice also has a secondary "Send reminder" in `actions`.
     - Crumbs are the shell's `crumbs` slot (with the previous and next document as ghost icon buttons in its `actions` slot), so the
       document's own title is the one heading; the header's `crumbs` prop is the alternative where the shell has no slot to spare.
     - The paper is deliberately not themed: it is a document, so it keeps its own warm white and near-black in the dark theme
       too, and the 500 and 600 weights a printed page uses. It is plain markup, not <atom:table>: a document table has a
       dark rule under its header and no hover.
     - Money is tabular, right-aligned, with the currency named once ("Amount (RM)", "Total (RM)"); identifiers are monospace.
     - The rail is `<atom:card>`s, each headed by a 12px muted title; label and value pairs are `<atom:dd.group>`, a progress bar is
       `<atom:progress>` in the success tone (it is money received, not the accent). From 1080px it sits beside the document; below that it stacks under it.
     - The history is <atom:timeline>: a dot per event in the tone of what happened (danger for a reminder about money owed, success
       for a payment, accent for a send, neutral for a creation), the time under it, and a detail where there is one. Items
       rendered in a @foreach (or an @if that changes) carry a `wire:key`, as here: their folded state is Alpine's. --}}
@php
$styles = $styles ?? [];
// a host's own stylesheet when it has one (config atom.docs.vite), so the page is styled inside the host; none in the test rig
$vite = $vite ?? app(\Jiannius\Atom\Services\Docs::class)->vite();
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$status = request()->query('status', 'overdue');
$status = is_string($status) && in_array($status, ['overdue', 'draft', 'paid'], true) ? $status : 'overdue';

// state label, tone, and the one primary action for that state
[$stateLabel, $stateTone, $primaryLabel] = [
    'overdue' => ['Overdue · 23 days', 'danger', 'Record payment'],
    'draft' => ['Draft', 'neutral', 'Send invoice'],
    'paid' => ['Paid', 'success', 'Download receipt'],
][$status];

$lines = [
    ['Base module', 'Users, roles, branches and an audit trail', 1, 1000.00],
    ['Bookings', 'Appointments and bay scheduling', 1, 2000.00],
    ['Reports', 'Sales and stock reports, scheduled by email', 1, 500.00],
    ['Data migration', 'From the old system, in hours', 12, 95.00],
];

$subtotal = array_sum(array_map(fn ($line) => $line[2] * $line[3], $lines));
$tax = round($subtotal * 0.08, 2);
$total = $subtotal + $tax;
$received = $status === 'paid' ? $total : ($status === 'overdue' ? 2000.00 : 0.00);
$balance = $total - $received;
$money = fn (float $amount) => number_format($amount, 2);
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Document page" :vite="$vite" :styles="$styles" :dark="$dark ?? true">
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

    <x-slot:crumbs>
        <div class="flex items-center gap-1.5 text-fg-subtle" data-testid="document-crumbs">
            <a href="list-page" class="text-fg-muted hover:text-fg">Invoices</a>
            <atom:icon.right class="size-3.5"/>
            <span class="font-id font-medium text-fg">INV-0412</span>
        </div>

        @if ($shell === 'header')
            <span class="ms-2 flex items-center">
                <atom:button variant="ghost" size="sm" icon="up" aria-label="Previous invoice"/>
                <atom:button variant="ghost" size="sm" icon="down" aria-label="Next invoice"/>
            </span>
        @endif
    </x-slot>

    {{-- previous and next: in the sidebar shell the bar's own right side, in the top-nav shell beside the crumbs (its right side is the account) --}}
    @if ($shell === 'sidebar')
    <x-slot:actions>
        <atom:button variant="ghost" size="sm" icon="up" aria-label="Previous invoice"/>
        <atom:button variant="ghost" size="sm" icon="down" aria-label="Next invoice"/>
    </x-slot>
    @endif

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page data-testid="document-page">
        <atom:page.header
        title="Invoice INV-0412"
        :facts="[
            ['label' => 'Bill to', 'value' => 'Customer A'],
            ['label' => 'Issued', 'value' => '2 Oct 2026'],
            ['label' => 'Due', 'value' => '1 Nov 2026 · Net 30'],
            ['label' => 'From quote', 'value' => 'Q-0231', 'mono' => true],
        ]">
            <x-slot:status>
                <atom:status :tone="$stateTone" :label="$stateLabel"/>
                @if ($status === 'overdue')
                    <atom:status tone="info" label="Partly paid"/>
                @endif
            </x-slot:status>

            <x-slot:actions>
                @if ($status === 'overdue')
                    <atom:button icon="send" data-testid="document-reminder">Send reminder</atom:button>
                @endif
                <atom:button>Edit</atom:button>
                <atom:button icon="download">PDF</atom:button>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="copy">Duplicate</atom:menu.item>
                <atom:menu.item icon="delete">Void invoice</atom:menu.item>
            </x-slot:menu>

            <x-slot:primary>
                <atom:button variant="primary" data-testid="document-primary">{{ $primaryLabel }}</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <div class="grid items-start gap-6 min-[1080px]:grid-cols-[minmax(0,1fr)_320px]">
            <article class="grid min-w-0 gap-7 rounded-md bg-[#fffefc] px-5 py-6 text-[12.5px] text-[#1d1c19] shadow-[0_0_0_1px_rgba(45,32,8,0.08),0_2px_10px_rgba(45,32,8,0.05)] sm:px-12 sm:pb-10 sm:pt-11" aria-label="Invoice document" data-testid="paper">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-[26px] items-center justify-center rounded-control bg-[#1d1c19] text-[#fffefc]"><atom:icon.blocks class="size-4"/></div>
                        <div>
                            <b class="block text-[13.5px] font-semibold">Acme Studio Sdn Bhd</b>
                            <span class="text-[#6b665c]">acme.example</span>
                        </div>
                    </div>
                    <div class="sm:text-end">
                        <h2 class="m-0 text-[26px] font-medium tracking-[-0.02em] text-[#1d1c19]">Invoice</h2>
                        <div class="font-id text-[#6b665c]">No. INV-0412</div>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <div class="mb-[3px] text-[11.5px] text-[#8a8478]">Bill to</div>
                        <div class="leading-[1.45]"><b class="font-semibold">Customer A Sdn Bhd</b><br>12 Jalan Contoh 3<br>41200 Klang, Selangor<br><span class="font-id">TIN C0000000000</span></div>
                    </div>
                    <div>
                        <div class="mb-[3px] text-[11.5px] text-[#8a8478]">Issued</div>
                        <div class="leading-[1.45]">2 Oct 2026</div>
                        <div class="mb-[3px] mt-2.5 text-[11.5px] text-[#8a8478]">Due</div>
                        <div class="leading-[1.45]">1 Nov 2026 · Net 30</div>
                    </div>
                    <div>
                        <div class="mb-[3px] text-[11.5px] text-[#8a8478]">Reference</div>
                        <div class="leading-[1.45]">Quote Q-0231</div>
                        <div class="mb-[3px] mt-2.5 text-[11.5px] text-[#8a8478]">Currency</div>
                        <div class="leading-[1.45]">Malaysian Ringgit (MYR)</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse tabular-nums">
                        <thead>
                            <tr class="text-[11.5px] text-[#8a8478]">
                                <th class="border-b border-[#1d1c19] pb-2 text-start font-medium">Description</th>
                                <th class="border-b border-[#1d1c19] pb-2 ps-4 text-end font-medium">Qty</th>
                                <th class="border-b border-[#1d1c19] pb-2 ps-4 text-end font-medium">Unit price</th>
                                <th class="border-b border-[#1d1c19] pb-2 ps-4 text-end font-medium">Amount (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as [$name, $note, $quantity, $price])
                                <tr>
                                    <td class="border-b border-[#1d1c19]/10 py-[11px] align-top">{{ $name }}<span class="mt-0.5 block text-[11.5px] text-[#8a8478]">{{ $note }}</span></td>
                                    <td class="border-b border-[#1d1c19]/10 py-[11px] ps-4 text-end align-top">{{ $quantity }}</td>
                                    <td class="border-b border-[#1d1c19]/10 py-[11px] ps-4 text-end align-top">{{ $money($price) }}</td>
                                    <td class="border-b border-[#1d1c19]/10 py-[11px] ps-4 text-end align-top">{{ $money($quantity * $price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <dl class="m-0 grid gap-1.5 tabular-nums sm:justify-end" data-testid="totals">
                    <div class="grid grid-cols-[minmax(0,1fr)_120px] sm:grid-cols-[170px_130px]"><dt>Subtotal</dt><dd class="m-0 text-end">{{ $money($subtotal) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_120px] sm:grid-cols-[170px_130px]"><dt>Service tax 8%</dt><dd class="m-0 text-end">{{ $money($tax) }}</dd></div>
                    <div class="mt-1 grid grid-cols-[minmax(0,1fr)_120px] border-t border-[#1d1c19] pt-2.5 text-[15px] font-semibold sm:grid-cols-[170px_130px]"><dt>Total (RM)</dt><dd class="m-0 text-end">{{ $money($total) }}</dd></div>
                    @if ($received > 0)
                        <div class="grid grid-cols-[minmax(0,1fr)_120px] sm:grid-cols-[170px_130px]"><dt>Paid 14 Oct</dt><dd class="m-0 text-end">−{{ $money($received) }}</dd></div>
                    @endif
                    @if ($balance > 0)
                        <div class="grid grid-cols-[minmax(0,1fr)_120px] font-semibold text-[#a3261c] sm:grid-cols-[170px_130px]"><dt>Balance due</dt><dd class="m-0 text-end">{{ $money($balance) }}</dd></div>
                    @endif
                </dl>

                <p class="m-0 border-t border-[#1d1c19]/10 pt-4 text-[11.5px] text-[#6b665c]">Thank you for your business. Please quote the invoice number with your transfer.</p>
            </article>

            <aside class="grid min-w-0 gap-3.5 min-[1080px]:sticky min-[1080px]:top-16" aria-label="Invoice details">
                <atom:card class="grid gap-3" data-testid="balance-box">
                    <h3 class="m-0 text-xs font-medium text-fg-subtle">Balance due</h3>
                    <div @class(['text-[26px] font-semibold leading-[1.15] tracking-[-0.02em] tabular-nums', 'text-danger-fg' => $balance > 0 && $status === 'overdue', 'text-success-fg' => $balance <= 0]) data-testid="balance">
                        <small class="me-1 text-[13px] font-medium tracking-normal text-fg-subtle">RM</small>{{ $money($balance) }}
                    </div>
                    <atom:progress :value="(int) round($received / $total * 100)" size="sm" variant="success" aria-label="Paid"/>
                    <div class="text-xs tabular-nums text-fg-subtle">RM {{ $money($received) }} of {{ $money($total) }} received{{ $status === 'overdue' ? ' · 23 days past due' : '' }}</div>
                </atom:card>

                <atom:card class="grid gap-3">
                    <h3 class="m-0 text-xs font-medium text-fg-subtle">Details</h3>
                    <atom:dd.group>
                        <atom:dd label="Customer">Customer A Sdn Bhd</atom:dd>
                        <atom:dd label="Contact">accounts@customer-a.example</atom:dd>
                        <atom:dd label="Terms">Net 30</atom:dd>
                        <atom:dd label="Owner">You</atom:dd>
                    </atom:dd.group>
                </atom:card>

                <atom:card class="grid gap-3" data-testid="history">
                    <h3 class="m-0 text-xs font-medium text-fg-subtle">Activity</h3>
                    <atom:timeline label="Invoice history">
                        @if ($status === 'overdue')
                            <atom:timeline.item wire:key="history-reminder" title="Reminder emailed" body="to accounts@customer-a.example" time="14 Nov" tone="danger">
                                <x-slot:detail>Opened twice, last on 15 Nov.</x-slot:detail>
                            </atom:timeline.item>
                            <atom:timeline.item wire:key="history-payment" title="Payment received" body="RM 2,000.00 by bank transfer" time="14 Oct" tone="success"/>
                        @endif
                        @if ($status !== 'draft')
                            <atom:timeline.item wire:key="history-emailed" title="Emailed" body="to accounts@customer-a.example" time="2 Oct" tone="accent"/>
                        @endif
                        <atom:timeline.item wire:key="history-created" title="Created" body="from quote Q-0231" actor="Ana" time="2 Oct"/>
                    </atom:timeline>
                </atom:card>
            </aside>
        </div>
    </atom:page>

    @livewireScripts
</x-dynamic-component>
