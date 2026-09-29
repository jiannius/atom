<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Modelled on humblebear's bank-statement import (jiannius/humblebear#378): the
 * component's root is a modal, an "upload" step inserts a mapping form, and
 * picking the Date Column makes the server re-guess the Date Format — a value
 * the user never touched, changed by a sibling select's updated* hook.
 */
class SelectSiblingSyncFixture extends Component
{
    use AtomComponent;

    public ?string $accountId = null;

    /**
     * Column values are integer indices, as in the consumer.
     *
     * @var array<string, int|string|null>
     */
    public array $mapping = [
        'date' => null,
        'description' => null,
        'amount' => null,
        'data_start_row' => 1,
        'date_format' => 'DMY',
    ];

    public ?string $listboxFormat = 'DMY';

    public bool $dateFormatPicked = false;

    public ?array $preview = null;

    /**
     * `?deferred=1` binds the Date Format with a plain (deferred) wire:model and
     * never marks it picked — the consumer's earlier shape, where the user's pick
     * rode along in the same request that the server then overrode.
     */
    public bool $deferred = false;

    /**
     * Read the binding shape from the query string.
     */
    public function mount(): void
    {
        $this->deferred = (bool) request()->query('deferred');
    }

    /**
     * Stand-in for the upload step: guess the mapping and show the form.
     */
    public function load(): void
    {
        $this->dateFormatPicked = false;
        $this->preview = [['US Txn Date', 'Posting Date', 'Description', 'Amount']];
        $this->mapping = array_merge($this->mapping, ['date' => 0, 'description' => 2, 'amount' => 3]);
        $this->mapping['date_format'] = $this->guessFor($this->mapping['date']);
        $this->listboxFormat = $this->mapping['date_format'];
    }

    /**
     * Back to the upload step.
     */
    public function cancel(): void
    {
        $this->reset('preview', 'mapping', 'listboxFormat', 'dateFormatPicked');
    }

    /**
     * Re-guess the Date Format when the Date Column changes, unless the user picked one.
     */
    public function updatedMappingDate(): void
    {
        if (! $this->preview || $this->dateFormatPicked) {
            return;
        }

        $this->mapping['date_format'] = $this->guessFor($this->mapping['date']);
        $this->listboxFormat = $this->mapping['date_format'];
    }

    /**
     * The user picked their own Date Format.
     */
    public function updatingMappingDateFormat(): void
    {
        $this->dateFormatPicked = ! $this->deferred;
    }

    /**
     * Column 0 holds US dates; any other column is ambiguous, so day-first.
     */
    protected function guessFor(int|string|null $column): string
    {
        return (string) $column === '0' ? 'MDY' : 'DMY';
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::select-sibling-sync', [
            'formats' => [
                ['value' => 'DMY', 'label' => 'DD/MM/YYYY'],
                ['value' => 'MDY', 'label' => 'MM/DD/YYYY'],
                ['value' => 'YMD', 'label' => 'YYYY-MM-DD'],
            ],
            'accounts' => [
                ['value' => 'acc-1', 'label' => 'Maybank'],
                ['value' => 'acc-2', 'label' => 'CIMB'],
            ],
        ]);
    }
}
