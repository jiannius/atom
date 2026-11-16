<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class Receipt extends Mailable
{
    /**
     * A mail is not a screen: it types its own blanks
     */
    public function details(): array
    {
        return [
            ['label' => 'Site', 'value' => $this->site ?: '--'],
            ['label' => 'Due', 'value' => '—'],
            ['label' => 'Ref', 'value' => $this->ref ?? '–'],
        ];
    }
}
