<atom:docs.example
title="Tones"
description="A dot plus a word, coloured by tone. The word is always printed: colour is never the only signal. Pass the word as label or as the slot."
view="atom::docs.demos.status.tones"/>

<atom:docs.example
title="Running"
description="running pulses the dot while something is in progress. Under prefers-reduced-motion the dot stays still and the word says it."
view="atom::docs.demos.status.running"/>

<atom:docs.example
title="From an enum"
description="Pass the state as value. The tone is the enum's tone(), else its color() (green success, yellow warning, red danger, blue info, gray neutral), and the word is its label(). An array with label and color (or tone) works the same; atom ships no enum of its own, so this demo uses arrays. The tone and label props win over the value."
view="atom::docs.demos.status.value"/>
