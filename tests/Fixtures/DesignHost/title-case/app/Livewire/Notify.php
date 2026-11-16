<?php

namespace App\Livewire;

class Notify
{
    public function save(): void
    {
        $this->toast('Invoice Saved Successfully'); // expect: title-case
        $this->toast(message: 'Saved', heading: 'Invoice Updated Now'); // expect: title-case
        $this->confirm('Delete This Record Permanently?', 'danger'); // expect: title-case
        $this->toast('Invoice saved');
        $this->toast('Synced with Xero');
        $this->toast($message, heading: $heading);
        $this->toast(t('Sent to the customer'));
        $this->toast("Saved Invoice $name Today");
        $this->notify('Not A Toast Or Confirm');
        $this->toast('success', 'Saved', 'Not Read Positional');
    }
}
