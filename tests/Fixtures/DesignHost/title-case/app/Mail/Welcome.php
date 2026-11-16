<?php

namespace App\Mail;

use Illuminate\Notifications\Messages\MailMessage;

class Welcome
{
    public function toMail(): MailMessage
    {
        $this->toast('Welcome To Your Account Today');

        return (new MailMessage)->subject('Welcome To Your Account');
    }
}
