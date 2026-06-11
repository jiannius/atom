<atom:docs.example
title="Variants"
description="Color and emphasis via the variant prop. Social variants (facebook, google, linkedin, whatsapp, telegram) also exist."
view="atom::docs.demos.button.variants"/>

<atom:docs.example
title="Sizes"
description="xs, sm, default, md, lg."
view="atom::docs.demos.button.sizes"/>

<atom:docs.example
title="Icons"
description="icon prefixes, iconSuffix appends, and a slotless button renders an icon-only square — auto-labelled for screen readers from the icon name (override with aria-label)."
view="atom::docs.demos.button.icons"/>

<atom:docs.example
title="Submit & delete types"
description="type=submit styles as primary with loading state on wire submit. type=delete auto-wires the confirm dialog and dispatches confirmed → $wire.delete() unless you override wire:click or x-on:click."
view="atom::docs.demos.button.delete"/>

<atom:docs.example
title="Ghost colors"
description="variant=ghost is de-emphasized (transparent). Add color (primary, danger, warning, success) to tint the text and invert to a solid fill on hover. Used for the standard form delete button: type=delete variant=ghost color=danger."
view="atom::docs.demos.button.ghost-colors"/>
