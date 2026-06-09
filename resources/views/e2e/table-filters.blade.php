<atom:html title="E2E: Table Filters" :vite="[]" :dark="false" class="min-h-screen bg-white">
<div x-data="{ filters: { status: null, type: null, category: null } }" class="p-4 space-y-8">
    <atom:table.filters>
        <atom:table.search placeholder="Search" />
        <atom:select variant="filter" x-model="filters.status" data-filter-key="filters.status" label="Status" :options="[
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ]" />
        <atom:select variant="filter" x-model="filters.type" data-filter-key="filters.type" label="Type" :options="[
            ['value' => 'a', 'label' => 'Type A'],
            ['value' => 'b', 'label' => 'Type B'],
        ]" />
    </atom:table.filters>

    <atom:table.filters overflow="card">
        <atom:table.search placeholder="Search" />
        <x-slot:more>
            <atom:select variant="filter" x-model="filters.category" data-filter-key="filters.category" label="Category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
            ]" />
        </x-slot:more>
    </atom:table.filters>
</div>

@livewireScripts
</atom:html>
