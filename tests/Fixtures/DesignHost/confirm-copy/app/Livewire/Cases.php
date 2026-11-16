<?php

namespace App\Livewire;

class Cases
{
    public function noButton(): void
    {
        $this->confirm(heading: 'Void invoice?'); // expect: confirm-copy
    }

    public function genericButton(): void
    {
        $this->confirm(heading: 'Void invoice?', buttonConfirm: 'Yes'); // expect: confirm-copy
    }

    public function viaAtom(): void
    {
        atom()->confirm(message: 'Delete the record?'); // expect: confirm-copy
        app('atom')->confirm(heading: 'Void invoice?', buttonConfirm: __('OK')); // expect: confirm-copy
    }

    public function shouting(): void
    {
        $this->confirm(heading: 'Void invoice?', message: 'THIS cannot be undone', buttonConfirm: 'Void'); // expect: confirm-copy
        $this->confirm(['heading' => 'Void invoice?', 'message' => ['It stays in the list.', 'NEVER do it twice.'], 'buttonConfirm' => 'Void']); // expect: confirm-copy
    }

    public function inAnArray(): array
    {
        return ['heading' => 'Void invoice?', 'buttonConfirm' => 'Proceed']; // expect: confirm-copy
    }

    public function positional(): void
    {
        $this->confirm('Remove the line?', 'warning'); // expect: confirm-copy
    }

    public function fine(): void
    {
        $this->confirm(heading: 'Void invoice?', message: 'It stays in the list. See the PDF and the SMTP log.', buttonConfirm: 'Void');
        $this->confirm(['heading' => 'Void invoice?', 'buttonConfirm' => 'Void invoice']);
        atom()->confirm(heading: 'Void invoice?', buttonConfirm: 'Void', onAccepted: 'doVoid');
    }

    public function unreadable(array $options): void
    {
        $this->confirm($options);
        $this->confirm(...$options);
        $request->confirm(heading: 'Void invoice?');
        $this->confirmation(heading: 'Void invoice?');
        $this->notify(message: 'CANNOT happen');
    }

    // $this->confirm(heading: 'Void invoice?');
}
