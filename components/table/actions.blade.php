@php
/* the actions in the slot are taken out of it (a marker stands for each one there) and placed: a row holds none visible, so each is a row of the ⋯ menu, untoned
   ones first, then a separator, then warning, danger and delete; a slot without an action is the slot as it was */
$registry = app(\Jiannius\Atom\Services\ActionItems::class);
[$slot, $fromSlot] = $registry->collect($slot, 'default');

$placed = \Jiannius\Atom\Services\ActionPlacement::place('row', $registry->keyed($fromSlot), [
    'hostMenu' => $slot->hasActualContent(),
]);
@endphp
<td x-on:click.stop x-on:auxclick.stop {{ $attributes->class('px-row py-row h-(--atom-row-min-h) w-10 text-right') }} data-atom-table-actions>
    {{-- the ⋯ trigger of <atom:page.header> and <atom:table.toolbar>: the shared <atom:menu._more> (a ghost `sm` button here, a row is dense, and a menu at its minimum width) --}}
    <atom:menu._more variant="ghost" size="sm" :wide="false">
        {{ $slot }}<atom:action._list :items="$placed['menu']" place="menu" surface="row" :separator="$placed['separator']" />
    </atom:menu._more>
</td>
