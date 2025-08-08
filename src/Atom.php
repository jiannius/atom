<?php

namespace Jiannius\Atom;

use Illuminate\Support\Arr;

class Atom
{
    /**
     * Trigger action from anywhere in the application
     */
    public function action($name, $params = [])
    {
        $name = str($name)->namespace()->toString();
        $method = Arr::pull($params, 'method') ?? 'handle';

        $class = collect([
            "App\Actions\\$name",
            "Jiannius\Atom\Actions\\$name",
        ])->first(fn ($ns) => class_exists($ns));

        throw_if(!$class, \Exception::class, "\App\Actions\\$name not found");

        return app($class)->$method($params);
    }

    /**
     * Trigger modal from anywhere in the application
     */
    public function modal($name)
    {
        return new class ($name) {
            public function __construct(public $name) {}

            public function show()
            {
                app('livewire')->current()->dispatch('atom-modal-show', name: $this->name);
            }

            public function slide($slide)
            {
                app('livewire')->current()->dispatch('atom-modal-slide', name: $this->name, slide: $slide);
            }

            public function close()
            {
                app('livewire')->current()->dispatch('atom-modal-close', name: $this->name);
            }
        };
    }

    /**
     * Trigger toast from anywhere in the application
     */
    public function toast(
        $message = null,
        $variant = null,
        $heading = null,
        $subheading = null,
        $html = null,
        $position = null,
        $align = null,
        $delay = null,
        $navigate = null,
        $url = null,
    ) {
        $params = array_filter(compact(
            'message',
            'variant',
            'heading',
            'subheading',
            'html',
            'position',
            'align',
            'delay',
            'navigate',
            'url',
        ));

        if (data_get($params, 'heading')) $params['heading'] = t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => t($m));

        app('livewire')->current()->dispatch('atom-toast-show', ...$params);
    }

    /**
     * Trigger alert from anywhere in the application
     */
    public function alert(
        $message = null,
        $variant = null,
        $heading = null,
        $subheading = null,
        $html = null,
        $button = null,
        $onDismissed = null,
    ) {
        $params = array_filter(compact(
            'variant',
            'heading',
            'subheading',
            'message',
            'html',
            'button',
            'onDismissed',
        ));

        if (data_get($params, 'heading')) $params['heading'] = t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => t($m));

        $component = app('livewire')->current();

        $component->dispatch('atom-alert-show', ...[
            ...$params,
            'wireId' => $component->getId(),
        ]);
    }

    /**
     * Trigger confirm from anywhere in the application
     */
    public function confirm(
        $message = null,
        $variant = null,
        $heading = null,
        $subheading = null,
        $html = null,
        $buttonConfirm = null,
        $buttonCancel = null,
        $password = false,
        $passphrase = null,
        $passphraseLabel = null,
        $onAccepted = null,
        $onRejected = null,
    ) {
        $params = array_filter(compact(
            'message',
            'variant',
            'heading',
            'subheading',
            'html',
            'buttonConfirm',
            'buttonCancel',
            'password',
            'passphrase',
            'passphraseLabel',
            'onAccepted',
            'onRejected',
        ));

        if (data_get($params, 'heading')) $params['heading'] = t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => t($m));

        $component = app('livewire')->current();

        $component->dispatch('atom-confirm-show', ...[
            ...$params,
            'wireId' => $component->getId(),
        ]);
    }

    /**
     * Build the breadcrumbs object
     */
    public function breadcrumbs()
    {
        return new class () {
            public $home;
            public $items = [];
            public $replace = false;

            public function home($title, $url = null)
            {
                $this->home = ['title' => t($title), 'url' => $url];
                return $this;
            }

            public function push($title, $url = null, $icon = null)
            {
                $this->items[] = ['title' => t($title), 'url' => $url ?? url()->current(), 'icon' => $icon];
                return $this;
            }

            public function replace()
            {
                $this->replace = true;
                return $this;
            }

            public function build()
            {
                return [
                    'home' => $this->home,
                    'items' => $this->items,
                    'replace' => $this->replace,
                ];
            }
        };
    }

    /**
     * Send an email
     */
    public function mail(
        $to = [],
        $cc = [],
        $bcc = [],
        $senderName = null,
        $senderEmail = null,
        $replyTo = null,
        $subject = '',
        $view = '',
        $markdown = 'atom::mail.generic',
        $content = '',
        $cta = null,
        $with = [],
        $tags = [],
        $metadata = [],
        $attachments = [],
        $track = false,
        $queue = false,
        $later = null,
        $logo = null,
    ) {
        throw_if(!$to, \Exception::class, 'Missing recipient "to"');
        throw_if(!$view && !$markdown && !$content, \Exception::class, 'Empty mail content or missing view');

        $mail = \Illuminate\Support\Facades\Mail::to($to)->cc($cc)->bcc($bcc);

        $message = new \Jiannius\Atom\Mail\Generic([
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'reply_to' => $replyTo,
            'subject' => $subject,
            'view' => $view,
            'markdown' => $markdown,
            'with' => $content
                ? ['cta' => $cta, 'content' => $content]
                : $with,
            'tags' => $tags,
            'metadata' => $metadata,
            'attachments' => $attachments,
            'track' => $track,
            'logo' => $logo,
        ]);

        if ($queue) {
            if (is_string($queue)) $message = $message->onQueue($queue);
            $mail->queue($message);
        }
        else if ($later) {
            $mail->later($later, $message);
        }
        else {
            $mail->send($message);
        }    
    }
}
