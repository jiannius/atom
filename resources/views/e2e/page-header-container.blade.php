@php
// Where page.header's actions go is whether the title's basis and the actions fit the header's own width, not how long the title or the meta line is: the same header in boxes of several widths, with a short and a
// very long meta line, on the consumer's Tailwind: ?tw=floor|latest
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$long = str_repeat('A very long fact about this record that keeps going and going. ', 6);
@endphp

<atom:html title="E2E: page.header container" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
<div class="grid gap-8 p-6">
    @foreach ([480, 700, 767, 768, 800, 1100] as $width)
        @foreach (['short' => 'Net 30', 'long' => $long] as $kind => $meta)
            <div style="width: {{ $width }}px" data-testid="box-{{ $width }}-{{ $kind }}">
                <atom:page.header title="Invoice INV-0042" :facts="[['label' => 'Terms', 'value' => $meta]]">
                    <x-slot:status><atom:status tone="success">Paid</atom:status></x-slot:status>
                    <x-slot:actions><atom:button>Duplicate</atom:button></x-slot:actions>
                    <x-slot:menu><atom:menu.item>Archive</atom:menu.item></x-slot:menu>
                    <x-slot:primary><atom:button variant="primary">Send reminder</atom:button></x-slot:primary>
                </atom:page.header>
            </div>
        @endforeach
    @endforeach

    {{-- how much the actions need is what decides: one small action fits beside the title at 480px, five do not fit at 800px --}}
    <div style="width: 480px" data-testid="box-480-one">
        <atom:page.header title="Invoice INV-0042" :facts="[['label' => 'Terms', 'value' => 'Net 30']]">
            <x-slot:primary><atom:button variant="primary">Edit</atom:button></x-slot:primary>
        </atom:page.header>
    </div>

    <div style="width: 800px" data-testid="box-800-five">
        <atom:page.header title="Invoice INV-0042" :facts="[['label' => 'Terms', 'value' => 'Net 30']]">
            <x-slot:actions>
                <atom:button>Duplicate</atom:button>
                <atom:button>Download PDF</atom:button>
                <atom:button>Print</atom:button>
                <atom:button>Share link</atom:button>
            </x-slot:actions>
            <x-slot:primary><atom:button variant="primary">Send reminder</atom:button></x-slot:primary>
        </atom:page.header>
    </div>

    {{-- the small header of a modal or slide-over keeps its old rule: the actions share the row when they fit --}}
    <div style="width: 480px" data-testid="box-small">
        <atom:page.header title="Edit contact" size="sm" level="2">
            <x-slot:actions><atom:button size="sm">Cancel</atom:button></x-slot:actions>
        </atom:page.header>
    </div>
</div>

@livewireScripts
</atom:html>
