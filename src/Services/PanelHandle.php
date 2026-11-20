<?php

namespace Jiannius\Atom\Services;

/**
 * What `atom()->command($name)` returns: show or close a panel of this kind (`command`, `modal`) on the current Livewire component, by name.
 */
class PanelHandle
{
    public function __construct(public $name, protected string $kind) {}

    /**
     * Show the panel.
     */
    public function show()
    {
        app('livewire')->current()->dispatch('atom-'.$this->kind.'-show', name: $this->name);
    }

    /**
     * Close the panel.
     */
    public function close()
    {
        app('livewire')->current()->dispatch('atom-'.$this->kind.'-close', name: $this->name);
    }
}
