<?php

use function Livewire\Volt\{state};

$freeze = function () {
    $this->confirm(heading: 'Freeze?', buttonConfirm: 'Freeze', onAccepted: 'doFreeze');
};

$thaw = function () {
    $this->toast('Thawed');
};

?>

<div>
    <atom:menu.item variant="warning" wire:click="freeze">Freeze</atom:menu.item>
    <atom:menu.item variant="warning" wire:click="thaw">Thaw</atom:menu.item> {{-- expect: tone-unconfirmed --}}
</div>
