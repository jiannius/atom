<atom:html title="E2E: Table Filters More" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- A real <atom:table.filters> with the DEFAULT (popover) overflow, whose `more` slot holds a
     date-picker and a variant="filter" select, for issue #80: a click on either used to close
     the More filters menu, because that dropdown was not locked. --}}
<div x-data="{ filters: { status: null, created: null, category: null } }" style="padding: 24px">
    <atom:table.filters>
        <atom:select variant="filter" x-model="filters.status" data-filter-key="filters.status" label="Status" :options="[
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ]" />

        <x-slot:more>
            <atom:date-picker x-model="filters.created" />

            <atom:select variant="filter" x-model="filters.category" data-filter-key="filters.category" label="Category" :options="[
                ['value' => 'x', 'label' => 'Category X'],
                ['value' => 'y', 'label' => 'Category Y'],
            ]" />
        </x-slot:more>
    </atom:table.filters>
</div>

{{-- atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
@livewireScripts
</atom:html>
