{{-- Placeholder page content for the two shell patterns. <main> is unpadded, so the page pads itself (in a real app,
     <atom:page> does this from v4.0-alpha.1). --}}
@php
$page = request()->query('page', 'invoices');
$title = str($page)->headline();
$rows = [
    ['INV-0412', 'Customer A', 'Paid', '1,280.00'],
    ['INV-0411', 'Customer B', 'Overdue', '640.00'],
    ['INV-0410', 'Customer C', 'Sent', '2,115.50'],
    ['INV-0409', 'Customer D', 'Draft', '320.00'],
];
$tones = [
    'Paid' => 'bg-success-bg text-success-fg',
    'Overdue' => 'bg-danger-bg text-danger-fg',
    'Sent' => 'bg-info-bg text-info-fg',
    'Draft' => 'bg-neutral-bg text-neutral-fg',
];
@endphp

<div class="flex flex-col gap-5 px-7 pb-24 pt-6 max-md:px-4" data-testid="pattern-page">
    <div>
        <div class="text-2xl font-semibold" data-testid="pattern-title">{{ $title }}</div>
        <div class="mt-1.5 flex flex-wrap gap-x-4 text-fg-muted">
            <span><b class="font-medium text-fg">4</b> invoices</span>
            <span><b class="font-medium text-fg">1</b> overdue</span>
        </div>
    </div>

    <div class="overflow-x-auto rounded-panel border border-border bg-surface">
        <table class="w-full min-w-[520px] border-collapse tabular-nums">
            <thead>
                <tr class="text-start text-xs text-fg-subtle">
                    <th class="border-b border-border px-row py-2 text-start font-medium">Number</th>
                    <th class="border-b border-border px-row py-2 text-start font-medium">Customer</th>
                    <th class="border-b border-border px-row py-2 text-start font-medium">Status</th>
                    <th class="border-b border-border px-row py-2 text-end font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as [$number, $customer, $status, $amount])
                    <tr class="[&:not(:last-child)>td]:border-b [&>td]:border-border">
                        <td class="min-h-row px-row py-row leading-row"><span class="font-id">{{ $number }}</span></td>
                        <td class="px-row py-row leading-row font-medium">{{ $customer }}</td>
                        <td class="px-row py-row"><span class="inline-flex h-[22px] items-center rounded-pill px-2 text-xs font-medium {{ $tones[$status] }}">{{ $status }}</span></td>
                        <td class="px-row py-row text-end leading-row">{{ $amount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
