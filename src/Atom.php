<?php

namespace Jiannius\Atom;

use Illuminate\Support\Arr;
use Jiannius\Atom\Contracts\WebAction;
use Jiannius\Atom\Services\Asset;
use Jiannius\Atom\Services\Broadcast;
use Jiannius\Atom\Services\Recaptcha;
use Jiannius\Atom\Services\Sitemap;

class Atom
{
    /**
     * The request attribute that holds how many crumbs the page's own trail declares (set by WithBreadcrumbs, read by the layouts).
     */
    public const CRUMB_COUNT_ATTRIBUTE = 'atom.breadcrumbs.count';

    /**
     * Asset manager
     */
    public function asset()
    {
        return new Asset();
    }

    /**
     * reCAPTCHA verifier
     */
    public function recaptcha()
    {
        return new Recaptcha();
    }

    /**
     * Broadcast a message
     */
    public function broadcast()
    {
        return new Broadcast();
    }

    /**
     * Sitemap generator
     */
    public function sitemap()
    {
        return new Sitemap();
    }

    /**
     * Build a DOM id that is unique on the page but stable across re-renders.
     *
     * A random id (uniqid()) changes on every render, which makes Livewire's morph
     * treat the markup as new: it replaces the node — orphaning any listener bound
     * to it — and re-evaluates an x-data expression carrying the id, leaving the
     * component's Alpine effects bound to the discarded data object. Numbering per
     * Livewire component instead keeps the id identical as long as the component
     * renders the same widgets in the same order.
     */
    public function uid(string $prefix = 'atom') : string
    {
        $component = app('livewire')->current() ?: null;
        $scope = $component ? $component->getId() : 'page';
        $counts = app()->bound('atom.uids') ? app('atom.uids') : [];

        $counts[$scope] = ($counts[$scope] ?? -1) + 1;
        app()->instance('atom.uids', $counts);

        return $prefix.'-'.$scope.'-'.$counts[$scope];
    }

    /**
     * Throw when the Livewire component whose view is being rendered lacks the trait
     * a piece of markup depends on, naming the trait and the component class.
     *
     * "Being rendered" is Livewire's render stack, which is pushed around a
     * component's view render (initial render, update, nested children and lazy loads)
     * and not around mount, hydrate or an action. So Blade::render('<atom:table>') in an
     * action (an export, a PDF, a mail), a static page and a docs demo have no
     * component to check and nothing happens.
     *
     * Two guards keep it from a false alarm. Livewire before 4.4.8 pops the render
     * stack without a `finally`, so a view that threw leaves a stale entry on a
     * long-lived worker (Octane, a queue): the top counts only when it is also the component Livewire
     * is currently working on, which is popped in a `finally`. An island render never
     * pushes the stack, but shares `__livewire` with the view, so that is read instead,
     * again only when it is the current component. If Livewire stops exposing the render
     * stack, nothing is checked.
     *
     * @param  class-string  $trait
     * @param  string  $for  what needs it, e.g. "<atom:table>"
     */
    public function requireTrait(string $trait, string $for) : void
    {
        if (!property_exists(\Livewire\Mechanisms\HandleComponents\HandleComponents::class, 'renderStack')) {
            return;
        }

        $current = app('livewire')->current() ?: null;

        if (!$current) {
            return;
        }

        $stack = \Livewire\Mechanisms\HandleComponents\HandleComponents::$renderStack;
        $top = end($stack) ?: null;

        $component = $top === $current
            ? $top
            : (app('view')->shared('__livewire') === $current ? $current : null);

        if (!$component || in_array($trait, class_uses_recursive($component), true)) {
            return;
        }

        throw new \LogicException(sprintf(
            '%s needs the %s trait: add `use \\%s;` to %s.',
            $for, class_basename($trait), $trait, get_class($component),
        ));
    }

    /**
     * Trigger action from anywhere in the application
     */
    public function action($name, $params = [])
    {
        $class = $this->resolveAction($name);
        $method = Arr::pull($params, 'method') ?? 'handle';

        throw_if(!$class, \Exception::class, "\App\Actions\\".str($name)->namespace()." not found");

        return app($class)->$method($params);
    }

    /**
     * Trigger action from the public POST /atom/action/{name} endpoint
     *
     * Unlike action(), this is reachable by anyone who can hit the app, so it
     * only runs actions that opted in via the WebAction contract, only ever
     * calls handle(), and honours an optional authorize() on the action.
     */
    public function webAction($name, $params = []) : mixed
    {
        $class = $this->resolveAction($name);

        // Same answer for "not opted in" and "does not exist" — otherwise the
        // endpoint reports which action classes the app has. The refusal is
        // logged instead, so a silently-dark front-end feature is diagnosable.
        if (!$class || !is_subclass_of($class, WebAction::class)) {
            if ($class) {
                logger()->warning(
                    "[atom] Refused POST /atom/action/$name: $class does not implement ".WebAction::class
                    .'. Actions are not reachable from the browser until they do — implement the contract if this'
                    .' action is meant to be called from JS (and give it an authorize() unless it is safe for guests).'
                    .' An action that overrides a packaged one opts in by extending it.'
                );
            }

            return response()->json(['message' => 'Not Found.'], 404);
        }

        // method stays reserved over HTTP as it is for action(), so an action's
        // handle() sees the same payload however it was invoked.
        Arr::pull($params, 'method');

        $action = app($class);

        if (method_exists($action, 'authorize') && !$action->authorize($params)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $action->handle($params);
    }

    /**
     * Resolve an action's class name from its dot-name
     *
     * The host app's App\Actions\{Name} wins over the package's, so an app can
     * override a packaged action.
     */
    protected function resolveAction($name) : ?string
    {
        $name = str($name)->namespace()->toString();

        return collect([
            "App\Actions\\$name",
            "Jiannius\Atom\Actions\\$name",
        ])->first(fn ($ns) => class_exists($ns));
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

            public function slide($position = null)
            {
                app('livewire')->current()->dispatch('atom-modal-show', name: $this->name, variant: 'slide', position: $position);
            }

            public function close()
            {
                app('livewire')->current()->dispatch('atom-modal-close', name: $this->name);
            }
        };
    }

    /**
     * Trigger command palette from anywhere in the application
     */
    public function command($name)
    {
        return new class ($name) {
            public function __construct(public $name) {}

            public function show()
            {
                app('livewire')->current()->dispatch('atom-command-show', name: $this->name);
            }

            public function close()
            {
                app('livewire')->current()->dispatch('atom-command-close', name: $this->name);
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

        if (data_get($params, 'heading')) $params['heading'] = (string) t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = (string) t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => (string) t($m));

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

        if (data_get($params, 'heading')) $params['heading'] = (string) t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = (string) t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => (string) t($m));

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
        $reason = false,
        $reasonLabel = null,
        $reasonPlaceholder = null,
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
            'reason',
            'reasonLabel',
            'reasonPlaceholder',
            'onAccepted',
            'onRejected',
        ));

        if (data_get($params, 'heading')) $params['heading'] = (string) t($params['heading']);
        if (data_get($params, 'subheading')) $params['subheading'] = (string) t($params['subheading']);
        if (data_get($params, 'message')) $params['message'] = Arr::map((array) $params['message'], fn ($m) => (string) t($m));

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
                $this->home = ['title' => (string) t($title), 'url' => $url];
                return $this;
            }

            public function push($title, $url = null, $icon = null)
            {
                $this->items[] = ['title' => (string) t($title), 'url' => $url ?? url()->current(), 'icon' => $icon];
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
     * Whether a shell's crumbs slot holds nothing but the trail: the top-level nodes are whitespace or comments, plus exactly
     * one element that is the trail (data-atom-breadcrumbs) or the element that persists it (x-persist, whose only child
     * element is the trail). Anything else, a wrapper or a sibling included, is the host's own content. The layouts ask
     * it before they leave a bar out on the first frame, and it fails closed: a slot that cannot be parsed is not "alone".
     * Keep it in step with syncHeader() in resources/js/alpinejs/breadcrumbs.js, which applies the same rule in the browser.
     */
    public function crumbsHoldOnlyTheTrail(mixed $crumbs): bool
    {
        $html = trim((string) $crumbs);

        if ($html === '' || ! class_exists(\DOMDocument::class)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();

            // the XML declaration makes libxml read the string as UTF-8; its processing instruction sits outside <body>
            if (! $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return false;
            }

            $body = $document->getElementsByTagName('body')->item(0);

            if (! $body) {
                return false;
            }

            $holder = null;

            foreach ($body->childNodes as $node) {
                if ($node->nodeType === XML_COMMENT_NODE || ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '')) {
                    continue;
                }

                if ($node->nodeType !== XML_ELEMENT_NODE || $holder !== null) {
                    return false;
                }

                $holder = $node;
            }

            if (! $holder) {
                return false;
            }

            if ($holder->hasAttribute('data-atom-breadcrumbs')) {
                return true;
            }

            if (! $holder->hasAttribute('x-persist')) {
                return false;
            }

            $children = array_values(array_filter(iterator_to_array($holder->childNodes), fn ($node) => $node->nodeType === XML_ELEMENT_NODE));

            return count($children) === 1 && $children[0]->hasAttribute('data-atom-breadcrumbs');
        }
        catch (\Throwable) {
            return false;
        }
        finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
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
