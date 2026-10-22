@props(['total' => 0])

@php
// a PHP comment: empty($x) and dd($y)
if (empty($total)) {
    $total = 0;
}
@endphp

@if (empty($total) && ! isset($x))
    <p>{{ empty($total) ? 'none' : 'some' }}</p>
@endif

<div x-data="{ open: false, items: [], label: '' }"
    x-init="label = total.currency('RM'); if (empty(items)) dd(items)"
    x-text="items.pluck('id').unique().join(',') + name.headline() + stamp.fromNow() + stamp.toDateFormat('Y')">
    <span x-text="atom.currency(total)"></span>
    <span x-text="atom.arr.pluck(items, 'id')"></span>
    <button @click="$refs.menu.classList.toggle('hidden'); this.toggle()">menu</button>
    <button @click="items.toggle(3)">pick</button>
    <button wire:click="toast('Saved')">save</button>
    <button @click="$wire.alert('Hi')">hi</button>
</div>

<script>
    // dd(items) in a comment is not a call
    const x = rows.sum('amount')
    console.log(empty(x), atom.empty(x))
</script>
