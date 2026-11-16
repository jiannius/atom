<?php

use Illuminate\Support\Facades\Log;
use Jiannius\Atom\Services\ActionItems;

// <atom:action>: the tag's contract (props, refusals, the label), the one element each action becomes, the confirm it carries per tone,
// and the registry behind it. Placement is ActionPlacementTest's, the header's output PageHeaderActionsTest's.

beforeEach(fn () => view()->share('errors', new \Illuminate\Support\ViewErrorBag));

/** The message of the InvalidArgumentException a render threw (a view wraps it in a ViewException), '' when nothing was thrown */
function actionRefusal(string $blade, array $data = []): string
{
    try {
        renderBlade($blade, $data);
    }
    catch (Throwable $e) {
        while ($e !== null && ! $e instanceof InvalidArgumentException) {
            $e = $e->getPrevious();
        }

        return $e?->getMessage() ?? 'something else was thrown';
    }

    return '';
}

/** An action placed by a page header with room: its first visible button, or the first menu row when the action is toned or low */
function drawnAction(string $tag, array $data = [], string $slot = 'actions'): DOMElement
{
    $html = renderBlade('<atom:page.header title="T"><x-slot:'.$slot.'>'.$tag.'</x-slot:'.$slot.'></atom:page.header>', $data);

    return domQuery($html, '//*[@data-atom-page-actions]//*[@data-atom-action]')[0];
}

describe('the tag\'s contract', function () {
    it('refuses what the surface decides, naming the replacement', function (string $prop) {
        expect(actionRefusal('<atom:action label="Edit" '.$prop.' />'))->toContain('does not take `'.explode('=', $prop)[0].'`')->toContain('`tone`');
    })->with(['variant="ghost"', 'color="danger"', 'size="sm"', 'type="submit"', 'inverted', 'block', 'social="google"', 'tooltip="Hi"']);

    it('refuses an empty label, a label and a slot together, an unknown priority and an unknown tone', function () {
        expect(actionRefusal('<atom:action />'))->toContain('needs a label')
            ->and(actionRefusal('<atom:action label="" />'))->toContain('needs a label')
            ->and(actionRefusal('<atom:action>  </atom:action>'))->toContain('needs a label')
            ->and(actionRefusal('<atom:action label="A">B</atom:action>'))->toContain('not both')
            ->and(actionRefusal('<atom:action label="A" priority="high" />'))->toContain('priority must be one of primary, normal, low')
            ->and(actionRefusal('<atom:action label="A" tone="success" />'))->toContain('tone must be one of warning, danger, delete');
    });

    it('refuses a markup label, an action inside an action, and a nameless icon-only one', function () {
        expect(actionRefusal('<atom:action :label="$l" />', ['l' => new \Illuminate\Support\HtmlString('<b>x</b>')]))->toContain('`label` is text, not markup')
            ->and(actionRefusal('<atom:action>Outer <atom:action label="Inner" wire:click="x" /></atom:action>'))->toContain('cannot hold another')
            ->and(actionRefusal('<atom:action><atom:icon.edit /></atom:action>'))->toContain('pass `label`, or `aria-label`')
            ->and(actionRefusal('<atom:action><atom:icon.edit /></atom:action>'))->not->toContain('needs a label')
            ->and(actionRefusal('<atom:action aria-label="Edit"><atom:icon.edit /></atom:action>'))->toBe('')
            ->and(actionRefusal('<atom:action>Edit <atom:icon.edit /></atom:action>'))->toBe('');
    });

    it('does not leave the nested action\'s marker in the registry or the output', function () {
        actionRefusal('<atom:action>Outer <atom:action label="Inner" wire:click="x" /></atom:action>');

        expect(app(ActionItems::class)->pending())->toBe(1);
    });

    it('takes the label from the prop or the slot, translated once', function () {
        app('translator')->setLoaded(['*' => ['*' => ['en' => ['Send :name' => 'Hantar :name', 'Plain' => 'Biasa']]]]);

        expect(actionText(drawnAction('<atom:action :label="[\'Send :name\', [\'name\' => $name]]" wire:click="x" />', ['name' => 'Smith | Sons'])))->toBe('Hantar Smith | Sons')
            ->and(actionText(drawnAction('<atom:action label="Plain" wire:click="x" />')))->toBe('Biasa')
            ->and(actionText(drawnAction('<atom:action wire:click="x">Do <b>this</b></atom:action>')))->toBe('Do this');
    });

    it('prints a label as text, and takes markup only through the slot', function () {
        $evil = '"><img src=x onerror=alert(1)> & co';
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions><atom:action :label="$evil" wire:click="x" /></x-slot:actions></atom:page.header>', ['evil' => $evil]);

        expect(domQuery($html, '//img'))->toBeEmpty()
            ->and(actionText(domQuery($html, '//*[@data-atom-action]')[0]))->toBe($evil)
            ->and($html)->toContain('&lt;img src=x');
    });

    it('does not cut a plain label at a pipe', function () {
        expect(actionText(drawnAction('<atom:action :label="$l" wire:click="x" />', ['l' => 'Smith | Sons'])))->toBe('Smith | Sons');
    });
});

describe('the one element an action becomes', function () {
    it('lands every attribute once: the click, the Alpine handler, `disabled`, data and aria', function () {
        $html = renderBlade('<atom:page.header title="T"><x-slot:actions>
            <atom:action label="Go" wire:click="go(1)" x-on:mouseenter="warm()" disabled data-x="1" aria-describedby="why" class="extra" />
        </x-slot:actions></atom:page.header>');
        $button = domQuery($html, '//*[@data-atom-action]')[0];

        expect(substr_count($html, 'wire:click="go(1)"'))->toBe(1)
            ->and(substr_count($html, 'x-on:mouseenter="warm()"'))->toBe(1)
            ->and($button->hasAttribute('disabled'))->toBeTrue()
            ->and($button->getAttribute('data-x'))->toBe('1')
            ->and($button->getAttribute('aria-describedby'))->toBe('why')
            ->and(domClasses($button))->toContain('extra');
    });

    it('draws an href as a link, with newtab, and drops a javascript: one', function () {
        $link = drawnAction('<atom:action label="Open" href="/x" newtab />');
        $blocked = drawnAction('<atom:action label="Open" href="javascript:alert(1)" wire:click="x" />');

        expect($link->nodeName)->toBe('a')
            ->and($link->getAttribute('href'))->toBe('/x')
            ->and($link->getAttribute('target'))->toBe('_blank')
            ->and($blocked->nodeName)->toBe('button')
            ->and($blocked->hasAttribute('href'))->toBeFalse();
    });

    it('draws a menu row for a low action, with the icon, the href and the click', function () {
        $row = drawnAction('<atom:action label="Duplicate" icon="copy" priority="low" wire:click="dup" />');

        expect($row->getAttribute('role'))->toBe('menuitem')
            ->and($row->getAttribute('data-atom-action'))->toBe('menu')
            ->and($row->getAttribute('wire:click'))->toBe('dup')
            ->and(domQuery($row->ownerDocument->saveHTML($row), '//svg'))->not->toBeEmpty();
    });
});

describe('confirm', function () {
    it('asks nothing of a neutral action, and in the label\'s words of a toned one, in its tone', function (string $tone, string $variant) {
        $row = drawnAction('<atom:action tone="'.$tone.'" label="Void invoice" wire:click="void" />');
        $confirm = json_decode($row->getAttribute('data-atom-confirm'), true);

        expect($row->getAttribute('data-atom-action'))->toBe('menu')
            ->and($confirm)->toMatchArray(['variant' => $variant, 'heading' => 'Void invoice?', 'buttonConfirm' => 'Void invoice']);
    })->with([['warning', 'warning'], ['danger', 'danger']]);

    it('has no gate on a neutral action unless it is given `confirm`', function () {
        expect(drawnAction('<atom:action label="Edit" wire:click="x" />')->hasAttribute('data-atom-confirm'))->toBeFalse()
            ->and(json_decode(drawnAction('<atom:action label="Edit" wire:click="x" confirm />')->getAttribute('data-atom-confirm'), true))->toMatchArray(['heading' => 'Edit?'])
            ->and(json_decode(drawnAction('<atom:action label="Edit" wire:click="x" :confirm="[\'message\' => \'Sure?\', \'buttonConfirm\' => \'Yes, edit\']" />')->getAttribute('data-atom-confirm'), true))->toMatchArray(['message' => ['Sure?'], 'buttonConfirm' => 'Yes, edit']);
    });

    it('lets `:confirm="false"` opt a toned action out', function () {
        $row = drawnAction('<atom:action tone="warning" label="Void" wire:click="void" :confirm="false" />');

        expect($row->hasAttribute('data-atom-confirm'))->toBeFalse()
            ->and($row->getAttribute('wire:click'))->toBe('void');
    });

    it('keeps a delete\'s own copy and, with no handler, calls $wire.delete() after the dialog', function () {
        $handler = drawnAction('<atom:action tone="delete" label="Delete invoice" />');
        $own = drawnAction('<atom:action tone="delete" label="Delete invoice" wire:click="destroy(7)" />');

        expect($handler->getAttribute('x-on:confirmed'))->toBe('$wire.delete()')
            ->and($handler->hasAttribute('data-atom-confirm'))->toBeFalse()
            ->and($handler->getAttribute('x-on:click'))->toContain('atom.confirm(', "variant: 'danger'", "buttonConfirm: 'Delete invoice'", 'Delete :count records?')
            ->and(domClasses($handler))->toContain('text-danger-fg')
            ->and(json_decode($own->getAttribute('data-atom-confirm'), true))->toMatchArray(['variant' => 'danger'])
            ->and($own->getAttribute('wire:click'))->toBe('destroy(7)');
    });

    it('carries the passphrase to the dialog', function () {
        $row = drawnAction('<atom:action tone="danger" label="Purge" passphrase="PURGE" wire:click="purge" />');

        expect(json_decode($row->getAttribute('data-atom-confirm'), true))->toMatchArray(['passphrase' => 'PURGE']);
    });
});

describe('the registry', function () {
    it('prints a marker and nothing else where an action is written', function () {
        $html = renderBlade('<atom:action label="Edit" wire:click="x" />');

        expect(trim($html))->toMatch('/^<!--atom:action:[0-9a-f]{32}-->$/')
            ->and(app(ActionItems::class)->pending())->toBe(1);
    });

    it('reports an action no surface took, once, without its label', function () {
        config(['cache.default' => 'array']);
        Log::spy();

        renderBlade('<atom:action label="Secret record name" tone="warning" priority="low" wire:click="x" />');
        $registry = app(ActionItems::class);
        $registry->reportUnplaced();
        $registry->reportUnplaced();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            return count($context['actions']) === 1
                && $context['actions'][0]['tone'] === 'warning'
                && $context['actions'][0]['priority'] === 'low'
                && ! str_contains(json_encode($context), 'Secret');
        });

        expect($registry->pending())->toBe(0);
    });

    it('says nothing when every action was placed', function () {
        Log::spy();

        renderBlade('<atom:page.header title="T"><x-slot:actions><atom:action label="Edit" wire:click="x" /></x-slot:actions></atom:page.header>');
        app(ActionItems::class)->reportUnplaced();

        Log::shouldNotHaveReceived('warning');
    });

    it('logs the same unplaced action once an hour across requests, and again after a new shape', function () {
        config(['cache.default' => 'array']);
        Log::spy();

        foreach ([1, 2] as $request) {
            $registry = new ActionItems;
            $registry->add(['label' => 'x', 'html' => 'x', 'tone' => null, 'priority' => null, 'attributes' => new \Illuminate\View\ComponentAttributeBag]);
            $registry->reportUnplaced();
        }

        $other = new ActionItems;
        $other->add(['label' => 'x', 'html' => 'x', 'tone' => 'danger', 'priority' => null, 'attributes' => new \Illuminate\View\ComponentAttributeBag]);
        $other->reportUnplaced();

        Log::shouldHaveReceived('warning')->twice();
    });

    it('registers one terminating callback for the container, however many requests or jobs a worker serves', function () {
        $callbacks = fn () => count((new ReflectionProperty(app(), 'terminatingCallbacks'))->getValue(app()));
        $before = $callbacks();

        foreach ([1, 2, 3] as $job) {
            renderBlade('<atom:action label="Edit" wire:click="x" />');
            app()->forgetScopedInstances();
        }

        expect($callbacks())->toBe($before);
    });

    it('is scoped, so a worker starts every request and job empty', function () {
        $binding = app()->getBindings()[ActionItems::class] ?? null;

        expect($binding['shared'] ?? null)->toBeTrue();

        app(ActionItems::class)->add(['label' => 'x', 'html' => 'x', 'attributes' => new \Illuminate\View\ComponentAttributeBag]);
        app()->forgetScopedInstances();

        expect(app(ActionItems::class)->pending())->toBe(0);
    });
});
