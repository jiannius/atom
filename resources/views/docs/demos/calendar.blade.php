{{-- Every demo here is static: no wire:model, so the calendar lays out the items it is given, switches views, and has no previous
     and next. In a Livewire component you bind the anchor date with wire:model and pass the items as a closure of the window;
     see the Calendar section of the README. --}}
<atom:docs.example
title="Month view"
description="Items are plain arrays: id, title and start are required; end, all_day, color, icon, danger, href, draggable and resizable are optional. An all-day or multi-day item is a bar across its days, a timed item is a dot, its time and its title. color is red, orange, yellow, green, blue, purple, black, zinc or a #hex; an item with none is zinc. href makes the item a link, and any other item fires calendar-item-click."
view="atom::docs.demos.calendar.basic"/>

<atom:docs.example
title="Busy days: +N more"
description="A day shows :max rows (3 by default), then +N more, which opens that day's list. Pass false, 0 or none to show every row. Click a day that has items, or press Enter on it, to open its list too."
view="atom::docs.demos.calendar.busy"/>

<atom:docs.example
title="Week view with overlapping items"
description="Pass more than one of month, week and day as views and the calendar shows a switcher. Timed items that overlap sit side by side in the time grid; all-day items are in the row above it. :slot-duration (5, 10, 15, 20, 30 or 60 minutes) is what a drag snaps to, scroll-time is the time the grid opens at, and the red line is now (:now-indicator)."
view="atom::docs.demos.calendar.week"/>

<atom:docs.example
title="Drag, resize and select"
description="Items can be dragged to move them and pulled by their end to resize them (draggable and resizable are on by default; an item's own flags win). Add selectable to create: click or drag over empty space. Each gesture fires an event on the calendar with its details in the viewer's own time, which is logged below. calendar-item-move and calendar-item-resize can be cancelled with preventDefault(), and all three carry waitUntil(promise) to hold the result while you save it. This demo has nothing to save to, so an item stays where it is dropped."
view="atom::docs.demos.calendar.gestures"/>

<atom:docs.example
title="Sunday start, weekend and 24-hour time"
description="week-start picks the first column. weekend is the days that get the tint, for a country whose weekend is not Saturday and Sunday. time-format and date-format take the date() tokens d j D l m n M F y Y and g G h H i a A."
view="atom::docs.demos.calendar.sunday"/>
