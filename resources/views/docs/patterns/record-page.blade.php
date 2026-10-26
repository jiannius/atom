{{-- Pattern: record page. One record (a work order) with everything a record page has: the title with its status, key facts,
     the stage path, the main action for the record's current state, a totals strip, record tabs, a main column and a rail of
     facts, and the three kinds of empty state. Built from atom's components with invented data; copy the structure, not the data.

     The rules this page follows:
     - The shell's <main> is unpadded; <atom:page> pads the page. Header first, then the stage path, stats and tabs, then the
       content, as siblings.
     - ONE primary action, and it is the action for the record's CURRENT status (look at the four states below: the
       `primary` slot changes with the status, the rest stays). Secondary actions are plain buttons; the rest is in the menu.
     - A status is a tone plus a word; `running` only while something is actually in progress, and then it says for how long.
     - A record that moves through stages shows them as `<atom:timeline kind="path">` under the header, the current segment
       matching the status (done, current, pending, failed; a time and a person on each).
     - The overview is a main column and a RAIL (from 1080px beside it, below that under it): the main column holds the record's
       lines in a panel and the long text; the rail is `<atom:card>`s, each headed by a 12px muted title, with the facts grouped by
       subject as `<atom:dd.group>` (Details, Customer, People, Linked records). An empty field is left out: it is never printed as
       a dash. Activity is the last card of the rail, with a link to all of it.
     - A panel for the record's lines has a heading row (the name, a count, a link to all of it) over a `frameless` table, and its
       totals under the table. Money and quantities are `numeric` on the column and the cell; identifiers are monospace (`font-id`).
     - Long text (a description, notes) is the reading size: `text-base`.
     - Crumbs are the shell's own `crumbs` slot here, with the previous and next record as ghost icon buttons in its `actions` slot,
       so the record's title is the page's one heading. (Use the header's own `crumbs` prop instead where the shell has no slot to
       spare, not both.)
     - A list, a search and a failed load each have their own empty state, each with a next step, in an <atom:card inset>. The next
       step is a plain button: the header's primary stays the one primary on the screen.
     - A person in a list is `<atom:avatar size="xs">` and their name. Something the reader has to act on, such as a blocked
       job, is one `<atom:callout>` under the header, in the tone of the status (warning here), never a hand-built box.
     - A process log is `<atom:timeline kind="step">` and a conversation is `<atom:timeline kind="message">`: the Log and Messages tabs.

     Open it with ?status=draft|in-progress|blocked|done to see the primary action and the stage path follow the status. --}}
@php
$styles = $styles ?? [];
// a host's own stylesheet when it has one (config atom.docs.vite), so the page is styled inside the host; none in the test rig
$vite = $vite ?? app(\Jiannius\Atom\Services\Docs::class)->vite();
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$status = request()->query('status', 'in-progress');
$status = is_string($status) && in_array($status, ['draft', 'in-progress', 'blocked', 'done'], true) ? $status : 'in-progress';

// label, tone, running, the one primary action for that status, and the stage that is current (0 to 3)
$states = [
    'draft' => ['Draft', 'neutral', false, 'Schedule job', 0],
    'in-progress' => ['In progress · 1h 12m', 'info', true, 'Mark as done', 2],
    'blocked' => ['Waiting for parts', 'warning', false, 'Order parts', 2],
    'done' => ['Done', 'success', false, 'Create invoice', 3],
];
[$statusLabel, $statusTone, $statusRunning, $primaryLabel, $currentStage] = $states[$status];

// the stage path: a stage before the current one is done, the current one is current (or all done when the job is), the rest pending
$stages = [
    ['Opened', '08:54', 'Ana'],
    ['Scheduled', 'Tue 09:00', 'Ben'],
    ['In progress', $status === 'blocked' ? 'Waiting for parts' : '1h 12m', 'Ana'],
    ['Done', $status === 'done' ? 'Today 11:20' : null, $status === 'done' ? 'Ana' : null],
];

$tab = request()->query('tab', 'overview');
$tab = is_string($tab) && in_array($tab, ['overview', 'tasks', 'parts', 'activity', 'files', 'log', 'messages'], true) ? $tab : 'overview';

// a done job has no open task left
$closing = $status === 'done' ? 'Done' : 'Open';
$tasks = [
    ['T-1', 'Isolate the pump', 'Done', 'Ana', '2.0'],
    ['T-2', 'Replace the seal kit', 'Done', 'Ben', '3.5'],
    ['T-3', 'Pressure test', $closing, 'Ana', '1.5'],
    ['T-4', 'Sign-off with the customer', $closing, 'Ben', '0.5'],
];
$openTasks = count(array_filter($tasks, fn ($task) => $task[2] !== 'Done'));
$hours = array_sum(array_column($tasks, 4));

// the facts of the rail; a field with no value is filtered out, so it is never printed as a dash
$details = array_filter([
    'Type' => 'Pump service',
    'Priority' => 'High',
    'Scheduled' => '29 Jul, 9:00',
    'Cost centre' => null,
    'Approved quote' => 'RM 4,200.00',
]);

$people = [['Assigned', 'Ana Lim', 'A07'], ['Technician', 'Ben Ooi', 'T12'], ['Checked by', null, null]];
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Record page" :vite="$vite" :styles="$styles" :dark="$dark ?? true">
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
        @include('atom::docs.patterns.nav', ['navCurrent' => 'jobs'])
    </x-slot>

    <x-slot:crumbs>
        <div class="flex items-center gap-1.5 text-fg-subtle" data-testid="record-crumbs">
            <a href="?page=jobs" class="text-fg-muted hover:text-fg">Jobs</a>
            <atom:icon.right class="size-3.5"/>
            <span class="font-id font-medium text-fg">WO-1042</span>
        </div>

        @if ($shell === 'header')
            <span class="ms-2 flex items-center">
                <atom:button variant="ghost" size="sm" icon="up" aria-label="Previous job"/>
                <atom:button variant="ghost" size="sm" icon="down" aria-label="Next job"/>
            </span>
        @endif
    </x-slot>

    {{-- previous and next: in the sidebar shell the bar's own right side, in the top-nav shell beside the crumbs (its right side is the account) --}}
    @if ($shell === 'sidebar')
    <x-slot:actions>
        <atom:button variant="ghost" size="sm" icon="up" aria-label="Previous job"/>
        <atom:button variant="ghost" size="sm" icon="down" aria-label="Next job"/>
    </x-slot>
    @endif

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page>
        <atom:page.header
        title="Work order WO-1042"
        :facts="[
            ['label' => 'Customer', 'value' => 'Lakeside Foods'],
            ['label' => 'Site', 'value' => 'Unit 7, Harbour Park'],
            ['label' => 'Due', 'value' => '29 Jul'],
            ['label' => 'Ref', 'value' => 'PO-88213', 'mono' => true],
        ]">
            <x-slot:status>
                <atom:status :tone="$statusTone" :running="$statusRunning" :label="$statusLabel"/>
            </x-slot:status>

            <x-slot:actions>
                <atom:button>Edit</atom:button>
                <atom:button icon="download">PDF</atom:button>
            </x-slot:actions>

            <x-slot:menu>
                <atom:menu.item icon="copy">Duplicate</atom:menu.item>
                <atom:menu.item icon="delete">Cancel job</atom:menu.item>
            </x-slot:menu>

            <x-slot:primary>
                <atom:button variant="primary" data-testid="record-primary">{{ $primaryLabel }}</atom:button>
            </x-slot:primary>
        </atom:page.header>

        @if ($status === 'blocked')
            <atom:callout variant="warning" heading="Waiting for parts" content="The seal kit is on back order, so the pressure test cannot start. Order it from another supplier or move the due date." data-testid="record-callout"/>
        @endif

        <atom:timeline kind="path" label="Job stages" data-testid="record-stages">
            @foreach ($stages as $index => [$stageName, $stageTime, $stagePerson])
                @php
                $stageState = $status === 'done' || $index < $currentStage ? 'done' : ($index === $currentStage ? 'current' : 'pending');
                @endphp

                <atom:timeline.item wire:key="stage-{{ $index }}" :title="$stageName" :state="$stageState" :tone="$statusTone === 'neutral' ? null : $statusTone" :time="$stageState === 'pending' ? null : $stageTime" :actor="$stageState === 'pending' ? null : $stagePerson"/>
            @endforeach
        </atom:timeline>

        <atom:stat.group>
            <atom:stat label="Labour hours" value="7.5" breakdown="of 10 quoted" delta="-2.5" inverse/>
            <atom:stat label="Parts cost" prefix="RM" value="0.00" breakdown="none added yet"/>
            <atom:stat label="Quoted" prefix="RM" value="4,200.00" breakdown="approved 3 Oct"/>
            <atom:stat label="Open tasks" :value="(string) $openTasks" href="?tab=tasks" breakdown="of 4"/>
        </atom:stat.group>

        <atom:page.tabs label="Job sections" :tabs="[
            ['label' => 'Overview', 'href' => '?tab=overview', 'current' => $tab === 'overview'],
            ['label' => 'Tasks', 'href' => '?tab=tasks', 'count' => 4, 'current' => $tab === 'tasks'],
            ['label' => 'Parts', 'href' => '?tab=parts', 'count' => 0, 'current' => $tab === 'parts'],
            ['label' => 'Activity', 'href' => '?tab=activity', 'current' => $tab === 'activity'],
            ['label' => 'Files', 'href' => '?tab=files', 'current' => $tab === 'files'],
            ['label' => 'Log', 'href' => '?tab=log', 'current' => $tab === 'log'],
            ['label' => 'Messages', 'href' => '?tab=messages', 'current' => $tab === 'messages'],
        ]"/>

        @if ($tab === 'parts')
            {{-- first use: nothing created yet, and the next step is to create the first one --}}
            <atom:card inset>
                <atom:empty kind="first-use" heading="No parts on this job" subheading="Add the first part to track its cost against the quote.">
                    <x-slot:action>
                        <atom:button size="sm" icon="add">Add part</atom:button>
                    </x-slot:action>
                </atom:empty>
            </atom:card>
        @elseif ($tab === 'activity' && request()->query('view') === 'all')
            {{-- "All changes" from the rail: the whole history, newest first --}}
            <atom:card data-testid="record-history">
                <h2 class="m-0 mb-3 text-sm font-semibold">All changes</h2>

                <atom:timeline label="Work order history" class="max-w-xl">
                    <atom:timeline.item wire:key="h-1" title="T-3 Pressure test" body="added to the job" actor="Ana" time="Today, 09:20" tone="info"/>
                    <atom:timeline.item wire:key="h-2" title="T-2 Replace the seal kit" body="marked done" actor="Ben" time="Today, 08:41" tone="success"/>
                    <atom:timeline.item wire:key="h-3" title="T-1 Isolate the pump" body="marked done" actor="Ana" time="Yesterday, 17:05" tone="success"/>
                    <atom:timeline.item wire:key="h-4" title="Seal kit" body="put on back order" actor="Ana" time="Yesterday, 16:30" tone="warning"/>
                    <atom:timeline.item wire:key="h-5" title="Due date" body="set to 29 Jul" actor="Ben" time="Yesterday, 16:12"/>
                    <atom:timeline.item wire:key="h-6" title="WO-1042" body="opened from quote Q-0231" actor="Ana" time="Yesterday, 16:02"/>
                </atom:timeline>
            </atom:card>
        @elseif ($tab === 'activity')
            {{-- an error: it could not load; the next step is to try again --}}
            <atom:card inset>
                <atom:empty kind="error" heading="Activity could not be loaded" subheading="Check your connection and try again.">
                    <x-slot:action>
                        <atom:button size="sm" icon="refresh">Try again</atom:button>
                    </x-slot:action>
                </atom:empty>
            </atom:card>
        @elseif ($tab === 'files')
            {{-- no results: a search found nothing; the next step is to widen it --}}
            <atom:card inset>
                <atom:empty kind="no-results" icon="search" heading="No files match" subheading="Nothing is named like your search.">
                    <x-slot:action>
                        <atom:button size="sm">Clear search</atom:button>
                    </x-slot:action>
                </atom:empty>
            </atom:card>
        @elseif ($tab === 'log')
            {{-- a process log: `kind="step"`, each step done, current, pending or failed, the body folded away --}}
            <atom:card data-testid="record-log">
                <h2 class="m-0 mb-3 text-sm font-semibold">Pressure test run</h2>

                <atom:timeline kind="step" label="Test run steps" class="max-w-xl">
                    <atom:timeline.item wire:key="log-1" title="Isolate the line" state="done" time="12m">Valves closed and tagged.</atom:timeline.item>
                    <atom:timeline.item wire:key="log-2" title="Fill and vent" state="done" time="20m">Air bled from the high point.</atom:timeline.item>
                    <atom:timeline.item wire:key="log-3" title="Hold at 16 bar" state="current" status="Running" time="38m">Holding. The gauge has not moved in 30 minutes.</atom:timeline.item>
                    <atom:timeline.item wire:key="log-4" title="Sign-off" state="pending">Needs the customer on site.</atom:timeline.item>
                </atom:timeline>
            </atom:card>
        @elseif ($tab === 'messages')
            {{-- a conversation: `kind="message"`, the other person at the start, yours at the end, a composer last --}}
            <atom:card data-testid="record-messages">
                <h2 class="m-0 mb-3 text-sm font-semibold">Customer thread</h2>

                <atom:timeline kind="message" label="Customer thread" class="max-w-xl">
                    <atom:timeline.item wire:key="msg-1" speaker="Mira (Lakeside Foods)" time="09:02" body="Can someone confirm the pressure test is still on for Tuesday?"/>
                    <atom:timeline.item wire:key="msg-2" speaker="You" side="end" time="09:10" body="Yes. We are waiting on one seal kit and will confirm the time by end of day."/>
                    <atom:timeline.item wire:key="msg-3" speaker="Mira (Lakeside Foods)" time="09:14" body="Thanks, that works."/>

                    <x-slot:composer>
                        <form x-data x-on:submit.prevent class="flex items-start gap-2">
                            <atom:input aria-label="Reply" placeholder="Write a reply"/>
                            <atom:button type="submit" variant="default" size="sm">Send</atom:button>
                        </form>
                    </x-slot:composer>
                </atom:timeline>
            </atom:card>
        @else
            <div @class(['grid items-start gap-6', 'min-[1080px]:grid-cols-[minmax(0,1fr)_320px]' => $tab === 'overview'])>
                <div class="grid min-w-0 gap-6">
                    {{-- the panel of the record's lines, the same on the overview and on the Tasks tab --}}
                    <div class="min-w-0 overflow-clip rounded-panel border bg-surface" data-testid="record-lines">
                        <div class="flex items-baseline justify-between gap-3 border-b px-3.5 py-2.5">
                            <h2 class="m-0 text-sm font-semibold">Tasks <span class="ms-1.5 text-xs font-normal text-fg-muted">{{ count($tasks) }} tasks</span></h2>
                            <atom:link href="?tab=tasks" class="text-xs">View all {{ count($tasks) }}</atom:link>
                        </div>

                        <atom:table frameless :empty="false">
                            <x-slot:columns>
                                <atom:table.column>Task</atom:table.column>
                                <atom:table.column>Status</atom:table.column>
                                <atom:table.column>Assigned</atom:table.column>
                                <atom:table.column numeric>Hours</atom:table.column>
                            </x-slot:columns>

                            <x-slot:rows>
                                @foreach ($tasks as [$id, $name, $taskStatus, $who, $taskHours])
                                    <atom:table.row>
                                        <atom:table.cell>
                                            <span class="font-id text-fg-subtle">{{ $id }}</span>
                                            <span class="ms-2 font-medium">{{ $name }}</span>
                                        </atom:table.cell>
                                        <atom:table.cell>
                                            <atom:status :tone="$taskStatus === 'Done' ? 'success' : 'neutral'" :label="$taskStatus"/>
                                        </atom:table.cell>
                                        <atom:table.cell>
                                            <span class="inline-flex items-center gap-2">
                                                <atom:avatar size="xs" :square="false" :name="$who"/>
                                                {{ $who }}
                                            </span>
                                        </atom:table.cell>
                                        <atom:table.cell numeric>{{ $taskHours }}</atom:table.cell>
                                    </atom:table.row>
                                @endforeach
                            </x-slot:rows>
                        </atom:table>

                        {{-- the totals sit under the table, in the panel --}}
                        <dl class="m-0 flex flex-wrap justify-end gap-x-6 gap-y-1 border-t px-3.5 py-2.5 text-xs tabular-nums text-fg-subtle" data-testid="record-totals">
                            <div class="flex gap-2"><dt>Quoted</dt><dd class="m-0">10.0 h</dd></div>
                            <div class="flex gap-2"><dt class="font-medium text-fg">Total hours</dt><dd class="m-0 font-medium text-fg">{{ number_format($hours, 1) }} h</dd></div>
                        </dl>
                    </div>

                    @if ($tab === 'overview')
                        <div class="min-w-0 overflow-hidden rounded-panel border bg-surface" data-testid="record-description">
                            <div class="border-b px-3.5 py-2.5">
                                <h2 class="m-0 text-sm font-semibold">Description</h2>
                            </div>

                            {{-- long text is the reading size: 14px in the compact shell --}}
                            <p class="m-0 px-3.5 py-3 text-base text-fg">The customer reports a drop in pressure on the second pump when both are running. Replace the seal kit, pressure test the line at 16 bar for an hour and sign the job off with the customer on site.</p>
                        </div>
                    @endif
                </div>

                @if ($tab === 'overview')
                    <aside class="grid min-w-0 gap-3.5" aria-label="Work order details" data-testid="record-rail">
                        <atom:card class="grid gap-3">
                            <h3 class="m-0 text-xs font-medium text-fg-subtle">Details</h3>
                            <atom:dd.group>
                                @foreach ($details as $label => $value)
                                    <atom:dd :label="$label">{{ $value }}</atom:dd>
                                @endforeach
                            </atom:dd.group>
                        </atom:card>

                        <atom:card class="grid gap-3">
                            <h3 class="m-0 text-xs font-medium text-fg-subtle">Customer</h3>
                            <atom:dd.group>
                                <atom:dd label="Name">Lakeside Foods Sdn Bhd</atom:dd>
                                <atom:dd label="Contact">Mira Tan</atom:dd>
                                <atom:dd label="Phone">012-345 6789</atom:dd>
                            </atom:dd.group>
                        </atom:card>

                        <atom:card class="grid gap-3">
                            <h3 class="m-0 text-xs font-medium text-fg-subtle">People</h3>
                            <atom:dd.group>
                                @foreach ($people as [$role, $person, $personId])
                                    @if ($person)
                                        <atom:dd :label="$role">
                                            <span class="inline-flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <atom:avatar size="xs" :square="false" :name="$person"/>
                                                {{ $person }}
                                                <span class="font-id text-fg-subtle">{{ $personId }}</span>
                                            </span>
                                        </atom:dd>
                                    @endif
                                @endforeach
                            </atom:dd.group>
                        </atom:card>

                        <atom:card class="grid gap-3" data-testid="record-linked">
                            <h3 class="m-0 text-xs font-medium text-fg-subtle">Linked records</h3>
                            <atom:dd.group>
                                <atom:dd label="Appointment"><span class="font-id">AP-26100118</span></atom:dd>
                                <atom:dd label="Order"><span class="font-id">PO-88213</span></atom:dd>
                            </atom:dd.group>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                <atom:link href="?tab=files" icon="add">Quote</atom:link>
                                <atom:link href="?tab=files" icon="add">Purchase order</atom:link>
                            </div>
                        </atom:card>

                        {{-- activity is last in the rail: a few events and a link to all of them --}}
                        <atom:card class="grid gap-3" data-testid="record-activity">
                            <div class="flex items-baseline justify-between gap-3">
                                <h3 class="m-0 text-xs font-medium text-fg-subtle">Activity</h3>
                                <atom:link href="?tab=activity&view=all" class="text-xs">All changes</atom:link>
                            </div>

                            <atom:timeline label="Work order activity">
                                <atom:timeline.item wire:key="activity-test" title="T-3 Pressure test" body="added to the job" actor="Ana" time="Today, 09:20" tone="info"/>
                                <atom:timeline.item wire:key="activity-seal" title="T-2 Replace the seal kit" body="marked done" actor="Ben" time="Today, 08:41" tone="success"/>
                                <atom:timeline.item wire:key="activity-parts" title="Seal kit" body="put on back order" actor="Ana" time="Yesterday, 16:30" tone="warning"/>
                                <atom:timeline.item wire:key="activity-opened" title="WO-1042" body="opened from quote Q-0231" actor="Ana" time="Yesterday, 16:02"/>
                            </atom:timeline>
                        </atom:card>
                    </aside>
                @endif
            </div>
        @endif
    </atom:page>

    {{-- no Livewire component on this page, so Livewire's own Alpine has to be loaded for atom.js --}}
    @livewireScripts
</x-dynamic-component>
