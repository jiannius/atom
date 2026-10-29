<atom:docs.example
title="Tiers"
description="Three tiers by emphasis. primary is the solid accent, one per view: the action the current state asks for. The default is bordered, with no shadow. ghost has no border or fill until hover. link is text. danger, warning, success and info are solid fills of the tone; add inverted for the tone's own tint (what type=delete gives). accent is the old name of primary; secondary and outline fall to the default tier."
view="atom::docs.demos.button.variants"/>

<atom:docs.example
title="Sizes"
description="The control token sets the height: default is 28px in the compact density, 32 in comfortable, 40 on a touch device. sm and xs sit 4px and 8px under it, md and lg 4px and 8px over."
view="atom::docs.demos.button.sizes"/>

<atom:docs.example
title="Icons"
description="icon prefixes, iconSuffix appends, 16px beside 13px text. A slotless button renders an icon-only square, auto-labelled for screen readers from the icon name (override with aria-label)."
view="atom::docs.demos.button.icons"/>

<atom:docs.example
title="Tooltip"
description="tooltip wraps the button in an atom:tooltip. On an icon-only button it is also the accessible name (a caller's aria-label wins), so it is announced once; on a button with a label it is a description. Hover or keyboard focus shows it, and it stays away while the button's own menu is open. For a position or a kbd hint, wrap the button in atom:tooltip yourself."
view="atom::docs.demos.button.tooltip"/>

<atom:docs.example
title="Submit & delete types"
description="type=submit is primary and shows a loading state on wire submit. type=delete is the danger tone's tint and auto-wires the confirm dialog, which dispatches confirmed → $wire.delete(), unless you override wire:click or x-on:click. Neither adds an icon of its own: pass icon=."
view="atom::docs.demos.button.delete"/>

<atom:docs.example
title="Ghost colors"
description="variant=ghost is bare until hover. Add color (primary or accent, danger, warning, success, info) to tint the text and the hover fill. Used for the standard form delete button: type=delete variant=ghost color=danger."
view="atom::docs.demos.button.ghost-colors"/>

<atom:docs.example
title="Social"
description="google, facebook, linkedin, whatsapp and telegram are the default tier with the brand's icon and no brand colour. Pass icon= (or :social for a share link)."
view="atom::docs.demos.button.social"/>
