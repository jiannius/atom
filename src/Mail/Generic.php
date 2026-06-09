<?php

namespace Jiannius\Atom\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Generic extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public $settings)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                data_get($this->settings, 'sender_email') ?? config('mail.from.address'),
                data_get($this->settings, 'sender_name') ?? config('mail.from.name'),
            ),
            replyTo: collect([data_get($this->settings, 'reply_to')])->filter()->map(fn($val) => new Address($val))->values()->all(),
            subject: data_get($this->settings, 'subject'),
            tags: data_get($this->settings, 'tags', []),
            metadata: data_get($this->settings, 'metadata', []),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = data_get($this->settings, 'view');
        $markdown = data_get($this->settings, 'markdown');
        $with = [
            ...data_get($this->settings, 'with') ?? [],
            'logo' => data_get($this->settings, 'logo'),
        ];

        return $markdown
            ? new Content(markdown: $markdown, with: $with)
            : new Content(view: $view, with: $with);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return collect(data_get($this->settings, 'attachments'))
            ->map(fn($data) => Attachment::fromPath(data_get($data, 'path'))->as(data_get($data, 'filename')))
            ->toArray();
    }
}
