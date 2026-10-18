<atom:docs.example
title="Default"
description="An empty-state block with an icon, heading and subheading. kind picks the words and the icon (no-results is the default); icon, heading and subheading override them, or pass a slot for custom content."
view="atom::docs.demos.empty.default"/>

<atom:docs.example
title="Three kinds, each with a next step"
description="first-use (nothing created yet: offer to create the first one), no-results (a search or filter found nothing: offer to widen it) and error (it failed to load: offer to try again). The next step goes in the action slot. The icon draws in once, about a second; not under prefers-reduced-motion."
view="atom::docs.demos.empty.kinds"/>

<atom:docs.example
title="Sizes & subtle"
description="The sm size lays the icon beside the text; subtle renders a compact single-line muted box for inline empties. Neither animates."
view="atom::docs.demos.empty.variants"/>
