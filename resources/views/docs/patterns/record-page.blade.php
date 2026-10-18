{{-- Pattern: record page. One record (a work order) with everything a record page has: crumbs, the title with its
     status, key facts, the main action for the record's current state, a totals strip, record tabs, and the three
     kinds of empty state. Built from atom's components with invented data; copy the structure, not the data.

     The rules this page follows:
     - The shell's <main> is unpadded; <atom:page> pads the page. Header first, then stats, tabs and content as siblings.
     - ONE primary action, and it is the action for the record's CURRENT status (look at the four states below: the
       `primary` slot changes with the status, the rest stays). Secondary actions are plain buttons; the rest is in the menu.
     - A status is a tone plus a word; `running` only while something is actually in progress.
     - Money is tabular and carries its currency as a small prefix; identifiers are monospace (`font-id`).
     - Crumbs are the header's own `crumbs` prop here (use the shell's `crumbs` slot instead, not both).
     - A list, a search and a failed load each have their own empty state, each with a next step.

     Open it with ?status=draft|in-progress|blocked|done to see the primary action follow the status. --}}
@php
$styles = $styles ?? [];
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$status = request()->query('status', 'in-progress');
$status = is_string($status) && in_array($status, ['draft', 'in-progress', 'blocked', 'done'], true) ? $status : 'in-progress';

// label, tone, running, and the one primary action for that status
$states = [
    'draft' => ['Draft', 'neutral', false, 'Schedule job'],
    'in-progress' => ['In progress', 'info', true, 'Mark as done'],
    'blocked' => ['Waiting for parts', 'warning', false, 'Order parts'],
    'done' => ['Done', 'success', false, 'Create invoice'],
];
[$statusLabel, $statusTone, $statusRunning, $primaryLabel] = $states[$status];

$tab = request()->query('tab', 'overview');
$tab = is_string($tab) && in_array($tab, ['overview', 'tasks', 'parts', 'activity', 'files'], true) ? $tab : 'overview';

$tasks = [
    ['T-1', 'Isolate the pump', 'Done', 'Ana', '2.0'],
    ['T-2', 'Replace the seal kit', 'Done', 'Ben', '3.5'],
    ['T-3', 'Pressure test', 'Open', 'Ana', '1.5'],
    ['T-4', 'Sign-off with the customer', 'Open', 'Ben', '0.5'],
];
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Record page" :vite="[]" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" @class([
            'flex items-center gap-[9px] px-2 pb-3 pt-1.5' => $shell === 'sidebar',
            'flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight' => $shell === 'header',
        ])>
            <div class="flex size-[26px] items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="truncate text-sm font-semibold">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:nav>
        <atom:navlist>
            <atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item>
            <atom:navlist.item href="?page=jobs" icon="document" count="24" :current="true">Jobs</atom:navlist.item>
            <atom:navlist.item href="?page=customers" icon="users">Customers</atom:navlist.item>
        </atom:navlist>
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page>
        <atom:page.header
        title="Work order WO-1042"
        :crumbs="[['label' => 'Jobs', 'href' => '?page=jobs'], ['label' => 'WO-1042']]"
        :facts="[
            ['label' => 'Customer', 'value' => 'Customer A'],
            ['label' => 'Site', 'value' => 'Unit 7, Harbour Park'],
            ['label' => 'Due', 'value' => '29 Jul'],
            ['label' => 'Ref', 'value' => 'PO-88213', 'mono' => true],
        ]">
            <x-slot:status>
                <atom:status :tone="$statusTone" :running="$statusRunning" :label="$statusLabel"/>
            </x-slot:status>

            <x-slot:actions>
                <atom:button size="sm">Edit</atom:button>
                <atom:button size="sm" icon="download">PDF</atom:button>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="copy">Duplicate</atom:menu.item>
                <atom:menu.item icon="delete">Cancel job</atom:menu.item>
            </x-slot:menu>

            <x-slot:primary>
                <atom:button size="sm" variant="primary" data-testid="record-primary">{{ $primaryLabel }}</atom:button>
            </x-slot:primary>
        </atom:page.header>

        <atom:stat.group>
            <atom:stat label="Labour hours" value="7.5" breakdown="of 10 quoted" delta="-2.5" inverse/>
            <atom:stat label="Parts cost" prefix="RM" value="1,280.00" breakdown="7 parts" delta="+6%" inverse/>
            <atom:stat label="Quoted" prefix="RM" value="4,200.00" breakdown="approved 3 Oct"/>
            <atom:stat label="Open tasks" value="2" href="?tab=tasks" breakdown="of 4"/>
        </atom:stat.group>

        <atom:page.tabs label="Job sections" :tabs="[
            ['label' => 'Overview', 'href' => '?tab=overview', 'current' => $tab === 'overview'],
            ['label' => 'Tasks', 'href' => '?tab=tasks', 'count' => 4, 'current' => $tab === 'tasks'],
            ['label' => 'Parts', 'href' => '?tab=parts', 'count' => 0, 'current' => $tab === 'parts'],
            ['label' => 'Activity', 'href' => '?tab=activity', 'current' => $tab === 'activity'],
            ['label' => 'Files', 'href' => '?tab=files', 'current' => $tab === 'files'],
        ]"/>

        @if ($tab === 'parts')
            {{-- first use: nothing created yet, and the next step is to create the first one --}}
            <div class="rounded-panel border bg-surface">
                <atom:empty kind="first-use" heading="No parts on this job" subheading="Add the first part to track its cost against the quote.">
                    <x-slot:action>
                        <atom:button size="sm" variant="primary" icon="add">Add part</atom:button>
                    </x-slot:action>
                </atom:empty>
            </div>
        @elseif ($tab === 'activity')
            {{-- an error: it could not load; the next step is to try again --}}
            <div class="rounded-panel border bg-surface">
                <atom:empty kind="error" heading="Activity could not be loaded" subheading="Check your connection and try again.">
                    <x-slot:action>
                        <atom:button size="sm" icon="refresh">Try again</atom:button>
                    </x-slot:action>
                </atom:empty>
            </div>
        @elseif ($tab === 'files')
            {{-- no results: a search found nothing; the next step is to widen it --}}
            <div class="rounded-panel border bg-surface">
                <atom:empty kind="no-results" icon="search" heading="No files match" subheading="Nothing is named like your search.">
                    <x-slot:action>
                        <atom:button size="sm">Clear search</atom:button>
                    </x-slot:action>
                </atom:empty>
            </div>
        @else
            <atom:table :empty="false">
                <x-slot:columns>
                    <atom:table.column>Task</atom:table.column>
                    <atom:table.column>Status</atom:table.column>
                    <atom:table.column>Assigned</atom:table.column>
                    <atom:table.column align="right">Hours</atom:table.column>
                </x-slot:columns>

                <x-slot:rows>
                    @foreach ($tasks as [$id, $name, $taskStatus, $who, $hours])
                        <atom:table.row>
                            <atom:table.cell>
                                <span class="font-id text-fg-subtle">{{ $id }}</span>
                                <span class="ms-2 font-medium">{{ $name }}</span>
                            </atom:table.cell>
                            <atom:table.cell>
                                <atom:status :tone="$taskStatus === 'Done' ? 'success' : 'neutral'" :label="$taskStatus"/>
                            </atom:table.cell>
                            <atom:table.cell>{{ $who }}</atom:table.cell>
                            <atom:table.cell align="right" class="tabular-nums">{{ $hours }}</atom:table.cell>
                        </atom:table.row>
                    @endforeach
                </x-slot:rows>
            </atom:table>
        @endif
    </atom:page>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</x-dynamic-component>
