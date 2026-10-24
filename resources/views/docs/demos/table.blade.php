<atom:docs.example
title="Basic"
description="Static rows shown here. In Livewire, sorting, checkboxes, max rows and pagination are driven by the $_table state from the WithTable trait plus the toTable() builder macro."
view="atom::docs.demos.table.basic"/>

<atom:docs.example
title="Empty state"
description="empty=true renders the empty-state component; with a paginator it is derived automatically."
view="atom::docs.demos.table.empty"/>

<atom:docs.example
title="Search"
description="atom:table.search is the standard listing search: a search-icon input bound to a filter key, Enter to run ($wire.$refresh). Replaces the per-page boilerplate. Bound with wire:model inside atom:table.filters it also joins the bar: its text shows as a chip named by the placeholder, and Clear all (or the chip's x) empties the box and refreshes. Shown here unbound, so no chip."
view="atom::docs.demos.table.search"/>

<atom:docs.example
title="Row actions"
description="atom:table.actions renders the trailing actions cell + a dropdown menu. Put atom:menu.item children inside. It stops row-click propagation so it works inside clickable rows. Delete items should use the confirm pattern."
view="atom::docs.demos.table.actions"/>

<atom:docs.example
title="Trashed toggle"
description="Add the trashed prop to atom:table to append an icon toggle (tooltip 'Show archived', solid-filled when active) at the end of the header bar — it toggles $_table.show_trashed and toTable() applies onlyTrashed(). The standalone atom:table.trashed shown here is for custom placement."
view="atom::docs.demos.table.trashed"/>

<atom:docs.example
title="Filters bar"
description="atom:table.filters wraps your filter controls (atom:select variant=filter, atom:date-picker variant=range, custom selects) and auto-derives active-filter chips + Clear all from each control's label and selected value. Put overflow filters in x-slot:more — the 'More filters' button follows the last filter in the bar and opens a popover by default; overflow=card expands a row below it, overflow=modal opens a modal that lays the slot out as a form, one labelled field per row — add table-filter to a form field there and it registers a chip like a bar control does. The first bar is chips='inline': the active filters are sentence pills (a name, a muted operator and the value, with an x) leading the same row, ahead of the dashed add pills, and add on a select filter or a date range draws it as a dashed '+ Customer' pill that steps aside for its chip. The default, chips='below', keeps the chips on a row of their own."
view="atom::docs.demos.table.filters"/>

<atom:docs.example
title="Loading skeleton"
description="Pass skeleton (or :skeleton=N for a row count) to show placeholder rows on first load while a lazy/deferred table fetches its data. Opt-in: a table without the flag is unaffected. Filter/search shows a spinner in the search box (rows stay); pagination/sort use a dim overlay."
view="atom::docs.demos.table.skeleton"/>

<atom:docs.example
title="Long values"
description="A nowrap cell grows its column to its longest value, so one long address can push the whole table wider than its box. Add truncate to atom:table.cell and it takes the room left over and cuts the text with an ellipsis instead. To let a cell or header wrap instead, pass whitespace-normal (md:whitespace-normal also works) — the nowrap default yields to it. Neither needs w-full on the table."
view="atom::docs.demos.table.truncate"/>

<atom:docs.example
title="Frameless"
description="frameless is for a table that sits in a panel your page already draws (a list with saved views and a toolbar above it): no outer box of its own, hairlines between the strips and the rows, a header and bulk bar that become strips. A table with no panel around it stays framed. A wide table scrolls sideways inside its panel, never the page."
view="atom::docs.demos.table.frameless"/>

<atom:docs.example
title="Selected bar"
description="While rows are ticked, the table's checked slot replaces the toolbar with a bar (the count, 'select all' and the host's bulk actions). It has no fill by default. Put tone='accent' on the slot (x-slot:checked tone='accent') to tint it with the accent-soft token and set its text in the accent ink, so the selected state stands out; any other tone is the plain bar. It works framed and frameless: framed, the bar is its own rounded strip above the panel; frameless, it is the panel's top strip. Both looks are drawn here with the table's own classes, because the real bar opens only from a Livewire page (WithTable keeps the ticked ids)."
view="atom::docs.demos.table.checked"/>

<atom:docs.example
title="Numeric columns"
description="numeric on a column and on its cells right-aligns the figures and makes them tabular, so a column of money lines up on the decimal point. Name the currency once, in the header (Amount (RM)), never in every cell. Identifiers (an invoice number) take the font-id class."
view="atom::docs.demos.table.numeric"/>

<atom:docs.example
title="Toolbar"
description="atom:table.toolbar lays out the search and the filters at the start and an end group (an export, the archived toggle) at the far side. In a Livewire page it goes in the table's header slot, where the table draws the strip around it and swaps it for the bulk-action bar while rows are ticked; the header slot is an Alpine template, so a count or a total belongs below the table, not in it. Outside a table, pass strip to draw its own hairline strip, as here."
view="atom::docs.demos.table.toolbar"/>

<atom:docs.example
title="Saved views"
description="atom:table.views is a strip of views with counts, for the top of the list's panel. Bind it with wire:model (add .live to refetch at once): the property holds the chosen view's value, so WithUrlFilters can carry it in the URL. The host supplies the counts, as an array keyed by value or a closure that gets the value; atom never runs a count query. An item with an href is a link instead."
view="atom::docs.demos.table.views"/>
