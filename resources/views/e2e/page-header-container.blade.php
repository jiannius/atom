@php
// Where page.header's actions go is the header's own width, not its content: the same header in boxes of several widths, with a short and a
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

    {{-- the small header of a modal or slide-over keeps its old rule: the actions share the row when they fit --}}
    <div style="width: 480px" data-testid="box-small">
        <atom:page.header title="Edit contact" size="sm" level="2">
            <x-slot:actions><atom:button size="sm">Cancel</atom:button></x-slot:actions>
        </atom:page.header>
    </div>
</div>

@livewireScripts
</atom:html>
