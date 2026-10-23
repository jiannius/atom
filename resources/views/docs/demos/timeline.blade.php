<atom:docs.example
title="Events"
description="The activity of a record: a dot in a tone on a spine, what happened in bold, then a line, who and when under it, an optional status and a detail that folds away. Titles, actors and bodies print as text and are not translated; put your own markup in the slot and you own escaping it."
view="atom::docs.demos.timeline.event"/>

<atom:docs.example
title="Steps"
description="kind=step is a numbered spine for a process (a run, an onboarding). Each step is done, current, pending or failed; the status beside the title takes the state's tone and pulses on the current one. A step with a body folds it away; the current and failed ones start open. Pass :collapsible=false for a list that is always open."
view="atom::docs.demos.timeline.step"/>

<atom:docs.example
title="Path"
description="kind=path is a horizontal path of stages for a record that moves through them: one segment per item, each done, current, pending or failed, with a title and a muted line of the time and the person (actor). The current segment is tinted. It stacks as a column when its own box is narrower than 42rem, so on a phone and in a narrow column. Use it under a record's header, with the status matching the current segment."
view="atom::docs.demos.timeline.path"/>

<atom:docs.example
title="Messages"
description="kind=message is a conversation between two speakers: the other one at the start, yours (side=end) at the end, at the 14px reading size. The composer slot is the last row."
view="atom::docs.demos.timeline.message"/>
