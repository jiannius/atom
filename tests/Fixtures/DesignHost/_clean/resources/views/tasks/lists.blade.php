{{-- A list built to the list rules: the filters bound inside the bar, a column hidden for a phone, a panel around a paginated table, and look-alikes of what the list checks flag. --}}
<atom:page>
    <atom:page.header title="Tasks">
        <x-slot:primary>
            <atom:button variant="primary" icon="add">Create task</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:panel>
        <atom:table.views label="Task views" wire:model.live="filters.view" :current="$view">
            <atom:table.views.item value="all" label="All" />
        </atom:table.views>

        <atom:table.toolbar strip>
            <atom:table.search wire:model="filters.search" placeholder="Search tasks" />

            <atom:table.filters chips="inline">
                <atom:select variant="filter" add wire:model="filters.owner" label="Owner" :options="$owners" />

                <x-slot:more>
                    <atom:select label="Category" variant="listbox" wire:model="filters.category" table-filter :options="$categories" />
                </x-slot:more>
            </atom:table.filters>

            <x-slot:end>
                <atom:table.trashed />
            </x-slot:end>
        </atom:table.toolbar>

        <atom:table frameless :paginate="$tasks">
            <x-slot:columns>
                <atom:table.column name="title">Task</atom:table.column>
                <atom:table.column name="owner" hideable class="max-sm:hidden">Owner</atom:table.column>
            </x-slot:columns>

            <x-slot:rows>
                @foreach ($tasks as $task)
                    <atom:table.row>
                        <atom:table.cell column="title">{{ $task->title }}</atom:table.cell>
                        <atom:table.cell column="owner" class="max-sm:hidden">{{ $task->owner }}</atom:table.cell>
                    </atom:table.row>
                @endforeach
            </x-slot:rows>

            <x-slot:empty>
                <atom:empty heading="No tasks match">
                    <x-slot:action>
                        <atom:button wire:click="$set('filters.owner', null)">Clear owner</atom:button>
                    </x-slot:action>
                </atom:empty>
            </x-slot:empty>
        </atom:table>
    </atom:panel>

    <atom:card heading="Range">
        <div class="overflow-x-auto">
            <atom:table>
                <x-slot:columns>
                    <atom:table.column>Week</atom:table.column>
                </x-slot:columns>
            </atom:table>
        </div>
    </atom:card>
</atom:page>
