<?php

namespace Jiannius\Atom\Services;

/**
 * What `atom()->modal($name)` returns: a panel that can also slide in from a side.
 */
class ModalHandle extends PanelHandle
{
    public function __construct($name)
    {
        parent::__construct($name, 'modal');
    }

    /**
     * Slide the modal in from a side.
     */
    public function slide($position = null)
    {
        app('livewire')->current()->dispatch('atom-modal-show', name: $this->name, variant: 'slide', position: $position);
    }
}
