<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\WithBreadcrumbs;
use Livewire\Component;

class BreadcrumbsStaticFixture extends Component
{
    use WithBreadcrumbs;

    public string $stage = 'short';

    /**
     * The component declares a trail of its own too: with `items` on the tag the engine must ignore it.
     */
    public function breadcrumbs($trail)
    {
        return $trail->home('From the component', '/atom/e2e/breadcrumbs-static/morph')->push('Never drawn', '/atom/e2e/breadcrumbs-static/morph');
    }

    /**
     * Change the trail the component's own view prints: a Livewire update morphs the tag's `data-atom-breadcrumbs-items`.
     */
    public function show(string $stage): void
    {
        $this->stage = in_array($stage, ['short', 'long', 'single', 'none'], true) ? $stage : 'short';
    }

    /**
     * The items of the current stage: a short trail, a long one that has to collapse, one crumb, and no crumb.
     *
     * @return array<int, array<string, string>>
     */
    public function items(): array
    {
        return match ($this->stage) {
            'long' => [
                ['label' => 'Dashboard', 'href' => '/atom/e2e/breadcrumbs-static/one'],
                ['label' => 'Sales and Marketing Operations', 'href' => '/atom/e2e/breadcrumbs-static/two'],
                ['label' => 'Regional Distribution Centres', 'href' => '/atom/e2e/breadcrumbs-static/other'],
                ['label' => 'Asia Pacific and Oceania', 'href' => '/atom/e2e/breadcrumbs-static/deep'],
                ['label' => 'Customer Accounts Receivable', 'href' => '/atom/e2e/breadcrumbs-static/new'],
                ['label' => 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur'],
            ],
            'single' => [['label' => 'Only page']],
            'none' => [],
            default => [['label' => 'Reports', 'href' => '/atom/e2e/breadcrumbs-static/one'], ['label' => 'Monthly']],
        };
    }

    /**
     * Render the trail and the buttons that change it.
     */
    public function render()
    {
        return <<<'BLADE'
        <div data-testid="static-morph">
            <atom:breadcrumbs :items="$this->items()" />

            <button type="button" wire:click="show('short')" data-stage="short">Short</button>
            <button type="button" wire:click="show('long')" data-stage="long">Long</button>
            <button type="button" wire:click="show('single')" data-stage="single">Single</button>
            <button type="button" wire:click="show('none')" data-stage="none">None</button>
        </div>
        BLADE;
    }
}
