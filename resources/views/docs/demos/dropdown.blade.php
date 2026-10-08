<atom:docs.example
title="Basic"
description="Wrap a trigger and an atom:menu (with the popover attribute). The first button (outside the menu) is the trigger; clicking it toggles the menu via the native popover API, positioned with Floating UI."
view="atom::docs.demos.dropdown.basic"/>

<atom:docs.example
title="Position & align"
description="position (top, bottom, left, right) and align (start, center, end) place the menu relative to the trigger."
view="atom::docs.demos.dropdown.positions"/>

<atom:docs.example
title="Locked"
description="locked keeps the menu open when an item is clicked (the default closes on inside-click). Esc or an outside click still dismisses it."
view="atom::docs.demos.dropdown.locked"/>

<atom:docs.example
title="Link or other non-button trigger"
description="The trigger does not have to be a button. With no button outside the menu, the first child that is not the menu, such as an atom:link, toggles it. Buttons inside the menu are never mistaken for the trigger, so clicking an item closes it. A link with no href cannot be focused, so this one has an href of # plus x-on:click.prevent: Tab reaches it and Enter opens the menu (Space does not, as with any link)."
view="atom::docs.demos.dropdown.link-trigger"/>

<atom:docs.example
title="Explicit trigger"
description="Put data-atom-dropdown-trigger on the element that should toggle the menu when it is not the first button or child, for example the second button of a split button. It wins over the automatic lookup, as long as it sits outside the menu: one inside the menu (a date picker's input, say) belongs to that component and is ignored."
view="atom::docs.demos.dropdown.explicit-trigger"/>
