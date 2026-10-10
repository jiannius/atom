<atom:docs.example
title="Month view"
description="Items are plain arrays: id, title and start are required; end, all_day, color, icon, danger and href are optional. All-day and multi-day items are bars across their days; a timed item is a time and a title. Shown here without a bound property, so the calendar is static: it lays out the items it is given and has no previous and next. In Livewire, bind the anchor date with wire:model and pass items as a closure of the window."
view="atom::docs.demos.calendar.basic"/>

<atom:docs.example
title="Busy days and a Sunday start"
description="A day shows three rows, then +N more, which opens the whole day. :max sets the number of rows (false shows them all) and week-start picks the first column. time-format and date-format take the date() tokens d j D l m n M F y Y and g G h H i a A."
view="atom::docs.demos.calendar.busy"/>
