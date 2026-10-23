<?php

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

new class extends Component {
    use AtomComponent;

    public $body;
};
?>

<div>
    <!-- <atom:table :rows="$rows"/> -->
    <atom:editor wire:model="body"/>
</div>
