<div @class(['rounded-panel border bg-surface', 'overflow-clip' => request()->query('host') === 'clip', 'overflow-hidden' => request()->query('host') === 'hidden']) data-testid="panel">
    @livewire('atom-e2e-table-filter-bar', $params)
</div>
