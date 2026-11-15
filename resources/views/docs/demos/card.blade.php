<atom:docs.example
title="Basic"
description="A fill and a hairline at the panel radius, with no shadow. Content renders in the default slot. subtle is the sunken fill; inset drops the padding; divided rules the children with the hairline. heading draws the box's title (see Heading); the stats and chart variants print it as their own label."
view="atom::docs.demos.card.basic"/>

<atom:docs.example
title="Heading, actions and menu"
description="heading is a 12px muted title (a string through t(), or a heading slot for markup) at level 2, 3 or 4 (2 by default). actions is one link or ghost button at the end of the row; menu is the items behind a ghost ⋯. The row is drawn only with one of the three. It keeps the card's 16px on an inset card and is the first row of a divided one. A list that runs to the edges is an atom:panel."
view="atom::docs.demos.card.heading"/>

<atom:docs.example
title="Divided and cover"
description="divided pads each child div and rules them. A cover slot bleeds to the card's edge."
view="atom::docs.demos.card.divided"/>

<atom:docs.example
title="Stats variant"
view="atom::docs.demos.card.stats"/>
