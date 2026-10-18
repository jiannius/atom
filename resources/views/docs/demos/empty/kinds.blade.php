<div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-panel border">
        <atom:empty kind="first-use" heading="No jobs yet" subheading="Create the first work order to start tracking it.">
            <x-slot:action>
                <atom:button size="sm" variant="primary" icon="add">New job</atom:button>
            </x-slot:action>
        </atom:empty>
    </div>

    <div class="rounded-panel border">
        <atom:empty kind="no-results" icon="search" heading="No jobs match" subheading="Try another search or clear the filters.">
            <x-slot:action>
                <atom:button size="sm">Clear filters</atom:button>
            </x-slot:action>
        </atom:empty>
    </div>

    <div class="rounded-panel border">
        <atom:empty kind="error">
            <x-slot:action>
                <atom:button size="sm" icon="refresh">Try again</atom:button>
            </x-slot:action>
        </atom:empty>
    </div>
</div>
