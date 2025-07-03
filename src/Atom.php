<?php

namespace Jiannius\Atom;

class Atom
{
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
        if (data_get($params, 'message')) $params['message'] = t($params['message']);

        app('livewire')->current()->dispatch('atom-toast-show', ...$params);
    }

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
        if (data_get($params, 'message')) $params['message'] = t($params['message']);

        $component = app('livewire')->current();

        $component->dispatch('atom-alert-show', ...[
            ...$params,
            'wireId' => $component->getId(),
        ]);
    }

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
        if (data_get($params, 'message')) $params['message'] = t($params['message']);

        $component = app('livewire')->current();

        $component->dispatch('atom-confirm-show', ...[
            ...$params,
            'wireId' => $component->getId(),
        ]);
    }

    public function breadcrumbs()
    {
        return new class () {
            public $create = false;
            public $home = null;
            public $items = [];

            public function home($title, $url = null)
            {
                $this->home = compact('title', 'url');
                return $this;
            }

            public function push($title, $url = null, $icon = null)
            {
                $this->items[] = compact('title', 'url', 'icon');
                return $this;
            }

            public function create()
            {
                $this->create = true;
                return $this;
            }

            public function dispatch()
            {
                app('livewire')->current()->dispatch('atom-breadcrumbs',
                    items: $this->items,
                    home: $this->home,
                    create: $this->create,
                );
            }
        };
    }
}
