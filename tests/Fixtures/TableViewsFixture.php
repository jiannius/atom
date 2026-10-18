<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Jiannius\Atom\Traits\WithUrlFilters;
use Livewire\Component;

/**
 * Drives the E2E for the list parts (V4-2c): saved views bound with wire:model so WithUrlFilters carries the view in the URL, a
 * toolbar in a frameless table's header slot, and the bulk bar that replaces it while a row is ticked.
 *
 * Rows are a static array held in a public property (the served app has no database); the host's counts are computed from them,
 * which is what a host does with a grouped query. `markPaid()` changes the rows, so a count chip has something to follow.
 */
class TableViewsFixture extends Component
{
    use AtomComponent;
    use WithUrlFilters;

    /** @var array<string, mixed> */
    public array $filters = ['view' => 'all', 'search' => ''];

    /** @var array<int, array{id: int, number: string, customer: string, amount: float, state: string}> */
    public array $invoices = [
        ['id' => 1, 'number' => 'INV-0401', 'customer' => 'Customer A', 'amount' => 1300.0, 'state' => 'unpaid'],
        ['id' => 2, 'number' => 'INV-0402', 'customer' => 'Customer B', 'amount' => 2000.0, 'state' => 'overdue'],
        ['id' => 3, 'number' => 'INV-0403', 'customer' => 'Customer C', 'amount' => 750.0, 'state' => 'overdue'],
        ['id' => 4, 'number' => 'INV-0404', 'customer' => 'Customer D', 'amount' => 650.0, 'state' => 'paid'],
        ['id' => 5, 'number' => 'INV-0405', 'customer' => 'Customer E', 'amount' => 500.0, 'state' => 'paid'],
    ];

    /**
     * The filters that live in the URL: the chosen view, and the search.
     *
     * @return array<string, mixed>
     */
    protected function urlFilters(): array
    {
        return ['view' => 'all', 'search' => ''];
    }

    /**
     * Only a view the list knows.
     */
    protected function isUrlFilterValid(string $key, mixed $value): bool
    {
        return $key !== 'view' || in_array($value, ['all', 'unpaid', 'overdue', 'paid'], true);
    }

    /**
     * The count for one view: what the host supplies, atom never counts.
     */
    public function count(string $view): int
    {
        return count($this->rowsFor($view));
    }

    /**
     * The rows of a view.
     *
     * @return array<int, array{id: int, number: string, customer: string, amount: float, state: string}>
     */
    private function rowsFor(string $view): array
    {
        return array_values(array_filter($this->invoices, fn ($row) => match ($view) {
            'unpaid' => in_array($row['state'], ['unpaid', 'overdue'], true),
            'overdue' => $row['state'] === 'overdue',
            'paid' => $row['state'] === 'paid',
            default => true,
        }));
    }

    /**
     * The rows of the chosen view that match the search.
     *
     * @return array<int, array{id: int, number: string, customer: string, amount: float, state: string}>
     */
    public function getRowsProperty(): array
    {
        $search = strtolower((string) ($this->filters['search'] ?? ''));

        return array_values(array_filter(
            $this->rowsFor((string) ($this->filters['view'] ?? 'all')),
            fn ($row) => $search === '' || str_contains(strtolower($row['customer'].' '.$row['number']), $search),
        ));
    }

    /**
     * Mark an invoice paid, so the counts change.
     */
    public function markPaid(int $id): void
    {
        foreach ($this->invoices as $index => $row) {
            if ($row['id'] === $id) {
                $this->invoices[$index]['state'] = 'paid';
            }
        }
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::table-views-fixture');
    }
}
