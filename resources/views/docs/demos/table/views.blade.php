{{-- Bound in a Livewire page: <atom:table.views wire:model.live="filters.view" :current="$filters['view']" :counts="fn ($view) => $this->count($view)">.
     Here it is static: `current` marks the chosen view, and the counts are an array. --}}
<div class="max-w-2xl overflow-hidden rounded-panel border bg-surface">
    <atom:table.views label="Invoice views" current="unpaid" :counts="['all' => 151, 'unpaid' => 12, 'overdue' => 3, 'paid' => 139]">
        <atom:table.views.item value="all" label="All"/>
        <atom:table.views.item value="unpaid" label="Unpaid"/>
        <atom:table.views.item value="overdue" label="Overdue"/>
        <atom:table.views.item value="paid" label="Paid"/>
    </atom:table.views>
</div>
