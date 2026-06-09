<atom:docs.example
title="Basic"
description="Static rows shown here. In Livewire, sorting, checkboxes, max rows and pagination are driven by the $_table state from AtomComponent plus the toTable() builder macro."
view="atom::docs.demos.table.basic"/>

<atom:docs.example
title="Empty state"
description="empty=true renders the empty-state component; with a paginator it is derived automatically."
view="atom::docs.demos.table.empty"/>

<atom:docs.example
title="Search"
description="atom:table.search is the standard listing search: a search-icon input bound to a filter key, Enter to run ($wire.$refresh). Replaces the per-page boilerplate."
view="atom::docs.demos.table.search"/>

<atom:docs.example
title="Row actions"
description="atom:table.actions renders the trailing actions cell + a dropdown menu. Put atom:menu.item children inside. It stops row-click propagation so it works inside clickable rows. Delete items should use the confirm pattern."
view="atom::docs.demos.table.actions"/>

<atom:docs.example
title="Trashed toggle"
description="atom:table.trashed toggles $_table.show_trashed; toTable() then applies onlyTrashed(). Place it inside atom:table.filters so it also surfaces as an active-filter chip."
view="atom::docs.demos.table.trashed"/>

<atom:docs.example
title="Filters bar"
description="atom:table.filters wraps your filter controls (atom:select variant=filter, atom:date-picker variant=range, custom selects) and auto-derives active-filter chips + Clear all from each control's label and selected value. Put overflow filters in x-slot:more — a 'More filters' popover by default, or set overflow=card for an expandable row."
view="atom::docs.demos.table.filters"/>
