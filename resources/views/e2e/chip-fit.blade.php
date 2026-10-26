@php
// A chip must not grow its control (?tw=floor|latest, ?density=compact|comfortable): the multi-email field and the multiple
// listbox select, each empty and with one value, plus the same with enough values to wrap onto a second row.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent'], ['value' => 'c', 'label' => 'Paid']];
@endphp

<atom:html title="E2E: Chip fit" :vite="[]" :dark="false" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="mx-auto grid max-w-2xl grid-cols-[minmax(0,1fr)] gap-6 p-8" x-data="{
    none: [],
    one: [{ name: 'Ada Lovelace', email: 'ada@example.com' }],
    bad: [{ name: 'nobody', email: 'nobody' }],
    many: [
        { name: 'Ada Lovelace', email: 'ada@example.com' },
        { name: 'Grace Hopper', email: 'grace@example.com' },
        { name: 'Alan Turing', email: 'alan@example.com' },
        { name: 'Edsger Dijkstra', email: 'edsger@example.com' },
    ],
    long: [{ name: '{{ str_repeat('a', 138) }}@example.com', email: '{{ str_repeat('a', 138) }}@example.com' }],
    selNone: [],
    selOne: ['a'],
    selMany: ['a', 'b', 'c'],
}">
    <div data-testid="email-none"><atom:input type="email" multiple x-model="none"/></div>
    <div data-testid="email-one"><atom:input type="email" multiple x-model="one"/></div>
    <div data-testid="email-bad"><atom:input type="email" multiple x-model="bad"/></div>
    <div data-testid="email-long"><atom:input type="email" multiple x-model="long"/></div>
    <div data-testid="email-many"><atom:input type="email" multiple x-model="many"/></div>

    <div data-testid="select-none"><atom:select variant="listbox" multiple :options="$options" x-model="selNone"/></div>
    <div data-testid="select-one"><atom:select variant="listbox" multiple :options="$options" x-model="selOne"/></div>
    <div data-testid="select-many"><atom:select variant="listbox" multiple :options="$options" x-model="selMany"/></div>
</div>

@livewireScripts
</atom:html>
